<?php
/**
 * Revision Reaper Uninstall
 *
 * This file runs when the plugin is deleted via the WordPress Admin.
 * It removes all persistent settings and clears the automated schedule.
 *
 * @package Thisismyurl_Revision_Reaper
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

/**
 * 3. Delete every pre-delete snapshot and its registry.
 *
 * Snapshots are stored as non-autoloaded options named
 * `timu_rr_export_<token>`, tracked in the `timu_rr_export_index` registry.
 * Walk the registry first, then sweep any orphaned snapshot rows the
 * registry missed (e.g. a snapshot whose registry entry was lost).
 */
$export_index = get_option( 'timu_rr_export_index', array() );
if ( is_array( $export_index ) ) {
    foreach ( array_keys( $export_index ) as $token ) {
        delete_option( 'timu_rr_export_' . $token );
    }
}
delete_option( 'timu_rr_export_index' );

global $wpdb;
$orphans = $wpdb->get_col( $wpdb->prepare(
    "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
    $wpdb->esc_like( 'timu_rr_export_' ) . '%'
) );
foreach ( $orphans as $orphan ) {
    delete_option( $orphan );
}
