<?php

namespace KaraDAV;

use stdClass;

class Users
{
	protected ?User $current = null;

	public function __construct()
	{
		if (!session_id()) {
			// Protect the cookie : CSRF/JS stealing the cookie
			session_set_cookie_params([
				'samesite' => 'Strict',
				'httponly' => true,
				'secure'   => parse_url(WWW_URL, PHP_URL_SCHEME) === 'https',
			]);
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
		return array_map([User::class, 'from'], iterator_to_array(DB::getInstance()->iterate('SELECT * FROM users ORDER BY login;')));
	}

	public function fetch(string $login): ?User
	{
		return User::from(DB::getInstance()->first('SELECT * FROM users WHERE login = ?;', $login));
	}

	public function get(string $login): ?User
	{
		$user = $this->fetch($login);

		if (!$user && LDAP::enabled()) {
			$ldap = LDAP::getInstance();

			if (!$ldap->checkUser($login)) {
				return null;
			}

			$user = new User;
			$user->create([
				'login' => $login,
				'password' => self::generatePassword(),
			]);

			$user->is_admin = $ldap->checkIsAdmin($login);
		}

		return $user;
	}

	public function getById(int $id): ?User
	{
		return User::from(DB::getInstance()->first('SELECT * FROM users WHERE id = ?;', $id));
	}

	public function current(): ?User
	{
		if ($this->current) {
			return $this->current;
		}

		$db = DB::getInstance();

		if (isset($_COOKIE[session_name()]) && !isset($_SESSION)) {
			session_start();
		}
		elseif (!empty($_COOKIE['permanent'])
			&& ($user = $db->first('SELECT * FROM users WHERE session_id = ?;', $_COOKIE['permanent']))) {
			@session_start();

			$_SESSION['user'] = User::from($user);

			// Make sure this session_id cannot be reused
			$this->setPermanentSession($user->id);
		}

		// Old sessions (pre-refactor) stored a stdClass with stale, byte-based quota values
		// FIXME: remove in 1.0 release
		if (isset($_SESSION['user']) && $_SESSION['user'] instanceof stdClass) {
			$_SESSION['user'] = $this->getById($_SESSION['user']->id);
		}

		$this->current = $_SESSION['user'] ?? null;

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

	public function login(?string $login, ?string $password, bool $permanent = false): ?User
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
			$ldap = LDAP::getInstance();

			if (!$ldap->checkPassword($login, $password)) {
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
			$user = new User;
			$user->create(['login' => $login, 'password' => random_bytes(10)]);
			$user = $this->get($login);
		}

		if (!$ok && !password_verify(trim($password), $user->password)) {
			return null;
		}

		@session_start();
		$_SESSION['user'] = $user;

		if ($permanent) {
			$this->setPermanentSession($user->id);
		}

		return $user;
	}

	protected function setPermanentSession(int $id_user)
	{
		DB::getInstance()->run('UPDATE users SET session_id = ? WHERE id = ?;', session_id(), $id_user);

		setcookie('permanent', session_id(), [
			'expires'  => time() + 3600*24*365,
			'path'     => '/',
			'httponly' => true,
			'samesite' => 'Strict',
			'secure'   => parse_url(WWW_URL, PHP_URL_SCHEME) === 'https',
		]);
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
			$hash = password_hash($password . $current->password, \PASSWORD_DEFAULT);
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
		$hash = password_hash($session->password . $user->password, \PASSWORD_DEFAULT);
		$session->token = self::generatePassword();
		$session->password = $session->token . ':' . $session->password;

		DB::getInstance()->run('UPDATE app_sessions
			SET token = ?, password = ?, expiry = datetime(\'now\', \'+1 month\')
			WHERE token = ?;',
			$session->token, $hash, $token);

		$session->user = $user;
		return $session;
	}

	public function appSessionLogin(?string $login, ?string $app_password): ?User
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
		$user = User::from($user);
		$_SESSION['user'] = $user;

		return $user;
	}

	public function indexAllFiles()
	{
		$db = DB::getInstance();
		$db->begin();

		foreach ($this->list() as $user) {
			Storage::indexFiles($user, null);
		}

		$db->commit();
	}
}
