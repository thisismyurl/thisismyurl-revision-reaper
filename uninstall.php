<?php
/**
 * Revision Reaper Uninstall
 *
 * This file runs when the plugin is deleted via the WordPress Admin.
 * It removes all persistent settings and clears the automated schedule.
 *
 * @package TIMU_Revision_Reaper
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * 1. Clear the scheduled cron job.
 */
wp_clear_scheduled_hook( 'timu_rr_scheduled_cleanup' );

/**
 * 2. Delete all persistent options from the database.
 */
$options = array(
    'timu_rr_limit',
    'timu_rr_auto_trash',
    'timu_rr_auto_spam',
    'timu_rr_report_email',
    'timu_rr_enable_automation',
    'timu_rr_schedule_date',
    'timu_rr_schedule_time',
    'timu_rr_schedule_recurrence'
);

foreach ( $options as $option ) {
    delete_option( $option );
}
