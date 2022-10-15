# Setting up Collabora with KaraDAV

This is entirely optional, but will allow you to edit office documents directly from the browser.

Note that Collabora has a soft limit of 20 

## With Docker

First install docker and docker-compose, then run `docker pull collabora/code` to fetch the docker image.

Now you will have to create a `docker-compose.yml` file containing:

```
collabora:
  image: collabora/code
  container_name: collabora
  environment:
    domain: "karadav.localhost"
    extra_params: "--o:ssl.enable=false --o:ssl.termination=false -o:net.frame_ancestors=karadav.localhost:*"
  expose:
    - 9980
  ports:
    - "9980:9980"
  extra_hosts:
    - "karadav.localhost:0.0.0.0"
```

This setup is for a localhost test environment, where `karadav.localhost` is hosting your WebDAV server, and `docs.karadav.localhost` will host the Collabora server. You will have to replace `0.0.0.0` with your computer IP.

Then create a new Apache virtual host:

```
<VirtualHost *:80>
	ServerName docs.karadav.localhost

	AllowEncodedSlashes NoDecode
	ProxyPreserveHost On

	<Location />
		<Limit OPTIONS>
			Header always set Access-Control-Allow-Origin "*"
		</Limit>

		RewriteEngine On
		RewriteCond %{REQUEST_METHOD} OPTIONS
		RewriteRule ^(.*)$ $1 [R=200,L]
	</Location>


	# static html, js, images, etc. served from coolwsd
	# browser is the client part of Collabora Online
	ProxyPass           /browser http://127.0.0.1:9980/browser retry=0
	ProxyPassReverse    /browser http://127.0.0.1:9980/browser


	# WOPI discovery URL
	ProxyPass           /hosting/discovery http://127.0.0.1:9980/hosting/discovery retry=0
	ProxyPassReverse    /hosting/discovery http://127.0.0.1:9980/hosting/discovery


	# Capabilities
	ProxyPass           /hosting/capabilities http://127.0.0.1:9980/hosting/capabilities retry=0
	ProxyPassReverse    /hosting/capabilities http://127.0.0.1:9980/hosting/capabilities

	# Main websocket
	ProxyPassMatch      "/cool/(.*)/ws$"      ws://127.0.0.1:9980/cool/$1/ws nocanon


	# Admin Console websocket
	ProxyPass           /cool/adminws ws://127.0.0.1:9980/cool/adminws


	# Download as, Fullscreen presentation and Image upload operations
	ProxyPass           /cool http://127.0.0.1:9980/cool
	ProxyPassReverse    /cool http://127.0.0.1:9980/cool
	# Compatibility with integrations that use the /lool/convert-to endpoint
	ProxyPass           /lool http://127.0.0.1:9980/cool
	ProxyPassReverse    /lool http://127.0.0.1:9980/cool
</VirtualHost>
```

Reload the apache configuration, and launch `docker-compose up`.

Lastly, in KaraDAV's `config.local.php` set `WOPI_DISCOVERY_URL` to `http://docs.karadav.localhost/hosting/discovery`.

Now you should be able to edit ODS/ODT/etc. files from the web UI using Collabora.