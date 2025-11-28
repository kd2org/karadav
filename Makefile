KD2FW_URL=https://fossil.kd2.org/kd2fw/doc/tip/src/lib/KD2/
INSTALL_PATH=/var/lib/karadav
INSTALL_USER=www-data

deps: js-deps php-deps

js-deps:
	wget -O www/webdav.js https://raw.githubusercontent.com/kd2org/webdav-manager.js/main/webdav.js
	wget -O www/webdav.css https://raw.githubusercontent.com/kd2org/webdav-manager.js/main/webdav.css

php-deps:
	wget -O lib/KD2/ErrorManager.php '${KD2FW_URL}ErrorManager.php'
	wget -O lib/KD2/WebDAV/Server.php '${KD2FW_URL}WebDAV/Server.php'
	wget -O lib/KD2/WebDAV/AbstractStorage.php '${KD2FW_URL}WebDAV/AbstractStorage.php'
	wget -O lib/KD2/WebDAV/NextCloud.php '${KD2FW_URL}WebDAV/NextCloud.php'
	wget -O lib/KD2/WebDAV/TrashInterface.php '${KD2FW_URL}WebDAV/TrashInterface.php'
	wget -O lib/KD2/WebDAV/NextCloudNotes.php '${KD2FW_URL}WebDAV/NextCloudNotes.php'
	wget -O lib/KD2/WebDAV/WOPI.php '${KD2FW_URL}WebDAV/WOPI.php'
	wget -O lib/KD2/HTTP/Server.php '${KD2FW_URL}HTTP/Server.php'
	wget -O lib/KD2/Graphics/Image.php '${KD2FW_URL}Graphics/Image.php'
	wget -O lib/KD2/Graphics/SVG/Avatar.php '${KD2FW_URL}Graphics/SVG/Avatar.php'

server:
	php -S 0.0.0.0:8080 -t www www/_router.php

install: deps
	mkdir -p ${INSTALL_PATH}
	mkdir ${INSTALL_PATH}/data
	cp -r lib www ${INSTALL_PATH}
	cp schema.sql ${INSTALL_PATH}/schema.sql
	cp config.dist.php ${INSTALL_PATH}/config.local.php
	chown -R ${INSTALL_USER}:${INSTALL_USER} ${INSTALL_PATH}

