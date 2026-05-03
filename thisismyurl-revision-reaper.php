<?php
/**
 * Author:      Christopher Ross
 * Author URI:  https://thisismyurl.com/
 * Plugin Name: Revision Reaper
 * Plugin URI:  https://thisismyurl.com/thisismyurl-revision-reaper/
 * Description: Non-destructive database optimization with persistent settings, custom scheduling, and email reporting.
 * Version:     0.6112
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: thisismyurl-revision-reaper
 * License:     GPL2
 * @package TIMU_Revision_Reaper
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'TIMU_REVISION_REAPER_VERSION' ) ) {
    define( 'TIMU_REVISION_REAPER_VERSION', '0.6123' );
}

require_once __DIR__ . '/includes/class-exporter.php';

/**
 * Class TIMU_Revision_Reaper
 * Handles database cleanup for revisions, trash, and spam with automated scheduling.
 */
class TIMU_Revision_Reaper {

    /**
     * Initialize plugin hooks.
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
        add_action( 'wp_ajax_timu_rr_purge_item', array( __CLASS__, 'ajax_purge_item' ) );
        add_action( 'wp_ajax_timu_rr_optimize_db', array( __CLASS__, 'ajax_optimize_db' ) );
        add_action( 'wp_ajax_timu_rr_pre_run_export', array( __CLASS__, 'ajax_pre_run_export' ) );
        add_action( 'admin_post_timu_rr_run', array( __CLASS__, 'handle_run_post' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'add_plugin_action_links' ) );
        
        // Automated schedule hook
        add_action( 'timu_rr_scheduled_cleanup', array( __CLASS__, 'do_scheduled_cleanup' ) );
    }

    public static function add_admin_menu() {
        add_management_page(
            __( 'Revision Reaper', 'thisismyurl-revision-reaper' ),
            __( 'Revision Reaper', 'thisismyurl-revision-reaper' ),
            'manage_options',
            'revision-reaper',
            array( __CLASS__, 'render_admin_page' )
        );
    }

    public static function add_plugin_action_links( $links ) {
        $custom_links = array(
            '<a href="' . admin_url( 'tools.php?page=revision-reaper' ) . '">' . esc_html__( 'Settings', 'thisismyurl-revision-reaper' ) . '</a>',
        );
        return array_merge( $custom_links, $links );
    }

    /**
     * Automated cleanup logic with email reporting.
     */
    public static function do_scheduled_cleanup() {
        $settings = array(
            'limit'         => get_option( 'timu_rr_limit', 3 ),
            'include_trash' => get_option( 'timu_rr_auto_trash', 0 ),
            'include_spam'  => get_option( 'timu_rr_auto_spam', 0 ),
        );
        $email = get_option( 'timu_rr_report_email', get_option( 'admin_email' ) );
        
        $items = self::get_eligible_items( $settings );
        $log = array();

        // Pre-delete snapshot so a regretted scheduled run can be reconstructed
        // from disk. Failure to write is logged but does not abort the run —
        // the run itself uses trash, not force-delete (see class header).
        if ( ! empty( $items ) ) {
            $export_path = TIMU_Revision_Reaper_Exporter::snapshot_before_run( $items, (int) $settings['limit'] );
            if ( $export_path ) {
                $log[] = 'Pre-run export written: ' . wp_basename( $export_path );
            } else {
                $log[] = 'WARNING: pre-run export failed to write.';
            }
        }

        foreach ( $items as $item ) {
            if ( 'trash' === $item['type'] ) {
                // Non-destructive: delegate to WP's trash lifecycle. WP itself
                // empties trash items older than EMPTY_TRASH_DAYS via the
                // wp_scheduled_delete cron — we never force-delete here.
                if ( ! self::trash_post_eligible_for_purge( $item['id'] ) ) {
                    continue;
                }
                wp_delete_post( $item['id'], false );
                $log[] = "Trashed Post #{$item['id']} purged (older than EMPTY_TRASH_DAYS)";
            } elseif ( 'spam' === $item['type'] ) {
                // Non-destructive: trash spam comments. Site owner can still
                // recover from the Comments > Trash list before WP empties it.
                wp_trash_comment( $item['id'] );
                $log[] = "Spam Comment #{$item['id']} moved to comment trash";
            } else {
                $revisions = wp_get_post_revisions( $item['id'] );
                $to_remove = array_slice( $revisions, $settings['limit'] );
                foreach ( $to_remove as $rev ) {
                    wp_delete_post_revision( $rev->ID );
                }
                $log[] = "Reaped revisions for Post #{$item['id']}";
            }
        }
        
        // Final database optimization
        global $wpdb;
        $tables = $wpdb->get_results( "SHOW TABLES", ARRAY_N );
        foreach ( $tables as $table ) {
            $wpdb->query( "OPTIMIZE TABLE {$table[0]}" );
        }

        // Send Email Report
        if ( ! empty( $email ) ) {
            $subject = '[' . get_bloginfo( 'name' ) . '] Revision Reaper Automated Report';
            $message = "The scheduled database cleanup has finished.\n\nItems Processed:\n" . ( ! empty( $log ) ? implode( "\n", $log ) : "No items required cleaning." );
            wp_mail( $email, $subject, $message );
        }
    }

