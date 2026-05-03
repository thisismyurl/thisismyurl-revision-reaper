<?php
/**
 * Site Health "Info" tab integration for Revision Reaper.
 *
 * Surfaces the metrics Revision Reaper cares about (revision rows, trashed
 * posts, spam comments, expired transient pairs, last scheduled run) inside
 * Tools > Site Health > Info, so site owners and support staff can see them
 * without needing to open the plugin's own Tools page.
 *
 * @package Thisismyurl_Revision_Reaper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TIMU_Revision_Reaper_Site_Health
 */
class TIMU_Revision_Reaper_Site_Health {

	/**
	 * Hook the debug-information filter.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'debug_information', array( __CLASS__, 'add_debug_section' ) );
	}

	/**
	 * Append a Revision Reaper section to Site Health > Info.
	 *
	 * @param array $info Existing debug info groups.
	 * @return array
	 */
	public static function add_debug_section( $info ) {
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

		$next_run     = wp_next_scheduled( 'timu_rr_scheduled_cleanup' );
		$next_run_str = $next_run
			? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run )
			: __( 'No scheduled run', 'thisismyurl-revision-reaper' );

		$automation = (int) get_option( 'timu_rr_enable_automation', 0 ) ? __( 'Enabled', 'thisismyurl-revision-reaper' ) : __( 'Disabled', 'thisismyurl-revision-reaper' );

		$info['thisismyurl-revision-reaper'] = array(
			'label'       => __( 'Revision Reaper', 'thisismyurl-revision-reaper' ),
			'description' => __( 'Cleanup-eligible counts and current scheduling state for the Revision Reaper plugin.', 'thisismyurl-revision-reaper' ),
			'fields'      => array(
				'revisions'         => array(
					'label' => __( 'Revision rows in wp_posts', 'thisismyurl-revision-reaper' ),
					'value' => number_format_i18n( $rev_count ),
				),
				'trashed_posts'     => array(
					'label' => __( 'Trashed posts', 'thisismyurl-revision-reaper' ),
					'value' => number_format_i18n( $trash_count ),
				),
				'spam_comments'     => array(
					'label' => __( 'Spam comments', 'thisismyurl-revision-reaper' ),
					'value' => number_format_i18n( $spam_count ),
				),
				'transient_pairs'   => array(
					'label' => __( 'Transient timeout rows', 'thisismyurl-revision-reaper' ),
					'value' => number_format_i18n( $transient_count ),
				),
				'automation'        => array(
					'label' => __( 'Automation', 'thisismyurl-revision-reaper' ),
					'value' => $automation,
				),
				'next_scheduled'    => array(
					'label' => __( 'Next scheduled run', 'thisismyurl-revision-reaper' ),
					'value' => $next_run_str,
				),
				'empty_trash_days'  => array(
					'label' => __( 'EMPTY_TRASH_DAYS', 'thisismyurl-revision-reaper' ),
					'value' => defined( 'EMPTY_TRASH_DAYS' ) ? (string) EMPTY_TRASH_DAYS : '30 (default)',
				),
				'plugin_version'    => array(
					'label' => __( 'Plugin version', 'thisismyurl-revision-reaper' ),
					'value' => defined( 'TIMU_REVISION_REAPER_VERSION' ) ? TIMU_REVISION_REAPER_VERSION : 'unknown',
				),
			),
		);

		return $info;
	}
}

TIMU_Revision_Reaper_Site_Health::init();
