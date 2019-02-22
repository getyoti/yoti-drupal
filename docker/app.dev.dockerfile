FROM docker_drupal-8-base:latest

# Install and configure xdebug.
RUN yes | pecl install xdebug \
    && echo "zend_extension=$(find /usr/local/lib/php/extensions/ -name xdebug.so)" > /usr/local/etc/php/conf.d/xdebug.extension.ini
COPY ./docker/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# Install PHP_CodeSniffer
RUN composer require dealerdirect/phpcodesniffer-composer-installer

# Upgrade and configure PHPUnit
COPY ./docker/phpunit.xml .
RUN composer run-script drupal-phpunit-upgrade
