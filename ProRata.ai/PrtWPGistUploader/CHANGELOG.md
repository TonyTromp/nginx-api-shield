# Changelog

## Version 1.1.0 - Background Processing & File Export (2025-10-18)

### 🎉 Major Features Added

#### Background Processing
- **Detached Sync Execution**: Sync operations now run in the background using WordPress cron
- **Non-blocking**: Users can close their browser during sync - the process continues on the server
- **Process Management**: Only one sync can run at a time to prevent conflicts
- **Real-time Progress**: WebSocket continues to show live updates while user is watching

#### HTML File Export
- **Date-Organized Storage**: Posts saved in `wp-content/uploads/prt-gist-sync/YYYYMMDD/` folders
- **Slug-Based Filenames**: Each post saved as `<post-slug>.html`
- **Complete HTML Documents**: Standalone files with embedded CSS, metadata, and full content
- **Automatic Directory Creation**: Plugin creates and manages folder structure automatically
- **Security Protection**: Auto-generated `.htaccess` files to protect directories

### 📝 New Files Created

1. **includes/class-background-sync.php**
   - Handles background process initialization
   - Manages sync job status and lifecycle
   - Integrates with WordPress cron system

2. **Updated Files**:
   - `includes/class-sync-manager.php`: Added file creation and HTML generation
   - `prt-wp-gist-uploader.php`: Integrated background sync handler
   - `assets/js/sync-client.js`: Updated UI for background processing
   - `admin/sync-page.php`: Added file storage information section

### 🔧 Technical Improvements

#### HTML Generation
- **Structured Output**: Complete HTML5 documents with proper DOCTYPE
- **Responsive Design**: Mobile-friendly embedded CSS
- **Rich Metadata**: Includes author, date, tags, categories, and permalink
- **Clean Formatting**: Properly escaped content with WordPress filters applied

#### File Organization
```
wp-content/uploads/prt-gist-sync/
├── 20251018/              # Posts published on Oct 18, 2025
│   ├── article-one.html
│   ├── article-two.html
├── 20251019/              # Posts published on Oct 19, 2025
│   └── another-post.html
└── .htaccess              # Security protection
```

#### WebSocket Integration
- Real-time progress updates continue to work during background processing
- Automatic detection of sync completion to re-enable UI
- Session-based sync counter updates
- Graceful handling of disconnections

### 🎨 UI Enhancements

#### Sync Page
- New "File Storage Information" section with:
  - Storage location path
  - Folder structure explanation
  - File format details
  - Background processing notice

#### User Feedback
- "Background sync started" message
- Process ID display in logs
- "You can close this page" notification
- Improved button states (Starting → Running → Complete)

### 🔒 Security Features

- Directory listings disabled via `.htaccess`
- Proper file permissions handling
- WordPress nonce verification maintained
- Capability checks for all operations

### 📚 Documentation Updates

#### README.md
- Added background processing explanation
- Documented file storage structure
- Added HTML file format specification
- Included instructions for accessing synced files from Docker
- Optional volume mounting guide

#### SETUP.md
- Added step for accessing synced files
- Included Docker commands for file retrieval
- Updated test sync instructions with background info

### 🐛 Bug Fixes
- None (new feature implementation)

### ⚡ Performance
- Background processing prevents PHP timeouts for large sync operations
- Non-blocking AJAX responses return immediately
- Efficient file I/O with proper error handling

### 🔄 Backwards Compatibility
- All existing features continue to work as before
- Scheduled syncs now also create HTML files
- API endpoint settings preserved (for future use)

---

## Version 1.0.0 - Initial Release (2025-10-18)

### Features
- WordPress plugin with admin interface
- Configurable scheduled sync (hourly, daily, weekly, custom)
- Manual "Sync Now" button
- WebSocket-based real-time logging
- Flexible filtering (all posts, by date, by tag)
- Docker development environment
- Modern, responsive admin UI
- Statistics dashboard

### Components
- Docker Compose setup with WordPress, MySQL, phpMyAdmin, WebSocket server
- Post filtering system
- Sync management
- WebSocket server for live updates
- Admin settings and sync pages
- CSS and JavaScript assets


