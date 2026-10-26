#!/bin/bash
set -e

# Disable conflicting MPM modules and ensure only mpm_prefork is active
a2dismod -f mpm_event mpm_worker 2>/dev/null || true
a2enmod mpm_prefork 2>/dev/null || true

# Dynamic port binding for Railway ($PORT)
TARGET_PORT="${PORT:-80}"
sed -i "s/Listen 80/Listen ${TARGET_PORT}/g" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost \*:${TARGET_PORT}>/g" /etc/apache2/sites-available/000-default.conf

# Execute CMD (apache2-foreground)
exec "$@"
