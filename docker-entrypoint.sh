#!/bin/sh
set -e

# Default to port 80 if PORT environment variable is not set
TARGET_PORT="${PORT:-80}"

# Configure Apache to listen on $PORT assigned by cloud platform (Railway/Render)
sed -i "s/Listen 80/Listen ${TARGET_PORT}/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:${TARGET_PORT}>/g" /etc/apache2/sites-available/000-default.conf

# Execute Apache foreground process
exec "$@"
