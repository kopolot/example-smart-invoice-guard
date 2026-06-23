FROM php:8.5-fpm-alpine AS base
RUN apk update
RUN apk add nano git bash sudo bash-completion mariadb-client autoconf build-base cronie nodejs npm composer;apk add --update linux-headers libzip-dev icu-dev
RUN pecl install "xdebug-3.5.0";
RUN echo "zend_extension=xdebug.so" > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN docker-php-ext-install mysqli intl zip pdo_mysql
RUN pecl install redis
RUN echo "extension=redis.so" > /usr/local/etc/php/conf.d/docker-php-ext-redis.ini

RUN adduser -s $(which bash) --disabled-password -u 1000 container
RUN echo -e "Defaults rootpw\nALL ALL=(ALL:ALL) PASSWD: ALL\nDefaults env_keep += ""*""" | tee -a /etc/sudoers
RUN chown root:root /bin/su && chmod 4755 /bin/su
RUN echo "root:root" | chpasswd
RUN mkdir /var/lib/php /var/lib/php/volume
RUN chown container:container /var/lib/php/volume; chmod 1733 /var/lib/php/volume

ENV SHELL=/bin/bash
RUN echo 'alias sudo="sudo -E"' > /etc/profile.d/00sudo.sh
RUN echo 'source /var/www/html/.bashrc' >> /etc/profile.d/00-bashrc.sh
