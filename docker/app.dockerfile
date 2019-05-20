FROM docker_drupal-7-base:latest
ARG BRANCH

# https://www.drupal.org/node/3060/release
ENV DIRPATH /var/www/html
ENV DEFAULT_BRANCH 7.x-1.x
ENV PLUGIN_PACKAGE_NAME yoti-for-drupal-7.x-1.x-edge.zip

WORKDIR $DIRPATH

RUN if [ "$BRANCH" = "" ]; then \
  $BRANCH = $DEFAULT_BRANCH; \
fi

RUN git clone -b ${BRANCH} https://github.com/getyoti/yoti-drupal.git --single-branch \
        && echo "Finished cloning ${BRANCH}" \
        && cd yoti-drupal \
        && mkdir __sdk-sym \
        && ./pack-plugin.sh \
        && mv ./${PLUGIN_PACKAGE_NAME} ${DIRPATH}/sites/all/modules \
        && cd .. \
        && rm -rf yoti-drupal \
        && cd ${DIRPATH}/sites/all/modules \
        && unzip ${PLUGIN_PACKAGE_NAME} \
        && rm -f ${PLUGIN_PACKAGE_NAME}
