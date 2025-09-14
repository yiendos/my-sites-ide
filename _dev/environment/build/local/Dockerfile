ARG PHP_EXTS="bcmath ctype fileinfo mbstring pdo pdo_mysql dom pcntl"
ARG PHP_PECL_EXTS="redis"

FROM php:8.3.26RC1-fpm-alpine AS fpm_server

# We need to declare that we want to use the args in this build step
ARG PHP_EXTS
ARG PHP_PECL_EXTS

WORKDIR /opt/sites

RUN apk add --no-cache ${PHPIZE_DEPS} \
        && pecl install ${PHP_PECL_EXTS} \
        && docker-php-ext-enable ${PHP_PECL_EXTS} \
        && apk del ${PHPIZE_DEPS} \ 
        && rm -Rf /var/cache/apk/* \ 
        && addgroup -g 20009 web \ 
        && adduser -u 20008 webuser -G web -D \
        && rm -Rf /etc/apk/*

# this ensures that we can customise fpm settings via deployment environment variables 
#COPY _cloud/docker/php/z-www.conf /usr/local/etc/php-fpm.d/z-www.conf

# Now that we've installed and configured, switch the user 

USER  webuser

# We need an nginx container which can pass requests to our FPM container,
# as well as serve any static content..
FROM nginx:stable-alpine3.20-slim AS nginx

WORKDIR /opt/sites

RUN apk --no-cache add shadow && usermod -u 10014 nginx && \
    groupmod -g 10014 nginx && apk del shadow

COPY _dev/environment/servers/nginx/local/ssl /etc/nginx/ssl

RUN touch /etc/nginx/ssl

RUN chown -R nginx:nginx /usr/share/nginx/html && \
        chown -R nginx:nginx /var/cache/nginx && \
        chown -R nginx:nginx /var/log/nginx && \
        chown -R nginx:nginx /etc/nginx && \ 
        chown -R nginx:nginx /etc/nginx/ssl

RUN touch /var/run/nginx.pid && \
        chown -R nginx:nginx /var/run/nginx.pid

USER nginx

FROM httpd:alpine3.22 AS apache

WORKDIR /opt/sites 

RUN apk --no-cache add shadow && usermod -u 10015 www-data && \
    groupmod -g 10015 www-data && apk del shadow 

RUN mkdir -p /var/log/apache2 \
        && chown -R www-data:www-data /usr/local/apache2 \ 
        && chown -R www-data:www-data /var/log/apache2 \ 
        && chown -R www-data:www-data /opt/sites 

USER www-data
