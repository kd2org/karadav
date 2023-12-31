<?php

namespace KaraDAV;

if (!empty($_SERVER['PATH_INFO'])) {
	require __DIR__ . '/_router.php';
	exit;
}

require_once __DIR__ . '/_inc.php';

$users = new Users;
$user = $users->current();

if (isset($_GET['logout'])) {
	$users->logout();
	$user = null;
}

if (!$user) {
	header(sprintf('Location: %slogin.php', WWW_URL));
	exit;
}

if (isset($_GET['empty_trash'])) {
	$users->emptyTrash($user);
	header('Location: ./');
	exit;
}

$quota = $users->quota($user, true);
$free = format_bytes($quota->free);
$used = format_bytes($quota->used);
$total = format_bytes($quota->total);
$trash = format_bytes($quota->trash ?? 0);
$percent = $quota->total ? floor(($quota->used / $quota->total)*100) . '%' : '100%';
$www_url = WWW_URL;
$username = htmlspecialchars($user->login);
$trash_button = '';

if ($quota->trash) {
	$trash_button = '<br /><a href="?empty_trash" class="btn sm">Empty trash now</a>';
}

html_head('My files');

echo <<<EOF
<h2 class="myfiles"><a class="btn" href="{$user->dav_url}">Manage my files</a></h2>
<h3>Hello, {$username} !</h3>
<dl>
	<dd><h3>{$percent} used, {$free} free</h3></dd>
	<dd><progress max="{$quota->total}" value="{$quota->used}"></progress>
	<dd>Used {$used} out of a total of {$total}.</dd>
	<dd>Trash: {$trash}. {$trash_button}</dd>
	<dt>WebDAV URL</dt>
	<dd><h3><a href="{$user->dav_url}"><tt>{$user->dav_url}</tt></a></h3>
	<dt>NextCloud URL</dt>
	<dd><tt>{$www_url}</tt></dd>
	<dd class="help">Use this URL to setup a NextCloud or ownCloud client to access your files.</dd>
</dl>
<p><a class="btn sm" href="?logout">Logout</a></p>
EOF;

if ($user->is_admin) {
	echo '<p><a class="btn sm" href="users.php">Manager users</a></p>';
}

html_foot();