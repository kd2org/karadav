<?php

namespace KaraDAV;

use KD2\ErrorManager;
use KD2\Translate;
use KD2\Smartyer;

require_once __DIR__ . '/../init.php';

$users = new Users;
$logged_user = $users->current();

$file = basename($_SERVER['PHP_SELF']);

if (!$logged_user
	&& !in_array($file, ['login.php', 'session.php'])) {
	header(sprintf('Location: %slogin.php', WWW_URL));
	exit;
}

$tpl = new Smartyer;
$tpl->setTemplatesDir(ROOT . '/templates');
$tpl->setCompiledDir(CACHE_PATH . '/compiled');

$tpl->assign('www_url', WWW_URL);
$tpl->assign('apps', EXTERNAL_APPS);
$tpl->assign(compact('logged_user', 'users'));

$tpl->register_function('form_csrf', function (): string {
	$expire = time() + 1800;
	$random = random_bytes(10);
	$action = $_SERVER['REQUEST_URI'];
	$token = hash_hmac('sha256', $expire . $random . $action, STORAGE_PATH . session_id());

	return sprintf('<input type="hidden" name="_c_" value="%s:%s:%s" />', $token, base64_encode($random), $expire);
});

$tpl->register_modifier('format_bytes', function ($bytes, string $unit = 'B'): string {
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
