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

	file_put_contents('php://stderr', $uri . "\n");
}

$s = new Server;

if (!$s->route($uri)) {
	http_response_code(404);
	echo '<h1>Invalid URL</h1>';
}
