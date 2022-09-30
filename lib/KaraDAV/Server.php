<?php

namespace KaraDAV;

use KD2\WebDAV\Server as WebDAV_Server;
use KD2\WebDAV\Exception;

class Server extends WebDAV_Server
{
	protected Users $users;

	public function __construct()
	{
		$users = new Users;
		$this->users = new Users;
		$storage = new Storage($this->users);
		$this->setStorage($storage);
	}

	public function route(?string $uri = null): bool
	{
		$nc = new NextCloud($this->users, sprintf(STORAGE_PATH, '_chunks'));

		if ($r = $nc->route($uri)) {
			if ($r['route'] == 'direct') {
				$this->http_get($r['uri']);
				return true;
			}
			elseif ($r['route'] == 'webdav') {
				$this->setBaseURI($r['base_uri']);
			}
			else {
				// NextCloud route already replied something, stop here
				return true;
			}
		}
		else {
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

			$this->setBaseURI('/files/' . $user->login . '/');
		}

		return parent::route($uri);
	}

	protected function html_directory(string $uri, iterable $list, array $strings = self::LANGUAGE_STRINGS): ?string
	{
		$out = parent::html_directory($uri, $list, $strings);

		if (null !== $out) {
			$out = str_replace('</head>', sprintf('<link rel="stylesheet" type="text/css" href="%sfiles.css" /></head>', WWW_URL), $out);
			$out = str_replace('</body>', sprintf('<script type="text/javascript" src="%sfiles.js"></script></body>', WWW_URL), $out);
		}

		return $out;
	}
}
