<?php

namespace KaraDAV;

require_once __DIR__ . '/_inc.php';

$uri = parse_url($_SERVER['REQUEST_URI'], \PHP_URL_PATH);
$base_uri = parse_url(WWW_URL, \PHP_URL_PATH);
$relative_uri = '/' . ltrim(substr($uri, strlen($base_uri)), '/');

$s = new Server;

$method = $_SERVER['REQUEST_METHOD'] ?? $_SERVER['REDIRECT_REQUEST_METHOD'];

if (PHP_SAPI == 'cli-server') {
	file_put_contents('php://stderr', $uri . "\n");

	// If file exists, just serve it
	if (is_file(__DIR__ . $relative_uri)) {
		return false;
	}
	// Serve root index.php file
	elseif ($relative_uri === '/' && $method != 'OPTIONS') {
		return false;
	}
}

if (isset($_SERVER['REDIRECT_REQUEST_METHOD'])) {
	$_SERVER['REQUEST_METHOD'] = $_SERVER['REDIRECT_REQUEST_METHOD'];
}

if (!$s->route($uri, $relative_uri)) {
	if (PHP_SAPI == 'cli-server') {
		$s->dav->log("ROUTER: => Route is not managed: 404");
	}

	http_response_code(404);
	echo '<h1>Page not found</h1>';
}
elseif (LOG_FILE) {
	http_log("ROUTER: => %d\nResponse headers:\n  %s", http_response_code(), implode("\n  ", headers_list()));
}
