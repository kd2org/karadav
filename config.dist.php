<?php

namespace KaraDAV;

/**
 * Default quota for new users (in MB)
 */
const DEFAULT_QUOTA = 200;

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
 *
 * If you don't define it, KaraDAV will try to auto-detects it as well as it can.
 * But you may have to assign something static instead if that fails, for example:
 *
 * const WWW_URL = 'https://dav.website.example/';
 */
#const WWW_URL = 'http://karadav.localhost/';

/**
 * WOPI client discovery URL
 * eg. http://onlyoffice.domain.tld/hosting/discovery for OnlyOffice
 * If set to NULL, WOPI support is disabled
 */
const WOPI_DISCOVERY_URL = null;

/**
 * Set this to TRUE if you want 'Access-Control-Allow-Origin' header to be set to '*'
 * and allow remote JS clients to make WebDAV requests.
 */
const ACCESS_CONTROL_ALL = false;

/**
 * Path to a log file (eg. __DIR__ . '/debug.log')
 * This will log all HTTP requests and responses received by the server
 */
const LOG_FILE = null;

/**
 * Set to TRUE if you have X-SendFile module installed and configured
 * see https://tn123.org/mod_xsendfile/
 */
const ENABLE_XSENDFILE = false;

/**
 * Set to TRUE if you have a slow filesystem (eg. NFS/BindFS)
 *
 * This will disable directory sizes and directory last modification date
 * (all directories will appear as 0-bytes, and the modification date might
 * not be accurate). Not a huge impact, but it can appear weird to the user.
 *
 * Details: to find out the size taken by a directory, we must do the sum
 * of all files and sub-directories, which might be slow if you have lots
 * of files. Same for directory modification date, we need to find out the
 * last modification of each file in that directory.
 *
 * Note that this will not disable slow operations used for quotas, as it
 * would effectively disable quotas. You must disable each user quota.
 */
const DISABLE_SLOW_OPERATIONS = false;

/**
 * External authentication callback
 *
 * Use this to authenticate a user with a third-party service.
 * Provide a valid PHP callback: either a function name, or a class name and method in an array.
 *
 * The callback will be passed the username and password as parameters, and must return
 * TRUE if auth was successful, or FALSE otherwise.
 *
 * If the callback returned TRUE and the user does not exist in the database,
 * it will be created with the default quota.
 *
 * @var string|array
 */
const AUTH_CALLBACK = null;
//const AUTH_CALLBACK = ['MyAuthClass', 'login'];
//const AUTH_CALLBACK = 'my_login';
//function my_login(string $user, string $password) {
//	return ($user == 'me' && $password == 'secret');
//}

/**
 * LDAP server configuration
 *
 * To use a LDAP server for login, fill those details.
 *
 * All users logging in will be created locally and have the default quota.
 */
const LDAP_HOST = null;
//const LDAP_URI = '127.0.0.1';

const LDAP_LOGIN = null;
//const LDAP_LOGIN = 'uid=%s,ou=users,dc=yunohost,dc=org';

const LDAP_BASE = null;
//const LDAP_BASE = 'dc=yunohost,dc=org';

const LDAP_DISPLAY_NAME = null;
//const LDAP_DISPLAY_NAME = 'displayname';

const LDAP_FIND_USER = null;
//const LDAP_FIND_USER = '(&(|(objectclass=posixAccount))(uid=%s)(permission=cn=karadav.main,ou=permission,dc=yunohost,dc=org))';

const LDAP_FIND_IS_ADMIN = null;
//const LDAP_FIND_IS_ADMIN = '(&(|(objectclass=posixAccount))(uid=%s)(permission=cn=karadav.admin.main,ou=permission,dc=yunohost,dc=org))';

/**
 * Show PHP errors details to users?
 * If set to TRUE, full error messages and source code will be displayed to visitors.
 * If set to FALSE, just a generic "an error happened" message will be displayed.
 *
 * It is recommended to set this to FALSE in production.
 * Default: TRUE
 *
 * @var bool
 */
const ERRORS_SHOW = true;

/**
 * Send PHP errors to this email address
 * The email will contain
 * Default: NULL (errors are not sent by email)
 *
 * @var string|null
 */
const ERRORS_EMAIL = null;

/**
 * Log PHP errors in this file.
 * Default: ROOT/error.log
 *
 * @var string
 */
const ERRORS_LOG = __DIR__ . '/error.log';

/**
 * Send errors reports to this errbit/airbrake compatible API endpoint
 * Default: NULL
 * Example: 'https://user:password@
 *
 * @var string|null
 * @see https://errbit.com/images/error_summary.png
 * @see https://airbrake.io/docs/api/#create-notice-v3
 */
const ERRORS_REPORT_URL = null;

/**
 * Randomly generated secret key
 * Usually you won't need to fill this constant. A random secret key will be generated
 * and written to this file when the first access is made.
 * But if you don't allow your web server to write to this file, then please use a true
 * random bytes generator to create a ~30 bytes random key and put it in this constant :)
 */
//const SECRET_KEY = 'verySECRETstringHEREplease';