    /**
     * Validate a YYYY-MM-DD date string strictly.
     *
     * @param string $date Date string from $_POST.
     * @return bool True when the input is a real Gregorian date in ISO form.
     */
    public static function validate_iso_date( $date ) {
        if ( ! is_string( $date ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            return false;
        }
        $parts = explode( '-', $date );
        return checkdate( (int) $parts[1], (int) $parts[2], (int) $parts[0] );
    }

    /**
     * Validate a HH:MM (24-hour) time string strictly.
     *
     * @param string $time Time string from $_POST.
     * @return bool True when the input is a real 24h time.
     */
    public static function validate_iso_time( $time ) {
        return is_string( $time ) && 1 === preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time );
    }

    /**
     * Convert a site-local "Y-m-d H:i" string to a UTC timestamp.
     *
     * @param string $local Site-local datetime string.
     * @return int|false UTC timestamp, or false on failure.
     */
    public static function parse_site_local_to_utc( $local ) {
        try {
            $tz = wp_timezone();
            $dt = new DateTimeImmutable( $local, $tz );
            return $dt->getTimestamp();
        } catch ( Exception $e ) {
            return false;
        }
    }

    /**
     * Whether a trashed post has lived in trash long enough to be purged.
     *
     * Honours the EMPTY_TRASH_DAYS constant — same rule WP core uses in
     * wp_scheduled_delete(). When EMPTY_TRASH_DAYS is 0 trash is disabled and
     * we never purge; when it's missing/unset we fall back to WP's 30-day
     * default. Only the post's own _wp_trash_meta_time is consulted.
     *
     * @param int $post_id Post ID to inspect.
     * @return bool True when the post is older than EMPTY_TRASH_DAYS.
     */
    public static function trash_post_eligible_for_purge( $post_id ) {
        $days = defined( 'EMPTY_TRASH_DAYS' ) ? (int) EMPTY_TRASH_DAYS : 30;

        if ( $days <= 0 ) {
            // Trash is disabled site-wide; never purge from a "trash" worker.
            return false;
        }

        $trashed_at = (int) get_post_meta( $post_id, '_wp_trash_meta_time', true );

        if ( $trashed_at <= 0 ) {
            // No trash timestamp — be conservative, don't purge.
            return false;
        }

        return ( time() - $trashed_at ) >= ( $days * DAY_IN_SECONDS );
    }

