# ProRata WordPress Gist Uploader Plugin

A WordPress plugin that syncs posts to an API with real-time WebSocket logging, configurable scheduling, and flexible filtering options.

## Features

- 📤 **Post Synchronization**: Creates HTML files for WordPress posts organized by date
- 🔄 **Flexible Filtering**: Sync all posts, posts modified since a date, or posts by tag
- ⚡ **Real-time Logging**: WebSocket-based live sync logs in the admin interface
- ⏰ **Scheduled Sync**: Configurable automatic sync (hourly, daily, weekly, or custom intervals)
- 🎯 **Manual Sync**: "Sync Now" button for on-demand synchronization
- 🔥 **Background Processing**: Syncs run detached from the web client - close your browser anytime
- 📁 **Date-Organized Storage**: Posts saved in YYYYMMDD folders with slug-based filenames
- 🎨 **Modern UI**: Beautiful, responsive admin interface with statistics dashboard

## Quick Start

### 1. Start the Docker Environment

```bash
cd /Users/ttromp/Projects/ProRata.ai/PrtWPGistUploader
docker-compose up -d
```

This will start:
- **WordPress**: http://localhost:8000
- **phpMyAdmin**: http://localhost:8080
- **WebSocket Server**: ws://localhost:8081

### 2. Complete WordPress Setup

1. Visit http://localhost:8000
2. Complete the WordPress installation wizard
3. Create an admin account

### 3. Activate the Plugin

