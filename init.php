<?php

namespace KaraDAV;

use KD2\ErrorManager;
use KD2\Translate;

require __DIR__ . '/lib/KD2/ErrorManager.php';

ErrorManager::enable(ErrorManager::DEVELOPMENT);

if (PHP_INT_SIZE === 4 && !function_exists('curl_init')) {
	throw new \LogicException('Extension "curl" is required for 32-bits systems.');
}

const ROOT = __DIR__;

spl_autoload_register(function ($class) {
    $class = str_replace('\\', '/', $class);
    require_once ROOT . '/lib/' . $class . '.php';
});

ErrorManager::setLogFile(ROOT . '/error.log');

$cfg_file = ROOT . '/config.local.php';

if (file_exists($cfg_file)) {
	require $cfg_file;
}

if (!defined(__NAMESPACE__ . '\DATA_ROOT')) {
	define(__NAMESPACE__ . '\DATA_ROOT', ROOT . '/data');
}

// Default configuration constants
$defaults = [
	'DEFAULT_QUOTA'          => 200,
	'DEFAULT_TRASHBIN_DELAY' => 60*60*24*30,
	'ENABLE_THUMBNAILS'      => true,
	'STORAGE_PATH'           => DATA_ROOT . '/%s',
	'CACHE_PATH'             => DATA_ROOT . '/.cache',
	'DB_FILE'                => DATA_ROOT . '/db.sqlite',
	'DB_JOURNAL_MODE'        => 'TRUNCATE',
	'WOPI_DISCOVERY_URL'     => null,
	'ACCESS_CONTROL_ALL'     => false,
	'EXTERNAL_APPS'          => null,
	'EXTERNAL_API_KEY'       => null,
	'LOG_FILE'               => null,
	'ENABLE_XSENDFILE'       => false,
	'ERRORS_SHOW'            => true,
	'ERRORS_EMAIL'           => null,
	'ERRORS_LOG'             => DATA_ROOT . '/error.log',
	'ERRORS_REPORT_URL'      => null,
	'AUTH_CALLBACK'          => null,
	'LDAP_HOST'              => null,
	'LDAP_PORT'              => null,
	'LDAP_SECURE'            => null,
	'LDAP_LOGIN'             => null,
	'LDAP_BASE'              => null,
	'LDAP_DISPLAY_NAME'      => null,
	'LDAP_FIND_USER'         => null,
	'LDAP_FIND_IS_ADMIN'     => null,
	'BLOCK_IOS_APPS'         => true,
];

foreach ($defaults as $const => $value) {
	if (!defined('KaraDAV\\' . $const)) {
		define('KaraDAV\\' . $const, $value);
	}
}

class UserException extends \RuntimeException {}

if (!ERRORS_SHOW) {
	ErrorManager::setEnvironment(ErrorManager::PRODUCTION);
}

if (ERRORS_EMAIL) {
	ErrorManager::setEmail(ERRORS_EMAIL);
}

if (ERRORS_LOG) {
	ErrorManager::setLogFile(ERRORS_LOG);
}
elseif (is_writeable(DATA_ROOT . '/error.log')) {
	ErrorManager::setLogFile(DATA_ROOT . '/error.log');
}

if (ERRORS_REPORT_URL) {
	ErrorManager::setRemoteReporting(ERRORS_REPORT_URL, true);
}

// Detect thumbnails support
$th = ENABLE_THUMBNAILS && (class_exists('imagick', false) || function_exists('imagecreatefromwebp'));
define('KaraDAV\ENABLE_THUMBNAILS_OK', $th);

// Create random secret key
if (!defined('KaraDAV\SECRET_KEY')) {
	$cfg = file_exists($cfg_file) ? file_get_contents($cfg_file) : "<?php\nnamespace KaraDAV;\n\n";

	if (false == strpos($cfg, 'SECRET_KEY')) {
		$secret = base64_encode(random_bytes(16));

		$c = sprintf("\n\n// Randomly generated secret key, please change only if necessary\nconst SECRET_KEY = %s;\n\n",
			var_export($secret, true));

		if ((file_exists($cfg_file) && !is_writeable($cfg_file)) || !is_writeable(dirname($cfg_file))) {
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

	if ($name === '0.0.0.0') {
		$name = 'localhost';
	}

	$port = !in_array($_SERVER['SERVER_PORT'], [80, 443]) ? ':' . $_SERVER['SERVER_PORT'] : '';
	$root = '/';

	define('KaraDAV\WWW_URL', sprintf('http%s://%s%s%s', $https, $name, $port, $root));
}

// Init database
if (!file_exists(DB_FILE)) {
	$parent = dirname(DB_FILE);

	if (!file_exists($parent)) {
		@mkdir($parent, 0777, true);
	}

	if (!is_writable($parent)) {
		throw new \RuntimeException('Cannot create database in directory: ' . $parent);
	}

	$db = DB::getInstance();
	$db->exec('BEGIN;');
	$db->exec(file_get_contents(ROOT . '/sql/schema.sql'));

	if (!LDAP::enabled()) {
		$users = new Users;
		$p = 'karadavdemo';
		$users->create('demo', $p, 10, true);
		$users->login('demo', $p);
		$_SESSION['install_password'] = $p;
	}

	$db->exec('PRAGMA user_version = ' . DB::VERSION . ';');

	$db->exec('END;');
}
else {
	$db = DB::getInstance();
	$db->upgradeVersion();
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

if (LOG_FILE && isset($_SERVER['REMOTE_ADDR'])) {
	$method = $_SERVER['REQUEST_METHOD'] ?? $_SERVER['REDIRECT_REQUEST_METHOD'];
	$uri = parse_url($_SERVER['REQUEST_URI'], \PHP_URL_PATH);

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
