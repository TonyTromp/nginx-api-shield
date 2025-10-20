#!/bin/bash

# ProRata WordPress Gist Uploader - Quick Start Script

echo "================================================"
echo "  ProRata WordPress Gist Uploader"
echo "  Quick Start Script"
echo "================================================"
echo ""

# Check if docker-compose is installed
if ! command -v docker-compose &> /dev/null; then
    echo "❌ Error: docker-compose is not installed"
    echo "Please install Docker Desktop from https://www.docker.com/products/docker-desktop"
    exit 1
fi

# Check if Docker is running
if ! docker info &> /dev/null; then
    echo "❌ Error: Docker is not running"
    echo "Please start Docker Desktop and try again"
    exit 1
fi

echo "✅ Docker is ready"
echo ""

# Start the containers
echo "🚀 Starting containers..."
docker-compose up -d

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ All services started successfully!"
    echo ""
    echo "================================================"
    echo "  Access Your Services"
    echo "================================================"
    echo ""
    echo "📝 WordPress:        http://localhost:8000"
    echo "🔧 WordPress Admin:  http://localhost:8000/wp-admin"
    echo "💾 phpMyAdmin:       http://localhost:8080"
    echo "🔌 WebSocket Server: ws://localhost:8081"
    echo ""
    echo "================================================"
    echo "  Next Steps"
    echo "================================================"
    echo ""
    echo "1. Wait 30-60 seconds for services to fully start"
    echo "2. Open http://localhost:8000 in your browser"
    echo "3. Complete the WordPress installation"
    echo "4. Activate the 'ProRata WP Gist Uploader' plugin"
    echo "5. Configure settings in Gist Uploader → Settings"
    echo ""
    echo "📖 For detailed instructions, see SETUP.md"
    echo ""
    echo "To view logs: docker-compose logs -f"
    echo "To stop:      docker-compose stop"
    echo ""
else
    echo ""
    echo "❌ Error: Failed to start containers"
    echo "Run 'docker-compose logs' to see error details"
    exit 1
fi

