<?php
/**
 * Plugin Name: ProRata WP Gist Uploader
 * Plugin URI: https://prorata.ai
 * Description: Upload WordPress posts in HTML form to an API with scheduled sync and real-time logging
 * Version: 1.0.0
 * Author: ProRata.ai
 * Author URI: https://prorata.ai
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: prt-wp-gist-uploader
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('PRT_GIST_VERSION', '1.0.0');
define('PRT_GIST_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PRT_GIST_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include required files
require_once PRT_GIST_PLUGIN_DIR . 'includes/class-post-filter.php';
require_once PRT_GIST_PLUGIN_DIR . 'includes/class-sync-manager.php';
require_once PRT_GIST_PLUGIN_DIR . 'includes/class-background-sync.php';
require_once PRT_GIST_PLUGIN_DIR . 'includes/class-export-manager.php';

/**
 * Plugin activation hook
 */
function prt_gist_activate() {
    // Set default options
    if (!get_option('prt_gist_api_key')) {
        add_option('prt_gist_api_key', '');
    }
    if (!get_option('prt_gist_api_endpoint')) {
        add_option('prt_gist_api_endpoint', 'https://api.example.com/sync');
    }
    if (!get_option('prt_gist_sync_frequency')) {
        add_option('prt_gist_sync_frequency', 'manual');
    }
    if (!get_option('prt_gist_last_sync')) {
        add_option('prt_gist_last_sync', '');
    }
    
    // Schedule the cron job (only if not manual)
    $sync_manager = new PRT_Gist_Sync_Manager();
    $sync_manager->schedule_sync();
}
register_activation_hook(__FILE__, 'prt_gist_activate');

/**
 * Plugin deactivation hook
 */
function prt_gist_deactivate() {
    // Clear scheduled cron
    $sync_manager = new PRT_Gist_Sync_Manager();
    $sync_manager->unschedule_sync();
}
register_deactivation_hook(__FILE__, 'prt_gist_deactivate');

/**
 * Add admin menu
 */
function prt_gist_admin_menu() {
    // Main menu item
    add_menu_page(
        'Gist Uploader',
        'Gist Uploader',
        'manage_options',
        'prt-gist-uploader',
        'prt_gist_sync_page',
        'dashicons-upload',
        30
    );
    
    // Sync page (same as main menu)
    add_submenu_page(
        'prt-gist-uploader',
        'Sync Posts',
        'Sync Posts',
        'manage_options',
        'prt-gist-uploader',
        'prt_gist_sync_page'
    );
    
    // Settings page
    add_submenu_page(
        'prt-gist-uploader',
        'Settings',
        'Settings',
        'manage_options',
        'prt-gist-settings',
        'prt_gist_settings_page'
    );
}
add_action('admin_menu', 'prt_gist_admin_menu');

/**
 * Include sync page
 */
function prt_gist_sync_page() {
    require_once PRT_GIST_PLUGIN_DIR . 'admin/sync-page.php';
}

/**
 * Include settings page
 */
function prt_gist_settings_page() {
    require_once PRT_GIST_PLUGIN_DIR . 'admin/settings.php';
}

/**
 * Enqueue admin scripts and styles
 */
function prt_gist_enqueue_admin_assets($hook) {
    // Only load on our plugin pages
    if (strpos($hook, 'prt-gist') === false) {
        return;
    }
    
    // Enqueue CSS
    wp_enqueue_style(
        'prt-gist-admin-style',
        PRT_GIST_PLUGIN_URL . 'assets/css/admin-style.css',
        array(),
        PRT_GIST_VERSION
    );
    
    // Enqueue JavaScript
    wp_enqueue_script(
        'prt-gist-sync-client',
        PRT_GIST_PLUGIN_URL . 'assets/js/sync-client.js',
        array('jquery'),
        PRT_GIST_VERSION,
        true
    );
    
    // Localize script with AJAX URL and nonce
    wp_localize_script('prt-gist-sync-client', 'prtGistData', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('prt_gist_sync_nonce'),
        'restUrl' => rest_url('prt-gist/v1')
    ));
}
add_action('admin_enqueue_scripts', 'prt_gist_enqueue_admin_assets');

/**
 * Register REST API routes
 */
function prt_gist_register_rest_routes() {
    register_rest_route('prt-gist/v1', '/sync-status', array(
        'methods' => 'GET',
        'callback' => 'prt_gist_rest_sync_status',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));
    
    register_rest_route('prt-gist/v1', '/sync-logs', array(
        'methods' => 'GET',
        'callback' => 'prt_gist_rest_sync_logs',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));
}
add_action('rest_api_init', 'prt_gist_register_rest_routes');

