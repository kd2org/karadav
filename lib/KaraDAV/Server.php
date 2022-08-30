<?php

namespace KaraDAV;

use KD2\WebDAV;
use KD2\WebDAV_Exception;
use KD2\WebDAV_NextCloud;
use KD2\WebDAV_NextCloud_Exception;

class Server extends WebDAV_NextCloud
{
	protected string $path;
	const LOCK = true;

	/**
	 * These file names will be ignored when doing a PUT
	 * as they are garbage, coming from some OS
	 */
	const PUT_IGNORE_PATTERN = '!^~(?:lock\.|^\._)|^(?:\.DS_Store|Thumbs\.db|desktop\.ini)$!';

	public function route(?string $uri = null): bool
	{
		if (!parent::route($uri)) {
			// If NextCloud layer didn't return anything
			// it means we fall back to the default WebDAV server
			// available on the root path. We need to handle a
			// classic login/password auth here.
			$this->setBaseURI('/');

			$users = new Users;
			$login = $users->login($_SERVER['PHP_AUTH_USER'] ?? null, $_SERVER['PHP_AUTH_PW'] ?? null);

			if (!$login) {
				http_response_code(401);
				header('WWW-Authenticate: Basic realm="Please login"');
				return true;
			}

			$this->setUser($login);

			return WebDAV::route($uri);
		}
	}

	protected function setUser(string $user): void
	{
		$path = sprintf(STORAGE_PATH, $user);
		$this->path = rtrim($path, '/') . '/';

		if (!file_exists($path)) {
			mkdir($path, 0770, true);
		}
	}

	protected function checkAppAuth(string $login, string $password): bool
	{
		$lines = file(APPS_PASSWD_FILE);
		$removed = 0;
		$ok = false;

		foreach ($lines as $k => $line) {
			$line = explode(':', trim($line));

			if (count($line) != 3) {
				continue;
			}

			if ($line[0] != $login) {
				continue;
			}

			// Expired session
			if ($line[2] < time()) {
				unset($lines[$k]);
				$removed++;
				continue;
			}

			if (password_verify($password, $line[1])) {
				$ok = true;
				break;
			}
		}

		// Clean up of expired sessions
		if ($removed) {
			file_put_contents(APPS_PASSWD_FILE, implode("\n", $lines) . "\n");
		}

		return $ok;
	}

	public function nc_auth(?string $login, ?string $password): bool
	{
		if (isset($_COOKIE[session_name()]) && !isset($_SESSION)) {
			session_start();
		}

		// Check if user already has a session
		if (!empty($_SESSION['user'])) {
			$this->setUser($_SESSION['user']);
			return true;
		}

		// If not, try to login
		$login = strtolower(trim($login));

		$users = new Users;
		$user_password_hash = $users->get($login);

		// User has vanished?
		if (!$user_password_hash) {
			return false;
		}

		// The app password contains the user password hash
		// this way we can invalidate all sessions if we change
		// the user password
		$password .= $user_password_hash;

		if (!$this->checkAppAuth($login, $password)) {
			return false;
		}

		@session_start();
		$_SESSION['user'] = $login;
		$this->setUser($login);

		return true;
	}

	public function nc_generate_token(): string
	{
		return sha1(random_bytes(16));
	}

	public function nc_store_token(string $token): void
	{
		@session_start();

		$_SESSION['token'] = $token;
	}

	public function nc_validate_token(string $token): ?array
	{
		if (!isset($_COOKIE[session_name()])) {
			return null;
		}

		@session_start();

		if (empty($_SESSION['token'])) {
			return null;
		}

		if ($_SESSION['token'] != $token) {
			return null;
		}

		unset($_SESSION['token']);

		$login = $_SESSION['user'];
		$hash = $this->listUsers()[$login] ?? null;

		if (!$hash) {
			return null;
		}

		// Generate a custom app password
		$password = sha1(random_bytes(16));
		$hash = password_hash($password);
		$expiry = time() + 3600*24*90; // Sessions expire after 3 months

		file_put_contents(APPS_PASSWD_FILE, sprintf("%s:%s:%d\n", $login, $hash, $expiry), FILE_APPEND);

		return (object) compact('login', 'password');
	}

	public function nc_login_url(?string $token): string
	{
		if ($token) {
			return sprintf('%s/login.php?nc_token=%s', $this->root_url, $token);
		}
		else {
			return sprintf('%s/login.php?nc_redirect=yes', $this->root_url);
		}
	}

	/**
	 * Simple locking implementation using sessions
	 * Because we have a user-centric store, we don't need a database,
	 * we just store the locks in session
	 */
	protected function getLock(string $uri, ?string $token = null): ?string
	{
		if ($scope = ($_SESSION['locks'][$uri][$token] ?? null)) {
			return $lock;
		}

		// Also check lock on parent directory as we support depth = 1
		if (trim($uri, '/') && $lock = ($_SESSION['locks'][dirname($uri)][$scope] ?? null)) {
			return $lock;
		}

		return null;
	}

