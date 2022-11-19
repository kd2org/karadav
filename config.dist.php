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
 * Run mode dictates whether errors are returned to callers.
 *
 * In production mode, errors are logged but not returned to callers.
 * In development mode, errors are logged as well as returned to callers.
 */
const RUN_MODE = 'development'

/**
 * Randomly generated secret key
 * Usually you won't need to fill this constant. A random secret key will be generated
 * and written to this file when the first access is made.
 * But if you don't allow your web server to write to this file, then please use a true
 * random bytes generator to create a ~30 bytes random key and put it in this constant :)
 */
//const SECRET_KEY = 'verySECRETstringHEREplease';
