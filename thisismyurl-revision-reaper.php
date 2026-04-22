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
        
        foreach ( $items as $item ) {
            if ( 'trash' === $item['type'] ) {
                wp_delete_post( $item['id'], true );
                $log[] = "Deleted Trashed Post #{$item['id']}";
            } elseif ( 'spam' === $item['type'] ) {
                wp_delete_comment( $item['id'], true );
                $log[] = "Deleted Spam Comment #{$item['id']}";
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
     * Scan database for eligible items using WP_Query.
     */
    public static function get_eligible_items( $settings ) {
        $items = array();
        $posts = new WP_Query( array( 'post_type' => 'any', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) );

        if ( ! empty( $posts->posts ) ) {
            foreach ( $posts->posts as $post_id ) {
                $revisions = wp_get_post_revisions( $post_id, array( 'fields' => 'ids' ) );
                if ( count( $revisions ) > $settings['limit'] ) {
                    $items[] = array( 'id' => $post_id, 'type' => 'revision' );
                }
            }
        }

        if ( $settings['include_trash'] ) {
            $trash = get_posts( array( 'post_status' => 'trash', 'posts_per_page' => -1, 'fields' => 'ids', 'post_type' => 'any' ) );
            foreach ( $trash as $id ) { $items[] = array( 'id' => $id, 'type' => 'trash' ); }
        }

        if ( $settings['include_spam'] ) {
            $spam = get_comments( array( 'status' => 'spam', 'fields' => 'ids' ) );
            foreach ( $spam as $id ) { $items[] = array( 'id' => $id, 'type' => 'spam' ); }
        }

        return $items;
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
                wp_delete_post( $id, true );
                wp_send_json_success( sprintf( __( 'Deleted Trashed Post #%d', 'thisismyurl-revision-reaper' ), $id ) );
                break;
            case 'spam':
                wp_delete_comment( $id, true );
                wp_send_json_success( sprintf( __( 'Deleted Spam Comment #%d', 'thisismyurl-revision-reaper' ), $id ) );
                break;
        }
        wp_send_json_error();
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
        // Handle Persistent Save Settings & Schedule
        if ( isset( $_POST['rr_save_settings'] ) ) {
            update_option( 'timu_rr_limit', absint( $_POST['rev_limit'] ) );
            update_option( 'timu_rr_auto_trash', isset( $_POST['include_trash'] ) ? 1 : 0 );
            update_option( 'timu_rr_auto_spam', isset( $_POST['include_spam'] ) ? 1 : 0 );
            update_option( 'timu_rr_report_email', sanitize_email( $_POST['report_email'] ) );
            
            // Automation persistent setting
            $enable_automation = isset( $_POST['enable_schedule'] ) ? 1 : 0;
            update_option( 'timu_rr_enable_automation', $enable_automation );
            
            // Meta display settings
            update_option( 'timu_rr_schedule_date', sanitize_text_field( $_POST['schedule_date'] ) );
            update_option( 'timu_rr_schedule_time', sanitize_text_field( $_POST['schedule_time'] ) );
            update_option( 'timu_rr_schedule_recurrence', sanitize_text_field( $_POST['schedule_recurrence'] ) );

            wp_clear_scheduled_hook( 'timu_rr_scheduled_cleanup' );
            if ( $enable_automation ) {
                $timestamp = strtotime( $_POST['schedule_date'] . ' ' . $_POST['schedule_time'] );
                if ( $timestamp ) {
                    wp_schedule_event( $timestamp, $_POST['schedule_recurrence'], 'timu_rr_scheduled_cleanup' );
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
        $items = isset( $_GET['reap'] ) ? self::get_eligible_items( $settings ) : array();
        $is_dry_run_active = isset( $_GET['dry_run'] ) && $_GET['dry_run'] === 'true';
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
                                                <option value="daily" <?php selected( $saved_recurrence, 'daily' ); ?>><?php _e('Daily'); ?></option>
                                                <option value="weekly" <?php selected( $saved_recurrence, 'weekly' ); ?>><?php _e('Weekly'); ?></option>
                                                <option value="monthly" <?php selected( $saved_recurrence, 'monthly' ); ?>><?php _e('Monthly'); ?></option>
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
                                    <button type="button" id="btn-dry-run" class="button button-secondary"><?php esc_html_e( 'Dry Run', 'thisismyurl-revision-reaper' ); ?></button>
                                    <button type="button" id="btn-start" class="button button-primary"><?php esc_html_e( 'Run Now (Live)', 'thisismyurl-revision-reaper' ); ?></button>
                                </p>
                            </div>
                        </div>
                        </form>

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
            
            $('#btn-start, #btn-dry-run').click(function() {
                const isDryRun = $(this).attr('id') === 'btn-dry-run';
                if(!isDryRun && !confirm('<?php echo esc_js( __( "Start live database cleanup?", "thisismyurl-revision-reaper" ) ); ?>')) return;
                window.location.href = 'tools.php?page=revision-reaper&reap=1&dry_run='+isDryRun;
            });

            <?php if ( ! empty( $items ) ) : ?>
                const items = <?php echo wp_json_encode( $items ); ?>;
                const total = items.length;
                let completed = 0;

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
                        limit: <?php echo (int)$current_limit; ?>,
                        dry_run: '<?php echo $is_dry_run_active ? "true" : "false"; ?>',
                        nonce: nonce 
                    }).done(function(res) {
                        completed++;
                        $('#rr-progress-bar').css('width', Math.round((completed / total) * 100) + '%');
                        $('#rr-log').prepend('<div>' + res.data + '</div>');
                        processNext();
                    });
                };
                processNext();
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