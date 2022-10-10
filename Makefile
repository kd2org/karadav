js-deps:
	wget -O www/webdav.js https://raw.githubusercontent.com/kd2org/webdav-manager.js/main/webdav.js
	wget -O www/webdav.css https://raw.githubusercontent.com/kd2org/webdav-manager.js/main/webdav.css

php-deps:
	wget -O lib/KD2/WebDAV.php 'https://fossil.kd2.org/kd2fw/doc/tip/src/lib/KD2/WebDAV.php'
	wget -O lib/KD2/WebDAV_NextCloud.php 'https://fossil.kd2.org/kd2fw/doc/tip/src/lib/KD2/WebDAV_NextCloud.php'

server:
	php -S 0.0.0.0:8080 -t www www/_router.php