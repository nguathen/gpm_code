#!/bin/sh

echo "Checking if cron is installed..."
if ! command -v cron > /dev/null 2>&1; then
    echo "Installing cron..."
    apt install -y cron
fi

echo "Starting cron..."
cron

# Giữ container chạy bằng cách chạy một tiến trình foreground
echo "Container is running..."
tail -f /dev/null