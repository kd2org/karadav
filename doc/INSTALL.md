# Installing KaraDAV

0. Setup your server with PHP 8.0+, and don't forget `php-sqlite3` and `php-simplexml` :)
1. Just download or clone this repo
2. Copy `config.dist.php` to `config.local.php`
3. Edit `config.local.php` and change constants to match your configuration
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

## Security issues

* Do not expose the `data` directory on your webserver, or your app database might be leaked, as well as your users data.
* Do not set the virtual host document root to the root of KaraDAV instead of the `www` directory. Please use a dedicated virtual host, or an `Alias`.

## Using per-user local UID/GID for user data

This would be useful if you want to have a different UNIX user for each of your users data directory, to keep them separate.

You'll need to install `apache2-mpm-itk` ([official website](http://mpm-itk.sesse.net)) and set up your virtualhost like that:

```
<VirtualHost *:80>
	ServerName karadav.localhost

	SetEnvIf Request_URI (.+) ITKUID=www-data ITKGID=www-data
	SetEnvIf Request_URI ^/files/([a-z]+)/ ITKUID=$1 ITKGID=$1

	# Do not allow root to be used as the ITK UID/GID
	SetEnvIf ITKUID ^root$ ITKUID=www-data
	SetEnvIf ITKGID ^root$ ITKGID=www-data

	AssignUserIDExpr %{reqenv:ITKUID}
	AssignGroupIDExpr %{reqenv:ITKGID}

	DocumentRoot /home/bohwaz/git/karadav/www
</VirtualHost>
```

# Using Docker

```
docker build -t karadav .
```

Then it is recommended to copy the `config.dist.php` file to `config.local.php` and at least change the `WWW_URL` constant to the correct http URL where the docker container will be accessible, like in this example:

```
const WWW_URL = 'http://192.168.1.1:8080/';
```

Then run the docker container like that to mount the local config file and the local data directory:

```
docker run -d -t --name karadav -p 8080:8080 -v $(pwd)/data:/var/karadav/data -v $(pwd)/config.local.php:/var/karadav/config.local.php karadav
```

If you don't want to use local mounts, then you can always use this:

```
docker run -d -t --name karadav -p 8080:8080 -v dav-data:/var/karadav/data karadav
```

**Note :** the provided docker file is using PHP built-in webserver with 4 workers. The [PHP manual says](https://www.php.net/manual/en/features.commandline.webserver.php) this is not intended for production usage. It works well for me, but you are welcome to use more classic setup with Apache or nginx and FPM if you wish.

# Using LDAP

See configuration constants in `config.local.php`.
