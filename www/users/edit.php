<?php

namespace KaraDAV;

use function KD2\_;

require_once __DIR__ . '/_inc.php';

$id = (int) ($_GET['id'] ?? 0);

if ($id === $logged_user->id) {
	$user = $logged_user;
}
else {
	$user = $users->getById($id);
}

if (!$user) {
	throw new \LogicException('This user does not exist');
}

form_exec_if('save', function () use ($ldap, $user, $logged_user) {
	if (!$ldap
		&& empty($_POST['is_admin'])
		&& $user->id == $logged_user->id) {
		throw new UserException(_('You cannot remove yourself from admins, ask another admin to do it for you.'));
	}

	$data = $_POST;
	$data['is_admin'] ??= '0'; // Make sure is_admin is set to false if checkbox is not checked

	$user->edit($data);
}, 'users/');

$tpl->assign(compact('user'));
$tpl->display('users/edit.tpl');
