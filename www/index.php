<?php

namespace KaraDAV;

require_once __DIR__ . '/_inc.php';

if (PHP_SAPI == 'cli-server' && is_file(__DIR__ . '/' . $_SERVER['REQUEST_URI'])) {
	return false;
}


$s = new Server;

if (!$s->route($_SERVER['REQUEST_URI'])) {
	die('The supplied URL is not managed by this server');
}
