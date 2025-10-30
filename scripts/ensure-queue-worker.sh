#!/bin/bash

# Script to ensure queue worker is always running
# This script can be run periodically via cron or supervisor

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

cd "$PROJECT_DIR"

# Find PHP executable (auto-detect for cPanel compatibility)
if command -v php > /dev/null 2>&1; then
    PHP_BIN=$(command -v php)
elif [ -f "/usr/local/bin/php" ]; then
    PHP_BIN="/usr/local/bin/php"
elif [ -f "/opt/cpanel/ea-php81/root/usr/bin/php" ]; then
    PHP_BIN="/opt/cpanel/ea-php81/root/usr/bin/php"
elif [ -f "/opt/cpanel/ea-php82/root/usr/bin/php" ]; then
    PHP_BIN="/opt/cpanel/ea-php82/root/usr/bin/php"
elif [ -f "/opt/cpanel/ea-php83/root/usr/bin/php" ]; then
    PHP_BIN="/opt/cpanel/ea-php83/root/usr/bin/php"
else
    PHP_BIN="php"
fi

# Ensure storage/logs directory exists
mkdir -p storage/logs

# Check if queue worker is running
QUEUE_WORKER_PIDS=$(ps aux | grep "queue:work database --queue=backups,default" | grep -v grep | awk '{print $2}')

if [ -z "$QUEUE_WORKER_PIDS" ]; then
    echo "$(date): Queue worker not running, starting..."
    
    # Start queue worker in background
    nohup "$PHP_BIN" artisan queue:work database --queue=backups,default --tries=3 --timeout=300 --sleep=3 --max-jobs=100 >> storage/logs/queue-worker.log 2>&1 &
    
    echo "$(date): Queue worker started (PID: $!) using PHP: $PHP_BIN"
else
    # Count processes
    QUEUE_WORKER_COUNT=$(echo "$QUEUE_WORKER_PIDS" | wc -l | tr -d ' ')
    
    # If more than 1 process, kill extras (keep only one)
    if [ "$QUEUE_WORKER_COUNT" -gt 1 ]; then
        echo "$(date): Multiple queue workers detected ($QUEUE_WORKER_COUNT), keeping only one..."
        # Keep the first PID, kill the rest
        FIRST_PID=$(echo "$QUEUE_WORKER_PIDS" | head -1)
        echo "$QUEUE_WORKER_PIDS" | tail -n +2 | xargs kill 2>/dev/null
        echo "$(date): Queue worker processes cleaned up, keeping PID: $FIRST_PID"
    else
        echo "$(date): Queue worker is running (PID: $QUEUE_WORKER_PIDS)"
    fi
fi

