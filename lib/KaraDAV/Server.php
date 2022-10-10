<?php

namespace KaraDAV;

class Server
{
	public Users $users;
	public WebDAV $dav;

	public function __construct()
	{
		$users = new Users;
		$this->users = new Users;
		$this->dav = new WebDAV;
		$this->dav->setStorage(new Storage($this->users));
	}

	public function route(?string $uri = null): bool
	{
		header('Access-Control-Allow-Origin: *', true);
		$method = $_SERVER['REQUEST_METHOD'] ?? null;

		// Always say YES to OPTIONS
		if ($method == 'OPTIONS') {
			$this->dav->http_options();
			return true;
		}

		$nc = new NextCloud($this->dav, $this->users);

		if ($r = $nc->route($uri)) {
			// NextCloud route already replied something, stop here
			return true;
		}

		// If NextCloud layer didn't return anything
		// it means we fall back to the default WebDAV server
		// available on the root path. We need to handle a
		// classic login/password auth here.

		if (0 !== strpos($uri, '/files/')) {
			return false;
		}

		$user = $this->users->login($_SERVER['PHP_AUTH_USER'] ?? null, $_SERVER['PHP_AUTH_PW'] ?? null);

		if (!$user) {
			http_response_code(401);
			header('WWW-Authenticate: Basic realm="Please login"');
			return true;
		}

		$this->dav->setBaseURI('/files/' . $user->login . '/');

		return $this->dav->route($uri);
	}
}
