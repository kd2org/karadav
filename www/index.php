<?php

namespace KaraDAV;

if (!empty($_SERVER['PATH_INFO'])) {
	require __DIR__ . '/_router.php';
	exit;
}

require_once __DIR__ . '/_inc.php';

$users = new Users;
$user = $users->current();

if (isset($_GET['logout'])) {
	$users->logout();
	$user = null;
	header(sprintf('Location: %slogin.php?logout=1', WWW_URL));
	exit;
}

if (isset($_GET['empty_trash'])) {
	$users->emptyTrash($user);
	header('Location: ./');
	exit;
}

$quota = $users->quota($user, true);
$percent = $quota->total ? floor(($quota->used / $quota->total)*100) . '%' : '100%';

$tpl->assign(compact('quota', 'percent'));

$tpl->display('index.tpl');
