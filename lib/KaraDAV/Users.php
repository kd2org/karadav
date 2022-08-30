<?php

namespace KaraDAV;

class Users
{
	public function list(): array
	{
		$users = [];

		foreach (file(USERS_PASSWD_FILE) as $line) {
			$login = strtolower(trim(strtok($line, ':')));
			$password = trim(strtok(''));
			$users[$login] = $password;
		}

		return $users;
	}

	public function get(string $login): ?string
	{
		return $this->list()[$login] ?? null;
	}

	public function login(?string $login, ?string $password): ?string
	{
		if (!$login || !$password) {
			return null;
		}

		if (isset($_COOKIE[session_name()]) && !isset($_SESSION)) {
			session_start();
		}

		// Check if user already has a session
		if (!empty($_SESSION['user'])) {
			return $_SESSION['user'];
		}

		// If not, try to login
		$login = strtolower(trim($login));
		$hash = $this->get($login);

		if (!$hash) {
			return null;
		}

		if (!password_verify($password, $hash)) {
			return null;
		}

		@session_start();
		$_SESSION['user'] = $login;

		return $login;
	}
}