    /**
     * Scan database for eligible items using paged WP_Query.
     *
     * Hard caps are applied so a site with 100k posts doesn't trip an OOM or
     * a wpdb timeout on a single render. Operators who want everything in
     * one pass should use the WP-CLI command, which streams batches.
     *
     * @param array $settings { limit, include_trash, include_spam }.
     * @param array $caps     Optional overrides: { batch_size, max_items, post_types }.
     * @return array
     */
    public static function get_eligible_items( $settings, $caps = array() ) {
        $defaults = array(
            // Per-query batch size. 200 keeps wp_get_post_revisions() loops bounded.
            'batch_size' => 200,
            // Total items returned across all types in one call.
            'max_items'  => 1000,
            // Public + private CPTs only. 'any' silently includes attachments
            // and revisions and is never what we want here.
            'post_types' => self::get_target_post_types(),
        );
        $caps = array_merge( $defaults, $caps );

        $items     = array();
        $remaining = (int) $caps['max_items'];

        // 1. Posts whose revision count exceeds the keep limit.
        $paged    = 1;
        $rev_done = false;
        while ( ! $rev_done && $remaining > 0 ) {
            $q = new WP_Query( array(
                'post_type'              => $caps['post_types'],
                'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
                'posts_per_page'         => $caps['batch_size'],
                'paged'                  => $paged,
                'fields'                 => 'ids',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'orderby'                => 'ID',
                'order'                  => 'ASC',
            ) );

            if ( empty( $q->posts ) ) {
                break;
            }

            foreach ( $q->posts as $post_id ) {
                $revisions = wp_get_post_revisions( $post_id, array( 'fields' => 'ids' ) );
                if ( count( $revisions ) > (int) $settings['limit'] ) {
                    $items[]    = array( 'id' => (int) $post_id, 'type' => 'revision' );
                    $remaining--;
                    if ( $remaining <= 0 ) {
                        $rev_done = true;
                        break;
                    }
                }
            }

            if ( count( $q->posts ) < $caps['batch_size'] ) {
                break;
            }
            $paged++;
        }

        // 2. Trashed posts. Same chunking.
        if ( ! empty( $settings['include_trash'] ) && $remaining > 0 ) {
            $paged = 1;
            while ( $remaining > 0 ) {
                $trash = get_posts( array(
                    'post_status'            => 'trash',
                    'posts_per_page'         => $caps['batch_size'],
                    'paged'                  => $paged,
                    'fields'                 => 'ids',
                    'post_type'              => $caps['post_types'],
                    'no_found_rows'          => true,
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                    'orderby'                => 'ID',
                    'order'                  => 'ASC',
                ) );
                if ( empty( $trash ) ) {
                    break;
                }
                foreach ( $trash as $id ) {
                    $items[] = array( 'id' => (int) $id, 'type' => 'trash' );
                    $remaining--;
                    if ( $remaining <= 0 ) {
                        break 2;
                    }
                }
                if ( count( $trash ) < $caps['batch_size'] ) {
                    break;
                }
                $paged++;
            }
        }

        // 3. Spam comments — split auto vs manual; only auto is reaped.
        if ( ! empty( $settings['include_spam'] ) && $remaining > 0 ) {
            $offset = 0;
            while ( $remaining > 0 ) {
                $spam = get_comments( array(
                    'status' => 'spam',
                    'fields' => 'ids',
                    'number' => $caps['batch_size'],
                    'offset' => $offset,
                    'orderby' => 'comment_ID',
                    'order'  => 'ASC',
                ) );
                if ( empty( $spam ) ) {
                    break;
                }
                foreach ( $spam as $comment_id ) {
                    if ( ! self::is_auto_spam( (int) $comment_id ) ) {
                        // Manual spam — operator marked this; respect that
                        // signal and never auto-purge.
                        continue;
                    }
                    $items[] = array( 'id' => (int) $comment_id, 'type' => 'spam' );
                    $remaining--;
                    if ( $remaining <= 0 ) {
                        break 2;
                    }
                }
                if ( count( $spam ) < $caps['batch_size'] ) {
                    break;
                }
                $offset += $caps['batch_size'];
            }
        }

        return $items;
    }

    /**
     * Public+private custom post types this plugin is willing to scan.
     * Excludes attachments and revisions explicitly — those have their own
     * lifecycles and are never what we mean by "scan for old revisions."
     *
     * @return string[]
     */
    public static function get_target_post_types() {
        $types = get_post_types( array( 'show_ui' => true ), 'names' );
        unset( $types['attachment'], $types['revision'], $types['nav_menu_item'] );
        return array_values( $types );
    }

