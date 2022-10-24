# Installing KaraDAV

0. Setup your server with PHP 8.0+, and don't forget `php-sqlite3` and `php-simplexml` :)
1. Just download or clone this repo
2. Copy `config.dist.php` to `config.local.php`
3. Edit `config.local.php` to match your configuration
4. Create a virtual host (nginx, Apache, etc.) pointing to the `www` folder
5. Redirect all requests to `www/_router.php`
6. Go to your new virtual host, a default admin user is created the first time you access the UX, with the login `demo` and the password `karadavdemo`, please change it.

## Example Apache vhost

```
<VirtualHost *:80>
	ServerName karadav.localhost
	DocumentRoot /home/user/git/karadav/www
</VirtualHost>

<Directory /home/user/git/karadav/www>
	Options -Indexes -Multiviews
	AllowOverride None
	DirectoryIndex index.php

	RewriteEngine On
	RewriteBase /
	RewriteCond %{REQUEST_FILENAME} !-d
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteRule ^.*$ /_router.php [L]
</Directory>
```

# Using Docker

```
docker build -t karadav .
docker run -d -t --name karadav -p 8080:8080 -v dav-data:/var/karadav/data karadav
```
