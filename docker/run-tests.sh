#!/bin/bash
TARGET="drupal-7-dev"

# Coding Standards
docker-compose exec $TARGET ./vendor/bin/phpcs --standard=Drupal --ignore=*yoti/sdk* ./sites/all/modules/yoti

# Enable Yoti module
docker-compose exec $TARGET drush en simpletest -y

# Run Tests.
docker-compose exec $TARGET sudo -u www-data -E php ./scripts/run-tests.sh --verbose --color --url https://localhost --php /usr/local/bin/php Yoti
