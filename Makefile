deps:
	wget -O lib/KD2/WebDAV.php 'https://fossil.kd2.org/kd2fw/doc/tip/src/lib/KD2/WebDAV.php'
	wget -O lib/KD2/WebDAV_NextCloud.php 'https://fossil.kd2.org/kd2fw/doc/tip/src/lib/KD2/WebDAV_NextCloud.php'

server:
	php -S localhost:8080 -t www www/_router.php