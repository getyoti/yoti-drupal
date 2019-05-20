FROM docker_drupal-7-base:latest

ARG ENABLE_XDEBUG

# Install and configure xdebug.
RUN if [ "$ENABLE_XDEBUG" = "1" ]; then \
  yes | pecl install xdebug \
  && echo "zend_extension=$(find /usr/local/lib/php/extensions/ -name xdebug.so)" > /usr/local/etc/php/conf.d/xdebug.extension.ini; \
fi
COPY ./docker/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# Install PHP_CodeSniffer
RUN composer require drupal/coder:^8.3.3
RUN composer require dealerdirect/phpcodesniffer-composer-installer
