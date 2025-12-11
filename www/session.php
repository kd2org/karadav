<?php

namespace KaraDAV;

require_once __DIR__ . '/_inc.php';

$pw = $_SERVER['PHP_AUTH_PW'] ?? null;

if (!$pw) {
	$header = apache_request_headers()['Authorization'] ?? '';

	if ($header && 0 === stripos($header, 'Basic ')) {
		$header = trim(substr($header, strlen('Basic ')));
		$header = base64_decode($header);
		$header = explode(':', $header, 2);

		if (count($header) === 2) {
			$pw = $header[1];
		}
	}
}

if (!$pw || !hash_equals(EXTERNAL_API_KEY, $pw)) {
	http_response_code(403);
	exit;
}

$id = $_GET['id'] ?? ($_POST['id'] ?? '');

if (empty($id) || !is_string($id) || !ctype_alnum($id) || strlen($id) > 100) {
	http_response_code(400);
	exit;
}

session_id($id);
session_start(['use_cookies' => false]);

$users = new Users;
$user = $users->current();

if (!$user) {
	http_response_code(404);
	exit;
}

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');

$quota = $users->quota($user);
$data = compact('user', 'quota');

echo json_encode($data, JSON_PRETTY_PRINT);
