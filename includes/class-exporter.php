<?php
/**
 * Pre-delete export for Revision Reaper.
 *
 * Writes a JSON snapshot of the rows we are about to remove (revisions and
 * comments) before any destructive call runs. Acts as a poor-man's restore
 * source: the user can re-insert manually if they regret a run, and we don't
 * bury the data behind a paywall.
 *
 * Storage: each snapshot is a single non-autoloaded option named
 * `timu_rr_export_<token>`, plus a registry option `timu_rr_export_index`
 * that tracks the snapshot tokens and their timestamps for retention.
 *
 * The snapshot rows can include comment PII (author email, author IP), so the
 * data is deliberately kept inside the database rather than on disk. A file in
 * `wp-content/uploads/` is web-root on every server and the deny-all `.htaccess`
 * that previously guarded it is inert on nginx, so a guessable filename could
 * expose the PII snapshot to an unauthenticated request. The options table is
 * never web-served, so the snapshot is unreachable regardless of web server.
 *
 * Retention: exports older than 30 days are pruned at the start of each run.
 *
 * @package Thisismyurl_Revision_Reaper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TIMU_Revision_Reaper_Exporter
 */
class TIMU_Revision_Reaper_Exporter {

	const RETENTION_DAYS = 30;

	/**
	 * Option-name prefix for an individual snapshot.
	 */
	const OPTION_PREFIX = 'timu_rr_export_';

	/**
	 * Option holding the snapshot registry: token => unix timestamp.
	 */
	const INDEX_OPTION = 'timu_rr_export_index';

	/**
	 * Snapshot the supplied work list to a non-autoloaded option. Returns an
	 * opaque handle (the snapshot's option name) on success, or false on
	 * failure.
	 *
	 * The handle is suitable for display to the operator and is what the
	 * run report, the WP-CLI command, and the AJAX pre-run check echo back.
	 *
	 * @param array $items List of { id, type } pairs that the worker will process.
	 * @param int   $limit Revisions-to-keep value used for the run.
	 * @return string|false
	 */
	public static function snapshot_before_run( array $items, $limit ) {
		self::prune_old_exports();

		$payload = array(
			'meta'  => array(
				'plugin'         => 'thisismyurl-revision-reaper',
				'plugin_version' => defined( 'TIMU_REVISION_REAPER_VERSION' ) ? TIMU_REVISION_REAPER_VERSION : 'unknown',
				'site_url'       => home_url( '/' ),
				'generated_at'   => gmdate( 'c' ),
				'limit'          => (int) $limit,
				'item_count'     => count( $items ),
			),
			'items' => self::expand_items_for_export( $items, (int) $limit ),
		);

		$json = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			return false;
		}

		$token       = gmdate( 'Y-m-d_His' ) . '-' . wp_generate_password( 8, false, false );
		$option_name = self::OPTION_PREFIX . $token;

		// Snapshots can hold comment PII and are read only on a manual restore,
		// so they must never be autoloaded into memory on every request.
		if ( ! add_option( $option_name, $json, '', false ) ) {
			return false;
		}

		self::register_snapshot( $token );

		return $option_name;
	}

	/**
	 * Record a snapshot token and its creation time in the registry.
	 *
	 * @param string $token Snapshot token (the part after OPTION_PREFIX).
	 * @return void
	 */
	private static function register_snapshot( $token ) {
		$index = get_option( self::INDEX_OPTION, array() );
		if ( ! is_array( $index ) ) {
			$index = array();
		}
		$index[ $token ] = time();
		update_option( self::INDEX_OPTION, $index, false );
	}

	/**
	 * Expand a worker queue into the actual rows that would be removed,
	 * so the export holds restoration-grade data rather than just IDs.
	 *
	 * @param array $items List of work items.
	 * @param int   $limit Revisions-to-keep.
	 * @return array
	 */
	private static function expand_items_for_export( array $items, $limit ) {
		$out = array();

		foreach ( $items as $item ) {
			if ( empty( $item['type'] ) || empty( $item['id'] ) ) {
				continue;
			}

			$id = (int) $item['id'];

			switch ( $item['type'] ) {
				case 'revision':
					$revisions = wp_get_post_revisions( $id );
					$to_remove = array_slice( $revisions, $limit );
					foreach ( $to_remove as $rev ) {
						$out[] = array(
							'type'    => 'revision',
							'post_id' => $id,
							'row'     => self::serialize_post( $rev ),
						);
					}
					break;

				case 'trash':
					$post = get_post( $id );
					if ( $post ) {
						$out[] = array(
							'type'    => 'trash',
							'post_id' => $id,
							'row'     => self::serialize_post( $post ),
						);
					}
					break;

				case 'spam':
					$comment = get_comment( $id );
					if ( $comment ) {
						$out[] = array(
							'type'       => 'spam',
							'comment_id' => $id,
							'row'        => self::serialize_comment( $comment ),
						);
					}
					break;
			}
		}

		return $out;
	}

	/**
	 * @param WP_Post $post
	 * @return array
	 */
	private static function serialize_post( $post ) {
		return array(
			'ID'           => (int) $post->ID,
			'post_author'  => (int) $post->post_author,
			'post_date'    => $post->post_date,
			'post_title'   => $post->post_title,
			'post_content' => $post->post_content,
			'post_excerpt' => $post->post_excerpt,
			'post_status'  => $post->post_status,
			'post_parent'  => (int) $post->post_parent,
			'post_type'    => $post->post_type,
			'meta'         => get_post_meta( $post->ID ),
		);
	}

	/**
	 * @param WP_Comment $comment
	 * @return array
	 */
	private static function serialize_comment( $comment ) {
		return array(
			'comment_ID'           => (int) $comment->comment_ID,
			'comment_post_ID'      => (int) $comment->comment_post_ID,
			'comment_author'       => $comment->comment_author,
			'comment_author_email' => $comment->comment_author_email,
			'comment_author_url'   => $comment->comment_author_url,
			'comment_author_IP'    => $comment->comment_author_IP,
			'comment_date'         => $comment->comment_date,
			'comment_content'      => $comment->comment_content,
			'comment_approved'     => $comment->comment_approved,
			'comment_agent'        => $comment->comment_agent,
			'comment_type'         => $comment->comment_type,
			'meta'                 => get_comment_meta( $comment->comment_ID ),
		);
	}

	/**
	 * Delete snapshot options older than RETENTION_DAYS days and drop their
	 * registry entries. Also reaps registry entries whose option has already
	 * been removed by hand, keeping the index honest.
	 *
	 * @return void
	 */
	public static function prune_old_exports() {
		$index = get_option( self::INDEX_OPTION, array() );
		if ( ! is_array( $index ) || empty( $index ) ) {
			return;
		}

		$cutoff  = time() - ( self::RETENTION_DAYS * DAY_IN_SECONDS );
		$changed = false;

		foreach ( $index as $token => $created ) {
			if ( (int) $created < $cutoff ) {
				delete_option( self::OPTION_PREFIX . $token );
				unset( $index[ $token ] );
				$changed = true;
			}
		}

		if ( $changed ) {
			update_option( self::INDEX_OPTION, $index, false );
		}
	}
}
