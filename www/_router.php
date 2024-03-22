<?php

namespace KaraDAV;

require_once __DIR__ . '/_inc.php';

$uri = parse_url($_SERVER['REQUEST_URI'], \PHP_URL_PATH);
$base_uri = parse_url(WWW_URL, \PHP_URL_PATH);

$uri = '/' . ltrim(substr($uri, strlen($base_uri)), '/');

$s = new Server;

$method = $_SERVER['REQUEST_METHOD'] ?? $_SERVER['REDIRECT_REQUEST_METHOD'];

if (LOG_FILE) {
	$qs = $_SERVER['QUERY_STRING'] ?? null;
	$headers = apache_request_headers();

	http_log("===== ROUTER: Got new request: %s from %s =====", date('d/m/Y H:i:s'), $_SERVER['REMOTE_ADDR']);

	http_log("ROUTER: <= %s %s (User: %s)\nRequest headers:\n  %s",
		$method,
		$uri . ($qs ? '?' : '') . $qs,
		$_SERVER['PHP_AUTH_USER'] ?? 'none',
		implode("\n  ", array_map(fn ($v, $k) => $k . ': ' . $v, $headers, array_keys($headers)))
	);

	if ($method != 'GET' && $method != 'OPTIONS' && $method != 'HEAD') {
		http_log("ROUTER: <= Request body:\n%s", file_get_contents('php://input'));
	}
}

if (PHP_SAPI == 'cli-server') {
	file_put_contents('php://stderr', $uri . "\n");

	if (is_file(__DIR__ . '/' . $uri)) {
		return false;
	}
	// Index.php
	elseif ($uri == '/' && $method != 'OPTIONS') {
		return false;
	}
}

if (isset($_SERVER['REDIRECT_REQUEST_METHOD'])) {
	$_SERVER['REQUEST_METHOD'] = $_SERVER['REDIRECT_REQUEST_METHOD'];
}

if (!$s->route($uri)) {
	if (PHP_SAPI == 'cli-server') {
		$s->dav->log("ROUTER: => Route is not managed: 404");
	}

	http_response_code(404);
	echo '<h1>Page not found</h1>';
}
elseif (LOG_FILE) {
	http_log("ROUTER: => %d\nResponse headers:\n  %s", http_response_code(), implode("\n  ", headers_list()));
}
