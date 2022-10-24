<?php

namespace KaraDAV;

use KD2\ErrorManager;

spl_autoload_register(function ($class) {
    $class = str_replace('\\', '/', $class);
    require_once __DIR__ . '/../lib/' . $class . '.php';
});

ErrorManager::enable(ErrorManager::DEVELOPMENT);
ErrorManager::setLogFile(__DIR__ . '/../error.log');

if (!file_exists(__DIR__ . '/../config.local.php')) {
	die('This server is not configured yet. Please copy config.dist.php to config.local.php and edit it.');
}

require __DIR__ . '/../config.local.php';

// Init database
if (!file_exists(DB_FILE)) {
	$db = DB::getInstance();
	$db->exec('BEGIN;');
	$db->exec(file_get_contents(__DIR__ . '/../schema.sql'));

	@session_start();
	$users = new Users;
	$p = 'karadavdemo';
	$users->create('demo', $p, 10, true);
	$_SESSION['install_password'] = $p;
	$users->login('demo', $p);

	$db->exec('END;');
}

if (isset($_COOKIE[session_name()]) && !isset($_SESSION)) {
	@session_start();
}

function html_head(string $title): void
{
	$title = htmlspecialchars($title);

	echo <<<EOF
	<!DOCTYPE html>
	<html>
	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0, target-densitydpi=device-dpi" />
		<title>{$title}</title>
		<link rel="stylesheet" type="text/css" href="/ui.css" />
	</head>
	<body>
	<h1>{$title}</h1>
	<main>
EOF;

	if (isset($_SESSION['install_password'])) {
		printf('<p class="info">Your server has been installed with a user named <tt>demo</tt> and the password <tt>%s</tt>, please change it.<br /><br />This message will disappear when you log out.</p>', htmlspecialchars($_SESSION['install_password']));
	}
}

function html_foot(): void
{
	echo '
	</main>
	<footer>
		Powered by <a href="https://github.com/kd2org/karadav/">KaraDAV</a>
	</footer>
	</body>
	</html>';
}

function format_bytes(int $bytes, string $unit = 'B'): string
{
	if ($bytes >= 1024*1024*1024) {
		return round($bytes / (1024*1024*1024), 1) . ' G' . $unit;
	}
	elseif ($bytes >= 1024*1024) {
		return round($bytes / (1024*1024), 1) . ' M' . $unit;
	}
	elseif ($bytes >= 1024) {
		return round($bytes / 1024, 1) . ' K' . $unit;
	}
	else {
		return $bytes . ' ' . $unit;
	}
}

function http_log(string $message, ...$params): void
{
	if (PHP_SAPI != 'cli-server' && !LOG_FILE) {
		return;
	}

	$msg = vsprintf($message, $params) . "\n\n";

	if (PHP_SAPI == 'cli-server') {
		file_put_contents('php://stderr', $msg);
	}

	if (LOG_FILE) {
		file_put_contents(LOG_FILE, $msg, FILE_APPEND);
	}
}
