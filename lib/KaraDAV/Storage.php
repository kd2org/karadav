<?php

namespace KaraDAV;

use KD2\WebDAV\AbstractStorage;
use KD2\WebDAV\Server as WebDAV_Server;
use KD2\WebDAV\WOPI;
use KD2\WebDAV\Exception as WebDAV_Exception;

class Storage extends AbstractStorage
{
	protected Users $users;

	/**
	 * These file names will be ignored when doing a PUT
	 * as they are garbage, coming from some OS
	 */
	const PUT_IGNORE_PATTERN = '!^~(?:lock\.|^\._)|^(?:\.DS_Store|Thumbs\.db|desktop\.ini)$!';

	public function __construct(Users $users)
	{
		$this->users = $users;
	}

	public function getLock(string $uri, ?string $token = null): ?string
	{
		// It is important to check also for a lock on parent directory as we support depth=1
		$sql = 'SELECT scope FROM locks WHERE user = ? AND (uri = ? OR uri = ?)';
		$params = [$this->users->current()->login, $uri, dirname($uri)];

		if ($token) {
			$sql .= ' AND token = ?';
			$params[] = $token;
		}

		$sql .= ' LIMIT 1';

		return DB::getInstance()->firstColumn($sql, ...$params);
	}

	public function lock(string $uri, string $token, string $scope): void
	{
		DB::getInstance()->run('REPLACE INTO locks VALUES (?, ?, ?, ?, datetime(\'now\', \'+5 minutes\'));', $this->users->current()->login, $uri, $token, $scope);
	}

	public function unlock(string $uri, string $token): void
	{
		DB::getInstance()->run('DELETE FROM locks WHERE user = ? AND uri = ? AND token = ?;', $this->users->current()->login, $uri, $token);
	}

	public function list(string $uri, ?array $properties): iterable
	{
		$dirs = glob($this->users->current()->path . $uri . '/*', \GLOB_ONLYDIR);
		$dirs = array_map('basename', $dirs);
		natcasesort($dirs);

		$files = glob($this->users->current()->path . $uri . '/*');
		$files = array_map('basename', $files);
		$files = array_diff($files, $dirs);
		natcasesort($files);

		$files = array_flip(array_merge($dirs, $files));
		$files = array_map(fn($a) => null, $files);
		return $files;
	}

	public function get(string $uri): ?array
	{
		$path = $this->users->current()->path . $uri;

		if (!file_exists($path)) {
			return null;
		}

		// Recommended: Use X-SendFile to make things more efficient
		// see https://tn123.org/mod_xsendfile/
		// or https://www.nginx.com/resources/wiki/start/topics/examples/xsendfile/
		if (ENABLE_XSENDFILE) {
			header('X-SendFile: ' . $path);
			exit;
		}

		return ['path' => $path];
	}

	public function exists(string $uri): bool
	{
		return file_exists($this->users->current()->path . $uri);
	}

