<?php

namespace KaraDAV;

class DB extends \SQLite3
{
	const VERSION = 3;

	static protected $instance;

	static public function getInstance(): self
	{
		if (!isset(self::$instance)) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function __construct()
	{
		if (isset(self::$instance)) {
			throw new \LogicException('Already started');
		}

		parent::__construct(DB_FILE);

		$this->busyTimeout(10 * 1000);

		$mode = strtoupper(DB_JOURNAL_MODE);
		$set_mode = $this->querySingle('PRAGMA journal_mode;');
		$set_mode = strtoupper($set_mode);

		// Only set journal mode if it is different, as setting it every time may be slow
		if ($set_mode !== $mode) {
			// WAL = performance enhancement
			// see https://www.cs.utexas.edu/~jaya/slides/apsys17-sqlite-slides.pdf
			// https://ericdraken.com/sqlite-performance-testing/
			$this->exec(sprintf(
				'PRAGMA journal_mode = %s; PRAGMA synchronous = NORMAL; PRAGMA journal_size_limit = %d;',
				$mode,
				32 * 1024 * 1024
			));
		}
	}

	public function run(string $sql, ...$params)
	{
		$st = $this->prepare($sql);

		foreach ($params as $key => $value) {
			$st->bindValue(is_int($key) ? $key+1 : ':' . $key, $value);
		}

		return $st->execute();
	}

	public function iterate(string $sql, ...$params): iterable
	{
		$res = $this->run($sql, ...$params);
		while ($row = $res->fetchArray(\SQLITE3_ASSOC)) {
			yield (object)$row;
		}
	}

	public function first(string $sql, ...$params)
	{
		$row = $this->run($sql, ...$params)->fetchArray(\SQLITE3_ASSOC);
		return $row ? (object) $row : null;
	}

	public function firstColumn(string $sql, ...$params)
	{
		return $this->run($sql, ...$params)->fetchArray(\SQLITE3_NUM)[0] ?? null;
	}

	public function getPathLikeExpression(string $path)
	{
		return str_replace(['?', '%'], ['\\?', '\\%'], $path) . '/%';
	}

	public function upgradeVersion(): void
	{
		$db_version = $this->firstColumn('PRAGMA user_version;');

		if ($db_version === self::VERSION) {
			return;
		}

		if ($db_version < 1) {
			$this->exec('BEGIN;');
			$this->exec(file_get_contents(ROOT . '/sql/migrate_0001.sql'));

			$users = new Users;
			$users->indexAllFiles();
			$this->exec('END;');
		}

		// Re-index to create directories in cache
		if ($db_version < 2) {
			$this->exec('BEGIN;');
			$users = new Users;
			$users->indexAllFiles();
			$this->exec('PRAGMA user_version = 2;');
			$this->exec('END;');
		}

		if ($db_version < 3) {
			$this->exec('BEGIN;');
			$this->exec(file_get_contents(ROOT . '/sql/migrate_0003.sql'));
			$this->exec('END;');
		}

		$db->exec('PRAGMA user_version = ' . self::VERSION . ';');
	}
}
