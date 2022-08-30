<?php

namespace KaraDAV;

/**
 * Where login/password tuples are stored
 * By default it contains a dav:superDAV user, please remove this user!
 * Add users to the password file using `htpasswd -B users.passwd login`
 */
const USERS_PASSWD_FILE = __DIR__ . '/users.passwd';

/**
 * Where the list of app credentials live
 * Each line is a set of login:custom_password credentials
 * Generated when logging in
 */
const APPS_PASSWD_FILE = __DIR__ . '/data/apps.passwd';

/**
 * Users file storage path
 * %s is replaced by the login name of the user
 */
const STORAGE_PATH = __DIR__ . '/data/%s';

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

define('KaraDAV\WWW_URL', sprintf('http%s://%s/', $https, $name, $port, $root));