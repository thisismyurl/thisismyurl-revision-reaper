<?php
/**
 * Pre-delete export for Revision Reaper.
 *
 * Writes a JSON snapshot of the rows we are about to remove (revisions and
 * comments) into the uploads directory before any destructive call runs.
 * Acts as a poor-man's restore source: the user can re-insert manually if
 * they regret a run, and we don't bury the data behind a paywall.
 *
 * Storage layout:
 *   uploads/revision-reaper/exports/<timestamp>-<uniqid>.json
 *   uploads/revision-reaper/exports/.htaccess  (deny-all for Apache)
 *   uploads/revision-reaper/exports/index.php  (silence is golden)
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
	const SUBDIR         = 'revision-reaper/exports';

	/**
	 * Returns the absolute path to the exports directory, creating it
	 * (with deny-all guards) on first call. Returns false on failure.
	 *
	 * @return string|false
	 */
	public static function get_export_dir() {
		$uploads = wp_upload_dir( null, false );
		if ( empty( $uploads['basedir'] ) || ! empty( $uploads['error'] ) ) {
			return false;
		}

		$dir = trailingslashit( $uploads['basedir'] ) . self::SUBDIR;
		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		// Guards. Idempotent — only written once.
		$htaccess = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( $htaccess, "Require all denied\nDeny from all\n" );
		}

		$index = trailingslashit( $dir ) . 'index.php';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( $index, "<?php // Silence is golden.\n" );
		}

		return $dir;
	}

	/**
	 * Snapshot the supplied work list to a JSON file. Returns the absolute
	 * file path on success, or false on failure.
	 *
	 * @param array $items   List of { id, type } pairs that the worker will process.
	 * @param int   $limit   Revisions-to-keep value used for the run.
	 * @return string|false
	 */
	public static function snapshot_before_run( array $items, $limit ) {
		$dir = self::get_export_dir();
		if ( false === $dir ) {
			return false;
		}

		self::prune_old_exports( $dir );

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

		$file = trailingslashit( $dir ) . gmdate( 'Y-m-d_His' ) . '-' . wp_generate_password( 8, false, false ) . '.json';
		$json = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if ( false === $json ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$ok = @file_put_contents( $file, $json );

		return $ok ? $file : false;
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
	 * Delete export files older than RETENTION_DAYS days.
	 *
	 * @param string $dir Absolute directory path.
	 * @return void
	 */
	public static function prune_old_exports( $dir ) {
		$cutoff = time() - ( self::RETENTION_DAYS * DAY_IN_SECONDS );
		$glob   = glob( trailingslashit( $dir ) . '*.json' );
		if ( empty( $glob ) ) {
			return;
		}
		foreach ( $glob as $file ) {
			$mtime = @filemtime( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			if ( false !== $mtime && $mtime < $cutoff ) {
				@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors,WordPress.WP.AlternativeFunctions.unlink_unlink
			}
		}
	}
}
