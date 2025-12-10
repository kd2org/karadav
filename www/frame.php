<?php

namespace KaraDAV;

require_once __DIR__ . '/_inc.php';

$url = $_GET['url'] ?? '';

if ($url === 'files') {
	$url = $logged_user->dav_url;
	$title = _('My files');
}
else {
	$found = null;

	foreach ($apps as $app) {
		if ($app->href === $url) {
			$found = $app;
			break;
		}
	}

	if (!$found) {
		throw new UserException('Invalid URL');
	}

	$title = _($app->label);
}

// Make sure we allow frames to work
header('X-Frame-Options: SAMEORIGIN', true);

$tpl->assign(compact('url', 'title'));

$tpl->display('frame.tpl');