	public function get_file_property(string $uri, string $name, int $depth)
	{
		$target = $this->users->current()->path . $uri;

		switch ($name) {
			case 'DAV::getcontentlength':
				return is_dir($target) ? null : filesize($target);
			case 'DAV::getcontenttype':
				// ownCloud app crashes if mimetype is provided for a directory
				// https://github.com/owncloud/android/issues/3768
				return is_dir($target) ? null : mime_content_type($target);
			case 'DAV::resourcetype':
				return is_dir($target) ? 'collection' : '';
			case 'DAV::getlastmodified':
				if (!$uri && $depth == 0 && is_dir($target)) {
					$mtime = self::getDirectoryMTime($target);
				}
				else {
					$mtime = filemtime($target);
				}

				if (!$mtime) {
					return null;
				}

				return new \DateTime('@' . $mtime);
			case 'DAV::displayname':
				return basename($target);
			case 'DAV::ishidden':
				return basename($target)[0] == '.';
			case 'DAV::getetag':
				if (!$uri && !$depth) {
					$hash = self::getDirectorySize($target) . self::getDirectoryMTime($target);
				}
				else {
					$hash = filemtime($target) . filesize($target);
				}

				return md5($hash . $target);
			case 'DAV::lastaccessed':
				return new \DateTime('@' . fileatime($target));
			case 'DAV::creationdate':
				// The ownCloud Android app doesn't like formatted dates, it makes it crash.
				if (false !== stripos($_SERVER['HTTP_USER_AGENT'] ?? '', 'owncloud')) {
					return filectime($target);
				}

				return new \DateTime('@' . filectime($target));
			case WebDAV::PROP_DIGEST_MD5:
				if (!is_file($target)) {
					return null;
				}

				return md5_file($target);
			// NextCloud stuff
			case NextCloud::PROP_NC_HAS_PREVIEW:
			case NextCloud::PROP_NC_IS_ENCRYPTED:
				return 'false';
			case NextCloud::PROP_OC_SHARETYPES:
				return WebDAV::EMPTY_PROP_VALUE;
			case Nextcloud::PROP_NC_RICH_WORKSPACE:
				if (!is_dir($target)) {
					return '';
				}

				$files = ['README.md', 'Readme.md', 'readme.md'];

				foreach ($files as $f) {
					if (file_exists($target . '/' . $f)) {
						return file_get_contents($target . '/' . $f);
					}
				}

				return '';
			case NextCloud::PROP_OC_ID:
				$username = $this->users->current()->login;
				return NextCloud::getDirectID($username, $uri);
			case NextCloud::PROP_OC_PERMISSIONS:
				return implode('', [NextCloud::PERM_READ, NextCloud::PERM_WRITE, NextCloud::PERM_CREATE, NextCloud::PERM_DELETE, NextCloud::PERM_RENAME_MOVE]);
			case 'DAV::quota-available-bytes':
				return null;
				return -3;
			case 'DAV::quota-used-bytes':
				return null;
			case Nextcloud::PROP_OC_SIZE:
				if (is_dir($target)) {
					return self::getDirectorySize($target);
				}
				else {
					return filesize($target);
				}
			case WOPI::PROP_FILE_URL:
				$id = gzcompress($uri);
				$id = WOPI::base64_encode_url_safe($id);
				return WWW_URL . 'wopi/files/' . $id;
			case WOPI::PROP_TOKEN:
				$p = $this->getResourceProperties($uri);
				$token = $p->get($name)['xml'] ?? null;

				// Check if token has expired, if so, then renew it
				if ($token) {
					$expiry = $p->get(WOPI::PROP_TOKEN_TTL);

					if ($expiry < time() * 1000) {
						$token = null;
					}
				}

				// Create token and store it
				if (!$token) {
					$token = $this->createWopiToken($uri);
					$p->set(WOPI::PROP_TOKEN, null, $token);
					$p->set(WOPI::PROP_TOKEN_TTL, null, (time()+3600*10)*1000);
				}

				return $token;
			default:
				break;
		}

		if (in_array($name, NextCloud::NC_PROPERTIES) || in_array($name, WebDAV::BASIC_PROPERTIES) || in_array($name, WebDAV::EXTENDED_PROPERTIES)) {
			return null;
		}

		return $this->getResourceProperties($uri)->get($name);
	}

	protected function createWopiToken(string $uri)
	{
		$login = $this->users->current()->login;
		$bytes = substr(md5(random_bytes(10)), 0, 10);
		return WOPI::base64_encode_url_safe(sprintf('%s:%s', sha1($login . $uri . $bytes), $bytes));
	}

	public function properties(string $uri, ?array $properties, int $depth): ?array
	{
		$target = $this->users->current()->path . $uri;

		if (!file_exists($target)) {
			return null;
		}

		if (null === $properties) {
			$properties = array_merge(WebDAV::BASIC_PROPERTIES, ['DAV::getetag', Nextcloud::PROP_OC_ID]);
		}

		$out = [];

		foreach ($properties as $name) {
			$v = $this->get_file_property($uri, $name, $depth);

			if (null !== $v) {
				$out[$name] = $v;
			}
		}

		return $out;
	}

	public function put(string $uri, $pointer, ?string $hash, ?int $mtime): bool
	{
		if (preg_match(self::PUT_IGNORE_PATTERN, basename($uri))) {
			return false;
		}

		$target = $this->users->current()->path . $uri;
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
		$quota = $this->users->quota($this->users->current());

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
		elseif ($hash && md5_file($tmp_file) != $hash) {
			@unlink($tmp_file);
			throw new WebDAV_Exception('The data sent does not match the supplied MD5 hash', 400);
		}
		else {
			rename($tmp_file, $target);
		}

		if ($mtime) {
			@touch($target, $mtime);
		}

		return $new;
	}

