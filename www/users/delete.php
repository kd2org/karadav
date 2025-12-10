<?php

namespace KaraDAV;

require_once __DIR__ . '/_inc.php';

$user = $users->getById((int) $_GET['id']);

if (!$user) {
	throw new \LogicException('This user does not exist');
}

form_exec_if('delete', function () use ($user, $logged_user, $users) {
	if ($user->id == $logged_user->id) {
		throw new UserException(_('You cannot delete your own account, ask another admin to do it for you.'));
	}

	$users->delete($user);
}, 'users/');

$tpl->assign(compact('user'));
$tpl->display('users/delete.tpl');
