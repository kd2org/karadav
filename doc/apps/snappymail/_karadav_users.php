<?php

if (!isset($_SESSION['user'])) {
	die('Invalid call');
}

$user = $_SESSION['user'];

if ($user->login === 'alice') {
	return ['alice@example.org', 'superSecretPassword'];
}
else {
	die('<h1>Unknown user</h1>');
}
