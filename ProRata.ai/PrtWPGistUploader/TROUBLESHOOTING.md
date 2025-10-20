# Troubleshooting Guide

## Issues and Solutions

### 1. Sync/Backup Not Working

#### Check WordPress Logs
```bash
docker-compose logs wordpress | grep "PRT Gist"
```

#### Enable WordPress Debug Mode
Inside the WordPress container, edit `wp-config.php`:
```bash
docker exec -it prt-wordpress bash
vi wp-content/wp-config.php
```

Add these lines before "/* That's all, stop editing! */":
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Then check logs:
```bash
docker exec -it prt-wordpress tail -f /var/www/html/wp-content/debug.log
```

#### Force Sync Execution
If WordPress cron isn't working, the plugin now has a fallback that runs syncs automatically. Just refresh the admin page a few times after clicking "Sync Now".

### 2. WebSocket Not Connecting

#### Check if WebSocket Server is Running
```bash
docker-compose ps | grep websocket
```

Should show: `Up` status

#### Check WebSocket Logs
```bash
docker-compose logs websocket
```

Should show: "WebSocket server started on 0.0.0.0:8081"

#### Test WebSocket Connection
```bash
# Install wscat if needed: npm install -g wscat
wscat -c ws://localhost:8081
```

#### Restart WebSocket Server
```bash
docker-compose restart websocket
```

### 3. Files Not Being Created

#### Check Upload Directory Permissions
```bash
docker exec -it prt-wordpress bash
cd /var/www/html/wp-content/uploads
ls -la prt-gist-sync
```

#### Check if Directory Exists
```bash
docker exec -it prt-wordpress bash
ls -la /var/www/html/wp-content/uploads/prt-gist-sync
```

#### Manual Permission Fix (if needed)
```bash
docker exec -it prt-wordpress bash
chmod 755 /var/www/html/wp-content/uploads/prt-gist-sync
chown www-data:www-data /var/www/html/wp-content/uploads/prt-gist-sync
```

### 4. Background Sync Not Running

#### Check Sync Job Status
In WordPress admin, open browser console and run:
```javascript
jQuery.post(prtGistData.ajaxUrl, {
    action: 'prt_gist_check_status',
    nonce: prtGistData.nonce
}, function(response) {
    console.log(response);
});
```

#### Check WordPress Cron
```bash
docker exec -it prt-wordpress bash
wp cron event list --allow-root
```

#### Trigger WordPress Cron Manually
```bash
docker exec -it prt-wordpress bash
wp cron event run prt_gist_background_sync --allow-root
```

### 5. "No Posts to Sync" Error

#### Verify Posts Exist
- Go to **Posts → All Posts** in WordPress
- Make sure posts are **Published** (not drafts)
- Create a test post if needed

#### Check Filter Settings
- If using "Modified Since Date", make sure the date is before your posts
- If using "By Tag", make sure your posts have that tag

### 6. Container Won't Start

#### Check Container Logs
```bash
docker-compose logs [container-name]
```

#### Rebuild Containers
```bash
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

#### Check Port Conflicts
```bash
# Check if ports are already in use
lsof -i :8000  # WordPress
lsof -i :8080  # phpMyAdmin
lsof -i :8081  # WebSocket
```

### 7. Access Synced Files

#### List All Synced Files
```bash
docker exec -it prt-wordpress bash
find /var/www/html/wp-content/uploads/prt-gist-sync -name "*.html" -type f
```

#### Copy Files to Host Machine
```bash
docker cp prt-wordpress:/var/www/html/wp-content/uploads/prt-gist-sync ./synced-files
```

#### View a Specific File
```bash
docker exec -it prt-wordpress cat /var/www/html/wp-content/uploads/prt-gist-sync/20251018/my-post.html
```

### 8. Plugin Not Appearing in WordPress

#### Check if Plugin Files Exist
```bash
docker exec -it prt-wordpress ls -la /var/www/html/wp-content/plugins/prt-wp-gist-uploader
```

#### Check Plugin Permissions
```bash
docker exec -it prt-wordpress bash
chmod -R 755 /var/www/html/wp-content/plugins/prt-wp-gist-uploader
chown -R www-data:www-data /var/www/html/wp-content/plugins/prt-wp-gist-uploader
```

### 9. MySQL Connection Issues

#### Check MySQL Status
```bash
docker-compose logs db | tail -20
```

#### Reset Database
```bash
docker-compose down -v  # WARNING: This deletes all data!
docker-compose up -d
```

## Common Solutions

### Complete Reset (Clean Slate)
```bash
# Stop and remove everything
docker-compose down -v

# Remove images
docker-compose rm -f

# Rebuild and start
docker-compose build --no-cache
docker-compose up -d

# Wait 60 seconds for services to initialize
sleep 60

# Reinstall WordPress at http://localhost:8000
```

### Quick Restart
```bash
docker-compose restart
```

### View Real-Time Logs
```bash
# All services
docker-compose logs -f

# Specific service
docker-compose logs -f wordpress
docker-compose logs -f websocket
```

## Debug Mode Commands

### Test Sync Manually via Command Line
```bash
docker exec -it prt-wordpress bash
php -r "
define('WP_USE_THEMES', false);
require('/var/www/html/wp-load.php');
\$sync = new PRT_Gist_Sync_Manager();
\$result = \$sync->sync_posts('all', '');
print_r(\$result);
"
```

### Check if PHP Sockets Extension is Loaded
```bash
docker exec -it prt-websocket-server php -m | grep sockets
```

Should output: `sockets`

### Test WebSocket from Inside Container
```bash
docker exec -it prt-websocket-server php -r "
echo (extension_loaded('sockets') ? 'Sockets extension loaded' : 'Sockets NOT loaded') . PHP_EOL;
"
```

## Getting Help

If you're still having issues:

1. **Collect Logs:**
   ```bash
   docker-compose logs > logs.txt
   ```

2. **Check System Resources:**
   ```bash
   docker stats --no-stream
   ```

3. **Verify Docker Version:**
   ```bash
   docker --version
   docker-compose --version
   ```

4. **Check File Contents:**
   - Share relevant error messages
   - Include browser console errors (F12 → Console tab)
   - Note which step is failing


