<?php

namespace KaraDAV;

use KD2\WebDAV\AbstractStorage;
use KD2\WebDAV\TrashInterface;
use KD2\WebDAV\WOPI;
use KD2\WebDAV\Exception as WebDAV_Exception;

class Storage extends AbstractStorage implements TrashInterface
{
	protected Users $users;
	protected NextCloud $nextcloud;
	protected array $properties = [];

	/**
	 * These file names will be ignored when doing a PUT
	 * as they are garbage, coming from some OS
	 */
	const PUT_IGNORE_PATTERN = '!^~(?:lock\.|^\._)|^(?:\.DS_Store|Thumbs\.db|desktop\.ini)$!';

	public function __construct(Users $users, NextCloud $nextcloud)
	{
		$this->users = $users;
		$this->nextcloud = $nextcloud;
	}

	protected function ensureDirectoryExists(string $path): void
	{
		if (!file_exists($path)) {
			@mkdir($path, @fileperms($this->users->current()->path) ?: 0770, true);
		}
	}

	protected function ensureTrashExists(): void
	{
		$this->ensureDirectoryExists($this->users->current()->path  . '.trash/info');
		$this->ensureDirectoryExists($this->users->current()->path . '.trash/files');
	}

	public function getLock(string $uri, ?string $token = null): ?string
	{
		// It is important to check also for a lock on parent directory as we support depth=1
		$sql = 'SELECT scope FROM locks WHERE user = ? AND (uri = ? OR uri = ?)';
		$params = [$this->users->current()->id, $uri, dirname($uri)];

		if ($token) {
			$sql .= ' AND token = ?';
			$params[] = $token;
		}

		$sql .= ' LIMIT 1';

		return DB::getInstance()->firstColumn($sql, ...$params);
	}

	public function lock(string $uri, string $token, string $scope): void
	{
		DB::getInstance()->run('REPLACE INTO locks VALUES (?, ?, ?, ?, datetime(\'now\', \'+5 minutes\'));', $this->users->current()->id, $uri, $token, $scope);
	}

	public function unlock(string $uri, string $token): void
	{
		DB::getInstance()->run('DELETE FROM locks WHERE user = ? AND uri = ? AND token = ?;', $this->users->current()->id, $uri, $token);
	}

