<?php

namespace KaraDAV;

/**
 * Users file storage path
 * %s is replaced by the login name of the user
 */
const STORAGE_PATH = __DIR__ . '/data/%s';

/**
 * SQLite3 database file
 * This is where the users, app sessions and stuff will be stored
 */
const DB_FILE = __DIR__ . '/data/db.sqlite';

/**
 * WWW_URL is the complete URL of the root of this server
 * This code auto-detects it as well as it can
 * But you may have to assign something static instead, eg.:
 * const WWW_URL = 'https://dav.website.example/';
 */
$https = (!empty($_SERVER['HTTPS']) || $_SERVER['SERVER_PORT'] == 443) ? 's' : '';
$name = $_SERVER['SERVER_NAME'];
$port = !in_array($_SERVER['SERVER_PORT'], [80, 443]) ? ':' . $_SERVER['SERVER_PORT'] : '';
$root = '/';

#define('KaraDAV\WWW_URL', sprintf('http%s://%s%s%s', $https, $name, $port, $root));

const WWW_URL = 'http://192.168.43.171:8081/';