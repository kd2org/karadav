<?php

namespace KaraDAV;

/**
 * This is the configuration file for KaraDAV
 * *DO NOT* edit the config.dist.php, copy it to config.local.php
 * and edit it to suit your needs. Changing config.dist.php won't do
 * anything.
 *
 * If config.local.php does not exist, default values will be used.
 */

/**
 * Default quota for new users (in MB)
 */
const DEFAULT_QUOTA = 200;

/**
 * Default delay after which files should be deleted from the trashbin
 * (in seconds)
 * Set to zero (0) to disable the trashbin (files will be deleted directly)
 */
const DEFAULT_TRASHBIN_DELAY = 60*60*24*30; // 30 days

/**
 * Users file storage path
 * %s is replaced by the login name of the user
 */
const STORAGE_PATH = __DIR__ . '/data/%s';

/**
 * Path to a directory containing the thumbnails of images
 *
 * Set to NULL to disable thumbnails completely.
 */
const THUMBNAIL_CACHE_PATH = __DIR__ . '/data/.thumbnails';

/**
 * SQLite3 database file
 * This is where the users, app sessions and stuff will be stored
 */
const DB_FILE = __DIR__ . '/data/db.sqlite';

/**
 * SQLite3 journaling mode
 * Default: TRUNCATE (slower)
 * Recommended: WAL (faster, but read below)
 *
 * If your database file is on a local disk, you will get better performance by using
 * 'WAL' journaling instead. But it is not enabled by default as it may
 * lead to database corruption on some network storage (eg. old NFS).
 *
 * @see https://www.sqlite.org/pragma.html#pragma_journal_mode
 * @see https://www.sqlite.org/wal.html
 * @see https://stackoverflow.com/questions/52378361/which-nfs-implementation-is-safe-for-sqlite-database-accessed-by-multiple-proces

 */
//const DB_JOURNAL_MODE = 'WAL';

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
//const AUTH_CALLBACK = 'KaraDAV\my_login';
//function my_login(string $user, string $password) {
//	return ($user == 'me' && $password == 'secret');
//}

/**
 * LDAP server configuration
 *
 * To use a LDAP server for login, fill those details.
 * All LDAP constants MUST be filled, if any constant is NULL, then LDAP support is disabled.
 *
 * All users signing in with success, who don't have an existing account,
 * will be created locally and have the default quota.
 *
 * Example strings are taken from https://yunohost.org/en/packaging_sso_ldap_integration#ldap-integration
 */
const LDAP_HOST = null;
//const LDAP_HOST = '127.0.0.1';

/**
 * LDAP server port
 * @var integer
 */
const LDAP_PORT = 389;

/**
 * LDAP security
 * Set to TRUE if using LDAPS
 * @var bool
 */
const LDAP_SECURE = false;

/**
 * LDAP user DN
 * This is used in bind. Use %s for user login string.
 * @var string
 */
const LDAP_LOGIN = null;
//const LDAP_LOGIN = 'uid=%s,ou=users,dc=yunohost,dc=org';

/**
 * LDAP base DN
 * @var string
 */
const LDAP_BASE = null;
//const LDAP_BASE = 'dc=yunohost,dc=org';

/**
 * LDAP display name attribute
 * @var string
 */
const LDAP_DISPLAY_NAME = null;
//const LDAP_DISPLAY_NAME = 'displayname';

/**
 * LDAP Search filter
 * This is used to find out if a logged-in user has the permission to access this application.
 * Use %s for the user login.
 * @var string
 */
const LDAP_FIND_USER = null;
//const LDAP_FIND_USER = '(&(|(objectclass=posixAccount))(uid=%s)(permission=cn=karadav.main,ou=permission,dc=yunohost,dc=org))';

/**
 * LDAP admin filter
 * This is used to find out if user can manage other users account and change quota etc.
 * Use %s for the user login
 * @var string
 */
const LDAP_FIND_IS_ADMIN = null;
//const LDAP_FIND_IS_ADMIN = '(&(|(objectclass=posixAccount))(uid=%s)(permission=cn=karadav.admin.main,ou=permission,dc=yunohost,dc=org))';

/**
 * Block iOS apps
 * This is enabled by default as they have been reported as not working,
 * and I don't have an iOS device to make any test.
 * To avoid any data loss, they are disabled.
 * If you want to test iOS apps, set this to FALSE, and if you can, send us logs
 * or patches!
 * @var bool
 */
const BLOCK_IOS_APPS = true;

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
 * Default: ROOT/data/error.log
 *
 * @var string
 */
const ERRORS_LOG = __DIR__ . '/data/error.log';

/**
 * Send errors reports to this errbit/airbrake compatible API endpoint
 * Default: NULL
 * Example: 'https://user:password@domain.tld/errors'
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
