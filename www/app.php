<?php

namespace KaraDAV;

require_once __DIR__ . '/_inc.php';

$app = $_GET['app'] ?? '';

if ($app === 'files') {
	$app_url = $logged_user->dav_url;
	$title = _('My files');
}
elseif (EXTERNAL_APPS && array_key_exists($app, EXTERNAL_APPS)) {
	$data = EXTERNAL_APPS[$app];
	$found = null;

	$title = _($data['label']);
	$app_url = $data['url'];
	$app_url = str_replace('%sessionid%', rawurlencode(session_id()), $app_url);
}
else {
	throw new UserException('Unknown app');
}

$tpl->assign(compact('app', 'app_url', 'title'));

$tpl->display('app.tpl');
