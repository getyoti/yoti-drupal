#!/bin/bash
TARGET=$1

if [ "$TARGET" = "" ]; then
    TARGET="drupal-7"
fi

docker-compose up -d $TARGET

# Wait for services to be ready
sleep 10

# Install Drupal
docker-compose exec $TARGET drush site:install -y

# Set private file path
docker-compose exec $TARGET drush vset file_private_path /var/www/private

# Enable Yoti module
docker-compose exec $TARGET drush en yoti -y