    /**
     * Distinguish Akismet auto-spam from manually-marked spam.
     *
     * Akismet stores akismet_result=spam and a history meta entry on
     * comments it flags. Anything without that signature was marked spam
     * by a human moderator and we don't auto-purge it.
     *
     * @param int $comment_id Comment ID.
     * @return bool True if this looks like Akismet auto-spam.
     */
    public static function is_auto_spam( $comment_id ) {
        $akismet_result = get_comment_meta( $comment_id, 'akismet_result', true );
        if ( 'true' === $akismet_result || 'spam' === $akismet_result ) {
            return true;
        }
        $history = get_comment_meta( $comment_id, 'akismet_as_submitted', true );
        if ( ! empty( $history ) ) {
            return true;
        }
        // No Akismet on the site at all? Treat as auto so the plugin remains
        // useful — operator opted into "include spam" knowing what that meant.
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if ( ! is_plugin_active( 'akismet/akismet.php' ) && ! function_exists( 'akismet_init' ) ) {
            return true;
        }
        return false;
    }

    /**
     * AJAX: Purge single item.
     */
    public static function ajax_purge_item() {
        check_ajax_referer( 'timu_rr_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Unauthorized' ); }

        $id         = absint( $_POST['item_id'] );
        $type       = sanitize_text_field( $_POST['item_type'] );
        $limit      = absint( $_POST['limit'] );
        $is_dry_run = isset( $_POST['dry_run'] ) && $_POST['dry_run'] === 'true';

        if ( $is_dry_run ) {
             wp_send_json_success( sprintf( __( '[DRY RUN] Would process item #%d (%s)', 'thisismyurl-revision-reaper' ), $id, $type ) );
        }

