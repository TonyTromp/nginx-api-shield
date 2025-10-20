<?php
/**
 * Settings Page
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Save settings
if (isset($_POST['prt_gist_save_settings'])) {
    check_admin_referer('prt_gist_settings_nonce');
    
    // Only update API settings if they are present in POST (from first form)
    if (isset($_POST['api_key'])) {
        update_option('prt_gist_api_key', sanitize_text_field($_POST['api_key'] ?? ''));
    }
    if (isset($_POST['api_endpoint'])) {
        $endpoint = $_POST['api_endpoint'] ?? '';
        update_option('prt_gist_api_endpoint', $endpoint ? esc_url_raw($endpoint) : '');
    }
    
    // Update scheduled sync settings (always present in second form)
    if (isset($_POST['sync_frequency'])) {
        $frequency = sanitize_text_field($_POST['sync_frequency'] ?? 'manual');
        update_option('prt_gist_sync_frequency', $frequency);
        
        // Only save sync_time if frequency is daily or weekly
        if ($frequency === 'daily' || $frequency === 'weekly') {
            if (isset($_POST['sync_time'])) {
                update_option('prt_gist_sync_time', sanitize_text_field($_POST['sync_time'] ?? '00:00'));
            }
        } else {
            // Reset time for hourly/manual
            update_option('prt_gist_sync_time', '00:00');
        }
    }
    if (isset($_POST['scheduled_filter_type'])) {
        update_option('prt_gist_scheduled_filter_type', sanitize_text_field($_POST['scheduled_filter_type'] ?? 'all'));
    }
    if (isset($_POST['scheduled_filter_date'])) {
        update_option('prt_gist_scheduled_filter_date', sanitize_text_field($_POST['scheduled_filter_date'] ?? ''));
    }
    if (isset($_POST['scheduled_filter_tag'])) {
        update_option('prt_gist_scheduled_filter_tag', sanitize_text_field($_POST['scheduled_filter_tag'] ?? ''));
    }
    
    // Reschedule the sync with new settings
    $sync_manager = new PRT_Gist_Sync_Manager();
    $sync_manager->unschedule_sync();
    $sync_manager->schedule_sync();
    
    echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
}

// Restore defaults
if (isset($_POST['prt_gist_restore_defaults'])) {
    check_admin_referer('prt_gist_settings_nonce');
    
    update_option('prt_gist_sync_frequency', 'manual');
    update_option('prt_gist_sync_time', '00:00');
    update_option('prt_gist_scheduled_filter_type', 'all');
    update_option('prt_gist_scheduled_filter_date', '');
    update_option('prt_gist_scheduled_filter_tag', '');
    
    // Unschedule any existing syncs
    $sync_manager = new PRT_Gist_Sync_Manager();
    $sync_manager->unschedule_sync();
    
    echo '<div class="notice notice-success is-dismissible"><p>Scheduled sync settings restored to defaults!</p></div>';
}

// Get current settings (always fetch from DB to show updated values)
$api_key = get_option('prt_gist_api_key', '');
$api_endpoint = get_option('prt_gist_api_endpoint', 'https://api.example.com/sync');
$sync_frequency = get_option('prt_gist_sync_frequency', 'manual');
$sync_time = get_option('prt_gist_sync_time', '00:00');
$scheduled_filter_type = get_option('prt_gist_scheduled_filter_type', 'all');
$scheduled_filter_date = get_option('prt_gist_scheduled_filter_date', '');
$scheduled_filter_tag = get_option('prt_gist_scheduled_filter_tag', '');

// Get available tags
$tags = get_tags(array('hide_empty' => false));

// Build current scheduled sync info text
$sync_info_parts = array();
if ($sync_frequency !== 'manual') {
    // Frequency (only show time for daily/weekly)
    $freq_text = ucfirst($sync_frequency);
    if (($sync_frequency === 'daily' || $sync_frequency === 'weekly') && $sync_time !== '00:00') {
        $freq_text .= ' at ' . date('g:i A', strtotime($sync_time));
    }
    $sync_info_parts[] = '<strong>Frequency:</strong> ' . $freq_text;
    
    // Filter
    if ($scheduled_filter_type === 'all') {
        $sync_info_parts[] = '<strong>Posts:</strong> All published posts';
    } elseif ($scheduled_filter_type === 'date' && !empty($scheduled_filter_date)) {
        $sync_info_parts[] = '<strong>Posts:</strong> Modified since ' . date('M j, Y', strtotime($scheduled_filter_date));
    } elseif ($scheduled_filter_type === 'tag' && !empty($scheduled_filter_tag)) {
        $tag_obj = get_term_by('slug', $scheduled_filter_tag, 'post_tag');
        $tag_name = $tag_obj ? $tag_obj->name : $scheduled_filter_tag;
        $sync_info_parts[] = '<strong>Posts:</strong> Tagged with "' . esc_html($tag_name) . '"';
    }
}
$has_scheduled_sync = !empty($sync_info_parts);
?>

<div class="wrap prt-gist-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="prt-gist-card">
        <form method="post" action="">
            <?php wp_nonce_field('prt_gist_settings_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="api_key">API Key</label>
                    </th>
                    <td>
                        <input type="text" 
                               id="api_key" 
                               name="api_key" 
                               value="<?php echo esc_attr($api_key); ?>" 
                               class="regular-text"
                               placeholder="Enter your API key">
                        <p class="description">Your API key for authentication</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="api_endpoint">API Endpoint URL</label>
                    </th>
                    <td>
                        <input type="url" 
                               id="api_endpoint" 
                               name="api_endpoint" 
                               value="<?php echo esc_url($api_endpoint); ?>" 
                               class="regular-text"
                               placeholder="https://api.example.com/sync">
                        <p class="description">The endpoint URL where posts will be synced</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" 
                       name="prt_gist_save_settings" 
                       id="submit" 
                       class="button button-primary" 
                       value="Save Settings">
            </p>
        </form>
    </div>
    
    <div class="prt-gist-card">
        <h2>Scheduled Sync</h2>
        <p class="description">Configure automatic sync settings for scheduled synchronization.</p>
        
        <?php if ($has_scheduled_sync): ?>
            <div class="notice notice-info inline" style="margin: 15px 0;">
                <p>
                    <span class="dashicons dashicons-clock" style="color: #2271b1;"></span>
                    <strong>Current Configuration:</strong><br>
                    <?php echo implode(' | ', $sync_info_parts); ?>
                </p>
            </div>
        <?php endif; ?>
        
        <form method="post" action="">
            <?php wp_nonce_field('prt_gist_settings_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="sync_frequency">Sync Frequency</label>
                    </th>
                    <td>
                        <select id="sync_frequency" name="sync_frequency" class="regular-text">
                            <option value="manual" <?php selected($sync_frequency, 'manual'); ?>>Manual (Sync Now)</option>
                            <option value="hourly" <?php selected($sync_frequency, 'hourly'); ?>>Hourly</option>
                            <option value="daily" <?php selected($sync_frequency, 'daily'); ?>>Daily</option>
                            <option value="weekly" <?php selected($sync_frequency, 'weekly'); ?>>Weekly</option>
                        </select>
                        <p class="description">How often should posts be automatically synced. Choose "Manual" to only sync when you click "Sync Now".</p>
                    </td>
                </tr>
                
                <tr id="sync_time_row" style="<?php echo ($sync_frequency === 'daily' || $sync_frequency === 'weekly') ? '' : 'display:none;'; ?>">
                    <th scope="row">
                        <label for="sync_time">Sync Time</label>
                    </th>
                    <td>
                        <input type="time" 
                               id="sync_time" 
                               name="sync_time" 
                               value="<?php echo esc_attr($sync_time); ?>" 
                               class="regular-text">
                        <p class="description">Specify the time when the sync should run (server time in 24-hour format)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label>Select Posts to Sync</label>
                    </th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="radio" 
                                       name="scheduled_filter_type" 
                                       value="all" 
                                       <?php checked($scheduled_filter_type, 'all'); ?>>
                                <strong>All Published Posts</strong>
                                <p class="description" style="margin-left: 25px;">Sync all published posts</p>
                            </label>
                            <br>
                            
                            <label>
                                <input type="radio" 
                                       name="scheduled_filter_type" 
                                       value="date" 
                                       <?php checked($scheduled_filter_type, 'date'); ?>>
                                <strong>Modified Since Date</strong>
                                <div style="margin-left: 25px; margin-top: 5px;">
                                    <input type="date" 
                                           id="scheduled_filter_date" 
                                           name="scheduled_filter_date" 
                                           value="<?php echo esc_attr($scheduled_filter_date); ?>" 
                                           class="regular-text">
                                    <p class="description">Sync posts modified after this date</p>
                                </div>
                            </label>
                            <br>
                            
                            <label>
                                <input type="radio" 
                                       name="scheduled_filter_type" 
                                       value="tag" 
                                       <?php checked($scheduled_filter_type, 'tag'); ?>>
                                <strong>By Tag</strong>
                                <div style="margin-left: 25px; margin-top: 5px;">
                                    <select id="scheduled_filter_tag" 
                                            name="scheduled_filter_tag" 
                                            class="regular-text">
                                        <option value="">Select a tag...</option>
                                        <?php foreach ($tags as $tag): ?>
                                            <option value="<?php echo esc_attr($tag->slug); ?>" 
                                                    <?php selected($scheduled_filter_tag, $tag->slug); ?>>
                                                <?php echo esc_html($tag->name); ?> (<?php echo $tag->count; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">Sync posts with specific tag</p>
                                </div>
                            </label>
                        </fieldset>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" 
                       name="prt_gist_save_settings" 
                       id="submit" 
                       class="button button-primary" 
                       value="Save Settings">
            </p>
        </form>
        
        <?php if ($has_scheduled_sync): ?>
            <form method="post" action="" style="margin-top: -20px;">
                <?php wp_nonce_field('prt_gist_settings_nonce'); ?>
                <p class="submit">
                    <input type="submit" 
                           name="prt_gist_restore_defaults" 
                           class="button button-secondary" 
                           value="Restore Default Settings"
                           onclick="return confirm('Are you sure you want to restore default settings? This will set sync frequency to Manual and clear all scheduled sync configuration.');">
                </p>
            </form>
        <?php endif; ?>
    </div>
    
    <div class="prt-gist-card">
        <h2>System Information</h2>
        <table class="widefat">
            <tr>
                <td><strong>Plugin Version:</strong></td>
                <td><?php echo PRT_GIST_VERSION; ?></td>
            </tr>
            <tr>
                <td><strong>WordPress Version:</strong></td>
                <td><?php echo get_bloginfo('version'); ?></td>
            </tr>
            <tr>
                <td><strong>PHP Version:</strong></td>
                <td><?php echo PHP_VERSION; ?></td>
            </tr>
            <tr>
                <td><strong>Sync Method:</strong></td>
                <td>REST API Polling (every 5 seconds)</td>
            </tr>
        </table>
    </div>
    
    <div class="prt-gist-card">
        <h2>Export Archives</h2>
        <p class="description">Create and download compressed archives of your exported posts organized by date.</p>
        
        <?php
        $export_manager = new PRT_Gist_Export_Manager();
        $export_folders = $export_manager->get_export_folders();
        ?>
        
        <?php if (empty($export_folders)): ?>
            <div class="notice notice-info inline">
                <p>No export folders found yet. Sync some posts first to create export data.</p>
            </div>
        <?php else: ?>
            <table class="widefat export-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Files</th>
                        <th>Size</th>
                        <th>Archive</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($export_folders as $folder): ?>
                        <tr data-date-folder="<?php echo esc_attr($folder['date_folder']); ?>">
                            <td>
                                <strong><?php echo esc_html($folder['formatted_date']); ?></strong>
                                <br>
                                <small class="description"><?php echo esc_html($folder['date_folder']); ?></small>
                            </td>
                            <td><?php echo esc_html($folder['file_count']); ?> HTML files</td>
                            <td><?php echo esc_html($export_manager->format_size($folder['folder_size'])); ?></td>
                            <td class="archive-status">
                                <?php if ($folder['archive_exists']): ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                                    <?php echo esc_html($export_manager->format_size($folder['archive_info']['size'])); ?>
                                    <br>
                                    <small class="description">
                                        Created: <?php echo date('M j, Y g:i a', $folder['archive_info']['created']); ?>
                                    </small>
                                <?php else: ?>
                                    <span class="dashicons dashicons-minus" style="color: #dba617;"></span>
                                    Not created
                                <?php endif; ?>
                            </td>
                            <td class="export-actions">
                                <?php if ($folder['archive_exists']): ?>
                                    <a href="<?php echo esc_url($export_manager->get_download_url($folder['date_folder'])); ?>" 
                                       class="button button-primary button-small">
                                        <span class="dashicons dashicons-download"></span> Download
                                    </a>
                                    <button class="button button-small delete-archive-btn" 
                                            data-date-folder="<?php echo esc_attr($folder['date_folder']); ?>"
                                            title="Delete entire export (archive + HTML files)">
                                        <span class="dashicons dashicons-trash"></span> Delete All
                                    </button>
                                <?php else: ?>
                                    <button class="button button-primary button-small create-archive-btn" 
                                            data-date-folder="<?php echo esc_attr($folder['date_folder']); ?>">
                                        <span class="dashicons dashicons-archive"></span> Create Archive
                                    </button>
                                    <button class="button button-small delete-archive-btn" 
                                            data-date-folder="<?php echo esc_attr($folder['date_folder']); ?>"
                                            title="Delete HTML files">
                                        <span class="dashicons dashicons-trash"></span> Delete All
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Toggle sync time field based on frequency (only show for daily/weekly)
    $('#sync_frequency').on('change', function() {
        const frequency = $(this).val();
        if (frequency === 'daily' || frequency === 'weekly') {
            $('#sync_time_row').show();
        } else {
            $('#sync_time_row').hide();
        }
    });
    
    // Export archive functionality
    const exportNonce = '<?php echo wp_create_nonce('prt_gist_export_nonce'); ?>';
    
    // Create archive button
    $('.create-archive-btn').on('click', function() {
        const btn = $(this);
        const dateFolder = btn.data('date-folder');
        const row = btn.closest('tr');
        
        if (confirm('Create archive for ' + dateFolder + '?')) {
            btn.prop('disabled', true).text('Creating...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'prt_gist_create_export',
                    nonce: exportNonce,
                    date_folder: dateFolder
                },
                success: function(response) {
                    if (response.success) {
                        location.reload(); // Reload to show updated archive status
                    } else {
                        alert('Error creating archive: ' + response.data);
                        btn.prop('disabled', false).html('<span class="dashicons dashicons-archive"></span> Create Archive');
                    }
                },
                error: function() {
                    alert('Error creating archive. Please try again.');
                    btn.prop('disabled', false).html('<span class="dashicons dashicons-archive"></span> Create Archive');
                }
            });
        }
    });
    
    // Delete archive button
    $('.delete-archive-btn').on('click', function() {
        const btn = $(this);
        const dateFolder = btn.data('date-folder');
        const row = btn.closest('tr');
        
        if (confirm('Delete entire export for ' + dateFolder + '?\n\nThis will remove:\n- Archive file (.tar.gz)\n- Date folder with all HTML files\n\nThis cannot be undone.')) {
            btn.prop('disabled', true).text('Deleting...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'prt_gist_delete_export',
                    nonce: exportNonce,
                    date_folder: dateFolder
                },
                success: function(response) {
                    if (response.success) {
                        // Remove the entire row with animation
                        row.fadeOut(400, function() {
                            $(this).remove();
                            
                            // Check if table is now empty
                            if ($('.export-table tbody tr').length === 0) {
                                $('.export-table').fadeOut(400, function() {
                                    $(this).after('<div class="notice notice-info inline"><p>No export folders found yet. Sync some posts first to create export data.</p></div>');
                                    $(this).remove();
                                });
                            }
                        });
                    } else {
                        alert('Error deleting archive: ' + response.data);
                        btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Delete');
                    }
                },
                error: function() {
                    alert('Error deleting archive. Please try again.');
                    btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Delete');
                }
            });
        }
    });
});
</script>

