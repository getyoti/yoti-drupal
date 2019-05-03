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

# Enable Yoti module
docker-compose exec $TARGET drush en yoti -y
