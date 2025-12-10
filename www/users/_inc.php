<?php

namespace KaraDAV;

require_once __DIR__ . '/../_inc.php';

if (!$logged_user->is_admin) {
	header(sprintf('Location: %slogin.php', WWW_URL));
	exit;
}

$ldap = LDAP::enabled();
$tpl->assign(compact('ldap'));
