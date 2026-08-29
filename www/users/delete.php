<?php

namespace KaraDAV;

use function KD2\_;

require_once __DIR__ . '/_inc.php';

$user = $users->getById((int) $_GET['id']);

if (!$user) {
	throw new \LogicException('This user does not exist');
}

form_exec_if('delete', function () use ($user, $logged_user) {
	if ($user->id === $logged_user->id) {
		throw new UserException(_('You cannot delete your own account, ask another admin to do it for you.'));
	}

	$user->delete();
}, 'users/');

$tpl->assign(compact('user'));
$tpl->display('users/delete.tpl');
