KD2FW_URL=https://fossil.kd2.org/kd2fw/doc/trunk/src/
INSTALL_PATH=/var/lib/karadav
INSTALL_USER=www-data

deps: js-deps php-deps

js-deps:
	wget -O www/webdav.js https://fossil.kd2.org/webdav-manager/doc/trunk/webdav.js
	wget -O www/webdav.css https://fossil.kd2.org/webdav-manager/doc/trunk/webdav.css

php-deps:
	for i in $$(find lib/KD2 -type f | sort); do \
		wget -O "$$i" '${KD2FW_URL}'"$$i"; \
	done

server:
	php -S 0.0.0.0:8080 -t www www/_router.php

install: deps
	mkdir -p ${INSTALL_PATH}
	mkdir ${INSTALL_PATH}/data
	cp -r lib www ${INSTALL_PATH}
	cp schema.sql ${INSTALL_PATH}/schema.sql
	cp config.dist.php ${INSTALL_PATH}/config.local.php
	chown -R ${INSTALL_USER}:${INSTALL_USER} ${INSTALL_PATH}