	public function delete(string $uri): void
	{
		$target = $this->users->current()->path . $uri;

		if (!file_exists($target)) {
			throw new WebDAV_Exception('Target does not exist', 404);
		}

		if (is_dir($target)) {
			foreach (glob($target . '/*') as $file) {
				$this->delete(substr($file, strlen($this->users->current()->path)));
			}

			rmdir($target);
		}
		else {
			unlink($target);
		}

		$this->getResourceProperties($uri)->clear();
	}

	public function copymove(bool $move, string $uri, string $destination): bool
	{
		$source = $this->users->current()->path . $uri;
		$target = $this->users->current()->path . $destination;
		$parent = dirname($target);

		if (!file_exists($source)) {
			throw new WebDAV_Exception('File not found', 404);
		}

		$overwritten = file_exists($target);

		if (!is_dir($parent)) {
			throw new WebDAV_Exception('Target parent directory does not exist', 409);
		}

		if (false === $move) {
			$quota = $this->users->quota($this->users->current());

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

			$this->getResourceProperties($uri)->move($destination);
		}

		return $overwritten;
	}

	public function copy(string $uri, string $destination): bool
	{
		return $this->copymove(false, $uri, $destination);
	}

	public function move(string $uri, string $destination): bool
	{
		return $this->copymove(true, $uri, $destination);
	}

	public function mkcol(string $uri): void
	{
		if (!$this->users->current()->quota) {
			throw new WebDAV_Exception('Your quota is exhausted', 403);
		}

		$target = $this->users->current()->path . $uri;
		$parent = dirname($target);

		if (file_exists($target)) {
			throw new WebDAV_Exception('There is already a file with that name', 405);
		}

		if (!file_exists($parent)) {
			throw new WebDAV_Exception('The parent directory does not exist', 409);
		}

		mkdir($target, 0770);
	}


	public function getResourceProperties(string $uri): Properties
	{
		if (!isset($this->properties[$uri])) {
			$this->properties[$uri] = new Properties($this->users->current()->login, $uri);
		}

		return $this->properties[$uri];
	}

	public function setProperties(string $uri, string $body): void
	{
		$properties = WebDAV::parsePropPatch($body);

		if (!count($properties)) {
			return;
		}

		$db = DB::getInstance();

		$db->exec('BEGIN;');

		foreach ($properties as $name => $prop) {
			if ($prop['action'] == 'set') {
				$this->getResourceProperties($uri)->set($name, $prop['attributes'], $prop['content']);
			}
			else {
				$this->getResourceProperties($uri)->remove($name);
			}
		}

		$db->exec('END');

		return;
	}

	static public function getDirectorySize(string $path): int
	{
		$total = 0;
		$path = rtrim($path, '/');

		foreach (glob($path . '/*', GLOB_NOSORT) as $f) {
			if (is_dir($f)) {
				$total += self::getDirectorySize($f);
			}
			else {
				$total += filesize($f);
			}
		}

		return $total;
	}

	static public function deleteDirectory(string $path): void
	{
		foreach (glob($path . '/*', GLOB_NOSORT) as $f) {
			if (is_dir($f)) {
				self::deleteDirectory($f);
				@rmdir($f);
			}
			else {
				@unlink($f);
			}
		}

		@rmdir($path);
	}

	static public function getDirectoryMTime(string $path): int
	{
		$last = 0;
		$path = rtrim($path, '/');

		foreach (glob($path . '/*', GLOB_NOSORT) as $f) {
			if (is_dir($f)) {
				$m = self::getDirectoryMTime($f);

				if ($m > $last) {
					$last = $m;
				}
			}

			$m = filemtime($f);

			if ($m > $last) {
				$last = $m;
			}
		}

		return $last;
	}

	public function getWopiURI(string $id, string $token): ?string
	{
		$id = WOPI::base64_decode_url_safe($id);
		$uri = gzuncompress($id);
		$token_decode = WOPI::base64_decode_url_safe($token);
		$hash = strtok($token_decode, ':');
		$bytes = strtok(false);

		$r = DB::getInstance()->first('SELECT user, uri FROM properties WHERE name = ? AND xml = ?;', WOPI::PROP_TOKEN, $token);

		if (!$r) {
			return null;
		}

		if (!hash_equals(sha1($r->user . $r->uri . $bytes), $hash)) {
			return null;
		}

		if (!$this->users->setCurrent($r->user)) {
			return null;
		}

		return $r->uri;
	}
}
