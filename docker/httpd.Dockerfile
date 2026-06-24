FROM httpd:2.4-alpine AS base
RUN adduser --disabled-password container
RUN mkdir /usr/local/apache2/sites
RUN apk add openssl
RUN openssl req -x509 -nodes -days 365 \
    -newkey rsa:2048 \
    -keyout /etc/ssl/private/server.key \
    -out /etc/ssl/certs/server.crt \
    -subj "/CN=localhost"
