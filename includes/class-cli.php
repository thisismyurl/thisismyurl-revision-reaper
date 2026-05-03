<?php
/**
 * WP-CLI surface for Revision Reaper.
 *
 * Registered only when WP_CLI is defined. Provides:
 *
 *   wp revision-reaper run [--dry-run] [--limit=N] [--include=...] [--max=N]
 *   wp revision-reaper status
 *
 * --include accepts a comma-separated subset of: revisions,trash,spam.
 * --limit  is the per-post revision-keep limit (default: option value, fallback 3).
 * --max    is a hard cap on items processed in this invocation (default 1000).
 *
 * Reaper run always honours the same trash-vs-force-delete rules as the
 * scheduled cron — never force-deletes, always pre-snapshots to JSON.
 *
 * @package Thisismyurl_Revision_Reaper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Class TIMU_Revision_Reaper_CLI
 */
class TIMU_Revision_Reaper_CLI {

	/**
	 * Run the cleanup pass.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report what would change without writing anything.
	 *
	 * [--limit=<n>]
	 * : Per-post revision-keep limit. Defaults to the saved option value (3 if unset).
	 *
	 * [--include=<list>]
	 * : Comma-separated subset of: revisions, trash, spam. Default: all three.
	 *
	 * [--max=<n>]
	 * : Hard cap on items processed in this invocation. Default 1000.
	 *
	 * ## EXAMPLES
	 *
	 *     wp revision-reaper run --dry-run
	 *     wp revision-reaper run --include=revisions --limit=5
	 *     wp revision-reaper run --max=200
	 *
	 * @when after_wp_load
	 */
	public function run( $args, $assoc_args ) {
		$dry_run = ! empty( $assoc_args['dry-run'] );
		$limit   = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : (int) get_option( 'timu_rr_limit', 3 );
		$max     = isset( $assoc_args['max'] ) ? (int) $assoc_args['max'] : 1000;
		$include = isset( $assoc_args['include'] ) ? array_map( 'trim', explode( ',', $assoc_args['include'] ) ) : array( 'revisions', 'trash', 'spam' );

		$settings = array(
			'limit'         => max( 0, $limit ),
			'include_trash' => in_array( 'trash', $include, true ) ? 1 : 0,
			'include_spam'  => in_array( 'spam', $include, true ) ? 1 : 0,
		);

		// "revisions" is implicit in the scan; --include can opt out by
		// listing only trash and/or spam.
		$want_revisions = in_array( 'revisions', $include, true );

		$caps  = array(
			'batch_size' => 200,
			'max_items'  => max( 1, $max ),
			'post_types' => TIMU_Revision_Reaper::get_target_post_types(),
		);
		$items = TIMU_Revision_Reaper::get_eligible_items( $settings, $caps );

		if ( ! $want_revisions ) {
			$items = array_values( array_filter( $items, function ( $i ) {
				return 'revision' !== $i['type'];
			} ) );
		}

		if ( empty( $items ) ) {
			WP_CLI::success( 'Nothing to do — no eligible items.' );
			return;
		}

		WP_CLI::log( sprintf( '%d eligible items found (limit=%d, max=%d, include=%s, dry-run=%s).',
			count( $items ),
			$limit,
			$max,
			implode( ',', $include ),
			$dry_run ? 'yes' : 'no'
		) );

		if ( ! $dry_run ) {
			$export = TIMU_Revision_Reaper_Exporter::snapshot_before_run( $items, $limit );
			if ( $export ) {
				WP_CLI::log( 'Pre-run export: ' . $export );
			} else {
				WP_CLI::error( 'Pre-run export failed to write. Aborting.' );
				return;
			}
		}

		$progress = \WP_CLI\Utils\make_progress_bar( $dry_run ? 'Simulating' : 'Reaping', count( $items ) );

		$counts = array( 'revision' => 0, 'trash' => 0, 'trash_skipped' => 0, 'spam' => 0 );
		$bytes  = 0;

		foreach ( $items as $item ) {
			$id   = (int) $item['id'];
			$type = (string) $item['type'];

			switch ( $type ) {
				case 'revision':
					$revisions = wp_get_post_revisions( $id );
					$to_remove = array_slice( $revisions, $settings['limit'] );
					foreach ( $to_remove as $rev ) {
						$bytes += strlen( (string) $rev->post_content );
						if ( ! $dry_run ) {
							wp_delete_post_revision( $rev->ID );
						}
					}
					$counts['revision']++;
					break;

				case 'trash':
					if ( ! TIMU_Revision_Reaper::trash_post_eligible_for_purge( $id ) ) {
						$counts['trash_skipped']++;
						break;
					}
					if ( ! $dry_run ) {
						wp_delete_post( $id, false );
					}
					$counts['trash']++;
					break;

				case 'spam':
					if ( ! $dry_run ) {
						wp_trash_comment( $id );
					}
					$counts['spam']++;
					break;
			}
			$progress->tick();
		}
		$progress->finish();

		$transients = 0;
		$optimized  = 0;
		if ( ! $dry_run ) {
			$transients = TIMU_Revision_Reaper::delete_expired_transients();
			$optimized  = TIMU_Revision_Reaper::optimize_wp_tables();
		}

		WP_CLI::success( sprintf(
			'%s — revisions:%d  trash:%d (skipped:%d)  spam:%d  bytes_freed:%d  transients:%d  tables_optimized:%d',
			$dry_run ? 'Simulation complete' : 'Reaper complete',
			$counts['revision'],
			$counts['trash'],
			$counts['trash_skipped'],
			$counts['spam'],
			$bytes,
			$transients,
			$optimized
		) );
	}

	/**
	 * Print current cleanup-eligible counts without modifying anything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp revision-reaper status
	 *
	 * @when after_wp_load
	 */
	public function status( $args, $assoc_args ) {
		global $wpdb;

		$rev_count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
			'revision'
		) );

		$trash_count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = %s",
			'trash'
		) );

		$spam_count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = %s",
			'spam'
		) );

		$transient_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->options}
			 WHERE option_name LIKE '\\_transient\\_timeout\\_%'
			    OR option_name LIKE '\\_site\\_transient\\_timeout\\_%'"
		);

		WP_CLI\Utils\format_items( 'table', array(
			array( 'metric' => 'revisions',           'count' => $rev_count ),
			array( 'metric' => 'trashed_posts',       'count' => $trash_count ),
			array( 'metric' => 'spam_comments',       'count' => $spam_count ),
			array( 'metric' => 'transient_pairs',     'count' => $transient_count ),
		), array( 'metric', 'count' ) );
	}
}

WP_CLI::add_command( 'revision-reaper', 'TIMU_Revision_Reaper_CLI' );
