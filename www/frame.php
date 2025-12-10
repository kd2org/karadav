<?php

namespace KaraDAV;

require_once __DIR__ . '/_inc.php';

$url = $_GET['url'] ?? '';

$urls = [];
$allowed_urls = array_merge($urls, [$logged_user->dav_url]);

if (!in_array($url, $allowed_urls, true)) {
	throw new UserException('Invalid URL');
}

if ($url === $logged_user->dav_url) {
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

$tpl->assign(compact('url', 'title'));

$tpl->display('frame.tpl');
