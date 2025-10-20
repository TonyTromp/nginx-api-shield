# Export Archives Feature

## Overview

The Export Archives feature allows you to create, manage, and download compressed tar.gz archives of your exported WordPress posts. Posts are organized by publication date and can be easily backed up or transferred.

## Features

### 1. Archive Management Interface
Located at **Settings → Gist Uploader Settings**, the Export Archives section displays:
- All available date folders (YYYYMMDD format)
- Number of HTML files in each folder
- Folder size
- Archive status (created or not created)
- Archive creation date and size

### 2. Create Archives
- Click **"Create Archive"** button for any date folder
- Archives are created in tar.gz format for maximum compression
- Filename format: `YYYYMMDD-wp-export.tar.gz`
- Archives contain all HTML files from that date folder

### 3. Download Archives
- Click **"Download"** button to download created archives
- Secure download with WordPress nonces
- Archives can be extracted with any standard tar tool

### 4. Delete Archives
- Click **"Delete"** to remove archive files
- Source HTML files remain intact (only the archive is deleted)
- Confirmation prompt prevents accidental deletion

## Technical Details

### File Structure

```
wp-content/uploads/prt-gist-sync/
├── 20251018/                    # Date folder
│   ├── post-one.html
│   ├── post-two.html
│   └── .htaccess
├── 20251018-wp-export.tar.gz   # Archive file
├── 20251019/
│   └── another-post.html
└── 20251019-wp-export.tar.gz
```

### Archive Contents

Each archive contains:
- All HTML files from the date folder
- .htaccess file for security
- Maintains original file structure

### API Endpoints

**Create Archive:**
- Action: `prt_gist_create_export`
- Method: POST (AJAX)
- Parameters: `date_folder`, `nonce`
- Returns: Success status, archive path, size

**Delete Archive:**
- Action: `prt_gist_delete_export`
- Method: POST (AJAX)
- Parameters: `date_folder`, `nonce`
- Returns: Success status, message

**Download Archive:**
- Action: `prt_gist_download_export`
- Method: GET
- Parameters: `date_folder`, `nonce`
- Returns: File download stream

### Security

- All operations require `manage_options` capability
- WordPress nonces protect against CSRF attacks
- Download URLs include unique nonces per date folder
- Archives stored in WordPress uploads directory (protected by .htaccess)

## Usage Examples

### Create Archive via Command Line (Docker)

```bash
# Navigate to plugin directory
cd /var/www/html/wp-content/uploads/prt-gist-sync

# Create archive manually (alternative to UI)
tar -czf 20251018-wp-export.tar.gz 20251018
```

### Extract Archive

```bash
# On your local machine
tar -xzf 20251018-wp-export.tar.gz

# This creates a 20251018/ folder with all HTML files
```

### List Archive Contents

```bash
tar -tzf 20251018-wp-export.tar.gz
```

## Classes and Methods

### PRT_Gist_Export_Manager

**Public Methods:**
- `get_export_folders()` - Returns array of all date folders with metadata
- `create_archive($date_folder)` - Creates tar.gz archive for date folder
- `delete_archive($date_folder)` - Deletes archive file
- `get_download_url($date_folder)` - Generates secure download URL
- `download_archive($date_folder, $nonce)` - Handles file download
- `format_size($bytes)` - Formats bytes to human-readable size

**Private Methods:**
- `format_date_folder($date_folder)` - Converts YYYYMMDD to readable date
- `count_html_files($folder_path)` - Counts HTML files in folder
- `get_folder_size($folder_path)` - Calculates folder size
- `check_archive_exists($date_folder)` - Checks if archive exists
- `get_archive_path($date_folder)` - Returns archive file path
- `get_archive_info($date_folder)` - Returns archive metadata

## WordPress Integration

### Hooks and Filters

```php
// AJAX handlers registered in main plugin file
add_action('wp_ajax_prt_gist_create_export', 'prt_gist_ajax_create_export');
add_action('wp_ajax_prt_gist_delete_export', 'prt_gist_ajax_delete_export');
add_action('wp_ajax_prt_gist_download_export', 'prt_gist_ajax_download_export');
```

### JavaScript Events

```javascript
// Create archive
jQuery('.create-archive-btn').on('click', function() { ... });

// Delete archive
jQuery('.delete-archive-btn').on('click', function() { ... });
```

## Troubleshooting

### Archive Creation Fails

**Issue:** Archive creation returns an error

**Solutions:**
1. Check PHP Phar extension is enabled:
   ```bash
   docker exec prt-wordpress php -m | grep Phar
   ```

2. Verify directory permissions:
   ```bash
   docker exec prt-wordpress ls -la /var/www/html/wp-content/uploads/prt-gist-sync/
   ```

3. Check PHP error log:
   ```bash
   docker exec prt-wordpress tail -f /var/log/apache2/error.log
   ```

### Download Doesn't Work

**Issue:** Download button doesn't trigger download

**Solutions:**
1. Check nonce is valid (regenerated on each page load)
2. Verify user has `manage_options` capability
3. Check browser console for JavaScript errors

### Archive Not Listed

**Issue:** Archive exists but not shown in UI

**Solutions:**
1. Refresh the settings page
2. Check archive filename matches pattern: `YYYYMMDD-wp-export.tar.gz`
3. Verify archive is in correct directory

## Performance Considerations

### Large Archives
- Archives are created using PHP's PharData (memory efficient)
- Streaming download prevents memory issues
- No size limits imposed by plugin (limited only by server)

### Archive Creation Time
- Typical folder (10-100 files): 1-2 seconds
- Large folder (1000+ files): 5-10 seconds
- Progress shown via button state change

## Future Enhancements

Potential improvements for future versions:
- [ ] Bulk archive creation (all folders at once)
- [ ] Automatic archive creation after sync
- [ ] Archive scheduling (create nightly)
- [ ] Archive retention policies
- [ ] Remote storage integration (S3, etc.)
- [ ] Archive encryption
- [ ] Incremental archives
- [ ] Archive verification/integrity checks

## Related Files

- `includes/class-export-manager.php` - Export manager class
- `admin/settings.php` - Settings page with export UI
- `assets/css/admin-style.css` - Export section styling
- `prt-wp-gist-uploader.php` - AJAX handlers and integration

## Version History

**Version 1.1.0** (2025-10-20)
- Initial export archives feature
- Create, download, and delete archives
- UI in settings page
- Full security implementation


