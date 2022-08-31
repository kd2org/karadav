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
	$p = Users::generatePassword();
	$users->create('demo', $p);
	$users->edit('demo', ['quota' => 10]);
	$_SESSION['install_password'] = $p;
	$users->login('demo', $p);

	$db->exec('END;');
}

function get_directory_size(string $path): int
{
	return 0;
	$total = 0;

	foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::LEAVES_ONLY, \RecursiveIteratorIterator::CATCH_GET_CHILD) as $p) {
		try {
			$total += $p->getSize();
		}
		catch (\RuntimeException $e) {
			// Ignore file that vanished
		}
	}

	return $total;
}

function html(string $title, string $html): void
{
	$title = htmlspecialchars($title);

	echo <<<EOF
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8" />
	<title>{$title}</title>
	<link rel="stylesheet" type="text/css" href="/admin.css" />
</head>
<body>
<h1>{$title}</h1>
{$html}
<footer>
	Powered by <a href="https://github.com/kd2.org/karadav/">KaraDAV</a>
</footer>
</body>
</html>
EOF;

}
