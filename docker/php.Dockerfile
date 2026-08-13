FROM php:8.5-fpm-alpine AS base
RUN apk update
ARG ELASTIC_APM_PHP_AGENT_VERSION=1.17.0
ARG TARGETARCH=amd64
RUN apk add nano git bash sudo bash-completion mariadb-client autoconf build-base cronie nodejs npm composer curl;apk add --update linux-headers libzip-dev icu-dev libmemcached-dev
# Official Elastic APM PHP agent (native extension). Not a PECL/core PHP module.
# Alpine's apk rejects Elastic's .apk (v2 package format), so install from the musl tarball.
RUN set -eux; \
    case "${TARGETARCH}" in \
        amd64|x86_64) arch=x86-64 ;; \
        arm64|aarch64) arch=arm64 ;; \
        *) echo "Unsupported TARGETARCH=${TARGETARCH}"; exit 1 ;; \
    esac; \
    curl -fsSL -o /tmp/elastic-apm-agent.tar \
        "https://github.com/elastic/apm-agent-php/releases/download/v${ELASTIC_APM_PHP_AGENT_VERSION}/apm-agent-php-linuxmusl-${arch}.tar"; \
    tar -xf /tmp/elastic-apm-agent.tar -C /; \
    ext_dir="$(php-config --extension-dir)"; \
    cp /opt/elastic/apm-agent-php/extensions/elastic_apm_loader.so "${ext_dir}/"; \
    cp /opt/elastic/apm-agent-php/extensions/elastic_apm-*.so "${ext_dir}/"; \
    rm -f /tmp/elastic-apm-agent.tar; \
    printf '%s\n' \
        'extension=elastic_apm_loader.so' \
        'elastic_apm.bootstrap_php_part_file=/opt/elastic/apm-agent-php/src/bootstrap_php_part.php' \
        > /usr/local/etc/php/conf.d/docker-php-ext-elastic-apm.ini
RUN pecl install "xdebug-3.5.0";
# Keep xdebug out of conf.d — entrypoint enables it via /tmp when APM is off.
RUN mkdir -p /usr/local/etc/php/conf.d-available \
    && echo "zend_extension=xdebug.so" > /usr/local/etc/php/conf.d-available/docker-php-ext-xdebug.ini
RUN docker-php-ext-install intl zip pdo_mysql pcntl sockets
RUN pecl install redis
RUN echo "extension=redis.so" > /usr/local/etc/php/conf.d/docker-php-ext-redis.ini
RUN pecl install memcached
RUN echo "extension=memcached.so" > /usr/local/etc/php/conf.d/docker-php-ext-memcached.ini

RUN adduser -s $(which bash) --disabled-password -u 1000 container
RUN echo -e "Defaults rootpw\nALL ALL=(ALL:ALL) PASSWD: ALL\nDefaults env_keep += ""*""" | tee -a /etc/sudoers
RUN chown root:root /bin/su && chmod 4755 /bin/su
RUN echo "root:root" | chpasswd
RUN mkdir /var/lib/php /var/lib/php/volume
RUN chown container:container /var/lib/php/volume; chmod 1733 /var/lib/php/volume

ENV SHELL=/bin/bash
# conf.d + writable extra dir managed by php-entrypoint.sh (xdebug toggle).
ENV PHP_INI_SCAN_DIR=/usr/local/etc/php/conf.d:/tmp/php-conf.d-extra
RUN echo 'alias sudo="sudo -E"' > /etc/profile.d/00sudo.sh
RUN echo 'source /var/www/html/.bashrc' >> /etc/profile.d/00-bashrc.sh
COPY php-entrypoint.sh /usr/local/bin/php-entrypoint.sh
ENTRYPOINT ["php-entrypoint.sh"]
# Re-declare: custom ENTRYPOINT clears CMD inherited from php:*-fpm.
CMD ["php-fpm"]

USER container