        switch ( $type ) {
            case 'revision':
                $revisions = wp_get_post_revisions( $id );
                $to_remove = array_slice( $revisions, $limit );
                foreach ( $to_remove as $rev ) { wp_delete_post_revision( $rev->ID ); }
                wp_send_json_success( sprintf( __( 'Reaped revisions for Post #%d', 'thisismyurl-revision-reaper' ), $id ) );
                break;
            case 'trash':
                if ( ! self::trash_post_eligible_for_purge( $id ) ) {
                    wp_send_json_success( sprintf(
                        /* translators: %d: post ID */
                        esc_html__( 'Skipped Trashed Post #%d (not yet older than EMPTY_TRASH_DAYS)', 'thisismyurl-revision-reaper' ),
                        $id
                    ) );
                    break;
                }
                wp_delete_post( $id, false );
                wp_send_json_success( sprintf(
                    /* translators: %d: post ID */
                    esc_html__( 'Purged Trashed Post #%d', 'thisismyurl-revision-reaper' ),
                    $id
                ) );
                break;
            case 'spam':
                wp_trash_comment( $id );
                wp_send_json_success( sprintf(
                    /* translators: %d: comment ID */
                    esc_html__( 'Moved Spam Comment #%d to comment trash', 'thisismyurl-revision-reaper' ),
                    $id
                ) );
                break;
        }
        wp_send_json_error();
    }

    /**
     * AJAX: snapshot eligible items before a live run.
     *
     * Triggered by the admin JS once, before the per-item delete loop. We
     * recompute the eligible set server-side rather than trusting the JS
     * payload — the page may have been open for hours.
     */
    public static function ajax_pre_run_export() {
        check_ajax_referer( 'timu_rr_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( esc_html__( 'Unauthorized', 'thisismyurl-revision-reaper' ), 403 );
        }

        $settings = array(
            'limit'         => (int) get_option( 'timu_rr_limit', 3 ),
            'include_trash' => (int) get_option( 'timu_rr_auto_trash', 0 ),
            'include_spam'  => (int) get_option( 'timu_rr_auto_spam', 0 ),
        );

        $items = self::get_eligible_items( $settings );

        if ( empty( $items ) ) {
            wp_send_json_success( esc_html__( 'Nothing to export — no eligible items.', 'thisismyurl-revision-reaper' ) );
        }

        $path = TIMU_Revision_Reaper_Exporter::snapshot_before_run( $items, $settings['limit'] );

        if ( ! $path ) {
            wp_send_json_error( esc_html__( 'Pre-run export failed to write. Aborting live run.', 'thisismyurl-revision-reaper' ), 500 );
        }

        wp_send_json_success( sprintf(
            /* translators: %s: filename of the JSON export */
            esc_html__( 'Pre-run export written: %s', 'thisismyurl-revision-reaper' ),
            esc_html( wp_basename( $path ) )
        ) );
    }

    /**
     * admin-post handler: state-changing entry point for "begin a run".
     *
     * Replaces the old GET trigger (tools.php?page=revision-reaper&reap=1).
     * Validates nonce + cap, then redirects back to the admin screen with
     * the run-mode flag carried in a transient (not the URL).
     */
    public static function handle_run_post() {
        check_admin_referer( 'timu_rr_run_action', 'timu_rr_run_nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to start a Revision Reaper run.', 'thisismyurl-revision-reaper' ) );
        }

        $is_dry_run = ! empty( $_POST['dry_run'] );

        $user_id = get_current_user_id();
        set_transient( 'timu_rr_run_intent_' . $user_id, $is_dry_run ? 'dry' : 'live', 5 * MINUTE_IN_SECONDS );

        wp_safe_redirect( admin_url( 'tools.php?page=revision-reaper&reap=1' ) );
        exit;
    }

    /**
     * AJAX: Optimize Database Tables.
     */
    public static function ajax_optimize_db() {
        check_ajax_referer( 'timu_rr_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }

        global $wpdb;
        $tables = $wpdb->get_results( "SHOW TABLES", ARRAY_N );
        foreach ( $tables as $table ) {
            $wpdb->query( "OPTIMIZE TABLE {$table[0]}" );
        }
        wp_send_json_success( __( 'Database tables optimized.', 'thisismyurl-revision-reaper' ) );
    }

    /**
     * Render Admin Dashboard.
     */
    public static function render_admin_page() {
        // Capability gate first — nothing in this view is safe for non-admins.
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to manage Revision Reaper.', 'thisismyurl-revision-reaper' ) );
        }

        // Handle Persistent Save Settings & Schedule.
        if ( isset( $_POST['rr_save_settings'] ) ) {
            // Nonce + capability gate. Both are required for any state change.
            check_admin_referer( 'timu_rr_save_settings', 'timu_rr_settings_nonce' );

            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You do not have permission to update these settings.', 'thisismyurl-revision-reaper' ) );
            }

            update_option( 'timu_rr_limit', absint( wp_unslash( $_POST['rev_limit'] ?? 3 ) ) );
            update_option( 'timu_rr_auto_trash', isset( $_POST['include_trash'] ) ? 1 : 0 );
            update_option( 'timu_rr_auto_spam', isset( $_POST['include_spam'] ) ? 1 : 0 );
            update_option( 'timu_rr_report_email', sanitize_email( wp_unslash( $_POST['report_email'] ?? '' ) ) );

            // Automation persistent setting.
            $enable_automation = isset( $_POST['enable_schedule'] ) ? 1 : 0;
            update_option( 'timu_rr_enable_automation', $enable_automation );

            // Validate schedule_date as YYYY-MM-DD; reject anything else.
            $raw_date     = isset( $_POST['schedule_date'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule_date'] ) ) : '';
            $raw_time     = isset( $_POST['schedule_time'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule_time'] ) ) : '';
            $valid_date   = self::validate_iso_date( $raw_date ) ? $raw_date : wp_date( 'Y-m-d' );
            $valid_time   = self::validate_iso_time( $raw_time ) ? $raw_time : '00:00';

            // Whitelist recurrence against actually-registered cron schedules.
            $allowed_recurrences = array_keys( wp_get_schedules() );
            $raw_recurrence      = isset( $_POST['schedule_recurrence'] ) ? sanitize_key( wp_unslash( $_POST['schedule_recurrence'] ) ) : 'weekly';
            $valid_recurrence    = in_array( $raw_recurrence, $allowed_recurrences, true ) ? $raw_recurrence : 'weekly';

            update_option( 'timu_rr_schedule_date', $valid_date );
            update_option( 'timu_rr_schedule_time', $valid_time );
            update_option( 'timu_rr_schedule_recurrence', $valid_recurrence );

            wp_clear_scheduled_hook( 'timu_rr_scheduled_cleanup' );
            if ( $enable_automation ) {
                // Treat the date+time as site-local; convert to UTC for cron.
                $timestamp = self::parse_site_local_to_utc( $valid_date . ' ' . $valid_time );
                if ( $timestamp ) {
                    wp_schedule_event( $timestamp, $valid_recurrence, 'timu_rr_scheduled_cleanup' );
                }
            }
            echo '<div class="updated"><p>' . esc_html__( 'Settings and Schedule updated successfully.', 'thisismyurl-revision-reaper' ) . '</p></div>';
        }

        // Persistent Option Retrieval
        $current_limit       = get_option( 'timu_rr_limit', 3 );
        $auto_trash          = get_option( 'timu_rr_auto_trash', 0 );
        $auto_spam           = get_option( 'timu_rr_auto_spam', 0 );
        $report_email        = get_option( 'timu_rr_report_email', get_option( 'admin_email' ) );
        $automation_enabled  = get_option( 'timu_rr_enable_automation', 0 );
        $next_run            = wp_next_scheduled( 'timu_rr_scheduled_cleanup' );
        $saved_date          = get_option( 'timu_rr_schedule_date', date('Y-m-d') );
        $saved_time          = get_option( 'timu_rr_schedule_time', '00:00' );
        $saved_recurrence    = get_option( 'timu_rr_schedule_recurrence', 'weekly' );

        $settings = array( 'limit' => $current_limit, 'include_trash' => $auto_trash, 'include_spam' => $auto_spam );

        // Run-mode is delivered via transient (set by handle_run_post) so it
        // can't be replayed from a URL or bookmarked. ?reap=1 is just a flag
        // indicating "we just came back from the admin-post POST".
        $user_id           = get_current_user_id();
        $run_intent_key    = 'timu_rr_run_intent_' . $user_id;
        $run_intent        = isset( $_GET['reap'] ) ? get_transient( $run_intent_key ) : false;
        $items             = $run_intent ? self::get_eligible_items( $settings ) : array();
        $is_dry_run_active = ( 'dry' === $run_intent );

        if ( $run_intent ) {
            // One-shot — clear so a refresh doesn't re-arm the runner.
            delete_transient( $run_intent_key );
        }
        ?>
        <div class="wrap">
            <h1>
                <?php esc_html_e( 'Revision Reaper', 'thisismyurl-revision-reaper' ); ?>
                <small style="font-size: 0.5em; font-weight: normal; vertical-align: middle; margin-left: 10px; color: #646970;">
                    <?php printf( esc_html__( 'by %s', 'thisismyurl-revision-reaper' ), 'thisismyurl' ); ?>
                </small>
            </h1>
            <p><?php esc_html_e( 'Optimize performance by reaping revisions, trash, and spam using persistent settings.', 'thisismyurl-revision-reaper' ); ?></p>

            <div id="poststuff">
                <div id="post-body" class="metabox-holder columns-2">
                    <div id="post-body-content">
                        
                        <form method="post">
                        <?php wp_nonce_field( 'timu_rr_save_settings', 'timu_rr_settings_nonce' ); ?>
                        <div class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e( 'Configuration & Automation', 'thisismyurl-revision-reaper' ); ?></span></h2>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Revisions to Keep', 'thisismyurl-revision-reaper' ); ?></th>
                                        <td><input type="number" name="rev_limit" id="rr-limit" value="<?php echo esc_attr( $current_limit ); ?>" class="small-text"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Include Trash', 'thisismyurl-revision-reaper' ); ?></th>
                                        <td><input type="checkbox" name="include_trash" id="rr-trash" value="1" <?php checked( $auto_trash ); ?>></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Include Spam', 'thisismyurl-revision-reaper' ); ?></th>
                                        <td><input type="checkbox" name="include_spam" id="rr-spam" value="1" <?php checked( $auto_spam ); ?>></td>
                                    </tr>
                                    <tr><td colspan="2"><hr></td></tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Enable Automation', 'thisismyurl-revision-reaper' ); ?></th>
                                        <td>
                                            <input type="checkbox" name="enable_schedule" value="1" <?php checked( $automation_enabled ); ?>>
                                            <?php if ( $next_run ) : ?>
                                                <p class="description" style="color:#2271b1; font-weight:bold;">
                                                    <?php printf( esc_html__( 'Next Scheduled Run: %s', 'thisismyurl-revision-reaper' ), date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_run ) ); ?>
                                                </p>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'First Run Date & Time', 'thisismyurl-revision-reaper' ); ?></th>
                                        <td>
                                            <input type="date" name="schedule_date" value="<?php echo esc_attr( $saved_date ); ?>">
                                            <input type="time" name="schedule_time" value="<?php echo esc_attr( $saved_time ); ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Recurrence Interval', 'thisismyurl-revision-reaper' ); ?></th>
                                        <td>
                                            <select name="schedule_recurrence">
                                                <?php
                                                // Render exactly the cron schedules WP knows about right now.
                                                // Falls back to hourly/daily/twicedaily/weekly which core ships.
                                                foreach ( wp_get_schedules() as $sched_key => $sched ) {
                                                    $label = isset( $sched['display'] ) ? $sched['display'] : $sched_key;
                                                    printf(
                                                        '<option value="%1$s" %2$s>%3$s</option>',
                                                        esc_attr( $sched_key ),
                                                        selected( $saved_recurrence, $sched_key, false ),
                                                        esc_html( $label )
                                                    );
                                                }
                                                ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><?php esc_html_e( 'Reporting Email', 'thisismyurl-revision-reaper' ); ?></th>
                                        <td><input type="email" name="report_email" value="<?php echo esc_attr( $report_email ); ?>" class="regular-text"></td>
                                    </tr>
                                </table>
                                
                                <p class="submit">
                                    <input type="submit" name="rr_save_settings" class="button button-secondary" value="<?php esc_attr_e( 'Save All Settings & Schedule', 'thisismyurl-revision-reaper' ); ?>">
                                </p>
                            </div>
                        </div>
                        </form>

                        <div class="postbox">
                            <h2 class="hndle"><span><?php esc_html_e( 'Run Cleanup', 'thisismyurl-revision-reaper' ); ?></span></h2>
                            <div class="inside">
                                <p>
                                    <?php esc_html_e( 'A "Dry Run" reports what would change without touching the database. A live run writes a JSON snapshot of every affected row to your uploads directory before any deletion, then trashes (never force-deletes) the matching items.', 'thisismyurl-revision-reaper' ); ?>
                                </p>

                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block; margin-right: 8px;">
                                    <?php wp_nonce_field( 'timu_rr_run_action', 'timu_rr_run_nonce' ); ?>
                                    <input type="hidden" name="action" value="timu_rr_run">
                                    <input type="hidden" name="dry_run" value="1">
                                    <button type="submit" class="button button-secondary"><?php esc_html_e( 'Dry Run', 'thisismyurl-revision-reaper' ); ?></button>
                                </form>

                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;" id="rr-live-run-form">
                                    <?php wp_nonce_field( 'timu_rr_run_action', 'timu_rr_run_nonce' ); ?>
                                    <input type="hidden" name="action" value="timu_rr_run">
                                    <p>
                                        <label>
                                            <input type="checkbox" name="rr_backup_confirm" id="rr-backup-confirm" value="1">
                                            <?php esc_html_e( 'I understand a JSON snapshot will be written to uploads/revision-reaper/exports/ before deletion and I have verified my own backups are current.', 'thisismyurl-revision-reaper' ); ?>
                                        </label>
                                    </p>
                                    <button type="submit" class="button button-primary" id="rr-live-run-btn" disabled><?php esc_html_e( 'Run Now (Live)', 'thisismyurl-revision-reaper' ); ?></button>
                                </form>
                            </div>
                        </div>

                        <div id="reap-area" class="postbox" <?php echo empty($items) ? 'style="display:none;"' : ''; ?>>
                            <h2 class="hndle"><span><?php echo $is_dry_run_active ? 'Simulation' : 'Live Activity'; ?> Log</span></h2>
                            <div class="inside">
                                <div id="rr-progress-container" style="background:#f0f0f1; height:20px; border-radius:3px; border:1px solid #c3c4c7; margin-bottom:10px; overflow:hidden;">
                                    <div id="rr-progress-bar" style="background:#2271b1; height:100%; width:0%;"></div>
                                </div>
                                <div id="rr-log" style="background:#f6f7f7; padding:10px; border:1px solid #dcdcde; height:200px; overflow-y:scroll; font-family:monospace;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            const nonce = '<?php echo esc_js( wp_create_nonce( "timu_rr_nonce" ) ); ?>';

            // Live-run button stays disabled until the operator ticks the
            // backup-confirm checkbox. The form itself also re-checks on
            // submit in case the disabled attr is removed in DevTools.
            $('#rr-backup-confirm').on('change', function() {
                $('#rr-live-run-btn').prop('disabled', ! this.checked);
            });

            $('#rr-live-run-form').on('submit', function(e) {
                if ( ! $('#rr-backup-confirm').is(':checked') ) {
                    e.preventDefault();
                    alert('<?php echo esc_js( __( 'Please confirm the backup acknowledgement before running a live cleanup.', 'thisismyurl-revision-reaper' ) ); ?>');
                    return false;
                }
                if ( ! confirm('<?php echo esc_js( __( 'Start live database cleanup? Items will be exported to JSON, then trashed.', 'thisismyurl-revision-reaper' ) ); ?>') ) {
                    e.preventDefault();
                    return false;
                }
                return true;
            });

            <?php if ( ! empty( $items ) ) : ?>
                const items = <?php echo wp_json_encode( $items ); ?>;
                const total = items.length;
                let completed = 0;

                const startProcessing = () => {
                    const processNext = () => {
                        if (items.length === 0) {
                            <?php if (!$is_dry_run_active) : ?>
                                $('#rr-log').prepend('<div><strong>Cleanup finished. Optimizing tables...</strong></div>');
                                $.post(ajaxurl, { action: 'timu_rr_optimize_db', nonce: nonce }).done(function(res) {
                                    $('#rr-log').prepend('<div style="color:green;">' + res.data + '</div>');
                                });
                            <?php else : ?>
                                 $('#rr-log').prepend('<div><strong>Simulation complete. No changes made.</strong></div>');
                            <?php endif; ?>
                            return;
                        }

                        const item = items.shift();
                        $.post(ajaxurl, {
                            action: 'timu_rr_purge_item',
                            item_id: item.id,
                            item_type: item.type,
                            limit: <?php echo (int) $current_limit; ?>,
                            dry_run: '<?php echo $is_dry_run_active ? 'true' : 'false'; ?>',
                            nonce: nonce
                        }).done(function(res) {
                            completed++;
                            $('#rr-progress-bar').css('width', Math.round((completed / total) * 100) + '%');
                            $('#rr-log').prepend('<div>' + res.data + '</div>');
                            processNext();
                        });
                    };
                    processNext();
                };

                <?php if ( ! $is_dry_run_active ) : ?>
                    // Live run: snapshot before we touch anything.
                    $('#rr-log').prepend('<div><strong>Writing pre-run export...</strong></div>');
                    $.post(ajaxurl, { action: 'timu_rr_pre_run_export', nonce: nonce }).done(function(res) {
                        if ( res && res.success ) {
                            $('#rr-log').prepend('<div style="color:green;">' + res.data + '</div>');
                            startProcessing();
                        } else {
                            const msg = ( res && res.data ) ? res.data : 'Pre-run export failed.';
                            $('#rr-log').prepend('<div style="color:#b32d2e;">Aborting: ' + msg + '</div>');
                        }
                    }).fail(function() {
                        $('#rr-log').prepend('<div style="color:#b32d2e;">Aborting: pre-run export request failed.</div>');
                    });
                <?php else : ?>
                    startProcessing();
                <?php endif; ?>
            <?php endif; ?>
        });
        </script>
        <?php
    }
}

/**
 * Initialize the plugin logic.
 */
TIMU_Revision_Reaper::init();


/**
 * GitHub Updater Integration.
 * Loads the updater logic once all plugins have been loaded by WordPress.
 */
add_action( 'plugins_loaded', function() {
    $updater_path = plugin_dir_path( __FILE__ ) . 'updater.php';
    
    // Check if the updater file exists before attempting to require it.
    if ( file_exists( $updater_path ) ) {
        require_once $updater_path;
        
        // Ensure the GitHub Updater class is available.
        if ( class_exists( 'FWO_GitHub_Updater' ) ) {
            new FWO_GitHub_Updater( array(
                'slug'               => 'thisismyurl-revision-reaper',
                'proper_folder_name' => 'thisismyurl-revision-reaper',
                'api_url'            => 'https://api.github.com/repos/thisismyurl/thisismyurl-revision-reaper/releases/latest',
                'github_url'         => 'https://github.com/thisismyurl/thisismyurl-revision-reaper',
                'plugin_file'        => __FILE__,
            ) );
        }
    }
});