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

RUN chown -R nobody.nobody /var/karadav

USER nobody

# Add application
WORKDIR /var/karadav/
COPY --chown=nobody lib /var/karadav/lib/
COPY --chown=nobody www /var/karadav/www/
COPY --chown=nobody schema.sql /var/karadav/
COPY --chown=nobody config.dist.php /var/karadav/config.local.php

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "www", "www/_router.php"]