1. Log into WordPress admin (http://localhost:8000/wp-admin)
2. Go to **Plugins** → **Installed Plugins**
3. Find "ProRata WP Gist Uploader" and click **Activate**

### 4. Configure Settings

1. Go to **Gist Uploader** → **Settings** in the admin menu
2. Enter your API Key (optional, for future use)
3. Set the API Endpoint URL
4. Choose sync frequency:
   - Hourly
   - Twice Daily
   - Daily
   - Weekly
   - Custom Interval (in minutes)
5. Click **Save Settings**

### 5. Sync Posts

1. Go to **Gist Uploader** → **Sync Posts**
2. Choose your filter:
   - **All Published Posts**: Sync all posts
   - **Modified Since Date**: Sync posts modified after a specific date
   - **By Tag**: Sync posts with a specific tag
3. Click **Sync Now**
4. Watch the real-time log display the sync progress
5. **Important**: The sync runs in the background - you can close the page anytime!

### 6. Access Your Synced Files

Synced posts are saved as HTML files in:
```
wp-content/uploads/prt-gist-sync/YYYYMMDD/<post-slug>.html
```

Example structure:
```
wp-content/uploads/prt-gist-sync/
├── 20251018/
│   ├── my-first-post.html
│   ├── another-article.html
├── 20251019/
│   ├── latest-news.html
```

Each HTML file is a complete, standalone document with:
- Post title, content, and metadata
- Author information
- Tags and categories
- Publication dates
- Embedded CSS styling

## Project Structure

```
PrtWPGistUploader/
├── docker-compose.yml
├── README.md
├── SETUP.md
├── start.sh
└── wordpress-plugin/
    └── prt-wp-gist-uploader/
        ├── prt-wp-gist-uploader.php    # Main plugin file
        ├── admin/
        │   ├── settings.php             # Settings page
        │   └── sync-page.php            # Sync interface
        ├── includes/
        │   ├── class-post-filter.php    # Post filtering logic
        │   ├── class-sync-manager.php   # Sync management & file creation
        │   ├── class-background-sync.php # Background process handler
        │   ├── class-websocket-server.php   # WebSocket server
        │   └── websocket-server-runner.php  # WebSocket runner script
        └── assets/
            ├── css/
            │   └── admin-style.css      # Admin styles
            └── js/
                └── sync-client.js       # WebSocket client & UI
```

## Technical Details

### Background Processing

The plugin uses WordPress cron to run sync operations in the background:

1. **Detached Execution**: When you click "Sync Now", the process starts immediately via `wp_schedule_single_event()` and runs independently of your browser session
2. **Real-time Feedback**: WebSocket connection provides live progress updates while you're watching
3. **Graceful Disconnection**: Close your browser anytime - the sync continues on the server
4. **Process Management**: Only one sync can run at a time to prevent conflicts

### File Storage Structure

Posts are organized by publication date:

```
wp-content/uploads/prt-gist-sync/
├── YYYYMMDD/           # Date folder (e.g., 20251018)
│   ├── post-slug-1.html
│   ├── post-slug-2.html
│   └── post-slug-3.html
├── YYYYMMDD/           # Another date
│   └── another-post.html
└── .htaccess           # Security protection
```

**Key Points:**
- Files are grouped by **publication date** (not modified date)
- Multiple posts from the same day go in the same folder
- Each file is named by the post's slug
- Complete HTML documents with embedded styling

### HTML File Format

Each generated HTML file includes:

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post Title</title>
    <meta name="author" content="Author Name">
    <meta name="date" content="2025-10-18">
    <meta name="keywords" content="tag1, tag2">
    <style>/* Embedded CSS for standalone viewing */</style>
</head>
<body>
    <article>
        <header>
            <h1>Post Title</h1>
            <div class="meta">Author, Date, Categories, Permalink</div>
        </header>
        <div class="content">
            <!-- Full post content with formatting -->
        </div>
        <div class="tags">
            <!-- Post tags -->
        </div>
    </article>
</body>
</html>
```

### Internal Post Data Structure

During processing, posts are represented as:

```php
array(
  "id" => 123,
  "title" => "Post Title",
  "content_html" => "<p>Full HTML content</p>",
  "excerpt" => "Post excerpt...",
  "permalink" => "https://example.com/post",
  "date_published" => "2025-10-18 12:00:00",
  "date_modified" => "2025-10-18 14:30:00",
  "author" => "John Doe",
  "tags" => ["tag1", "tag2"],
  "categories" => ["category1"],
  "featured_image" => "https://example.com/image.jpg"
)
```

### WebSocket Communication

The plugin uses a custom WebSocket server for real-time logging:

- **Server**: Runs on port 8081 (containerized)
- **Protocol**: Standard WebSocket (ws://)
- **Messages**: JSON format with `type`, `message`, and `timestamp`
- **Message Types**: `info`, `success`, `error`, `warning`

### WordPress Cron

The plugin uses WordPress's built-in cron system:

- Scheduled events use `wp_schedule_event()`
- Custom intervals registered via `cron_schedules` filter
- Event hook: `prt_gist_sync_event`

### AJAX Endpoints

The plugin registers these AJAX endpoints:

- `prt_gist_sync_now`: Trigger manual sync
- `prt_gist_get_tags`: Get available tags

## Development

### Modifying the Plugin

The plugin is volume-mounted in Docker, so changes are reflected immediately:

```bash
# Plugin files are in:
./wordpress-plugin/prt-wp-gist-uploader/

# Edit any file and refresh the WordPress admin to see changes
```

### Accessing Synced Files

To access the generated HTML files from your host machine:

```bash
# The WordPress data is stored in a Docker volume
# To access it, you can:

# 1. Copy files from the container to your host
docker cp prt-wordpress:/var/www/html/wp-content/uploads/prt-gist-sync ./synced-files

# 2. Or enter the container and browse files
docker exec -it prt-wordpress bash
cd /var/www/html/wp-content/uploads/prt-gist-sync
ls -la

# 3. Or view via browser (if you set up a direct URL)
# http://localhost:8000/wp-content/uploads/prt-gist-sync/
```

**Mounting a Local Directory (Optional):**

To have synced files appear directly on your host machine, modify `docker-compose.yml`:

```yaml
volumes:
  - ./wordpress-plugin/prt-wp-gist-uploader:/var/www/html/wp-content/plugins/prt-wp-gist-uploader
  - ./synced-files:/var/www/html/wp-content/uploads/prt-gist-sync  # Add this line
  - wordpress_data:/var/www/html
```

Then create the directory:
```bash
mkdir synced-files
docker-compose restart wordpress
```

### Viewing Logs

**Docker logs:**
```bash
docker-compose logs -f wordpress
docker-compose logs -f websocket
```

**WordPress debug log:**
Edit `wp-config.php` to enable debug logging:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### Testing the WebSocket Server

You can test the WebSocket server independently:

```bash
# Connect with a WebSocket client
wscat -c ws://localhost:8081

# Or use browser console
const ws = new WebSocket('ws://localhost:8081');
ws.onmessage = (e) => console.log(e.data);
```

## Docker Services

### WordPress
- **Port**: 8000
- **Container**: prt-wordpress
- **Database**: MySQL 8.0

### MySQL
- **Container**: prt-mysql
- **Database**: wordpress
- **User**: wordpress
- **Password**: wordpress
- **Root Password**: rootpassword

### phpMyAdmin
- **Port**: 8080
- **Container**: prt-phpmyadmin
- **Host**: db

### WebSocket Server
- **Port**: 8081
- **Container**: prt-websocket-server
- **Protocol**: WebSocket

## Stopping the Environment

```bash
# Stop containers
docker-compose stop

# Stop and remove containers
docker-compose down

# Stop and remove containers + volumes (clean slate)
docker-compose down -v
```

## API Integration

Currently, the plugin **simulates** API calls (fake endpoint). To implement real API calls:

1. Update `includes/class-sync-manager.php`
2. Modify the `simulate_api_call()` method
3. Add actual HTTP request logic (use `wp_remote_post()`)

Example real API call:

```php
private function simulate_api_call($post) {
    $api_key = get_option('prt_gist_api_key', '');
    $api_endpoint = get_option('prt_gist_api_endpoint', '');
    
    $response = wp_remote_post($api_endpoint, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($this->prepare_post_data($post)),
        'timeout' => 30
    ));
    
    return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
}
```

## Troubleshooting

### WebSocket Not Connecting

1. Check if the WebSocket container is running:
   ```bash
   docker-compose ps
   ```

2. Check WebSocket logs:
   ```bash
   docker-compose logs websocket
   ```

3. Restart the WebSocket container:
   ```bash
   docker-compose restart websocket
   ```

### Plugin Not Appearing

1. Check plugin directory permissions
2. View WordPress error logs
3. Ensure plugin is in: `wp-content/plugins/prt-wp-gist-uploader/`

### Sync Not Working

1. Check that posts exist and are published
2. View browser console for JavaScript errors
3. Check WordPress cron status: `wp cron event list` (using WP-CLI)

## Future Enhancements

- [ ] Real API endpoint integration
- [ ] Post categories filtering
- [ ] Sync status history/logs persistence
- [ ] Bulk post selection UI
- [ ] Export sync reports
- [ ] Webhook callbacks on sync completion
- [ ] OAuth authentication support

## License

GPL v2 or later

## Author

ProRata.ai

---

**Note**: This plugin is currently configured for development/testing with a fake API endpoint. Update the sync manager to connect to your actual API when ready for production use.

