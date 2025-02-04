<?php

namespace KaraDAV;

use stdClass;

class Users
{
	protected ?stdClass $current = null;

	public function __construct()
	{
		if (!session_id()) {
			// Protect the cookie : CSRF/JS stealing the cookie
			session_set_cookie_params(['samesite' => 'Lax', 'httponly' => true]);
		}
	}

	static public function generatePassword(): string
	{
		$password = base64_encode(random_bytes(16));
		$password = substr(str_replace(['/', '+', '='], '', $password), 0, 16);
		return $password;
	}

	public function list(): array
	{
		return array_map([$this, 'makeUserObjectGreatAgain'], iterator_to_array(DB::getInstance()->iterate('SELECT * FROM users ORDER BY login;')));
	}

	public function fetch(string $login): ?stdClass
	{
		return DB::getInstance()->first('SELECT * FROM users WHERE login = ?;', $login);
	}

	public function get(string $login): ?stdClass
	{
		$user = $this->fetch($login);

		if (!$user && LDAP::enabled() && LDAP::checkUser($login)) {
			$this->create($login, self::generatePassword(), DEFAULT_QUOTA);
			$user = $this->fetch($login);

			if (!$user) {
				throw new \LogicException('User does not exist after getting created?');
			}

			$user->is_admin = LDAP::checkIsAdmin($login);
		}
		elseif (!$user) {
			return null;
		}

		return $this->makeUserObjectGreatAgain($user);
	}

	public function getById(int $id): ?stdClass
	{
		$user = DB::getInstance()->first('SELECT * FROM users WHERE id = ?;', $id);
		return $this->makeUserObjectGreatAgain($user);
	}

	protected function makeUserObjectGreatAgain(?stdClass $user): ?stdClass
	{
		if ($user) {
			$user->path = sprintf(STORAGE_PATH, $user->login);
			$user->path = rtrim($user->path, '/') . '/';

			if (!file_exists($user->path)) {
				$parent = dirname($user->path);

				// Create parent directory with default permissions, if required
				if (!file_exists($parent)) {
					mkdir($parent, 0770, true);
				}

				mkdir($user->path, fileperms($parent), true);
			}

			$user->path = rtrim(realpath($user->path), '/') . '/';

			$user->dav_url = WWW_URL . 'files/' . $user->login . '/';
			$user->avatar_url = WWW_URL . 'avatars/' . substr(md5($user->login), 0, 16);
		}

		return $user;
	}

	public function create(string $login, string $password, int $quota = DEFAULT_QUOTA, bool $is_admin = false)
	{
		$login = strtolower(trim($login));
		$hash = password_hash(trim($password), null);
		DB::getInstance()->run('INSERT OR IGNORE INTO users (login, password, quota, is_admin) VALUES (?, ?, ?, ?);',
			$login, $hash, $quota * 1024 * 1024, $is_admin ? 1 : 0);
	}

	public function edit(int $id, array $data)
	{
		$old_user = $this->getById($id);

		if (!$old_user) {
			throw new \LogicException('User does not exist: ' . $id);
		}

		$params = [];
		$new_login = null;

		if (!empty($data['password'])) {
			$params['password'] = password_hash(trim($data['password']), null);
		}

		if (!empty($data['login'])) {
			$new_login = strtolower(trim($data['login']));

			if ($new_login !== $old_user->login) {
				$exists = $this->get($new_login);
				$params['login'] = $new_login;

				if ($exists) {
					throw new \LogicException('User login already exists: ' . $params['login']);
				}
			}
			else {
				$new_login = null;
			}
		}

		if (isset($data['quota'])) {
			$params['quota'] = $data['quota'] <= 0 ? (int) $data['quota'] : (int) $data['quota'] * 1024 * 1024;
		}

		if (isset($data['is_admin'])) {
			$params['is_admin'] = (int) $data['is_admin'];
		}

		$update = array_map(fn($k) => $k . ' = ?', array_keys($params));
		$update = implode(', ', $update);
		$params = array_values($params);
		$params[] = $id;

		DB::getInstance()->run(sprintf('UPDATE users SET %s WHERE id = ?;', $update), ...$params);

		if ($new_login) {
			$path = sprintf(STORAGE_PATH, $new_login);
			$path = rtrim($path, '/') . '/';

			rename($old_user->path, $path);
		}
	}

	public function current(): ?stdClass
	{
		if ($this->current) {
			return $this->current;
		}

		if (isset($_COOKIE[session_name()]) && !isset($_SESSION)) {
			session_start();
		}

		$this->current = $this->makeUserObjectGreatAgain($_SESSION['user'] ?? null);

		return $this->current;
	}

	public function setCurrent(string $login): bool
	{
		$user = $this->get($login);

		if (!$user) {
			return false;
		}

		$this->current = $user;
		return true;
	}

