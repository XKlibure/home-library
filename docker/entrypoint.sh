#!/bin/sh
set -e
# This entrypoint runs as root BEFORE Apache starts.
# Docker named volumes are mounted after the image is built, so any
# mkdir/chown done in the Dockerfile is overwritten. We fix that here.

mkdir -p /var/www/html/storage/ebooks/files
mkdir -p /var/www/html/storage/ebooks/covers
mkdir -p /var/www/html/storage/backups

chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage

exec apache2-foreground "$@"
