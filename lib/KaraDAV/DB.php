<?php

namespace KaraDAV;

class DB extends \SQLite3
{
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
}
