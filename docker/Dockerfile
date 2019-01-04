# from https://www.drupal.org/requirements/php#drupalversions
FROM php:7.2-apache
ARG BRANCH

# Fix for Error: Package 'php-XXX' has no installation candidate
RUN rm /etc/apt/preferences.d/no-debian-php

COPY default.conf /etc/apache2/sites-available/000-default.conf
COPY ./keys/server.crt /etc/apache2/ssl/server.crt
COPY ./keys/server.key /etc/apache2/ssl/server.key

RUN a2enmod rewrite ssl

# install the PHP extensions we need
RUN set -ex \
	&& buildDeps=' \
		libjpeg62-turbo-dev \
		libpng-dev \
		libpq-dev \
	' \
	&& apt-get update && apt-get install -y --no-install-recommends $buildDeps && rm -rf /var/lib/apt/lists/* \
	&& docker-php-ext-configure gd \
		--with-jpeg-dir=/usr \
		--with-png-dir=/usr \
	&& docker-php-ext-install -j "$(nproc)" gd mbstring opcache pdo pdo_mysql pdo_pgsql zip \
	&& apt-get update \
	&& apt-get install -y git zip unzip vim nano php7.0-gd \
# PHP Warning:  PHP Startup: Unable to load dynamic library '/usr/local/lib/php/extensions/no-debug-non-zts-20151012/gd.so' - libjpeg.so.62: cannot open shared object file: No such file or directory in Unknown on line 0
# PHP Warning:  PHP Startup: Unable to load dynamic library '/usr/local/lib/php/extensions/no-debug-non-zts-20151012/pdo_pgsql.so' - libpq.so.5: cannot open shared object file: No such file or directory in Unknown on line 0
	&& apt-mark manual \
		libjpeg62-turbo \
		libpq5 \
	&& apt-get purge -y --auto-remove $buildDeps

# set recommended PHP.ini settings
# see https://secure.php.net/manual/en/opcache.installation.php
RUN { \
		echo 'opcache.memory_consumption=128'; \
		echo 'opcache.interned_strings_buffer=8'; \
		echo 'opcache.max_accelerated_files=4000'; \
		echo 'opcache.revalidate_freq=60'; \
		echo 'opcache.fast_shutdown=1'; \
		echo 'opcache.enable_cli=1'; \
	} > /usr/local/etc/php/conf.d/opcache-recommended.ini

# https://www.drupal.org/node/3060/release
ENV DIRPATH /var/www/html
ENV DEFAULT_BRANCH 8.x-1.x
ENV DRUPAL_VERSION 8.6.5
ENV PLUGIN_PACKAGE_NAME yoti-for-drupal-8.x-1.x-edge.zip
ENV DRUPAL_MD5 3db4ab3674ae38d1b15d5faac3b10ff0

WORKDIR $DIRPATH

RUN if [ "$BRANCH" = "" ]; then \
  $BRANCH = $DEFAULT_BRANCH; \
fi

RUN curl -fSL "https://ftp.drupal.org/files/projects/drupal-${DRUPAL_VERSION}.tar.gz" -o drupal.tar.gz \
	&& echo "${DRUPAL_MD5} *drupal.tar.gz" | md5sum -c - \
	&& tar -xz --strip-components=1 -f drupal.tar.gz \
	&& rm drupal.tar.gz \
	&& chown -R www-data:www-data sites modules themes

RUN git clone -b ${BRANCH} https://github.com/getyoti/yoti-drupal.git --single-branch \
        && echo "Finished cloning ${BRANCH}" \
        && cd yoti-drupal \
        && mkdir __sdk-sym \
        && ./pack-plugin.sh \
        && mv ./${PLUGIN_PACKAGE_NAME} ${DIRPATH}/modules \
        && cd .. \
        && rm -rf yoti-drupal \
        && cd ${DIRPATH}/modules \
        && unzip ${PLUGIN_PACKAGE_NAME} \
        && rm -f ${PLUGIN_PACKAGE_NAME}

EXPOSE 443