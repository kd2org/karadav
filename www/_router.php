<?php

namespace KaraDAV;

require_once __DIR__ . '/_inc.php';

$uri = strtok($_SERVER['REQUEST_URI'], '?');

if (PHP_SAPI == 'cli-server') {
	if (is_file(__DIR__ . '/' . $uri)) {
		return false;
	}
	// Index.php
	elseif ($uri == '/') {
		return false;
	}

	$method = $_SERVER['REQUEST_METHOD'] ?? $_SERVER['REDIRECT_REQUEST_METHOD'];
	file_put_contents('php://stderr', sprintf("%s %s\n", $method, $uri));

	if ($method != 'GET' && $method != 'HEAD') {
		file_put_contents('php://stderr', file_get_contents('php://input') . "\n");
	}
}

if (isset($_SERVER['REDIRECT_REQUEST_METHOD'])) {
	$_SERVER['REQUEST_METHOD'] = $_SERVER['REDIRECT_REQUEST_METHOD'];
}

$s = new Server;

if (!$s->route($uri)) {
	http_response_code(404);
	echo '<h1>Invalid URL</h1>';
}
