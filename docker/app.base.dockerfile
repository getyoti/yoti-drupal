# from https://www.drupal.org/requirements/php#drupalversions
FROM drupal:8.7-apache AS drupal_8_base

COPY default.conf /etc/apache2/sites-available/000-default.conf
COPY ./keys/server.crt /etc/apache2/ssl/server.crt
COPY ./keys/server.key /etc/apache2/ssl/server.key

RUN a2enmod rewrite ssl

# Install dependencies.
RUN apt-get update \
  && apt-get install -y git zip unzip vim nano mysql-client

ENV DIRPATH /var/www/html
WORKDIR $DIRPATH

# Install Composer
RUN EXPECTED_SIGNATURE="$(curl https://composer.github.io/installer.sig)" \
  php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
  ACTUAL_SIGNATURE="$(php -r "echo hash_file('sha384', 'composer-setup.php');")" \
  if [ "$EXPECTED_SIGNATURE" != "$ACTUAL_SIGNATURE" ] \
  then \
    >&2 echo 'ERROR: Invalid installer signature' \
    rm composer-setup.php \
    exit 1 \
  fi \
  && php composer-setup.php --quiet --filename=composer \
  && mv composer /usr/local/bin \
  && rm composer-setup.php

# Install Drush
RUN curl -L -o drush.phar https://github.com/drush-ops/drush-launcher/releases/download/0.4.2/drush.phar \
    && chmod +x drush.phar \
    && mv drush.phar /usr/local/bin/drush \
    && composer require drush/drush:^9.0

# Create writable public files directory
RUN mkdir sites/default/files \
    && chown www-data:www-data sites/default/files

# Create private file directory
RUN mkdir /var/www/private \
    && chown www-data:www-data /var/www/private

# Copy local Drupal settings
COPY settings.php ${DIRPATH}/sites/default/settings.php
RUN chown www-data:www-data sites/default/settings.php

EXPOSE 443