FROM alpine:edge
LABEL Maintainer="BohwaZ <https://bohwaz.net/>" \
      Description="KaraDAV file sharing server"

RUN apk --no-cache add php81 php81-curl php81-ctype php81-opcache php81-simplexml php81-session php81-sqlite3 php81-fileinfo
ENV PHP_CLI_SERVER_WORKERS=4

# Setup document root
RUN mkdir -p /var/karadav
RUN mkdir /var/karadav/data
RUN mkdir /var/karadav/lib
RUN mkdir /var/karadav/www

# Add application
WORKDIR /var/karadav/
COPY lib /var/karadav/lib/
COPY www /var/karadav/www/
COPY schema.sql /var/karadav/
COPY config.dist.php /var/karadav/config.local.php

EXPOSE 8080

VOLUME ["/var/karadav/data"]

ENV PHP_CLI_SERVER_WORKERS=3
CMD ["php", "-S", "0.0.0.0:8080", "-t", "www", "www/_router.php"]
