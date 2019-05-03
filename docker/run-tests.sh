#!/bin/bash
docker-compose up -d drupal-7-dev

# Coding Standards
docker-compose exec drupal-7-dev ./vendor/bin/phpcs --standard=Drupal --ignore=*yoti/sdk* ./sites/all/modules/yoti
