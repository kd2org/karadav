<?php

namespace KaraDAV;

class Properties
{
	protected string $user;
	protected string $uri;
	protected array $ns = [];
	protected array $xml = [];

	protected bool $loaded = false;

	public function __construct(string $user, string $uri) {
		$this->user = $user;
		$this->uri = $uri;
	}

	public function load(): void
	{
		if (!$this->loaded) {
			$this->loaded = true;
			$list = DB::getInstance()->iterate('SELECT ns, xml FROM properties WHERE user = ? AND uri = ?;', $this->user, $this->uri);

			foreach ($list as $row) {
				$this->ns = array_merge($this->ns, json_decode($row->ns, true));
				$this->xml[] = $row->xml;
			}
		}
	}

	public function xml(): string
	{
		$this->load();
		return implode("\n", $this->xml);
	}

	public function ns(): array
	{
		$this->load();

		return $this->ns;
	}

	public function set(string $ns_url, string $name, array $ns, string $xml)
	{
		$ns = json_encode($ns);

		DB::getInstance()->run('REPLACE INTO properties (user, uri, ns_url, name, ns, xml) VALUES (?, ?, ?, ?, ?, ?);', $this->user, $this->uri, $ns_url, $name, $ns, $xml);
	}

	public function remove(string $ns_url, string $name)
	{
		DB::getInstance()->run('DELETE FROM properties WHERE user = ? AND uri = ? AND ns_url = ? AND name = ?;', $this->user, $this->uri, $ns_url, $name);
	}

	public function clear()
	{
		DB::getInstance()->run('DELETE FROM properties WHERE user = ? AND uri = ?;', $this->user, $this->uri);
	}
}
