<?php

namespace KaraDAV;

use KD2\WebDAV;
use KD2\WebDAV_Exception;
use KD2\WebDAV_NextCloud;
use KD2\WebDAV_NextCloud_Exception;

class Server extends WebDAV_NextCloud
{
	protected Users $users;
	protected \stdClass $user;
	const LOCK = true;
	protected bool $parse_propfind = true;

	/**
	 * These file names will be ignored when doing a PUT
	 * as they are garbage, coming from some OS
	 */
	const PUT_IGNORE_PATTERN = '!^~(?:lock\.|^\._)|^(?:\.DS_Store|Thumbs\.db|desktop\.ini)$!';

	public function __construct()
	{
		$this->users = new Users;
		$this->root_url = WWW_URL;
	}

	public function route(?string $uri = null): bool
	{
		if (parent::route($uri)) {
			return true;
		}

		if (0 !== strpos($uri, '/files/')) {
			return false;
		}

		// If NextCloud layer didn't return anything
		// it means we fall back to the default WebDAV server
		// available on the root path. We need to handle a
		// classic login/password auth here.

		$users = new Users;
		$user = $users->login($_SERVER['PHP_AUTH_USER'] ?? null, $_SERVER['PHP_AUTH_PW'] ?? null);

		if (!$user) {
			http_response_code(401);
			header('WWW-Authenticate: Basic realm="Please login"');
			return true;
		}

		$this->user = $user;
		$this->setBaseURI('/files/' . $user->login . '/');

		return WebDAV::route($uri);
	}

	public function nc_auth(?string $login, ?string $password): bool
	{
		$user = $this->users->appSessionLogin($login, $password);

		if (!$user) {
			return false;
		}

		$this->user = $user;

		return true;
	}

	public function nc_get_user(): ?string
	{
		return $this->users->current()->login ?? null;
	}

	public function nc_get_quota(): array
	{
		return (array) $this->users->quota($this->users->current());
	}

	public function nc_generate_token(): string
	{
		return sha1(random_bytes(16));
	}

	public function nc_validate_token(string $token): ?array
	{
		$session = $this->users->appSessionValidateToken($token);

		if (!$session) {
			return null;
		}

		return ['login' => $session->user, 'password' => $session->password];
	}

	public function nc_login_url(?string $token): string
	{
		if ($token) {
			return sprintf('%slogin.php?nc=%s', $this->root_url, $token);
		}
		else {
			return sprintf('%slogin.php?nc=redirect', $this->root_url);
		}
	}

	protected function getLock(string $uri, ?string $token = null): ?string
	{
		// It is important to check also for a lock on parent directory as we support depth=1
		$sql = 'SELECT scope FROM locks WHERE user = ? AND (uri = ? OR uri = ?)';
		$params = [$this->user->login, $uri, dirname($uri)];

		if ($token) {
			$sql .= ' AND token = ?';
			$params[] = $token;
		}

		$sql .= ' LIMIT 1';

		return DB::getInstance()->firstColumn($sql, ...$params);
	}

	protected function lock(string $uri, string $token, string $scope): void
	{
		DB::getInstance()->run('REPLACE INTO locks VALUES (?, ?, ?, ?, datetime(\'now\', \'+5 minutes\'));', $this->user->login, $uri, $token, $scope);
	}

	protected function unlock(string $uri, string $token): void
	{
		DB::getInstance()->run('DELETE FROM locks WHERE user = ? AND uri = ? AND token = ?;', $this->user->login, $uri, $token);
	}

	protected function list(string $uri): iterable
	{
		$dirs = glob($this->user->path . $uri . '/*', \GLOB_ONLYDIR);
		$dirs = array_map('basename', $dirs);
		natcasesort($dirs);

		$files = glob($this->user->path . $uri . '/*');
		$files = array_map('basename', $files);
		$files = array_diff($files, $dirs);
		natcasesort($files);

		$files = array_flip(array_merge($dirs, $files));
		$files = array_map(fn($a) => null, $files);
		return $files;
	}

	protected function get(string $uri): ?array
	{
		if (!file_exists($this->user->path . $uri)) {
			return null;
		}

		//return ['content' => file_get_contents($this->path . $uri)];
		//return ['resource' => fopen($this->path . $uri, 'r')];
		return ['path' => $this->user->path . $uri];
	}

	protected function exists(string $uri): bool
	{
		return file_exists($this->user->path . $uri);
	}

