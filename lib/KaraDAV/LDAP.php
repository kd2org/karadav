<?php

namespace KaraDAV;

class LDAP
{
	protected $ldap = null;
	static protected $instance;

	static public function getInstance()
	{
		self::$instance ??= new self;
		return self::$instance;
	}

	static public function enabled(): bool
	{
		$config = [LDAP_HOST, LDAP_PORT, LDAP_SECURE, LDAP_LOGIN, LDAP_FIND_USER, LDAP_FIND_IS_ADMIN, LDAP_BASE, LDAP_DISPLAY_NAME];
		$target = count($config);
		$config = array_filter($config, static fn($v) => $v !== null);
		return count($config) == $target;
	}

	public function __destruct()
	{
		if (isset($this->ldap)) {
			ldap_close($this->ldap);
		}
	}

	public function connect(): void
	{
		if (isset($this->ldap)) {
			return;
		}

		$uri = sprintf('ldap%s://%s:%d', LDAP_SECURE ? 's' : '', LDAP_HOST, LDAP_PORT);
		$l = ldap_connect($uri);

		if (!$l) {
			throw new \RuntimeException('Invalid LDAP connection URI: ' . $uri);
		}

		ldap_set_option($l, \LDAP_OPT_PROTOCOL_VERSION, 3);
		ldap_set_option($l, \LDAP_OPT_REFERRALS, 0);
		ldap_set_option($l, \LDAP_OPT_NETWORK_TIMEOUT, 10);
		$this->ldap = $l;
	}

	static protected function find(string $filter, string $login): bool
	{
		$this->connect();

		$filter = sprintf($filter, ldap_escape($login, '', \LDAP_ESCAPE_FILTER));
		$results = ldap_search($this->ldap, LDAP_BASE, $filter, [LDAP_DISPLAY_NAME]);
		$info = ldap_get_entries($this->ldap, $results);

		return empty($info[0][LDAP_DISPLAY_NAME][0]) ? false : true;
	}

	/**
	 * Return TRUE if a user exists in LDAP and can login
	 */
	static public function checkUser(string $login): bool
	{
		return $this->find(LDAP_FIND_USER, $login);
	}

	/**
	 * Return TRUE if a user is an admin
	 */
	static public function checkIsAdmin(string $login): bool
	{
		return $this->find(LDAP_FIND_IS_ADMIN, $login);
	}

	/**
	 * Return TRUE if the supplied login and password are valid
	 */
	static public function checkPassword(string $login, string $password): bool
	{
		$this->connect();
		$ok = ldap_bind($this->ldap, sprintf(LDAP_LOGIN, $login, $password));
		return (bool) $ok;
	}
}