	public function list(string $uri, ?array $properties): iterable
	{
		$dirs = self::glob($this->users->current()->path . $uri, '/*', \GLOB_ONLYDIR);
		$dirs = array_map('basename', $dirs);
		natcasesort($dirs);

		$files = self::glob($this->users->current()->path . $uri, '/*');
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

		if (!is_readable($path)) {
			throw new WebDAV_Exception('You don\'t have the right to read this file', 403);
		}

		// Recommended: Use X-SendFile to make things more efficient
		// see https://tn123.org/mod_xsendfile/
		// or https://www.nginx.com/resources/wiki/start/topics/examples/xsendfile/
		if (ENABLE_XSENDFILE) {
			header('X-SendFile: ' . $path);
			return ['stop' => true];
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
				return is_dir($target) ? null : @mime_content_type($target);
			case 'DAV::resourcetype':
				return is_dir($target) ? 'collection' : '';
			case 'DAV::getlastmodified':
				if (!DISABLE_SLOW_OPERATIONS && !$uri && $depth == 0 && is_dir($target)) {
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
				if (!DISABLE_SLOW_OPERATIONS && !$uri && !$depth) {
					$hash = self::getDirectorySize($target) . self::getDirectoryMTime($target);
				}
				else {
					$hash = filemtime($target) . filesize($target);
				}

				return md5($hash . $target);
			case 'DAV::lastaccessed':
				return new \DateTime('@' . fileatime($target));
			case 'DAV::creationdate':
				return new \DateTime('@' . filectime($target));
			case WebDAV::PROP_DIGEST_MD5:
				if (!is_file($target) || is_dir($target) || !is_readable($target)) {
					return null;
				}

				return md5_file($target);
			// NextCloud stuff
			case NextCloud::PROP_OC_CHECKSUMS:
				// We are not returning OC checksums as this could slow directory listings
				return null;
			case NextCloud::PROP_NC_HAS_PREVIEW:
			case NextCloud::PROP_NC_IS_ENCRYPTED:
				return 'false';
			case NextCloud::PROP_OC_SHARETYPES:
				return WebDAV::EMPTY_PROP_VALUE;
			case NextCloud::PROP_OC_DOWNLOADURL:
				return $this->nextcloud->getDirectDownloadURL($uri, $this->users->current()->login);
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
				// fileId is required by NextCloud desktop client
				return fileinode($target);
			case NextCloud::PROP_OC_PERMISSIONS:
				$permissions = [];

				if (is_writeable($target)) {
					$permissions = [NextCloud::PERM_WRITE, NextCloud::PERM_DELETE, NextCloud::PERM_RENAME, NextCloud::PERM_MOVE, NextCloud::PERM_CREATE_FILES_DIRS];
				}

				if (is_readable($target)) {
					$permissions[] = NextCloud::PERM_READ;
				}

				return implode('', $permissions);
			case NextCloud::PROP_NC_TRASHBIN_FILENAME:
				if (0 !== strpos($uri, '.trash/')) {
					return null;
				}

				return basename($uri);
			case NextCloud::PROP_NC_TRASHBIN_DELETION_TIME:
				if (0 !== strpos($uri, '.trash/')) {
					return null;
				}

				return $this->getTrashInfo(basename($uri))['DeletionDate'] ?? null;
			case NextCloud::PROP_NC_TRASHBIN_ORIGINAL_LOCATION:
				if (0 !== strpos($uri, '.trash/')) {
					return null;
				}

				return $this->getTrashInfo(basename($uri))['Path'] ?? null;
			case 'DAV::quota-available-bytes':
				return null;
			case 'DAV::quota-used-bytes':
				return null;
			case Nextcloud::PROP_OC_SIZE:
				if (!DISABLE_SLOW_OPERATIONS && is_dir($target)) {
					return self::getDirectorySize($target);
				}
				else {
					return filesize($target);
				}
			case WOPI::PROP_FILE_URI:
				$id = gzcompress($uri);
				$id = WOPI::base64_encode_url_safe($id);
				return WWW_URL . 'wopi/files/' . $id;
			default:
				break;
		}

		if (in_array($name, NextCloud::NC_PROPERTIES) || in_array($name, WebDAV::BASIC_PROPERTIES) || in_array($name, WebDAV::EXTENDED_PROPERTIES)) {
			return null;
		}

		return $this->getResourceProperties($uri)->get($name);
	}

	public function propfind(string $uri, ?array $properties, int $depth): ?array
	{
		$target = $this->users->current()->path . $uri;

		if (!file_exists($target)) {
			return null;
		}

		if (null === $properties) {
			$properties = array_merge(WebDAV::BASIC_PROPERTIES, ['DAV::getetag', Nextcloud::PROP_OC_ID]);
		}

		$out = [];

		// Generate a new token for WOPI, and provide also TTL
		if (in_array(WOPI::PROP_TOKEN, $properties)) {
			$out = $this->createWopiToken($uri);
			unset($properties[WOPI::PROP_TOKEN], $properties[WOPI::PROP_TOKEN_TTL]);
		}

		foreach ($properties as $name) {
			$v = $this->get_file_property($uri, $name, $depth);

			if (null !== $v) {
				$out[$name] = $v;
			}
		}

		return $out;
	}

	public function put(string $uri, $pointer, ?string $hash_algo = null, ?string $hash = null): bool
	{
		if (preg_match(self::PUT_IGNORE_PATTERN, basename($uri))) {
			return false;
		}

		$target = $this->users->current()->path . $uri;
		$parent = dirname($target);

		if (is_dir($target)) {
			throw new WebDAV_Exception('Target is a directory', 409);
		}

		$this->ensureDirectoryExists($parent);

		$new = !file_exists($target);

		if ((!$new && !is_writeable($target)) || ($new && !is_writeable($parent))) {
			throw new WebDAV_Exception('You don\'t have the rights to write to this file', 403);
		}

		$delete = false;
		$size = 0;
		$quota = $this->users->quota($this->users->current());

		if ($quota->free <= 0) {
			throw new WebDAV_Exception('Your quota is exhausted', 507);
		}

		$tmp_dir = sprintf(STORAGE_PATH, '_tmp');

		if (!file_exists($tmp_dir)) {
			@mkdir($tmp_dir, 0777, true);
		}

		if (!is_writeable($tmp_dir)) {
			throw new \RuntimeException('Cannot write to temporary storage path: ' . $tmp_dir);
		}

		$tmp_file = $tmp_dir . sha1($target);
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
			throw new WebDAV_Exception('Your quota is exhausted', 507);
		}
		elseif ($hash && $hash_algo == 'MD5' && md5_file($tmp_file) != $hash) {
			@unlink($tmp_file);
			throw new WebDAV_Exception('The data sent does not match the supplied MD5 hash', 400);
		}
		elseif ($hash && $hash_algo == 'SHA1' && sha1_file($tmp_file) != $hash) {
			@unlink($tmp_file);
			throw new WebDAV_Exception('The data sent does not match the supplied SHA1 hash', 400);
		}
		else {
			rename($tmp_file, $target);
		}

		return $new;
	}

