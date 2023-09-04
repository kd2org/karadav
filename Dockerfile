FROM alpine:edge
LABEL Maintainer="BohwaZ <https://bohwaz.net/>" \
      Description="KaraDAV file sharing server"

RUN apk --no-cache add php82 php82-curl php82-ctype php82-opcache php82-simplexml php82-session php82-sqlite3 php82-fileinfo
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

EXPOSE 8080

VOLUME ["/var/karadav/data", "/var/karadav/config.local.php"]

ENV PHP_CLI_SERVER_WORKERS=3
CMD ["php", "-S", "0.0.0.0:8080", "-t", "www", "www/_router.php"]
