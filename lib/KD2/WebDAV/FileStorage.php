<?php

namespace KD2\WebDAV;

/**
 * This is mostly an example of an implementation of WebDAV
 * to serve files from the local filesystem.
 *
 * Demo:
 *
 * $fs = new WebDAV_FS('/home/user/files', '/home/user/.cache/davlocks.sqlite');
 * $fs->route('/files/');
 */
class FileStorage extends AbstractStorage
{
	protected string $path;
	protected ?\SQLite3 $db;

	const XSENDFILE = false;

	/**
	 * These file names will be ignored when doing a PUT
	 * as they are garbage, coming from some OS
	 * @see https://raw.githubusercontent.com/owncloud/client/master/sync-exclude.lst
	 */
	const PUT_IGNORE_PATTERN = '!^~|~$|^~.*tmp$|^Thumbs\.db$|^desktop\.ini$|\.unison$|^My Saved Places'
		. '|^\.(lock\.|_|DS_Store|DocumentRevisions|directory|Trash|Temp|fseventsd|apdisk|synkron|sync|symform|fuse|nfs)!i';

	public function __construct(string $path, ?string $lockdb = null)
	{
		$this->path = rtrim($path, '/') . '/';
		$lockdb_init = null !== $lockdb && !file_exists($lockdb);
		$this->db = $lockdb ? new \SQLite3($lockdb) : null;

		if ($lockdb_init) {
			$this->db->exec('CREATE TABLE locks (
				uri TEXT NOT NULL,
				token TEXT NOT NULL,
				scope TEXT NOT NULL,
				expiry TEXT NOT NULL
			);

			CREATE INDEX locks_uri ON locks (uri);

			CREATE UNIQUE INDEX locks_unique ON locks (uri, token);');
		}
	}

	protected function db(string $sql, ...$params)
	{
		$st = $this->db->prepare($sql);

		foreach ($params as $key => $value) {
			$st->bindValue(is_int($key) ? $key + 1 : ':' . $key, $value);
		}

		return $st->execute();
	}

	protected function getLock(string $uri, ?string $token = null): ?string
	{
		// It is important to check also for a lock on parent directory as we support depth=1
		$sql = 'SELECT scope FROM locks WHERE (uri = ? OR uri = ?)';
		$params = [$uri, dirname($uri)];

		if ($token) {
			$sql .= ' AND token = ?';
			$params[] = $token;
		}

		$sql .= ' LIMIT 1';

		$r = $this->db($sql, ...$params)->fetchArray(\SQLITE3_NUM);
		$r = $r[0] ?? null;

		return $r;
	}

	protected function lock(string $uri, string $token, string $scope): void
	{
		$this->db('REPLACE INTO locks VALUES (?, ?, ?, datetime(\'now\', \'+5 minutes\'));', $uri, $token, $scope);
	}

	protected function unlock(string $uri, string $token): void
	{
		$this->db('DELETE FROM locks WHERE uri = ? AND token = ?;', $uri, $token);
	}

	protected function list(string $uri, array $properties): iterable
	{
		foreach (glob($this->path . $uri . '/*') as $file) {
			yield basename($file) => null;
		}
	}

	protected function get(string $uri): ?array
	{
		if (!file_exists($this->path . $uri)) {
			return null;
		}

		// Recommended: Use X-SendFile to make things more efficient
		// see https://tn123.org/mod_xsendfile/
		// or https://www.nginx.com/resources/wiki/start/topics/examples/xsendfile/
		if (self::XSENDFILE) {
			header('X-SendFile: ' . $this->path . $uri);
			exit;
		}

		//return ['content' => file_get_contents($this->path . $uri)];
		//return ['resource' => fopen($this->path . $uri, 'r')];
		return ['path' => $this->path . $uri];
	}

	protected function exists(string $uri): bool
	{
		return file_exists($this->path . $uri);
	}

	protected function properties(string $uri, bool $all = false): ?array
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

	protected function put(string $uri, $pointer, ?string $hash_algo, ?string $hash): bool
	{
		if (preg_match(self::PUT_IGNORE_PATTERN, basename($uri))) {
			return false;
		}

		$target = $this->path . $uri;
		$parent = dirname($target);

		if (is_dir($target)) {
			throw new Exception('Target is a directory', 409);
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
			throw new Exception('Target does not exist', 404);
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
			throw new Exception('File not found', 404);
		}

		$overwritten = file_exists($target);

		if (!is_dir($parent)) {
			throw new Exception('Target parent directory does not exist', 409);
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
			throw new Exception('There is already a file with that name', 405);
		}

		if (!file_exists($parent)) {
			throw new Exception('The parent directory does not exist', 409);
		}

		mkdir($target, 0770);
	}
}
