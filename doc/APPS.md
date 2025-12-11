# Using KaraDAV with external apps

## Known compatible apps

* [oPodSync](https://fossil.kd2.org/opodsync/): podcast synchronization, compatible with the GPodder/NextCloud APIs (alternative to [NextCloud GPodder API](https://apps.nextcloud.com/apps/gpoddersync))

## How you can add an external app to KaraDAV

You can add external apps to the main menu. They are basically just links to external apps. Each link can have an icon, a label and a URL.

A link can either be opened in an iframe inside KaraDAV, opened in a new tab, or in the current tab.

You have to add the links to the `EXTERNAL_APPS` array inside the `config.local.php`.

Example to add an external link:

```
const EXTERNAL_APPS = [
	'podcasts' => [
		'label'  => 'Podcasts',
		'url'    => 'https://opodsync.example.org/',
		'icon'   => 'https://opodsync.example.org/logo.svg',
		'target' => '_blank',
	],
];
```

The key (`podcasts` here) is important and has to be unique for every app.

The icon can either be a URL to an external image, or some HTML code, for example to include a SVG that will then follow the theme colors.

Here is an example of an app embedded in an iframe:

```
const EXTERNAL_APPS = [
	'podcasts' => [
		'label'  => 'Podcasts',
		'url'    => 'https://opodsync.example.org/',
		'icon'   => '<svg...</svg>',
	],
];
```

The only difference is that it doesn't have a `target` key. Without a target, the link is opened in KaraDAV iframe.

## Single auth API

Your embedded app can authenticate the current user by receiving the current user session ID. You can then do a request to a KaraDAV endpoint to fetch the corresponding user information.

It is very easy to adapt an existing app to interact with KaraDAV.

You first need to create an API key by configuring the `EXTERNAL_API_KEY` in `config.local.php`. This will be required to make requests on the API.

```
const EXTERNAL_API_KEY = 'very strong password';
```

Just put `%sessionid%` inside your app URL and it will be replaced by the real session ID:

```
const EXTERNAL_APPS = [
	'podcasts' => [
		'label'  => 'Podcasts',
		'url'    => 'https://opodsync.example.org/?ext_session_id=%sessionid%',
		'icon'   => '<svg...</svg>',
	],
];
```

Here is an example of a PHP app that will detect this case and confirm with the upstream KaraDAV server that the session is valid:

```
<?php
session_start();

$id = $_GET['ext_session_id'] ?? null;

if (!empty($_SESSION['user'])) {
	// Nothing to do: user is already logged in locally
}
elseif ($id && ctype_alnum($id)) {
	$response = file_get_contents('https://:SECRETKEY@karadav.example.org/session.php?id=' . $id);
	$response = json_decode($response);

	// Response is invalid or session has expired
	if (!$response) {
		header('Location: /login.php');
		exit;
	}

	$_SESSION['user'] = $response;
}
else {
	header('Location: /login.php');
	exit;

}
```

Replace `SECRETKEY` with the key configured in `EXTERNAL_API_KEY` earlier.

### Endpoint documentation

* URL: `/session.php`
* Authentication: HTTP Basic (password only, user is not used)
* Parameters: (can be passed in URL or in POST)
  * `id`: session ID 
* HTTP code returned:
  * `200 OK` if the session exists
  * `400 Bad Request` if the session ID itself is invalid
  * `403 Forbidden` if there is no Authorization header, or if the provided password is invalid
  * `404 Not Found` if no session can be found for the provided ID
  * `500 Internal Server Error` in case something borks
* Reponse body for `200` response: complete user object in JSON (see below for example)

### Usage example with curl

```
curl -v http://karadav.localhost/session.php \
  -F id=330e647c4624900f1d8874bca2e9e5df \
  -u ':abcd
```

### Example response

```
{
	"user": {
		"id": 1,
		"login": "demo",
		"password": "$2y$10$Us0o2F1QIsku1TL1lXsk0uNjhQvFGw7cwgeDRJD5F4GaUg4yK91wy",
		"quota": 104857600,
		"is_admin": 1,
		"path": "\/home\/demo\/karadav\/data\/demo\/",
		"dav_url": "http:\/\/karadav.localhost\/files\/demo\/",
		"avatar_url": "http:\/\/karadav.localhost\/avatars\/fe01ce2a7fbac8fa"
	},
	"quota": {
		"free": 76213091,
		"total": 104857600,
		"used": 28644509,
		"trash": null
	}
}
```
