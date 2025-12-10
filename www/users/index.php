<?php

namespace KaraDAV;

require_once __DIR__ . '/_inc.php';

$tpl->assign('list', $users->list());
$tpl->display('users/index.tpl');
