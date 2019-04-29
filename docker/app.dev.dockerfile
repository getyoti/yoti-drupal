FROM docker_drupal-8-base:latest

ARG ENABLE_XDEBUG

# Install and configure xdebug.
RUN if [ "$ENABLE_XDEBUG" = "1" ]; then \
  yes | pecl install xdebug \
  && echo "zend_extension=$(find /usr/local/lib/php/extensions/ -name xdebug.so)" > /usr/local/etc/php/conf.d/xdebug.extension.ini; \
fi
COPY ./docker/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# Allows tests to be run as www-data
RUN apt-get install sudo

# Install PHP_CodeSniffer
RUN composer require dealerdirect/phpcodesniffer-composer-installer

# Upgrade and configure PHPUnit
COPY ./docker/phpunit.xml .
RUN composer run-script drupal-phpunit-upgrade