	protected function lock(string $uri, string $token, string $scope): void
	{
		if (!isset($_SESSION['locks'])) {
			$_SESSION['locks'] = [];
		}

		if (!isset($_SESSION['locks'][$uri])) {
			$_SESSION['locks'][$uri] = [];
		}

		$_SESSION['locks'][$uri][$token] = 'scope';
	}

	protected function unlock(string $uri, string $token): void
	{
		unset($_SESSION['locks'][$uri][$token]);
	}

	protected function list(string $uri): iterable
	{
		$dirs = glob($this->path . $uri . '/*', \GLOB_ONLYDIR);
		$dirs = array_map('basename', $dirs);
		natcasesort($dirs);

		$files = glob($this->path . $uri . '/*');
		$files = array_map('basename', $files);
		$files = array_diff($files, $dirs);
		natcasesort($files);

		$files = array_flip(array_merge($dirs, $files));
		$files = array_map(fn($a) => null, $files);
		return $files;
	}

	protected function get(string $uri): ?array
	{
		if (!file_exists($this->path . $uri)) {
			return null;
		}

		//return ['content' => file_get_contents($this->path . $uri)];
		//return ['resource' => fopen($this->path . $uri, 'r')];
		return ['path' => $this->path . $uri];
	}

	protected function exists(string $uri): bool
	{
		return file_exists($this->path . $uri);
	}

	protected function metadata(string $uri, bool $all = false): ?array
	{
		$target = $this->path . $uri;

		if (!file_exists($target)) {
			return null;
		}

		$meta = [
			'modified'   => filemtime($target),
			'size'       => filesize($target),
			'type'       => mime_content_type($target),
			'collection' => is_dir($target),
		];

		if ($all) {
			$meta['created']  = filectime($target);
			$meta['accessed'] = fileatime($target);
			$meta['hidden']   = basename($target)[0] == '.';
		}

		return $meta;
	}

	protected function put(string $uri, $pointer): bool
	{
		if (preg_match(self::PUT_IGNORE_PATTERN, basename($uri))) {
			return false;
		}

		$target = $this->path . $uri;
		$parent = dirname($target);

		if (is_dir($target)) {
			throw new WebDAV_Exception('Target is a directory', 409);
		}

		if (!file_exists($parent)) {
			mkdir($parent, 0770, true);
		}

		$new = !file_exists($target);

		$out = fopen($target, 'w');
		stream_copy_to_stream($pointer, $out);
		fclose($out);
		fclose($pointer);

		return $new;
	}

	protected function delete(string $uri): void
	{
		$target = $this->path . $uri;

		if (!file_exists($target)) {
			throw new WebDAV_Exception('Target does not exist', 404);
		}

		if (is_dir($target)) {
			foreach (glob($target . '/*') as $file) {
				$this->delete(substr($file, strlen($this->path)));
			}

			rmdir($target);
		}
		else {
			unlink($target);
		}
	}

	protected function copymove(bool $move, string $uri, string $destination): bool
	{
		$source = $this->path . $uri;
		$target = $this->path . $destination;
		$parent = dirname($target);

		if (!file_exists($source)) {
			throw new WebDAV_Exception('File not found', 404);
		}

		$overwritten = file_exists($target);

		if (!is_dir($parent)) {
			throw new WebDAV_Exception('Target parent directory does not exist', 409);
		}

		if ($overwritten) {
			$this->delete($destination);
		}

		$method = $move ? 'rename' : 'copy';

		if ($method == 'copy' && is_dir($source)) {
			@mkdir($target, 0770, true);

			foreach ($iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source), \RecursiveIteratorIterator::SELF_FIRST) as $item)
			{
				if ($item->isDir()) {
					@mkdir($target . DIRECTORY_SEPARATOR . $iterator->getSubPathname());
				} else {
					copy($item, $target . DIRECTORY_SEPARATOR . $iterator->getSubPathname());
				}
			}
		}
		else {
			$method($source, $target);
		}

		return $overwritten;
	}

	protected function copy(string $uri, string $destination): bool
	{
		return $this->copymove(false, $uri, $destination);
	}

	protected function move(string $uri, string $destination): bool
	{
		return $this->copymove(true, $uri, $destination);
	}

	protected function mkcol(string $uri): void
	{
		$target = $this->path . $uri;
		$parent = dirname($target);

		if (file_exists($target)) {
			throw new WebDAV_Exception('There is already a file with that name', 405);
		}

		if (!file_exists($parent)) {
			throw new WebDAV_Exception('The parent directory does not exist', 409);
		}

		mkdir($target, 0770);
	}

	protected function html_directory(string $uri, iterable $list, array $strings = self::LANGUAGE_STRINGS): string
	{
		$out = parent::html_directory($uri, $list, $strings);

		$out = str_replace('</head>', '<link rel="stylesheet" type="text/css" href="/_files.css" /></head>', $out);
		$out = str_replace('</body>', '<script type="text/javascript" src="/_files.js"></script></body>', $out);

		return $out;
	}
}
