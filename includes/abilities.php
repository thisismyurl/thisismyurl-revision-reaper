<?php
/**
 * WP 7 Abilities API registration for This Is My URL - Revision Reaper.
 *
 * Exposes the plugin's database-cleanup pass (revisions, trashed posts,
 * spam comments, expired transients, table optimization) as a discoverable,
 * REST/AI-invokable ability that returns per-category counts and ROI.
 *
 * @package Thisismyurl_Revision_Reaper
 * @since   1.6147
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_abilities_api_init',
	static function (): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return; // Abilities API unavailable (WordPress < 6.9).
		}

		wp_register_ability(
			'thisismyurl-revision-reaper/clean',
			array(
				'label'               => __( 'Run database cleanup', 'thisismyurl-revision-reaper' ),
				'description'         => __( 'Reaps surplus post revisions, purges aged trashed posts, trashes spam comments, deletes expired transients, and optimizes database tables, returning per-category counts and bytes reclaimed. Trashed posts are only purged once older than EMPTY_TRASH_DAYS and a JSON snapshot is written before any row is removed. Use dry_run to preview the would-clean counts without deleting anything.', 'thisismyurl-revision-reaper' ),
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'revisions'        => array(
							'type'        => 'boolean',
							'description' => __( 'Reap post revisions beyond the keep limit. Defaults to true.', 'thisismyurl-revision-reaper' ),
						),
						'trashed_posts'    => array(
							'type'        => 'boolean',
							'description' => __( 'Purge trashed posts that have aged past EMPTY_TRASH_DAYS. Defaults to the saved auto-trash option.', 'thisismyurl-revision-reaper' ),
						),
						'spam_comments'    => array(
							'type'        => 'boolean',
							'description' => __( 'Move spam comments to the comment trash. Defaults to the saved auto-spam option.', 'thisismyurl-revision-reaper' ),
						),
						'limit'            => array(
							'type'        => 'integer',
							'minimum'     => 0,
							'description' => __( 'Number of recent revisions to keep per post. Defaults to the saved option (3 if unset).', 'thisismyurl-revision-reaper' ),
						),
						'max_items'        => array(
							'type'        => 'integer',
							'minimum'     => 1,
							'description' => __( 'Hard cap on the number of eligible items processed in this pass. Defaults to 1000.', 'thisismyurl-revision-reaper' ),
						),
						'dry_run'          => array(
							'type'        => 'boolean',
							'description' => __( 'Preview the would-clean counts without deleting anything. Defaults to false.', 'thisismyurl-revision-reaper' ),
						),
					),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output_schema'       => array(
					'type'                 => 'object',
					'required'             => array( 'dry_run', 'revisions', 'trashed_posts', 'trash_skipped', 'spam_comments', 'transients', 'tables', 'bytes_freed', 'bytes_freed_human' ),
					'properties'           => array(
						'dry_run'           => array(
							'type'        => 'boolean',
							'description' => __( 'Whether this was a preview run that deleted nothing.', 'thisismyurl-revision-reaper' ),
						),
						'revisions'         => array(
							'type'        => 'integer',
							'description' => __( 'Number of posts whose surplus revisions were reaped.', 'thisismyurl-revision-reaper' ),
						),
						'trashed_posts'     => array(
							'type'        => 'integer',
							'description' => __( 'Number of trashed posts purged (aged past EMPTY_TRASH_DAYS).', 'thisismyurl-revision-reaper' ),
						),
						'trash_skipped'     => array(
							'type'        => 'integer',
							'description' => __( 'Number of trashed posts skipped because they were not yet old enough to purge.', 'thisismyurl-revision-reaper' ),
						),
						'spam_comments'     => array(
							'type'        => 'integer',
							'description' => __( 'Number of spam comments moved to the comment trash.', 'thisismyurl-revision-reaper' ),
						),
						'transients'        => array(
							'type'        => 'integer',
							'description' => __( 'Number of expired transient pairs removed (or eligible, on a dry run).', 'thisismyurl-revision-reaper' ),
						),
						'tables'            => array(
							'type'        => 'integer',
							'description' => __( 'Number of database tables optimized (always 0 on a dry run).', 'thisismyurl-revision-reaper' ),
						),
						'bytes_freed'       => array(
							'type'        => 'integer',
							'description' => __( 'Bytes reclaimed from reaped revision rows.', 'thisismyurl-revision-reaper' ),
						),
						'bytes_freed_human' => array(
							'type'        => 'string',
							'description' => __( 'Human-readable size of the reclaimed bytes, e.g. "1.4 MB".', 'thisismyurl-revision-reaper' ),
						),
						'export'            => array(
							'type'        => 'string',
							'description' => __( 'Filename of the pre-run JSON snapshot, or an empty string when none was written.', 'thisismyurl-revision-reaper' ),
						),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => static function ( $input = array() ) {
					if ( ! class_exists( 'TIMU_Revision_Reaper' ) ) {
						return new WP_Error(
							'timu_rr_unavailable',
							__( 'Revision Reaper is not available.', 'thisismyurl-revision-reaper' )
						);
					}

					$input = is_array( $input ) ? $input : array();

					$settings = array(
						'limit'             => isset( $input['limit'] )
							? max( 0, (int) $input['limit'] )
							: (int) get_option( 'timu_rr_limit', 3 ),
						'include_revisions' => ! array_key_exists( 'revisions', $input ) || ! empty( $input['revisions'] ),
						'include_trash'     => array_key_exists( 'trashed_posts', $input )
							? ( ! empty( $input['trashed_posts'] ) ? 1 : 0 )
							: (int) get_option( 'timu_rr_auto_trash', 0 ),
						'include_spam'      => array_key_exists( 'spam_comments', $input )
							? ( ! empty( $input['spam_comments'] ) ? 1 : 0 )
							: (int) get_option( 'timu_rr_auto_spam', 0 ),
					);

					$caps = array();
					if ( isset( $input['max_items'] ) ) {
						$caps['max_items'] = max( 1, (int) $input['max_items'] );
					}

					$dry_run = ! empty( $input['dry_run'] );

					$report = TIMU_Revision_Reaper::run_cleanup( $settings, $caps, $dry_run );

					return array(
						'dry_run'           => (bool) $report['dry_run'],
						'revisions'         => (int) $report['revisions'],
						'trashed_posts'     => (int) $report['trashed_posts'],
						'trash_skipped'     => (int) $report['trash_skipped'],
						'spam_comments'     => (int) $report['spam_comments'],
						'transients'        => (int) $report['transients'],
						'tables'            => (int) $report['tables'],
						'bytes_freed'       => (int) $report['bytes_freed'],
						'bytes_freed_human' => size_format( (int) $report['bytes_freed'] ) ?: '0 B',
						'export'            => '' !== $report['export'] ? wp_basename( $report['export'] ) : '',
					);
				},
				'permission_callback' => static function (): bool {
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}
);
