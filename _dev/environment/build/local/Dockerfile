ARG PHP_PECL_EXTS="redis"
ARG SITE="default"

FROM composer:2.8.11 AS composer_base

ARG SITE

RUN mkdir -p /opt/sites 

WORKDIR /opt/sites 

RUN addgroup -S composer \
    &&  adduser -S composer -G composer \
    &&  chown -R composer /opt/sites

USER composer 

COPY --chown=composer Sites ./

RUN composer --working-dir=${SITE} install --no-scripts --ignore-platform-reqs --no-autoloader --prefer-dist

RUN composer --working-dir=${SITE} install --no-dev --prefer-dist

ENTRYPOINT ["tail", "-f", "/dev/null"]

FROM php:8.3.26RC1-fpm-alpine AS fpm_server

# We need to declare that we want to use the args in this build step
ARG PHP_PECL_EXTS

WORKDIR /opt/sites

RUN apk add --no-cache ${PHPIZE_DEPS} \
        && pecl install ${PHP_PECL_EXTS} \
        && docker-php-ext-enable ${PHP_PECL_EXTS} \
        && apk del ${PHPIZE_DEPS} \ 
        && rm -Rf /var/cache/apk/* \ 
        && rm -Rf /etc/apk/* \
        && addgroup -g 20009 web \ 
        && adduser -u 20008 webuser -G web -D

# Now that we've installed and configured, switch the user 
USER  webuser

# We need an nginx container which can pass requests to our FPM container,
# as well as serve any static content..
FROM nginx:stable-alpine3.20-slim AS nginx

WORKDIR /opt/sites

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

WORKDIR /opt/sites 

RUN apk --no-cache add shadow && usermod -u 10015 www-data && \
    groupmod -g 10015 www-data && apk del shadow 

RUN mkdir -p /var/log/apache2 \
        && chown -R www-data:www-data /usr/local/apache2 \ 
        && chown -R www-data:www-data /var/log/apache2 \ 
        && chown -R www-data:www-data /opt/sites 

USER www-data
