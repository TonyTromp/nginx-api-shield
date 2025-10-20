<?php
/**
 * Background Sync Process Class
 * Handles background sync processing detached from web client
 */

class PRT_Gist_Background_Sync {
    
    private $sync_manager;
    private $process_id;
    
    public function __construct($process_id = null) {
        $this->process_id = $process_id ?: uniqid('sync_');
        $this->sync_manager = new PRT_Gist_Sync_Manager($this->process_id);
    }
    
    /**
     * Start background sync process (deprecated - now handled in AJAX handler)
     *
     * @param string $filter_type Type of filter
     * @param mixed $filter_value Filter value
     * @return array Process info
     */
    public function start_background_sync($filter_type, $filter_value = '') {
        // This method is kept for backward compatibility but execution 
        // is now handled directly in the AJAX handler
        return array(
            'success' => true,
            'process_id' => $this->process_id,
            'message' => 'Background sync started'
        );
    }
    
    /**
     * Execute the background sync (called by cron)
     *
     * @param string $filter_type Type of filter
     * @param mixed $filter_value Filter value
     * @param string $process_id Process ID
     * @return array Result with success status and count
     */
    public function execute_sync($filter_type, $filter_value, $process_id) {
        // Update job status
        $job_data = get_option('prt_gist_current_job', array());
        $job_data['status'] = 'processing';
        update_option('prt_gist_current_job', $job_data);
        
        try {
            // Execute the sync
            $result = $this->sync_manager->sync_posts($filter_type, $filter_value);
            
            // Update job status
            $job_data['status'] = 'completed';
            $job_data['completed_at'] = current_time('mysql');
            $job_data['result'] = $result;
            update_option('prt_gist_current_job', $job_data);
            
            // Update last sync timestamp
            update_option('prt_gist_last_sync', current_time('mysql'));
            
            return $result;
            
        } catch (Exception $e) {
            $job_data['status'] = 'failed';
            $job_data['error'] = $e->getMessage();
            $job_data['completed_at'] = current_time('mysql');
            update_option('prt_gist_current_job', $job_data);
            
            return array(
                'success' => false,
                'message' => $e->getMessage(),
                'count' => 0
            );
        }
    }
    
    /**
     * Get current job status
     *
     * @return array Job status
     */
    public static function get_job_status() {
        return get_option('prt_gist_current_job', array(
            'status' => 'idle',
            'process_id' => null
        ));
    }
    
    /**
     * Check if a sync is currently running
     *
     * @return bool
     */
    public static function is_sync_running() {
        $job = self::get_job_status();
        return isset($job['status']) && in_array($job['status'], array('running', 'processing'));
    }
}

