ARG PHP_EXTS="bcmath ctype fileinfo mbstring pdo pdo_mysql dom pcntl"
ARG PHP_PECL_EXTS="redis"

FROM php:8.3.26RC1-zts-alpine3.22 AS fpm_server

# We need to declare that we want to use the args in this build step
ARG PHP_EXTS
ARG PHP_PECL_EXTS

WORKDIR /opt/apps/laravel-in-kubernetes

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

#CMD ["/usr/bin/tail", "-f","/dev/null"]
