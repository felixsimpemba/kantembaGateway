#!/bin/bash

# Configuration
SERVER="feltech-payments"
REMOTE_PATH="/var/www/html/payment_gateway"
RSYNC_EXCLUDES=(
    "--exclude=.git"
    "--exclude=.env"
    "--exclude=node_modules"
    "--exclude=vendor"
    "--exclude=storage/logs/*.log"
    "--exclude=storage/framework/sessions/*"
    "--exclude=storage/framework/cache/*"
    "--exclude=storage/framework/views/*"
    "--exclude=.phpunit.result.cache"
    "--exclude=deploy.sh"
)

echo "🚀 Starting deployment to $SERVER..."

# Sync files
echo "📦 Syncing files..."
rsync -avz --rsync-path="sudo rsync" "${RSYNC_EXCLUDES[@]}" ./ "$SERVER:$REMOTE_PATH"

# Remote commands
echo "🔧 Running remote commands..."
ssh "$SERVER" "cd $REMOTE_PATH && \
    sudo composer install --no-dev --optimize-autoloader && \
    sudo php artisan config:cache && \
    sudo php artisan route:cache && \
    sudo php artisan view:cache && \
    sudo php artisan queue:restart && \
    sudo chown -R www-data:www-data $REMOTE_PATH"

# Migration (Added but not running automatically as requested)
# To run migrations, uncomment the following line or run it manually:
# ssh "$SERVER" "cd $REMOTE_PATH && sudo php artisan migrate --force"

echo "✅ Deployment completed successfully!"
