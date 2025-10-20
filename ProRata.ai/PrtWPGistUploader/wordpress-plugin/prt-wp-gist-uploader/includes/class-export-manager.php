<?php
/**
 * Export Manager Class
 * Handles creation and management of export archives
 */

class PRT_Gist_Export_Manager {
    
    private $upload_base_dir;
    private $upload_base_url;
    
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->upload_base_dir = $upload_dir['basedir'] . '/prt-gist-sync';
        $this->upload_base_url = $upload_dir['baseurl'] . '/prt-gist-sync';
    }
    
    /**
     * Get all date folders with export information
     *
     * @return array Array of date folders with metadata
     */
    public function get_export_folders() {
        $folders = array();
        
        if (!file_exists($this->upload_base_dir)) {
            return $folders;
        }
        
        $items = scandir($this->upload_base_dir);
        
        foreach ($items as $item) {
            // Skip hidden files and current/parent directory markers
            if ($item[0] === '.' || $item === '.htaccess') {
                continue;
            }
            
            $full_path = $this->upload_base_dir . '/' . $item;
            
            // Only process directories that match YYYYMMDD format
            if (is_dir($full_path) && preg_match('/^\d{8}$/', $item)) {
                $folders[] = array(
                    'date_folder' => $item,
                    'path' => $full_path,
                    'formatted_date' => $this->format_date_folder($item),
                    'file_count' => $this->count_html_files($full_path),
                    'folder_size' => $this->get_folder_size($full_path),
                    'archive_exists' => $this->check_archive_exists($item),
                    'archive_info' => $this->get_archive_info($item)
                );
            }
        }
        
        // Sort by date descending (newest first)
        usort($folders, function($a, $b) {
            return strcmp($b['date_folder'], $a['date_folder']);
        });
        
        return $folders;
    }
    
    /**
     * Format YYYYMMDD to readable date
     *
     * @param string $date_folder YYYYMMDD format
     * @return string Formatted date
     */
    private function format_date_folder($date_folder) {
        $year = substr($date_folder, 0, 4);
        $month = substr($date_folder, 4, 2);
        $day = substr($date_folder, 6, 2);
        
        return date('F j, Y', mktime(0, 0, 0, $month, $day, $year));
    }
    
    /**
     * Count HTML files in a folder
     *
     * @param string $folder_path Path to folder
     * @return int Number of HTML files
     */
    private function count_html_files($folder_path) {
        $count = 0;
        $files = scandir($folder_path);
        
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'html') {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Get folder size in bytes
     *
     * @param string $folder_path Path to folder
     * @return int Size in bytes
     */
    private function get_folder_size($folder_path) {
        $size = 0;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder_path, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'html') {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }
    
    /**
     * Format bytes to human readable size
     *
     * @param int $bytes Size in bytes
     * @return string Formatted size
     */
    public function format_size($bytes) {
        $units = array('B', 'KB', 'MB', 'GB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Check if archive exists for a date folder
     *
     * @param string $date_folder YYYYMMDD format
     * @return bool True if archive exists
     */
    private function check_archive_exists($date_folder) {
        $archive_path = $this->get_archive_path($date_folder);
        return file_exists($archive_path);
    }
    
    /**
     * Get archive file path
     *
     * @param string $date_folder YYYYMMDD format
     * @return string Archive file path
     */
    private function get_archive_path($date_folder) {
        return $this->upload_base_dir . '/' . $date_folder . '-wp-export.tar.gz';
    }
    
    /**
     * Get archive information
     *
     * @param string $date_folder YYYYMMDD format
     * @return array|null Archive info or null if not exists
     */
    private function get_archive_info($date_folder) {
        $archive_path = $this->get_archive_path($date_folder);
        
        if (!file_exists($archive_path)) {
            return null;
        }
        
        return array(
            'size' => filesize($archive_path),
            'created' => filemtime($archive_path),
            'path' => $archive_path,
            'filename' => basename($archive_path)
        );
    }
    
    /**
     * Create tar.gz archive for a date folder
     *
     * @param string $date_folder YYYYMMDD format
     * @return array Result with success status and message
     */
    public function create_archive($date_folder) {
        try {
            // Validate date folder format
            if (!preg_match('/^\d{8}$/', $date_folder)) {
                throw new Exception('Invalid date folder format');
            }
            
            $folder_path = $this->upload_base_dir . '/' . $date_folder;
            
            if (!file_exists($folder_path) || !is_dir($folder_path)) {
                throw new Exception('Date folder does not exist');
            }
            
            $archive_path = $this->get_archive_path($date_folder);
            
            // Use PharData to create tar.gz archive (PHP built-in)
            try {
                // Remove existing archive if present
                if (file_exists($archive_path)) {
                    unlink($archive_path);
                }
                
                $tar_path = $this->upload_base_dir . '/' . $date_folder . '-wp-export.tar';
                
                // Create tar archive
                $phar = new PharData($tar_path);
                $phar->buildFromDirectory($folder_path);
                
                // Compress to gz
                $phar->compress(Phar::GZ);
                
                // Remove uncompressed tar file
                unlink($tar_path);
                
                return array(
                    'success' => true,
                    'message' => 'Archive created successfully',
                    'archive_path' => $archive_path,
                    'archive_size' => filesize($archive_path)
                );
                
            } catch (Exception $e) {
                throw new Exception('Failed to create archive: ' . $e->getMessage());
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Get download URL for an archive
     *
     * @param string $date_folder YYYYMMDD format
     * @return string|null Download URL or null if archive doesn't exist
     */
    public function get_download_url($date_folder) {
        if (!$this->check_archive_exists($date_folder)) {
            return null;
        }
        
        // Create a secure download URL with nonce
        return admin_url('admin-ajax.php') . '?' . http_build_query(array(
            'action' => 'prt_gist_download_export',
            'date_folder' => $date_folder,
            'nonce' => wp_create_nonce('prt_gist_download_' . $date_folder)
        ));
    }
    
    /**
     * Handle archive download request
     *
     * @param string $date_folder YYYYMMDD format
     * @param string $nonce Security nonce
     * @return bool True on success, false on failure
     */
    public function download_archive($date_folder, $nonce) {
        // Verify nonce
        if (!wp_verify_nonce($nonce, 'prt_gist_download_' . $date_folder)) {
            return false;
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        $archive_path = $this->get_archive_path($date_folder);
        
        if (!file_exists($archive_path)) {
            return false;
        }
        
        // Set headers for download
        header('Content-Type: application/x-gzip');
        header('Content-Disposition: attachment; filename="' . basename($archive_path) . '"');
        header('Content-Length: ' . filesize($archive_path));
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        
        // Output file
        readfile($archive_path);
        
        return true;
    }
    
    /**
     * Delete an archive file and its date folder
     *
     * @param string $date_folder YYYYMMDD format
     * @return array Result with success status and message
     */
    public function delete_archive($date_folder) {
        try {
            // Delete archive file if exists
            $archive_path = $this->get_archive_path($date_folder);
            if (file_exists($archive_path)) {
                if (!unlink($archive_path)) {
                    throw new Exception('Failed to delete archive file');
                }
            }
            
            // Also delete the date folder with all HTML files
            $folder_path = $this->upload_base_dir . '/' . $date_folder;
            if (file_exists($folder_path) && is_dir($folder_path)) {
                $this->delete_directory($folder_path);
            }
            
            return array(
                'success' => true,
                'message' => 'Export deleted successfully'
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Recursively delete a directory and its contents
     *
     * @param string $dir Directory path
     * @return bool Success status
     */
    private function delete_directory($dir) {
        if (!file_exists($dir)) {
            return true;
        }
        
        if (!is_dir($dir)) {
            return unlink($dir);
        }
        
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->delete_directory($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
}