	public function delete(string $uri): void
	{
		$target = $this->users->current()->path . $uri;

		if (!file_exists($target)) {
			throw new WebDAV_Exception('Target does not exist', 404);
		}

		// Move to trash
		if (DEFAULT_TRASHBIN_DELAY > 0 && 0 !== strpos($uri, '.trash')) {
			$this->moveToTrash($uri);
			return;
		}

		if (is_dir($target)) {
			self::deleteDirectory($target);
		}
		else {
			@unlink($target);
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
				throw new WebDAV_Exception('Your quota is exhausted', 507);
			}
		}

		if ($overwritten) {
			$this->delete($destination);
		}

		$method = $move ? 'rename' : 'copy';

		if ($method == 'copy' && is_dir($source)) {
			$this->ensureDirectoryExists($parent);

			if (!is_dir($target)) {
				throw new WebDAV_Exception('Target directory could not be created', 409);
			}

			foreach ($iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source), \RecursiveIteratorIterator::SELF_FIRST) as $item)
			{
				if ($item->isDir()) {
					@mkdir($target . DIRECTORY_SEPARATOR . $iterator->getSubPathname());
				} else {
					copy($item, $target . DIRECTORY_SEPARATOR . $iterator->getSubPathname());
				}
				touch($target . DIRECTORY_SEPARATOR . $iterator->getSubPathname(), filemtime($item));
			}
		}
		else {
			$method($source, $target);

			if ($method === 'rename') {
				$this->getResourceProperties($uri)->move($destination);
			}
			else {
				touch($target, filemtime($source));
			}
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
			throw new WebDAV_Exception('Your quota is exhausted', 507);
		}

		$target = $this->users->current()->path . $uri;
		$parent = dirname($target);

		if (file_exists($target)) {
			throw new WebDAV_Exception('There is already a file with that name', 405);
		}

		if (!file_exists($parent)) {
			throw new WebDAV_Exception('The parent directory does not exist', 409);
		}

		if (!is_writeable($parent)) {
			throw new WebDAV_Exception('You don\'t have the right to create a directory here', 403);
		}

		$this->ensureDirectoryExists($target);
	}

	public function touch(string $uri, \DateTimeInterface $datetime): bool
	{
		$target = $this->users->current()->path . $uri;

		if (!file_exists($target)) {
			return false;
		}

		if (!is_writeable($target)) {
			throw new WebDAV_Exception('You don\'t have the right to create a directory here', 403);
		}

		return touch($target, $datetime->getTimestamp());
	}

	public function getResourceProperties(string $uri): Properties
	{
		if (!isset($this->properties[$uri])) {
			$this->properties[$uri] = new Properties($this->users->current()->id, $uri);
		}

		return $this->properties[$uri];
	}

	public function proppatch(string $uri, array $properties): array
	{
		$db = DB::getInstance();

		$db->exec('BEGIN;');

		$out = [];

		foreach ($properties as $name => $prop) {
			if ($prop['action'] == 'set') {
				$this->getResourceProperties($uri)->set($name, $prop['attributes'], $prop['content']);
			}
			else {
				$this->getResourceProperties($uri)->remove($name);
			}

			$out[$name] = 200;
		}

		$db->exec('END');

		return $out;
	}

	static protected function glob(string $path, string $pattern = '', int $flags = 0): array
	{
		$path = preg_replace('/[\*\?\[\]]/', '\\\\$0', $path);
		return glob($path . $pattern, $flags) ?: [];
	}

	static public function getDirectorySize(string $path): int
	{
		$total = 0;
		$path = rtrim($path, '/');
		$path = realpath($path);

		if (!$path || !file_exists($path)) {
			return 0;
		}

		foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $f) {
			$total += $f->getSize();
		}

		return $total;
	}

	static public function deleteDirectory(string $path): void
	{
		$path = rtrim($path, '/');
		$path = realpath($path);

		$dir = opendir($path);

		while ($f = readdir($dir)) {
			// Skip dots
			if ($f === '.' || $f === '..') {
				continue;
			}

			$f = $path . DIRECTORY_SEPARATOR . $f;

			if (is_dir($f)) {
				self::deleteDirectory($f);
				@rmdir($f);
			}
			else {
				@unlink($f);
			}
		}

		closedir($dir);

		rmdir($path);
	}

	static public function getDirectoryMTime(string $path): int
	{
		$last = 0;
		$path = rtrim($path, '/');

		foreach (self::glob($path, '/*', GLOB_NOSORT) as $f) {
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

	protected function createWopiToken(string $uri): ?array
	{
		if (false !== strpos($uri, '../')) {
			return null;
		}

		$user = $this->users->current();
		$ttl = time()+(3600*10);

		$hash = sha1($uri);
		$random = substr(sha1(random_bytes(10)), 0, 10);
		$login = $user->login;

		// Use the user password as a server secret
		$hmac = WebDAV::hmac(compact('ttl', 'random', 'hash', 'login'), $user->password);
		$data = sprintf('%s_%s_%d_%s', $hmac, $random, $ttl, $login);

		$id = gzcompress($uri);
		$id = WOPI::base64_encode_url_safe($id);
		$url = WWW_URL . 'wopi/files/' . $id;

		return [
			WOPI::PROP_WOPI_URL => $url,
			WOPI::PROP_TOKEN => WOPI::base64_encode_url_safe($data),
			WOPI::PROP_TOKEN_TTL => $ttl * 1000,
		];
	}

	public function verifyWopiToken(string $id, string $token): ?array
	{
		$id = WOPI::base64_decode_url_safe($id);
		$uri = gzuncompress($id);

		if (false !== strpos($uri, '../')) {
			return null;
		}

		$token_decode = WOPI::base64_decode_url_safe($token);

		list($user_hmac, $random, $ttl, $login) = explode('_', $token_decode);
		$ttl = (int) $ttl;

		if ($ttl < time()) {
			return null;
		}

		if (!$this->users->setCurrent($login)) {
			return null;
		}

		$hash = sha1($uri);
		$user = $this->users->current();
		$hmac = WebDAV::hmac(compact('ttl', 'random', 'hash', 'login'), $user->password);

		if (!hash_equals($hmac, $user_hmac)) {
			return null;
		}

		$path = $user->path . $uri;

		if (!file_exists($path)) {
			return null;
		}

		$readonly = !is_writeable($path);

		return [
			WOPI::PROP_FILE_URI      => $uri,
			WOPI::PROP_READ_ONLY     => $readonly,
			WOPI::PROP_USER_NAME     => $user->login,
			WOPI::PROP_USER_ID       => md5($user->login),
			WOPI::PROP_USER_AVATAR   => $user->avatar_url,
			WOPI::PROP_LAST_MODIFIED => $this->get_file_property($uri, WOPI::PROP_LAST_MODIFIED, 0),
		];
	}

	/**
	 * @see https://specifications.freedesktop.org/trash-spec/trashspec-latest.html
	 */
	public function moveToTrash(string $uri): void
	{
		$this->ensureTrashExists();

		$name = basename($uri);

		$target = $this->users->current()->path . '.trash/info/' . $name . '.trashinfo';
		$info = sprintf("[Trash Info]\nPath=%s\nDeletionDate=%s\n",
			str_replace('%2F', '/', rawurlencode($uri)),
			date(DATE_RFC3339)
		);

		file_put_contents($target, $info);

		$this->move($uri, '.trash/files/' . $name);
	}

	public function restoreFromTrash(string $uri): void
	{
		$src = $this->users->current()->path . '.trash/files/' . $uri;

		if (!file_exists($src)) {
			return;
		}

		$info = $this->getTrashInfo($uri);
		$dest = $info['Path'] ?? $uri;

		if ($info) {
			$this->delete('.trash/info/' . $uri . '.trashinfo');
		}

		$this->move('.trash/files/' . $uri, $dest);
	}

	public function emptyTrash(): void
	{
		$this->delete('.trash');
		$this->ensureTrashExists();
	}

	public function deleteFromTrash(string $uri): void
	{
		$this->delete('.trash/files/' . $uri);
		$this->delete('.trash/info/' . $uri . '.trashinfo');
	}

	protected function getTrashInfo(string $uri): ?array
	{
		$info_file = $this->users->current()->path . '.trash/info/' . $uri . '.trashinfo';
		$info = @parse_ini_file($info_file, false, INI_SCANNER_RAW);

		if (!isset($info['Path'], $info['DeletionDate'])) {
			return null;
		}

		$info['Path'] = rawurldecode($info['Path']);
		$info['DeletionDate'] = strtotime($info['DeletionDate']);
		$info['InfoFilePath'] = $info_file;
		return $info;
	}

	public function pruneTrash(int $delete_before_timestamp): int
	{
		$this->ensureTrashExists();

		$info_dir = $this->users->current()->path . '.trash/info';
		$count = 0;

		foreach (glob($info_dir . '/*.trashinfo') as $file) {
			$name = basename($file);
			$name = str_replace('.trashinfo', '', $name);
			$info = $this->getTrashInfo($name);

			if (!$info) {
				continue;
			}

			if ($info['DeletionDate'] < $delete_before_timestamp) {
				$this->delete('.trash/files/' . $name);
				$this->delete('.trash/info/' . $name . '.trashinfo');
				$count++;
			}
		}

		return $count;
	}

	public function listTrashFiles(): iterable
	{
		$this->pruneTrash(time() - DEFAULT_TRASHBIN_DELAY);

		$this->ensureTrashExists();
		$info_dir = $this->users->current()->path . '.trash/info';
		$files_dir = $this->users->current()->path . '.trash/files';

		foreach (glob($info_dir . '/*.trashinfo') as $file) {
			$name = basename($file);
			$name = str_replace('.trashinfo', '', $name);
			$target = $files_dir . '/' . $name;

			if (!file_exists($target)) {
				@unlink($file);
				continue;
			}

			$info = $this->getTrashInfo($name);

			$is_dir = is_dir($target);
			$size = $is_dir ? self::getDirectorySize($target) : filesize($target);

			yield $name => [
				NextCloud::PROP_NC_TRASHBIN_FILENAME => $name,
				NextCloud::PROP_NC_TRASHBIN_ORIGINAL_LOCATION => $info['Path'],
				NextCloud::PROP_NC_TRASHBIN_DELETION_TIME => $info['DeletionDate'],
				NextCloud::PROP_OC_SIZE => $size,
				NextCloud::PROP_OC_ID => fileinode($file),
				'DAV::getcontentlength' => $size,
				'DAV::getcontenttype' => $is_dir ? null : @mime_content_type($target),
				'DAV::resourcetype' => $is_dir ? 'collection' : '',
			];
		}
	}
}
