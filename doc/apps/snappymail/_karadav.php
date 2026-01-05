<?php

const KARADAV_URL = 'https://:SECRETKEY@karadav.example.org/';
const SNAPPYMAIL_VERSION = '2.38.2';

// This is used for running in a PHP development server
$uri = preg_replace('/\?.*$/', '', $_SERVER['REQUEST_URI']);

if (PHP_SAPI === 'cli-server' && $uri !== '/') {
	$path = __DIR__ . $uri;

	if (file_exists($path)) {
		return false;
	}
}

// Download and unzip SnappyMail
if (!is_dir(__DIR__ . '/snappymail/v/' . SNAPPYMAIL_VERSION)) {
	$url = sprintf('https://github.com/the-djmaze/snappymail/releases/download/v%s/snappymail-%1$s.tar.gz', SNAPPYMAIL_VERSION);

	copy($url, 'snappymail.tar.gz');

	$zip = new \PharData('snappymail.tar.gz');
	$zip->extractTo(__DIR__, null, true);
	unset($zip);

	unlink('snappymail.tar.gz');
}

function karadav_login(): array
{
	session_start();

	$id = $_GET['ext_session_id'] ?? null;

	if (!empty($_SESSION['user'])) {
		return require __DIR__ . '/_karadav_users.php';
	}
	elseif ($id && ctype_alnum($id)) {
		$response = file_get_contents(KARADAV_URL . 'session.php?id=' . $id);
		$response = json_decode($response);

		// Response is invalid or session has expired
		if (!$response) {
			die('<h1>Your session as expired, please reload the page to retry</h1>');
		}

		return require __DIR__ . '/_karadav_users.php';
	}
	else {
		die('<h1>Invalid session ID</h1>');
	}
}

$_ENV['SNAPPYMAIL_INCLUDE_AS_API'] = true;
require_once __DIR__ . '/index.php';

// Disable logout link in user menu, as logging out would just re-login the user
$oConfig = \RainLoop\Api::Config();
$oConfig->Set('labs', 'custom_logout_link', '#disabled');
$oConfig->Save();

$oActions = \RainLoop\Api::Actions();

// Log user in if they don't have a auth cookie
if (!\SnappyMail\Cookies::getSecure($oActions::AUTH_SPEC_TOKEN_KEY)) {
	list($login, $password) = karadav_login();
	$password = new \SnappyMail\SensitiveString($password);
	$oActions->LoginProcess($login, $password);
}

// Start SnappyMail
RainLoop\Service::Handle();
