#!/bin/bash
TARGET=$1

if [ "$TARGET" = "" ]; then
    TARGET="drupal-8"
fi

docker-compose up -d

# Wait for services to be ready
sleep 10

# Install Drupal
docker-compose exec $TARGET drush site:install -y

# Enable Yoti module
docker-compose exec $TARGET drush en yoti -y
