ARG PHP_PECL_EXTS="redis"

FROM php:8.3.27-fpm-alpine3.21 AS fpm_server

# We need to declare that we want to use the args in this build step
ARG PHP_PECL_EXTS

WORKDIR /opt/repos

RUN apk add --no-cache ${PHPIZE_DEPS} linux-headers mariadb-client \
        && pecl install ${PHP_PECL_EXTS} xdebug \
        && docker-php-ext-install pdo_mysql \
        && docker-php-ext-enable ${PHP_PECL_EXTS} xdebug \
        && apk del ${PHPIZE_DEPS} linux-headers \ 
        && rm -Rf /var/cache/apk/* \ 
        && rm -Rf /etc/apk/* \
        && addgroup -g 20009 web \ 
        && adduser -u 20008 webuser -G web -D

#lets harden php with some default values 
COPY ./conf/custom.ini /usr/local/etc/php/conf.d/custom.ini

# Now that we've installed and configured, switch the user 
USER  webuser 