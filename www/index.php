<?php

namespace KaraDAV;

require_once __DIR__ . '/_inc.php';

$users = new Users;
$user = $users->current();

if (!$user) {
	header(sprintf('Location: %slogin.php', WWW_URL));
	exit;
}

$quota = $users->quota($user);
$server = new Server;
$free = $server->format_bytes($quota->free);
$used = $server->format_bytes($quota->used);
$total = $server->format_bytes($quota->total);
$www_url = WWW_URL;

html('My files', <<<EOF
<dl>
	<dt>WebDAV URL</dt>
	<dd><a href="{$user->dav_url}"><tt>{$user->dav_url}</tt></a> (click to manage your files from your browser)</dd>
	<dt>NextCloud URL</dt>
	<dd><tt>{$www_url}</tt></dd>
	<dt>Quota</dt>
	<dd>Used {$used} out of {$total} (free: {$free})</dd>
</dl>
EOF);
