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