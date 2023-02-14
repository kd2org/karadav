<?php

namespace KaraDAV;

use KD2\ErrorManager;

spl_autoload_register(function ($class) {
    $class = str_replace('\\', '/', $class);
    require_once __DIR__ . '/../lib/' . $class . '.php';
});

ErrorManager::setLogFile(__DIR__ . '/../error.log');

$cfg_file = __DIR__ . '/../config.local.php';

if (!file_exists($cfg_file)) {
	die('This server is not configured yet. Please copy config.dist.php to config.local.php and edit it.');
}

require $cfg_file;

if (!defined('KaraDAV\ERRORS_SHOW')) {
	define('KaraDAV\ERRORS_SHOW', true);
}

if (!ERRORS_SHOW) {
	ErrorManager::enable(ErrorManager::PRODUCTION);
}
else {
	ErrorManager::enable(ErrorManager::DEVELOPMENT);
}

if (defined('KaraDAV\ERRORS_EMAIL') && ERRORS_EMAIL) {
	ErrorManager::setEmail(ERRORS_EMAIL);
}

if (defined('KaraDAV\ERRORS_LOG') && ERRORS_LOG) {
	ErrorManager::setLogFile(ERRORS_LOG);
}
elseif (is_writeable(__DIR__ . '/../error.log')) {
	ErrorManager::setLogFile(__DIR__ . '/../error.log');
}

if (defined('KaraDAV\ERRORS_REPORT_URL') && ERRORS_REPORT_URL) {
	ErrorManager::setRemoteReporting(ERRORS_REPORT_URL, true);
}

// Create random secret key
if (!defined('KaraDAV\SECRET_KEY')) {
	$cfg = file_get_contents($cfg_file);

	if (false == strpos($cfg, 'SECRET_KEY')) {
		$secret = base64_encode(random_bytes(16));

		$c = sprintf("\n\n// Randomly generated secret key, please change only if necessary\nconst SECRET_KEY = %s;\n\n",
			var_export($secret, true));

		if (!is_writeable($cfg_file)) {
			echo "<h2>Configuration missing</h2>";
			echo "<h4>KaraDAV cannot write to <tt>config.local.php</tt></h4>";
			echo "<p>Please append the following code to the <tt>config.local.php</tt> file:</p>";
			printf('<textarea onclick="this.select();" cols="70" rows="5">%s</textarea>', htmlspecialchars($c));
			exit(1);
		}

		$cfg = preg_replace('/\?>\s*$|$/', $c, $cfg, 1);

		file_put_contents($cfg_file, $cfg);
		define('KaraDAV\SECRET_KEY', $secret);
		unset($secret, $cfg_file, $cfg);
	}
}

if (!defined('KaraDAV\WWW_URL')) {
	$https = (!empty($_SERVER['HTTPS']) || $_SERVER['SERVER_PORT'] == 443) ? 's' : '';
	$name = $_SERVER['SERVER_NAME'];
	$port = !in_array($_SERVER['SERVER_PORT'], [80, 443]) ? ':' . $_SERVER['SERVER_PORT'] : '';
	$root = '/';

	define('KaraDAV\WWW_URL', sprintf('http%s://%s%s%s', $https, $name, $port, $root));
}

if (!defined('KaraDAV\DEFAULT_QUOTA')) {
	define('KaraDAV\DEFAULT_QUOTA', 200);
}

// Init database
if (!file_exists(DB_FILE)) {
	$db = DB::getInstance();
	$db->exec('BEGIN;');
	$db->exec(file_get_contents(__DIR__ . '/../schema.sql'));

	if (!LDAP::enabled()) {
		$users = new Users;
		$p = 'karadavdemo';
		$users->create('demo', $p, 10, true);
		$users->login('demo', $p);
		$_SESSION['install_password'] = $p;
	}

	$db->exec('END;');
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
	if (!LOG_FILE) {
		return;
	}

	$msg = vsprintf($message, $params) . "\n\n";

	if (LOG_FILE) {
		file_put_contents(LOG_FILE, $msg, FILE_APPEND);
	}
}

function html_csrf()
{
	$expire = time() + 1800;
	$random = random_bytes(10);
	$action = $_SERVER['REQUEST_URI'];
	$token = hash_hmac('sha256', $expire . $random . $action, STORAGE_PATH . session_id());

	return sprintf('<input type="hidden" name="_c_" value="%s:%s:%s" />', $token, base64_encode($random), $expire);
}

function csrf_check(): bool
{
	if (empty($_POST['_c_'])) {
		return false;
	}

	$verify = strtok($_POST['_c_'], ':');
	$random = base64_decode(strtok(':'));
	$expire = strtok(false);

	if ($expire < time()) {
		return false;
	}

	$action = $_SERVER['REQUEST_URI'];

	$token = hash_hmac('sha256', $expire . $random . $action, STORAGE_PATH . session_id());

	return hash_equals($token, $verify);
}

function html_csrf_error()
{
	if (empty($_POST['_c_'])) {
		return;
	}

	if (!csrf_check()) {
		echo '<p class="error">Sorry, but the form expired, please submit it again.</p>';
	}
}
