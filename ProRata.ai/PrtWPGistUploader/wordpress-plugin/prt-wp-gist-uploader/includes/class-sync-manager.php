<?php
/**
 * Sync Manager Class
 * Handles synchronization of posts to the API
 */

class PRT_Gist_Sync_Manager {
    
    private $post_filter;
    private $upload_base_dir;
    private $process_id;
    private $logs = array();
    
    public function __construct($process_id = null) {
        $this->post_filter = new PRT_Gist_Post_Filter();
        $this->process_id = $process_id;
        
        // Set up upload directory
        $upload_dir = wp_upload_dir();
        $this->upload_base_dir = $upload_dir['basedir'] . '/prt-gist-sync';
        
        // Ensure base directory exists
        $this->ensure_directory_exists($this->upload_base_dir);
        
        // Clear old logs when starting new sync
        if ($this->process_id) {
            delete_transient('prt_gist_sync_logs_' . $this->process_id);
        }
    }
    
    /**
     * Sync posts based on filter criteria
     *
     * @param string $filter_type Type of filter (all, date, tag)
     * @param mixed $filter_value Filter value
     * @return array Result array with success status and message
     */
    public function sync_posts($filter_type, $filter_value = '') {
        try {
            // Get posts to sync
            $posts = $this->post_filter->get_posts($filter_type, $filter_value);
            
            if (empty($posts)) {
                $this->store_sync_log('info', 'No posts found matching the filter criteria.');
                return array(
                    'success' => true,
                    'message' => 'No posts to sync',
                    'count' => 0
                );
            }
            
            $this->store_sync_log('info', 'Starting sync operation...');
            $this->store_sync_log('info', sprintf('Found %d post(s) to sync.', count($posts)));
            
            // Simulate API calls for each post
            $synced_count = 0;
            foreach ($posts as $post) {
                $result = $this->simulate_api_call($post);
                
                if ($result) {
                    $synced_count++;
                    $this->store_sync_log(
                        'success',
                        sprintf('Syncing post #%d: %s', $post['id'], $post['title'])
                    );
                } else {
                    $this->store_sync_log(
                        'error',
                        sprintf('Failed to sync post #%d: %s', $post['id'], $post['title'])
                    );
                }
                
                // Small delay to simulate API call
                usleep(100000); // 0.1 second
            }
            
            $this->store_sync_log('success', sprintf('Sync complete: %d post(s) synced successfully!', $synced_count));
            
            return array(
                'success' => true,
                'message' => 'Sync completed successfully',
                'count' => $synced_count
            );
            
        } catch (Exception $e) {
            $this->store_sync_log('error', 'Sync failed: ' . $e->getMessage());
            
            return array(
                'success' => false,
                'message' => $e->getMessage(),
                'count' => 0
            );
        }
    }
    