/**
 * REST API: Get sync status
 */
function prt_gist_rest_sync_status() {
    $job = PRT_Gist_Background_Sync::get_job_status();
    return rest_ensure_response($job);
}

/**
 * REST API: Get sync logs
 */
function prt_gist_rest_sync_logs(WP_REST_Request $request) {
    $process_id = $request->get_param('process_id');
    if (empty($process_id)) {
        return rest_ensure_response(array());
    }
    
    $logs = get_transient('prt_gist_sync_logs_' . $process_id);
    return rest_ensure_response($logs ?: array());
}

/**
 * Register AJAX handlers
 */
function prt_gist_register_ajax_handlers() {
    add_action('wp_ajax_prt_gist_sync_now', 'prt_gist_ajax_sync_now');
    add_action('wp_ajax_prt_gist_get_tags', 'prt_gist_ajax_get_tags');
    add_action('wp_ajax_prt_gist_check_status', 'prt_gist_ajax_check_status');
    add_action('wp_ajax_prt_gist_create_export', 'prt_gist_ajax_create_export');
    add_action('wp_ajax_prt_gist_delete_export', 'prt_gist_ajax_delete_export');
    add_action('wp_ajax_prt_gist_download_export', 'prt_gist_ajax_download_export');
}
add_action('init', 'prt_gist_register_ajax_handlers');

/**
 * AJAX handler for checking sync status
 */
function prt_gist_ajax_check_status() {
    check_ajax_referer('prt_gist_sync_nonce', 'nonce');
    
    $job_status = PRT_Gist_Background_Sync::get_job_status();
    wp_send_json_success($job_status);
}

/**
 * AJAX handler for manual sync
 */
