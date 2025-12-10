<?php

namespace KaraDAV;

require_once __DIR__ . '/_inc.php';

if ($ldap) {
	throw new UserException('Cannot create users in LDAP');
}

form_exec_if('create', function () use ($users) {
	if (empty($_POST['login'])) {
		throw new UserException(_('Login is empty'));
	}

	if (empty($_POST['password'])) {
		throw new UserException(_('Password is empty'));
	}

	$users->create(trim($_POST['login']), trim($_POST['password']));
}, 'users/');

$tpl->display('users/new.tpl');
