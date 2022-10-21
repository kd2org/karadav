<?php

namespace KaraDAV;

require_once __DIR__ . '/_inc.php';

$uri = strtok($_SERVER['REQUEST_URI'], '?');

$s = new Server;

$method = $_SERVER['REQUEST_METHOD'] ?? $_SERVER['REDIRECT_REQUEST_METHOD'];
$qs = $_SERVER['QUERY_STRING'] ?? null;
$s->dav->log("<= %s %s", $method, $uri . ($qs ? '?' : '') . $qs);
$s->dav->log('%s', print_r(apache_request_headers(), true));
$s->dav->log('%s', $_SERVER['PHP_AUTH_USER'] ?? 'not logged in');

if (PHP_SAPI == 'cli-server') {
	if (is_file(__DIR__ . '/' . $uri)) {
		return false;
	}
	// Index.php
	elseif ($uri == '/' && $method != 'OPTIONS') {
		return false;
	}

	if ($method != 'GET' && $method != 'HEAD') {
		$s->dav->log('%s', file_get_contents('php://input'));
	}
}

if (isset($_SERVER['REDIRECT_REQUEST_METHOD'])) {
	$_SERVER['REQUEST_METHOD'] = $_SERVER['REDIRECT_REQUEST_METHOD'];
}

if (!$s->route($uri)) {
	if (PHP_SAPI == 'cli-server') {
		$s->dav->log("=> Router fail: 404");
	}

	http_response_code(404);
	echo '<h1>Invalid URL</h1>';
}
else {
	$s->dav->log('=> %d %s', http_response_code(), print_r(headers_list(), true));
}