	protected function metadata(string $uri, bool $all = false): ?array
	{
		$target = $this->user->path . $uri;

		if (!file_exists($target)) {
			return null;
		}

		$meta = [
			'modified'   => filemtime($target),
			'size'       => is_dir($target) ? null : filesize($target),
			'type'       => mime_content_type($target),
			'collection' => is_dir($target),
			'nc_permissions' => implode('', [self::PERM_READ, self::PERM_WRITE, self::PERM_CREATE, self::PERM_DELETE, self::PERM_RENAME_MOVE]),
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

		$target = $this->user->path . $uri;
		$parent = dirname($target);

		if (is_dir($target)) {
			throw new WebDAV_Exception('Target is a directory', 409);
		}

		if (!file_exists($parent)) {
			mkdir($parent, 0770, true);
		}

		$new = !file_exists($target);
		$delete = false;
		$size = 0;
		$quota = $this->users->quota($this->user);

		if (!$new) {
			$size -= filesize($target);
		}

		$tmp_file = '.tmp.' . sha1($target);
		$out = fopen($tmp_file, 'w');

		while (!feof($pointer)) {
			$bytes = fread($pointer, 8192);
			$size += strlen($bytes);

			if ($size > $quota->free) {
				$delete = true;
				break;
			}

			fwrite($out, $bytes);
		}

		fclose($out);
		fclose($pointer);

		if ($delete) {
			@unlink($tmp_file);
			throw new WebDAV_Exception('Your quota is exhausted', 403);
		}
		else {
			rename($tmp_file, $target);
		}

		return $new;
	}

	protected function delete(string $uri): void
	{
		$target = $this->user->path . $uri;

		if (!file_exists($target)) {
			throw new WebDAV_Exception('Target does not exist', 404);
		}

		if (is_dir($target)) {
			foreach (glob($target . '/*') as $file) {
				$this->delete(substr($file, strlen($this->user->path)));
			}

			rmdir($target);
		}
		else {
			unlink($target);
		}

		$this->properties($uri)->clear();
	}

	protected function copymove(bool $move, string $uri, string $destination): bool
	{
		$source = $this->user->path . $uri;
		$target = $this->user->path . $destination;
		$parent = dirname($target);

		if (!file_exists($source)) {
			throw new WebDAV_Exception('File not found', 404);
		}

		$overwritten = file_exists($target);

		if (!is_dir($parent)) {
			throw new WebDAV_Exception('Target parent directory does not exist', 409);
		}

		if (false === $move) {
			$quota = $this->users->quota($this->user);

			if (filesize($source) > $quota->free) {
				throw new WebDAV_Exception('Your quota is exhausted', 403);
			}
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

			$this->properties($uri)->move($destination);
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
		if (!$this->user->quota) {
			throw new WebDAV_Exception('Your quota is exhausted', 403);
		}

		$target = $this->user->path . $uri;
		$parent = dirname($target);

		if (file_exists($target)) {
			throw new WebDAV_Exception('There is already a file with that name', 405);
		}

		if (!file_exists($parent)) {
			throw new WebDAV_Exception('The parent directory does not exist', 409);
		}

		mkdir($target, 0770);
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

	protected function properties(string $uri): Properties
	{
		if (!isset($this->properties[$uri])) {
			$this->properties[$uri] = new Properties($this->user->login, $uri);
		}

		return $this->properties[$uri];
	}

	protected function get_extra_ns(string $uri): array
	{
		$out = parent::get_extra_ns($uri);
		$out = array_merge($out, $this->properties($uri)->ns());
		return $out;
	}

	protected function get_extra_properties(string $uri, string $file, array $meta, array $requested_properties): string
	{
		$out = parent::get_extra_properties($uri, $file, $meta, $requested_properties);
		$out .= $this->properties($uri)->xml();
		return $out;
	}

	protected function set_extra_properties(string $uri, string $body): void
	{
		$xml = @simplexml_load_string($body);
		// Select correct namespace if required
		if (!empty(key($xml->getDocNameSpaces()))) {
			$xml = $xml->children('DAV:');
		}

		$db = DB::getInstance();

		$db->exec('BEGIN;');
		$i = 0;

		if (isset($xml->set)) {
			foreach ($xml->set as $prop) {
				$prop = $prop->prop->children();
				$ns = $prop->getNamespaces(true);
				$ns = array_flip($ns);

				if (!key($ns)) {
					throw new WebDAV_Exception('Empty xmlns', 400);
				}

				$this->properties($uri)->set(key($ns), $prop->getName(), array_filter($ns, 'trim'), $prop->asXML());
			}
		}

		if (isset($xml->remove)) {
			foreach ($xml->remove as $prop) {
				$prop = $prop->prop->children();
				$ns = $prop->getNamespaces();
				$this->properties($uri)->remove(current($ns), $prop->getName());
			}
		}

		$db->exec('END');

		return;
	}
}
