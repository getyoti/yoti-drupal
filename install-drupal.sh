#!/bin/bash

docker-compose up -d

# Wait for services to be ready
sleep 10

# Install Drupal
docker-compose exec drupal-8 drush site:install -y

# Enable Yoti module
docker-compose exec drupal-8 drush en yoti -y
