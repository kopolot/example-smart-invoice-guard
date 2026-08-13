#!/bin/sh
set -eu

# Xdebug must not load alongside Elastic APM. conf.d is root-owned (and this
# image often runs as USER container / CI uid), so toggle via a writable scan dir.
XDEBUG_AVAILABLE='/usr/local/etc/php/conf.d-available/docker-php-ext-xdebug.ini'
XDEBUG_SCAN_DIR='/tmp/php-conf.d-extra'
XDEBUG_ENABLED="${XDEBUG_SCAN_DIR}/docker-php-ext-xdebug.ini"

mkdir -p "${XDEBUG_SCAN_DIR}"
rm -f "${XDEBUG_ENABLED}"

if [ "${ELASTIC_APM_ENABLED:-false}" != "true" ] && [ -f "${XDEBUG_AVAILABLE}" ]; then
    ln -sf "${XDEBUG_AVAILABLE}" "${XDEBUG_ENABLED}"
fi

export PHP_INI_SCAN_DIR="/usr/local/etc/php/conf.d:${XDEBUG_SCAN_DIR}"

exec docker-php-entrypoint "$@"
