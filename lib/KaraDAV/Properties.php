<?php

namespace KaraDAV;

class Properties
{
	protected string $user;
	protected string $uri;
	protected array $properties = [];

	protected bool $loaded = false;

	public function __construct(string $user, string $uri) {
		$this->user = $user;
		$this->uri = $uri;
	}

	public function load(): void
	{
		if (!$this->loaded) {
			$this->loaded = true;
			$list = DB::getInstance()->iterate('SELECT name, attributes, xml FROM properties WHERE user = ? AND uri = ?;', $this->user, $this->uri);

			foreach ($list as $row) {
				$this->properties[$row->name] = [
					'attributes' => $row->attributes ? json_decode($row->attributes) : null,
					'xml' => $row->xml,
				];
			}
		}
	}

	public function all(): array
	{
		$this->load();
		return $this->properties;
	}

	public function get(string $name): ?array
	{
		$this->load();
		return $this->properties[$name] ?? null;
	}

	public function set(string $name, ?array $attributes, ?string $xml)
	{
		DB::getInstance()->run('REPLACE INTO properties (user, uri, name, attributes, xml) VALUES (?, ?, ?, ?, ?);', $this->user, $this->uri, $name, $attributes ? json_encode($attributes) : null, $xml);
	}

	public function remove(string $name)
	{
		DB::getInstance()->run('DELETE FROM properties WHERE user = ? AND uri = ? AND name = ?;', $this->user, $this->uri, $name);
	}

	public function clear()
	{
		DB::getInstance()->run('DELETE FROM properties WHERE user = ? AND uri = ?;', $this->user, $this->uri);
	}

	public function move(string $uri)
	{
		DB::getInstance()->run('UPDATE properties SET uri = ? WHERE user = ? AND uri = ?;', $uri, $this->user, $this->uri);
	}
}