	public function login(?string $login, ?string $password): ?stdClass
	{
		$login = null !== $login ? strtolower(trim($login)) : null;

		// Check if user already has a session
		$current = $this->current();

		if ($current && (!$login || $current->login == $login)) {
			return $current;
		}

		if (!$login || !$password) {
			return null;
		}

		// If not, try to login
		$ok = false;

		if (LDAP::enabled()) {
			if (!LDAP::checkPassword($login, $password)) {
				return null;
			}

			$ok = true;
		}
		elseif (AUTH_CALLBACK) {
			$r = call_user_func(AUTH_CALLBACK, $login, $password);

			if ($r !== true) {
				return null;
			}

			$ok = true;
		}

		$user = $this->get($login);

		if (!$user && !$ok) {
			return null;
		}
		elseif (!$user && $ok) {
			$this->create($login, random_bytes(10));
			$user = $this->get($login);
		}

		if (!$ok && !password_verify(trim($password), $user->password)) {
			return null;
		}

		@session_start();
		$_SESSION['user'] = $user;

		return $user;
	}

	public function logout(): void
	{
		session_destroy();
	}

	public function appSessionCreate(?string $token = null): ?stdClass
	{
		$current = $this->current();

		if (!$current) {
			return null;
		}

		if (null !== $token) {
			if (!ctype_alnum($token) || strlen($token) > 100) {
				return null;
			}

			$expiry = '+10 minutes';
			$hash = null;
			$password = null;
		}
		else {
			$expiry = '+1 month';
			$password = $this->generatePassword();

			// The app password contains the user password hash
			// this way we can invalidate all sessions if we change
			// the user password
			$hash = password_hash($password . $current->password, null);
			$token = $this->generatePassword();
		}

		DB::getInstance()->run(
			'INSERT OR IGNORE INTO app_sessions (user, password, expiry, token) VALUES (?, ?, datetime(\'now\', ?), ?);',
			$current->id, $hash, $expiry, $token);

		return (object) compact('password', 'token');
	}

	public function appSessionCreateAndGetRedirectURL(): string
	{
		$session = $this->appSessionCreate();
		$current = $this->current();

		return sprintf(NextCloud::AUTH_REDIRECT_URL, WWW_URL, $current->login, $session->token . ':' . $session->password);
	}

	public function appSessionValidateToken(string $token): ?stdClass
	{
		$session = DB::getInstance()->first('SELECT * FROM app_sessions WHERE token = ?;', $token);

		if (!$session) {
			return null;
		}

		// the token can only be exchanged against a session once,
		// so we set a password and remove the token
		$session->password = $this->generatePassword();

		// The app password contains the user password hash
		// this way we can invalidate all sessions if we change
		// the user password
		$user = $this->getById($session->user);
		$hash = password_hash($session->password . $user->password, null);
		$session->token = self::generatePassword();
		$session->password = $session->token . ':' . $session->password;

		DB::getInstance()->run('UPDATE app_sessions
			SET token = ?, password = ?, expiry = datetime(\'now\', \'+1 month\')
			WHERE token = ?;',
			$session->token, $hash, $token);

		$session->user = $user;
		return $session;
	}

	public function appSessionLogin(?string $login, ?string $app_password): ?stdClass
	{
		// From time to time, clean up old sessions
		if (time() % 100 == 0) {
			DB::getInstance()->run('DELETE FROM app_sessions WHERE expiry < datetime();');
		}

		if (($user = $this->current()) && $login == $user->login) {
			return $user;
		}

		if (!$app_password) {
			return null;
		}

		$token = strtok($app_password, ':');
		$password = strtok('');

		$user = DB::getInstance()->first('SELECT s.password AS app_hash, u.*
			FROM app_sessions s INNER JOIN users u ON u.id = s.user
			WHERE s.token = ? AND s.expiry > datetime();', $token);

		if (!$user) {
			return null;
		}

		$password = trim($password) . $user->password;

		if (!password_verify($password, $user->app_hash)) {
			return null;
		}

		@session_start();
		$_SESSION['user'] = $user;

		return $this->makeUserObjectGreatAgain($user);
	}

	public function quota(?stdClass $user = null, bool $with_trash = false): stdClass
	{
		$user ??= $this->current();
		$used = $total = $free = 0;
		$trash = null;

		if ($user) {
			if ($user->quota == -1) {
				$total = (int) @disk_total_space($user->path);
				$free = (int) @disk_free_space($user->path);
				$used = $total - $free;
			}
			elseif ($user->quota == 0) {
				$total = 0;
				$free = 0;
				$used = 0;
			}
			else {
				$used = Storage::getDirectorySize($user->path);
				$total = $user->quota;
				$free = max(0, $total - $used);
			}

			$trash = $with_trash ? Storage::getDirectorySize($user->path . '/.trash') : null;
		}

		return (object) compact('free', 'total', 'used', 'trash');
	}

	public function delete(?stdClass $user)
	{
		Storage::deleteDirectory($user->path);
		DB::getInstance()->run('DELETE FROM users WHERE id = ?;', $user->id);
	}

	public function emptyTrash(?stdClass $user)
	{
		$path = rtrim($user->path, '/') . '/.trash';
		Storage::deleteDirectory($path);
	}

	public function indexAllFiles()
	{
		foreach ($this->list() as $user) {
			Storage::indexFiles($user, null);
		}
	}
}
