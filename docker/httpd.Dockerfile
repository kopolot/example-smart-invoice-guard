FROM httpd:2.4-alpine AS base
RUN adduser --disabled-password container
RUN mkdir /usr/local/apache2/sites