function prt_gist_ajax_sync_now() {
    check_ajax_referer('prt_gist_sync_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }
    
    // Check if a sync is already running
    if (PRT_Gist_Background_Sync::is_sync_running()) {
        wp_send_json_error(array('message' => 'A sync is already in progress'));
        return;
    }
    
    $filter_type = isset($_POST['filter_type']) ? sanitize_text_field($_POST['filter_type']) : 'all';
    $filter_value = isset($_POST['filter_value']) ? sanitize_text_field($_POST['filter_value']) : '';
    
    // Create background sync instance with process ID
    $process_id = uniqid('sync_');
    $background_sync = new PRT_Gist_Background_Sync($process_id);
    
    // Store sync job info
    $job_data = array(
        'process_id' => $process_id,
        'filter_type' => $filter_type,
        'filter_value' => $filter_value,
        'status' => 'running',
        'started_at' => current_time('mysql'),
        'completed_at' => null
    );
    update_option('prt_gist_current_job', $job_data);
    
    // Execute sync IMMEDIATELY in this request
    error_log('PRT Gist: Starting sync execution for process ' . $process_id);
    
    try {
        // Execute the sync NOW (synchronously)
        $sync_result = $background_sync->execute_sync($filter_type, $filter_value, $process_id);
        error_log('PRT Gist: Sync execution completed successfully - ' . $sync_result['count'] . ' posts synced');
        
        // Automatically create archive for today's sync
        $export_manager = new PRT_Gist_Export_Manager();
        $sync_date = date('Ymd', current_time('timestamp'));
        
        error_log('PRT Gist: Creating archive for ' . $sync_date);
        $archive_result = $export_manager->create_archive($sync_date);
        
        if ($archive_result['success']) {
            error_log('PRT Gist: Archive created successfully - ' . $archive_result['archive_path']);
        } else {
            error_log('PRT Gist: Archive creation failed - ' . $archive_result['message']);
        }
        
        wp_send_json_success(array(
            'success' => true,
            'process_id' => $process_id,
            'message' => 'Sync completed successfully',
            'count' => $sync_result['count'],
            'archive_created' => $archive_result['success']
        ));
    } catch (Exception $e) {
        error_log('PRT Gist: Sync execution failed - ' . $e->getMessage());
        
        // Update job status to failed
        $job_data['status'] = 'failed';
        $job_data['error'] = $e->getMessage();
        $job_data['completed_at'] = current_time('mysql');
        update_option('prt_gist_current_job', $job_data);
        
        wp_send_json_error(array(
            'message' => 'Sync failed: ' . $e->getMessage()
        ));
    }
}

/**
 * AJAX handler for getting tags
 */
function prt_gist_ajax_get_tags() {
    check_ajax_referer('prt_gist_sync_nonce', 'nonce');
    
    $tags = get_tags(array('hide_empty' => false));
    $tag_list = array();
    
    foreach ($tags as $tag) {
        $tag_list[] = array(
            'id' => $tag->term_id,
            'name' => $tag->name,
            'count' => $tag->count
        );
    }
    
    wp_send_json_success($tag_list);
}


/**
 * Scheduled sync callback
 */
function prt_gist_scheduled_sync() {
    $sync_manager = new PRT_Gist_Sync_Manager();
    // Default to syncing all posts for scheduled sync
    $sync_manager->sync_posts('all', '');
}
add_action('prt_gist_sync_event', 'prt_gist_scheduled_sync');

/**
 * Background sync callback
 */
function prt_gist_background_sync_callback($filter_type, $filter_value, $process_id) {
    error_log('PRT Gist: Background sync callback triggered - Process ID: ' . $process_id);
    
    $background_sync = new PRT_Gist_Background_Sync();
    $background_sync->execute_sync($filter_type, $filter_value, $process_id);
    
    error_log('PRT Gist: Background sync callback completed');
}
add_action('prt_gist_background_sync', 'prt_gist_background_sync_callback', 10, 3);

/**
 * Alternative: Run sync immediately if cron doesn't trigger
 * This ensures sync runs even if WordPress cron is disabled
 */
function prt_gist_ensure_sync_runs() {
    // Only check on admin pages to avoid performance impact
    if (!is_admin()) {
        return;
    }
    
    // Check for pending sync jobs that haven't started
    $job = get_option('prt_gist_current_job', array());
    
    if (isset($job['status']) && $job['status'] === 'running') {
        $started_time = strtotime($job['started_at']);
        $current_time = current_time('timestamp');
        
        // If job has been "running" for more than 3 seconds without starting, execute it now
        if (($current_time - $started_time) > 3) {
            error_log('PRT Gist: Forcing sync execution (cron may not be working)');
            
            // Execute in shutdown to avoid blocking the page load
            add_action('shutdown', function() use ($job) {
                // Double-check the job is still pending
                $current_job = get_option('prt_gist_current_job', array());
                if (isset($current_job['status']) && $current_job['status'] === 'running') {
                    $background_sync = new PRT_Gist_Background_Sync();
                    $background_sync->execute_sync(
                        $job['filter_type'],
                        $job['filter_value'],
                        $job['process_id']
                    );
                }
            });
        }
    }
}
add_action('init', 'prt_gist_ensure_sync_runs');

/**
 * AJAX handler to clear stuck sync job
 */
function prt_gist_ajax_clear_stuck_sync() {
    check_ajax_referer('prt_gist_sync_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }
    
    // Clear the stuck job
    delete_option('prt_gist_current_job');
    
    wp_send_json_success(array('message' => 'Sync job cleared'));
}
add_action('wp_ajax_prt_gist_clear_stuck_sync', 'prt_gist_ajax_clear_stuck_sync');

/**
 * AJAX handler for creating export archive
 */
function prt_gist_ajax_create_export() {
    check_ajax_referer('prt_gist_export_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }
    
    $date_folder = isset($_POST['date_folder']) ? sanitize_text_field($_POST['date_folder']) : '';
    
    if (empty($date_folder)) {
        wp_send_json_error('Date folder is required');
        return;
    }
    
    $export_manager = new PRT_Gist_Export_Manager();
    $result = $export_manager->create_archive($date_folder);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result['message']);
    }
}

/**
 * AJAX handler for deleting export archive
 */
function prt_gist_ajax_delete_export() {
    check_ajax_referer('prt_gist_export_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }
    
    $date_folder = isset($_POST['date_folder']) ? sanitize_text_field($_POST['date_folder']) : '';
    
    if (empty($date_folder)) {
        wp_send_json_error('Date folder is required');
        return;
    }
    
    $export_manager = new PRT_Gist_Export_Manager();
    $result = $export_manager->delete_archive($date_folder);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result['message']);
    }
}

/**
 * AJAX handler for downloading export archive
 */
function prt_gist_ajax_download_export() {
    $date_folder = isset($_GET['date_folder']) ? sanitize_text_field($_GET['date_folder']) : '';
    $nonce = isset($_GET['nonce']) ? sanitize_text_field($_GET['nonce']) : '';
    
    if (empty($date_folder) || empty($nonce)) {
        wp_die('Invalid request');
    }
    
    $export_manager = new PRT_Gist_Export_Manager();
    
    if ($export_manager->download_archive($date_folder, $nonce)) {
        exit;
    } else {
        wp_die('Download failed. Please check permissions and try again.');
    }
}

