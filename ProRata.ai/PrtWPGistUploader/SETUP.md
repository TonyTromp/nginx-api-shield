# Quick Setup Guide

## Step 1: Start Docker Environment

```bash
docker-compose up -d
```

Wait for all containers to start (about 30-60 seconds).

## Step 2: Verify Services

Check that all services are running:

```bash
docker-compose ps
```

You should see 4 services running:
- `prt-wordpress` (port 8000)
- `prt-mysql` (port 3306)
- `prt-phpmyadmin` (port 8080)
- `prt-websocket-server` (port 8081)

## Step 3: Install WordPress

1. Open your browser and go to: http://localhost:8000
2. Select your language and click "Continue"
3. Fill in the installation form:
   - **Site Title**: Your site name
   - **Username**: admin (or your preferred username)
   - **Password**: Choose a strong password
   - **Your Email**: Your email address
4. Click "Install WordPress"
5. Log in with your credentials

## Step 4: Activate the Plugin

1. In WordPress admin, go to **Plugins** → **Installed Plugins**
2. Find "ProRata WP Gist Uploader"
3. Click **Activate**

## Step 5: Configure Plugin

1. In the WordPress admin menu, click **Gist Uploader** → **Settings**
2. Configure the settings:
   - **API Key**: Enter your API key (or leave blank for testing)
   - **API Endpoint URL**: `https://api.example.com/sync` (default is fine for testing)
   - **Sync Frequency**: Choose how often to auto-sync (e.g., "Daily")
3. Click **Save Settings**

## Step 6: Create Test Posts

1. Go to **Posts** → **Add New**
2. Create a few test posts with different tags
3. Publish them

## Step 7: Test Sync

1. Go to **Gist Uploader** → **Sync Posts**
2. Select "All Published Posts"
3. Click **Sync Now**
4. Watch the real-time log display!
5. **Note**: The sync runs in the background - you can close the page anytime

## Step 8: Access Your Synced Files

Files are saved in: `wp-content/uploads/prt-gist-sync/YYYYMMDD/`

To access them from your host machine:
```bash
docker cp prt-wordpress:/var/www/html/wp-content/uploads/prt-gist-sync ./synced-files
```

Or browse them in the container:
```bash
docker exec -it prt-wordpress bash
cd /var/www/html/wp-content/uploads/prt-gist-sync
ls -la
```

## Step 9: Test Different Filters

Try syncing with different filters:

### By Date
1. Select "Modified Since Date"
2. Choose a date from the date picker
3. Click **Sync Now**

### By Tag
1. Select "By Tag"
2. Choose a tag from the dropdown
3. Click **Sync Now**

## Troubleshooting

### If WordPress doesn't load:
```bash
docker-compose logs wordpress
```

### If WebSocket shows disconnected:
```bash
docker-compose restart websocket
docker-compose logs websocket
```

### To reset everything:
```bash
docker-compose down -v
docker-compose up -d
```
Then repeat the setup from Step 3.

## Stopping the Environment

```bash
# Stop (keeps data)
docker-compose stop

# Stop and remove (keeps data)
docker-compose down

# Stop and remove everything (clean slate)
docker-compose down -v
```

## Accessing Services

- **WordPress**: http://localhost:8000
- **WordPress Admin**: http://localhost:8000/wp-admin
- **phpMyAdmin**: http://localhost:8080
- **WebSocket Server**: ws://localhost:8081

## Default Credentials

### MySQL Database
- **Database**: wordpress
- **User**: wordpress
- **Password**: wordpress
- **Root Password**: rootpassword

### phpMyAdmin
- **Server**: db
- **Username**: root
- **Password**: rootpassword

---

Need help? Check the main README.md for detailed documentation!

