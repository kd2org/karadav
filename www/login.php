<?php

namespace KaraDAV;

require_once __DIR__ . '/_inc.php';

$users = new Users;

if (empty($_GET['nc']) && $users->current()) {
	header('Location: ' . WWW_URL);
	exit;
}

$error = 0;

if (!empty($_POST['login']) && !empty($_POST['password']) && csrf_check()) {
	if ($users->login($_POST['login'], $_POST['password'])) {
		$url = null;

		if (!empty($_POST['nc']) && $_POST['nc'] == 'redirect') {
			$url = $users->appSessionCreateAndGetRedirectURL();
		}
		elseif (!empty($_POST['nc'])) {
			$users->appSessionCreate($_POST['nc']);
			$error = -1;
		}
		else {
			$url = './';
		}

		if ($url) {
			header('Location: ' . $url);
			exit;
		}
	}
	else {
		$error = 1;
	}
}

$app_login = $_GET['nc'] ?? null;
$tpl->assign(compact('error', 'app_login'));
$tpl->display('login.tpl');
