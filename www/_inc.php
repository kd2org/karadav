<?php

namespace KaraDAV;

use KD2\ErrorManager;

require_once __DIR__ . '/../lib/KD2/ErrorManager.php';

ErrorManager::enable(ErrorManager::DEVELOPMENT);

require_once __DIR__ . '/../lib/KD2/WebDAV.php';
require_once __DIR__ . '/../lib/KD2/WebDAV_NextCloud.php';
require_once __DIR__ . '/../lib/KaraDAV/Users.php';
require_once __DIR__ . '/../lib/KaraDAV/Server.php';

if (!file_exists(__DIR__ . '/../config.local.php')) {
	die('This server is not configured yet. Please copy config.dist.php to config.local.php and edit it.');
}

require __DIR__ . '/../config.local.php';