    /**
     * Process post and create HTML file
     *
     * @param array $post Post data
     * @return bool Success status
     */
    private function simulate_api_call($post) {
        try {
            // Create HTML file for the post
            $result = $this->create_post_html_file($post);
            
            // Log the data that would be sent (for debugging)
            error_log('PRT Gist Uploader - Created file: ' . $result['file_path']);
            
            return $result['success'];
            
        } catch (Exception $e) {
            error_log('PRT Gist Uploader - Error creating file: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create HTML file for a post in date-organized folder
     *
     * @param array $post Post data
     * @return array Result with success status and file path
     */
    private function create_post_html_file($post) {
        // Get current sync date in YYYYMMDD format (when sync is run)
        $sync_date = date('Ymd', current_time('timestamp'));
        
        // Create date folder path based on sync date
        $date_folder = $this->upload_base_dir . '/' . $sync_date;
        $this->ensure_directory_exists($date_folder);
        
        // Get post slug
        $post_obj = get_post($post['id']);
        $slug = $post_obj->post_name;
        
        // Create file path
        $file_path = $date_folder . '/' . $slug . '.html';
        
        // Generate HTML content
        $html_content = $this->generate_html_content($post);
        
        // Write file
        $bytes_written = file_put_contents($file_path, $html_content);
        
        if ($bytes_written === false) {
            throw new Exception('Failed to write file: ' . $file_path);
        }
        
        return array(
            'success' => true,
            'file_path' => $file_path,
            'date_folder' => $sync_date,
            'slug' => $slug
        );
    }
    
    /**
     * Generate complete HTML content for a post
     *
     * @param array $post Post data
     * @return string HTML content
     */
    private function generate_html_content($post) {
        $html = '<!DOCTYPE html>' . "\n";
        $html .= '<html lang="en">' . "\n";
        $html .= '<head>' . "\n";
        $html .= '    <meta charset="UTF-8">' . "\n";
        $html .= '    <meta name="viewport" content="width=device-width, initial-scale=1.0">' . "\n";
        $html .= '    <title>' . esc_html($post['title']) . '</title>' . "\n";
        $html .= '    <meta name="author" content="' . esc_attr($post['author']) . '">' . "\n";
        $html .= '    <meta name="date" content="' . esc_attr($post['date_published']) . '">' . "\n";
        
        // Add tags as keywords
        if (!empty($post['tags'])) {
            $html .= '    <meta name="keywords" content="' . esc_attr(implode(', ', $post['tags'])) . '">' . "\n";
        }
        
        // Add basic styling
        $html .= '    <style>' . "\n";
        $html .= '        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; max-width: 800px; margin: 0 auto; padding: 20px; color: #333; }' . "\n";
        $html .= '        h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }' . "\n";
        $html .= '        .meta { color: #7f8c8d; font-size: 0.9em; margin: 20px 0; padding: 15px; background: #ecf0f1; border-radius: 5px; }' . "\n";
        $html .= '        .content { margin-top: 30px; }' . "\n";
        $html .= '        .tags { margin-top: 30px; }' . "\n";
        $html .= '        .tag { display: inline-block; background: #3498db; color: white; padding: 5px 10px; margin: 5px; border-radius: 3px; font-size: 0.85em; }' . "\n";
        $html .= '        img { max-width: 100%; height: auto; }' . "\n";
        $html .= '    </style>' . "\n";
        $html .= '</head>' . "\n";
        $html .= '<body>' . "\n";
        
        // Add header
        $html .= '    <article>' . "\n";
        $html .= '        <header>' . "\n";
        $html .= '            <h1>' . esc_html($post['title']) . '</h1>' . "\n";
        $html .= '            <div class="meta">' . "\n";
        $html .= '                <strong>Author:</strong> ' . esc_html($post['author']) . '<br>' . "\n";
        $html .= '                <strong>Published:</strong> ' . date('F j, Y', strtotime($post['date_published'])) . '<br>' . "\n";
        
        if (!empty($post['categories'])) {
            $html .= '                <strong>Categories:</strong> ' . esc_html(implode(', ', $post['categories'])) . '<br>' . "\n";
        }
        
        $html .= '                <strong>Permalink:</strong> <a href="' . esc_url($post['permalink']) . '">' . esc_html($post['permalink']) . '</a>' . "\n";
        $html .= '            </div>' . "\n";
        $html .= '        </header>' . "\n";
        
        // Add content
        $html .= '        <div class="content">' . "\n";
        $html .= $post['content_html'] . "\n";
        $html .= '        </div>' . "\n";
        
        // Add tags
        if (!empty($post['tags'])) {
            $html .= '        <div class="tags">' . "\n";
            $html .= '            <strong>Tags:</strong><br>' . "\n";
            foreach ($post['tags'] as $tag) {
                $html .= '            <span class="tag">' . esc_html($tag) . '</span>' . "\n";
            }
            $html .= '        </div>' . "\n";
        }
        
        $html .= '    </article>' . "\n";
        $html .= '</body>' . "\n";
        $html .= '</html>';
        
        return $html;
    }
    
    /**
     * Ensure directory exists and is writable
     *
     * @param string $directory Directory path
     * @return bool Success status
     */
    private function ensure_directory_exists($directory) {
        if (!file_exists($directory)) {
            if (!wp_mkdir_p($directory)) {
                throw new Exception('Failed to create directory: ' . $directory);
            }
            
            // Create .htaccess to protect directory
            $htaccess_file = $directory . '/.htaccess';
            if (!file_exists($htaccess_file)) {
                file_put_contents($htaccess_file, 'Options -Indexes' . "\n" . 'Require all granted');
            }
        }
        
        if (!is_writable($directory)) {
            throw new Exception('Directory is not writable: ' . $directory);
        }
        
        return true;
    }
    
    /**
     * Prepare post data for API
     *
     * @param array $post Post data from filter
     * @return array Formatted post data for API
     */
    private function prepare_post_data($post) {
        return array(
            'id' => $post['id'],
            'title' => $post['title'],
            'content_html' => $post['content_html'],
            'excerpt' => $post['excerpt'],
            'permalink' => $post['permalink'],
            'date_published' => $post['date_published'],
            'date_modified' => $post['date_modified'],
            'author' => $post['author'],
            'tags' => $post['tags'],
            'categories' => $post['categories'],
            'featured_image' => $post['featured_image']
        );
    }
    
    /**
     * Store log message in transient for polling
     *
     * @param string $type Message type (info, success, error, warning)
     * @param string $message Log message
     */
    private function store_sync_log($type, $message) {
        $this->logs[] = array(
            'type' => $type,
            'message' => $message,
            'timestamp' => time()
        );
        
        // Store logs in transient if process_id is set
        if ($this->process_id) {
            set_transient('prt_gist_sync_logs_' . $this->process_id, $this->logs, 3600);
        }
        
        // Also log to error_log for debugging
        error_log('PRT Gist [' . strtoupper($type) . ']: ' . $message);
    }
    
    /**
     * Schedule sync based on settings
     */
    public function schedule_sync() {
        // Clear any existing schedule
        $this->unschedule_sync();
        
        $frequency = get_option('prt_gist_sync_frequency', 'manual');
        
        // Don't schedule anything if manual mode
        if ($frequency === 'manual') {
            return;
        }
        
        // Schedule the event
        if (!wp_next_scheduled('prt_gist_sync_event')) {
            wp_schedule_event(time(), $frequency, 'prt_gist_sync_event');
        }
    }
    
    /**
     * Unschedule sync
     */
    public function unschedule_sync() {
        $timestamp = wp_next_scheduled('prt_gist_sync_event');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'prt_gist_sync_event');
        }
    }
}

