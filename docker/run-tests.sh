#!/bin/bash
docker-compose up -d drupal-8-dev
sleep 10

# Coding Standards
docker-compose exec drupal-8-dev ./vendor/bin/phpcs --standard=Drupal --ignore=*/var/www/html/modules/yoti/sdk* ./modules/yoti

# Run Unit Tests
docker-compose exec drupal-8-dev sudo -u www-data ./vendor/bin/phpunit
