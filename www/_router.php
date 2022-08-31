<?php

namespace KaraDAV;

require_once __DIR__ . '/_inc.php';

if (PHP_SAPI == 'cli-server') {
	if (is_file(__DIR__ . '/' . $_SERVER['REQUEST_URI'])) {
		return false;
	}
	// Index.php
	elseif ($_SERVER['REQUEST_URI'] == '/') {
		return false;
	}
}

$s = new Server;

if (!$s->route($_SERVER['REQUEST_URI'])) {
	http_response_code(404);
	echo '<h1>Invalid URL</h1>';
}
