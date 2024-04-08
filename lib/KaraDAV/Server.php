<?php

namespace KaraDAV;

use KD2\WebDAV\WOPI;

class Server
{
	public Users $users;
	public WebDAV $dav;
	public NextCloud $nc;

	public function __construct()
	{
		$users = new Users;
		$this->users = new Users;
		$this->dav = new WebDAV;
		$this->nc = new NextCloud($this->users);
		$storage = new Storage($this->users, $this->nc);
		$this->dav->setStorage($storage);
	}

	public function route(string $uri, string $relative_uri): bool
	{
		$method = $_SERVER['REQUEST_METHOD'] ?? null;

		// Always say YES to OPTIONS
		if ($method == 'OPTIONS') {
			$this->dav->http_options();
			return true;
		}

		if (WOPI_DISCOVERY_URL) {
			$wopi = new WOPI;
			$wopi->setServer($this->dav);

			if ($wopi->route($relative_uri)) {
				return true;
			}
		}

		$this->nc->setServer($this->dav);

		if ($r = $this->nc->route($relative_uri)) {
			// NextCloud route already replied something, stop here
			return true;
		}

		// If NextCloud layer didn't return anything
		// it means we fall back to the default WebDAV server
		// available on the root path. We need to handle a
		// classic login/password auth here.

		$base = rtrim(parse_url(WWW_URL, PHP_URL_PATH), '/');

		if (0 !== strpos($uri, $base . '/files/')) {
			return false;
		}

		$user = $this->users->login($_SERVER['PHP_AUTH_USER'] ?? null, $_SERVER['PHP_AUTH_PW'] ?? null);

		if (!$user) {
			http_response_code(401);
			header('WWW-Authenticate: Basic realm="Please login"');
			return true;
		}

		$this->dav->setBaseURI($base . '/files/' . $user->login . '/');

		return $this->dav->route($uri);
	}
}
