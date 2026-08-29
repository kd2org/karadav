<?php

namespace KaraDAV;

use stdClass;

class User
{
	// DB columns
	protected ?int $id;
	protected string $login;
	protected string $password;
	/**
	 * Quota (in MiB)
	 */
	protected int $quota;
	protected bool $is_admin;
	protected ?string $session_id = null;

	// Generated properties
	protected string $path;
	protected string $dav_url;
	protected string $avatar_url;

	const DB_PROPERTIES = [
		'id',
		'login',
		'password',
		'quota',
		'is_admin',
		'session_id',
	];

	public function __get(string $key): mixed
	{
		return $this->$key;
	}

	public function __set(string $key, $value): void
	{
		if (!in_array($key, self::DB_PROPERTIES, true)) {
			throw new \LogicException('Cannot modify this property: ' . $key);
		}

		$this->$key = $value;
	}

	public function __isset(string $key): bool
	{
		if (!property_exists($this, $key)) {
			throw new \LogicException('Unknown property: ' . $key);
		}

		return isset($this->$key);
	}

	public function __serialize(): array
	{
		// Only store ID when session is serialized
		return ['id' => $this->id];
	}

	public function __unserialize(array $data): void
	{
		// Reload data from DB
		$this->load(DB::getInstance()->first('SELECT * FROM users WHERE id = ?;', $data['id']));
	}

	protected function init(): void
	{
		$this->path = sprintf(STORAGE_PATH, $this->login);
		$this->path = rtrim($this->path, '/') . '/';

		if (!file_exists($this->path)) {
			$parent = dirname($this->path);

			// Create parent directory with default permissions, if required
			if (!file_exists($parent)) {
				mkdir($parent, 0770, true);
			}

			mkdir($this->path, fileperms($parent), true);
		}

		$this->path = rtrim(realpath($this->path), '/') . '/';
		$this->dav_url = WWW_URL . 'files/' . $this->login . '/';
		$this->avatar_url = WWW_URL . 'avatars/' . substr(md5($this->login), 0, 16);
	}

	public function load($data)
	{
		foreach ($data as $key => $value) {
			// Ignore other properties
			if (!property_exists($this, $key)) {
				continue;
			}

			$this->$key = $value;
		}

		$this->init();
	}

	static public function from(?stdClass $data): ?self
	{
		if ($data === null) {
			return null;
		}

		$user = new self;
		$user->load($data);
		return $user;
	}

	public function quota(bool $with_trash = false): stdClass
	{
		$trash = null;

		if ($this->quota === 0) {
			$total = 0;
			$free = 0;
			$used = 0;
		}
		elseif ($this->quota === -1) {
			$total = @disk_total_space($this->path);
			$free = @disk_free_space($this->path);
			$used = Storage::getDirectorySize($this->path);
		}
		else {
			$used = Storage::getDirectorySize($this->path);
			$total = null;
			$free = null;
		}

		if ($with_trash) {
			$trash = Storage::getDirectorySize($this->path . '/.trash');
		}

		$out = compact('free', 'total', 'used', 'trash');

		// We strip the last 6 numbers, this way we can support 32-bits systems
		// This means we are calculating the quota in MiB, not in bytes,
		// and you might slightly overflow your quota by 1 MiB, but it's fine.
		array_walk($out, fn (&$value) => $value = $value ? (int) round(substr((string) $value, 0, -6) ?: 0) : $value);

		unset($value);

		if ($out['total'] === null) {
			$out['total'] = $this->quota;
		}

		if ($out['free'] === null) {
			$out['free'] = max(0, $out['total'] - $out['used']);
		}

		return (object) $out;
	}

	public function delete()
	{
		Storage::deleteDirectory($this->path);
		DB::getInstance()->run('DELETE FROM users WHERE id = ?;', $this->id);
	}

	public function emptyTrash()
	{
		$path = rtrim($this->path, '/') . '/.trash';
		Storage::deleteDirectory($path);
	}

	protected function assert(bool $ok, string $message, ...$args): void
	{
		if ($ok) {
			return;
		}

		if ($message) {
			throw new UserException(vsprintf($message, $args));
		}
		else {
			throw new \RuntimeException('Assertion failed');
		}
	}

	protected function filterUserData(array $data): stdClass
	{
		$data = array_map('trim', $data);
		$data = array_filter($data, fn($v) => $v !== null);
		$data = array_filter($data, fn($key) => property_exists($this, $key), ARRAY_FILTER_USE_KEY);
		$data = (object) $data;
		$db = DB::getInstance();

		if (isset($data->login)) {
			$data->login = strtolower($data->login);
			$this->assert(strlen($data->login), _('Login is empty'));

			if (!isset($this->login) || $data->login !== $this->login) {
				if (isset($this->id)) {
					$exists = $db->firstColumn('SELECT 1 FROM users WHERE login = ? AND id != ?;', $data->login, $this->id);
				}
				else {
					$exists = $db->firstColumn('SELECT 1 FROM users WHERE login = ?;', $data->login);
				}

				$this->assert(!$exists, _('This login already exists: %s'), $data->login);
			}
		}

		if (isset($data->password) && $data->password === '') {
			unset($data->password);
		}
		elseif (isset($data->password)) {
			$this->assert(strlen($data->password) >= 10, _('Password must have at least 10 characters'));
			$data->password = password_hash(trim($data->password), \PASSWORD_DEFAULT);
		}

		if (isset($data->is_admin)) {
			$data->is_admin = (bool) $data->is_admin;
		}

		if (isset($data->quota)) {
			$data->quota = (int) $data->quota;
		}

		if (LDAP::enabled()) {
			unset($data->is_admin, $data->password, $data->login);
		}

		return $data;
	}

	public function create(array $data): void
	{
		$data = $this->filterUserData($data);
		$this->load($data);

		$this->quota ??= DEFAULT_QUOTA;
		$this->is_admin ??= false;

		$db = DB::getInstance();
		$db->begin();

		$db->run('INSERT OR IGNORE INTO users (login, password, quota, is_admin) VALUES (?, ?, ?, ?);',
			$this->login, $this->password, $this->quota, $this->is_admin ? 1 : 0);

		$this->id = $db->lastInsertRowId();

		$this->init();

		if (file_exists($this->path)) {
			// Just in case the user data directory already exists, index its files
			Storage::indexFiles($this, null);
		}

		$db->commit();
	}

	public function edit(array $data): void
	{
		$data = $this->filterUserData($data);

		$data = (array) $data;

		if (!count($data)) {
			return;
		}

		$update = array_map(fn($k) => $k . ' = ?', array_keys($data));
		$update = implode(', ', $update);
		$params = array_values($data);
		$params[] = $this->id;

		DB::getInstance()->run(sprintf('UPDATE users SET %s WHERE id = ?;', $update), ...$params);

		$old_path = $this->path;

		// Reload object properties
		$this->load($data);

		// move user directory if login has changed
		if ($old_path !== $this->path) {
			rename($old_path, $this->path);
		}
	}

	public function logout(): void
	{
		DB::getInstance()->run('UPDATE users SET session_id = NULL WHERE id = ?;', $this->id);
		session_destroy();
		$_SESSION = [];

		foreach (get_object_vars($this) as $key => $value) {
			unset($this->$key);
		}
	}
}
