FROM mysql:5.7

ENV MYSQL_ALLOW_EMPTY_PASSWORD=0
ENV MYSQL_ROOT_HOST=%
ENV MYSQL_DATABASE=sites_default
ENV MYSQL_USER=joomlatools
ENV MYSQL_PASSWORD=joomlatools
ENV MYSQL_ROOT_PASSWORD=root

COPY ./_mysites/docker/mysql/all-dbs.sql /docker-entrypoint-initdb.d/
COPY ./_mysites/docker/mysql/conf/my.cnf /etc/mysql/mysql.cnf