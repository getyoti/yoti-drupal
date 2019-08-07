#!/bin/bash
TARGET="drupal-7-dev"

# Coding Standards
docker-compose exec $TARGET sh -c "cd ./sites/all/modules/yoti && /var/www/html/vendor/bin/phpcs"

# Enable Yoti module
docker-compose exec $TARGET drush en simpletest -y

# Run Tests.
docker-compose exec $TARGET sudo -u www-data -E php ./scripts/run-tests.sh --verbose --color --url https://localhost --php /usr/local/bin/php Yoti
