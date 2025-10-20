<?php
/**
 * Sync Page
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Get last sync time
$last_sync = get_option('prt_gist_last_sync', '');
$last_sync_display = 'Never';
if ($last_sync) {
    // Convert to timestamp and format using WordPress timezone
    $timestamp = strtotime($last_sync);
    $last_sync_display = date_i18n('F j, Y g:i a', $timestamp);
}

// Count export archives
$upload_dir = wp_upload_dir();
$sync_dir = $upload_dir['basedir'] . '/prt-gist-sync';
$archive_count = 0;
if (file_exists($sync_dir)) {
    $files = glob($sync_dir . '/*-wp-export.tar.gz');
    $archive_count = count($files);
}

// Get all tags
$tags = get_tags(array('hide_empty' => false));
?>

<div class="wrap prt-gist-sync">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="prt-gist-card">
        <h2>Sync Posts to API</h2>
        
        <div class="sync-status">
            <p><strong>Last Sync:</strong> <span id="last-sync-time"><?php echo esc_html($last_sync_display); ?></span></p>
        </div>
        
        <div class="sync-filters">
            <h3>Select Posts to Sync</h3>
            
            <div class="filter-option">
                <label>
                    <input type="radio" name="filter_type" value="all" checked>
                    <strong>All Published Posts</strong>
                </label>
                <p class="description">Sync all published posts</p>
            </div>
            
            <div class="filter-option">
                <label>
                    <input type="radio" name="filter_type" value="date">
                    <strong>Modified Since Date</strong>
                </label>
                <div class="filter-input" id="date-filter" style="display:none; margin-left: 25px;">
                    <input type="date" 
                           id="filter_date" 
                           name="filter_date" 
                           value="<?php echo date('Y-m-d', strtotime('-7 days')); ?>">
                    <p class="description">Sync posts modified since this date</p>
                </div>
            </div>
            
            <div class="filter-option">
                <label>
                    <input type="radio" name="filter_type" value="tag">
                    <strong>By Tag</strong>
                </label>
                <div class="filter-input" id="tag-filter" style="display:none; margin-left: 25px;">
                    <select id="filter_tag" name="filter_tag">
                        <option value="">Select a tag...</option>
                        <?php foreach ($tags as $tag): ?>
                            <option value="<?php echo esc_attr($tag->term_id); ?>">
                                <?php echo esc_html($tag->name); ?> (<?php echo $tag->count; ?> posts)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Sync posts with a specific tag</p>
                </div>
            </div>
        </div>
        
        <div class="sync-actions">
            <button id="sync-now-btn" class="button button-primary button-large">
                <span class="dashicons dashicons-update"></span> Sync Now
            </button>
            <button id="reset-sync-btn" class="button button-secondary button-large" style="display:none;">
                <span class="dashicons dashicons-dismiss"></span> Reset Sync
            </button>
            <span id="sync-status-message"></span>
        </div>
    </div>
    
    <div class="prt-gist-card">
        <h2>Sync Log</h2>
        <div id="sync-log" class="sync-log-container">
            <div class="log-entry log-info">Waiting for sync operation...</div>
        </div>
        <div class="log-actions">
            <button id="clear-log-btn" class="button">Clear Log</button>
        </div>
    </div>
    
    <div class="prt-gist-card">
        <h2>Quick Stats</h2>
        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-value"><?php echo wp_count_posts()->publish; ?></div>
                <div class="stat-label">Total Published Posts</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?php echo count($tags); ?></div>
                <div class="stat-label">Total Tags</div>
            </div>
            <div class="stat-box">
                <div class="stat-value" id="archive-count"><?php echo $archive_count; ?></div>
                <div class="stat-label">Export Archives</div>
            </div>
        </div>
    </div>
    
    <div class="prt-gist-card">
        <h2>File Storage Information</h2>
        <table class="widefat">
            <tr>
                <td><strong>Storage Location:</strong></td>
                <td>
                    <code><?php 
                        $upload_dir = wp_upload_dir();
                        echo esc_html($upload_dir['basedir'] . '/prt-gist-sync/');
                    ?></code>
                </td>
            </tr>
            <tr>
                <td><strong>Folder Structure:</strong></td>
                <td>
                    Posts are organized by date (YYYYMMDD folders)<br>
                    <small>Example: <code>20251018/my-article.html</code></small>
                </td>
            </tr>
            <tr>
                <td><strong>File Format:</strong></td>
                <td>
                    Complete HTML documents named by post slug<br>
                    <small>Each file includes post title, content, metadata, and styling</small>
                </td>
            </tr>
            <tr>
                <td><strong>Background Processing:</strong></td>
                <td>
                    Syncs run and complete immediately, with progress tracked in logs<br>
                    <small>Status updates are retrieved via REST API polling every 5 seconds</small>
                </td>
            </tr>
        </table>
    </div>
</div>

