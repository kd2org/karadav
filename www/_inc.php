<?php

namespace KaraDAV;

use KD2\ErrorManager;
use KD2\Translate;
use KD2\Smartyer;

const ROOT = __DIR__ . '/../';

spl_autoload_register(function ($class) {
    $class = str_replace('\\', '/', $class);
    require_once ROOT . 'lib/' . $class . '.php';
});

ErrorManager::setLogFile(ROOT . 'error.log');

$cfg_file = ROOT . 'config.local.php';

if (file_exists($cfg_file)) {
	require $cfg_file;
}

if (!defined(__NAMESPACE__ . '\DATA_ROOT')) {
	define(__NAMESPACE__ . '\DATA_ROOT', ROOT . 'data');
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
	ErrorManager::enable(ErrorManager::PRODUCTION);
}
else {
	ErrorManager::enable(ErrorManager::DEVELOPMENT);
}

if (ERRORS_EMAIL) {
	ErrorManager::setEmail(ERRORS_EMAIL);
}

if (ERRORS_LOG) {
	ErrorManager::setLogFile(ERRORS_LOG);
}
elseif (is_writeable(ROOT . 'data/error.log')) {
	ErrorManager::setLogFile(ROOT . 'data/error.log');
}

if (ERRORS_REPORT_URL) {
	ErrorManager::setRemoteReporting(ERRORS_REPORT_URL, true);
}

// Detect thumbnails support
$th = ENABLE_THUMBNAILS && (class_exists('imagick') || function_exists('imagecreatefromwebp'));
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
	$db->exec(file_get_contents(ROOT . 'sql/schema.sql'));

	if (!LDAP::enabled()) {
		$users = new Users;
		$p = 'karadavdemo';
		$users->create('demo', $p, 10, true);
		$users->login('demo', $p);
		$_SESSION['install_password'] = $p;
	}

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

$users = new Users;
$logged_user = $users->current();

if (!$logged_user
	&& basename($_SERVER['PHP_SELF']) !== 'login.php') {
	header(sprintf('Location: %slogin.php', WWW_URL));
	exit;
}

$tpl = new Smartyer;
$tpl->setTemplatesDir(ROOT . 'templates');
$tpl->setCompiledDir(CACHE_PATH . '/compiled');

$tpl->assign('www_url', WWW_URL);
$tpl->assign(compact('logged_user', 'users'));

$tpl->register_function('form_csrf', function (): string {
	$expire = time() + 1800;
	$random = random_bytes(10);
	$action = $_SERVER['REQUEST_URI'];
	$token = hash_hmac('sha256', $expire . $random . $action, STORAGE_PATH . session_id());

	return sprintf('<input type="hidden" name="_c_" value="%s:%s:%s" />', $token, base64_encode($random), $expire);
});

$tpl->register_modifier('format_bytes', function (int $bytes, string $unit = 'B'): string {
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
});

$tpl->assign('form_error' , null);

function form_exec_if($condition, callable $fn, ?string $redirect = null): void
{
    global $tpl;

    if (is_string($condition)) {
        $condition = !empty($_POST[$condition]);
    }

    if (!$condition) {
        return;
    }

    try {
        if (!csrf_check()) {
            throw new UserException(_('Temporary error, please re-submit the form'));
        }

        $fn();

        if ($redirect) {
            header('Location: ' . WWW_URL . $redirect);
            exit;
        }
    }
    catch (UserException|\UnexpectedValueException $e) {
        $tpl->assign('form_error', $e->getMessage());
    }
}


Translate::extendSmartyer($tpl);
Translate::setLocale('en_NZ');

