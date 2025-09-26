ARG PHP_PECL_EXTS="redis"

FROM composer:2.8.11 AS composer_base

WORKDIR /opt/repos 

RUN addgroup -S composer \
    &&  adduser -S composer -G composer \
    && chown -R composer:composer /opt/repos

USER composer

FROM node:23.9.0-alpine3.21 AS frontend

WORKDIR /opt/repos/


FROM php:8.3.26RC1-fpm-alpine3.22 AS fpm_server

# We need to declare that we want to use the args in this build step
ARG PHP_PECL_EXTS

WORKDIR /opt/repos

RUN apk add --no-cache ${PHPIZE_DEPS} linux-headers libpng-dev \
        && pecl install ${PHP_PECL_EXTS} xdebug \
        &&  docker-php-ext-configure gd \
        &&  docker-php-ext-install pdo_mysql gd \
        && docker-php-ext-enable ${PHP_PECL_EXTS} xdebug gd \
        && apk del ${PHPIZE_DEPS} \ 
        && rm -Rf /var/cache/apk/* \ 
        && rm -Rf /etc/apk/* \
        && addgroup -g 20009 web \ 
        && adduser -u 20008 webuser -G web -D

#lets harden php with some default values 
COPY _dev/environment/preprocessors/fpm/conf/custom.ini /usr/local/etc/php/conf.d/custom.ini

# Now that we've installed and configured, switch the user 
USER  webuser 

FROM php:8.3.26RC1-cli-alpine3.22 AS cli

# We need to declare that we want to use the args in this build step
ARG PHP_PECL_EXTS

WORKDIR /opt/repos

RUN apk add --no-cache ${PHPIZE_DEPS} linux-headers libpng-dev \
        && pecl install ${PHP_PECL_EXTS} xdebug \
        &&  docker-php-ext-configure gd \
        &&  docker-php-ext-install pdo_mysql gd \
        && docker-php-ext-enable ${PHP_PECL_EXTS} xdebug gd \
        && apk del ${PHPIZE_DEPS} \ 
        && rm -Rf /var/cache/apk/* \ 
        && rm -Rf /etc/apk/* \
        && addgroup -g 20009 web \ 
        && adduser -u 20008 webuser -G web -D

# Now that we've installed and configured, switch the user 
USER  webuser 

FROM cli AS cron

WORKDIR /opt/repos

# We need an nginx container which can pass requests to our FPM container,
# as well as serve any static content..
FROM nginx:stable-alpine3.20-slim AS nginx

WORKDIR /opt/repos

RUN apk --no-cache add shadow && usermod -u 10014 nginx && \
    groupmod -g 10014 nginx && apk del shadow

RUN touch /var/run/nginx.pid && \
        chown -R nginx:nginx /usr/share/nginx/html && \
        chown -R nginx:nginx /var/cache/nginx && \
        chown -R nginx:nginx /var/log/nginx && \
        chown -R nginx:nginx /etc/nginx && \ 
        chown -R nginx:nginx /var/run/nginx.pid     

USER nginx

# We need an apache container which can pass requests to our FPM container,
# as well as serve any static content..
FROM httpd:alpine3.22 AS apache

WORKDIR /opt/repos 

RUN apk --no-cache add shadow && usermod -u 10015 www-data && \
    groupmod -g 10015 www-data && apk del shadow 

RUN mkdir -p /var/log/apache2 \
        && chown -R www-data:www-data /usr/local/apache2 \ 
        && chown -R www-data:www-data /var/log/apache2 \ 
        && chown -R www-data:www-data /opt/repos 

USER www-data
