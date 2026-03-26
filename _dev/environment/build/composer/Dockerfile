FROM composer:2.9.5 AS composer_base

WORKDIR /opt/repos 

RUN addgroup -S composer \
    &&  adduser -S composer -G composer \
    && chown -R composer:composer /opt/repos

USER composer