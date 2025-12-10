<?php

namespace KaraDAV;

if (!empty($_SERVER['PATH_INFO'])) {
	require __DIR__ . '/_router.php';
	exit;
}

require_once __DIR__ . '/_inc.php';

if (isset($_GET['logout'])) {
	$users->logout();
	header(sprintf('Location: %slogin.php?logout=1', WWW_URL));
	exit;
}

if (isset($_GET['empty_trash'])) {
	$users->emptyTrash($logged_user);
	header('Location: ./');
	exit;
}

$quota = $users->quota($logged_user, true);
$percent = $quota->total ? floor(($quota->used / $quota->total)*100) . '%' : '100%';

$tpl->assign(compact('quota', 'percent'));

$tpl->display('index.tpl');
