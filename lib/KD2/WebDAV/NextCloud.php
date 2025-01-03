<?php

namespace KD2\WebDAV;

abstract class NextCloud
{
	use NextCloudNotes;

	const NC_VERSION = '99.0.0';

	/**
	 * File permissions for NextCloud clients
	 * https://doc.owncloud.com/desktop/next/appendices/architecture.html#server-side-permissions
	 *
	 * R = Shareable
	 * S = Shared
	 * M = Mounted
	 * D = Delete
	 * G = Readable
	 * N = Can rename
	 * V = Can move
	 * W = Can Write (Update)
	 * C = Can create file in folder
	 * K = Can create folder (mkdir)
	 */
	const PERM_READ = 'G';
	const PERM_SHARE = 'R';
	const PERM_SHARED = 'S';
	const PERM_MOUNTED = 'M';
	const PERM_DELETE = 'D';
	const PERM_RENAME = 'N';
	const PERM_MOVE = 'V';
	const PERM_WRITE = 'W';
	const PERM_CREATE = 'C';
	const PERM_MKDIR = 'K';
	// NextCloud Android is buggy and doesn't differentiate between C and K, you must use CK
	// or nothing (eg. C or K alone is not recognized, same with KC)
	const PERM_CREATE_FILES_DIRS = 'CK';

	const NC_NAMESPACE = 'http://nextcloud.org/ns';
	const OC_NAMESPACE = 'http://owncloud.org/ns';

	const PROP_OC_ID = self::OC_NAMESPACE . ':id';
	const PROP_OC_SIZE = self::OC_NAMESPACE . ':size';
	const PROP_OC_DOWNLOADURL = self::OC_NAMESPACE . ':downloadURL';
	const PROP_OC_PERMISSIONS = self::OC_NAMESPACE . ':permissions';
	const PROP_OC_CHECKSUMS = self::OC_NAMESPACE . ':checksums';

	// Preview
	const PROP_NC_HAS_PREVIEW = self::NC_NAMESPACE . ':has-preview';

	// If you supply Markdown content in this property
	// it will be displayed at the top of a directory listing
	// in Android app
	const PROP_NC_RICH_WORKSPACE = self::NC_NAMESPACE . ':rich-workspace';

	const PROP_NC_TRASHBIN_FILENAME = self::NC_NAMESPACE . ':trashbin-filename';
	const PROP_NC_TRASHBIN_ORIGINAL_LOCATION = self::NC_NAMESPACE . ':trashbin-original-location';
	const PROP_NC_TRASHBIN_DELETION_TIME = self::NC_NAMESPACE . ':trashbin-deletion-time';

	// Useless?
	const PROP_OC_SHARETYPES = self::OC_NAMESPACE . ':share-types';
	const PROP_NC_NOTE = self::NC_NAMESPACE . ':note';
	const PROP_NC_IS_ENCRYPTED = self::NC_NAMESPACE . ':is-encrypted';
	const PROP_NC_DDC = self::NC_NAMESPACE . ':dDC';

	const NC_PROPERTIES = [
		self::PROP_OC_ID,
		self::PROP_OC_SIZE,
		self::PROP_OC_DOWNLOADURL,
		self::PROP_OC_PERMISSIONS,
		self::PROP_OC_CHECKSUMS,
		self::PROP_OC_SHARETYPES,
		self::PROP_NC_HAS_PREVIEW,
		self::PROP_NC_NOTE,
		self::PROP_NC_IS_ENCRYPTED,
		self::PROP_NC_DDC,
	];

	protected string $prefix = '';
	protected string $root_url;
	protected Server $server;
	protected AbstractStorage $storage;

	protected bool $block_ios_clients = true;

	/**
	 * Handle your authentication
	 * you should handle real user login/password as well as app-specific passwords here
	 * (in a second condition) to cover all cases
	 */
	abstract public function auth(?string $login, ?string $password): bool;
	/*  This is a simple example:
		session_start();

		if (!empty($_SESSION['user'])) {
			return true;
		}

		if ($login != 'admin' && $password != 'abcd') {
			return false;
		}

		$_SESSION['user'] = 'admin';
		return true;
	*/

	/**
	 * Return username (a-z_0-9) of currently logged-in user
	 */
	abstract public function getUserName(): ?string;
	/*
		return $_SESSION['user'] ?? null;
	 */

	/**
	 * Set username of currently logged-in user
	 * Return FALSE if user is invalid
	 */
	abstract public function setUserName(string $login): bool;
	/*
		$_SESSION['user'] = $login;
		return true;
	 */

	/**
	 * Return quota for currently loggged-in user
	 * @return array ['free' => 123, 'used' => 123, 'total' => 246]
	 */
	abstract public function getUserQuota(): array;
	/*
		return ['free' => 123, 'used' => 123, 'total' => 246];
	 */

	/**
	 * Return a unique token for v2 login flow
	 */
	abstract public function generateToken(): string;
	/*
		return sha1(random_bytes(16));
	*/

	/**
	 * Validate the provided token to get a session, returns either NULL or a user login and app password
	 * @return array ['login' => ..., 'password' => ...]
	 */
	abstract public function validateToken(string $token): ?array;
	/*
		$session = $db->get('SELECT login, password FROM sessions WHERE token = ?;', $token);

		if (!$session) {
			return null;
		}

		// Make sure to have a single-use token
		$db->query('UPDATE sessions SET token = NULL WHERE token = ?;', $token);

		return (array)$session;
	*/

	abstract public function getLoginURL(?string $token): string;
	/*
		if ($token) {
			return $this->root_url . '/admin/login.php?nc_token=' . $token;
		}
		else {
			return $this->root_url . '/admin/login.php?nc_redirect=true';
		}
	 */

	/**
	 * Direct download API
	 * Return a unique secret to authentify a direct URL request (for direct API)
	 * meaning a third party (eg. local user app) can access the file without auth
	 * @param  string $uri
	 * @param  string $login User name
	 * @return string a secret string (eg. a hash)
	 */
	abstract public function getDirectDownloadSecret(string $uri, string $login): string;

	/**
	 * Chunked upload API.
	 * You should manage automatic removal of incomplete uploads after around 24 hours.
	 * @param  string $login Current user name
	 * @return string Path to temporary storage for user
	 */
	abstract public function storeChunk(string $login, string $name, string $part, $pointer): void;

	abstract public function deleteChunks(string $login, string $name): void;

	abstract public function assembleChunks(string $login, string $name, string $target, ?int $mtime): array;

	abstract public function listChunks(string $login, string $name): array;

	/**
	 * Thumbnail API
	 * @param string $uri URI path to the file we want a thumbnail for
	 * @param int $width
	 * @param int $height
	 * @param bool $crop TRUE if a cropped image is desired
	 * @param bool $preview TRUE if the thumbnail is for preview (in NextCloud Android, see below)
	 */
	public function serveThumbnail(string $uri, int $width, int $height, bool $crop = false, bool $preview = false): void
	{
		if (!preg_match('/\.(?:jpe?g|gif|png|webp)$/', $uri)) {
			http_response_code(404);
			return;
		}

		// We don't support thumbnails, but you are free to generate cropped thumbnails and send them to the HTTP client
		if (!$preview) {
			http_response_code(404);
			return;
		}
		// On Android, the app is annoying and asks to download the image
		// every time ("no resized image available") if no preview is available.
		// So to avoid that you can just redirect to the file if it's not too large
		// But you are free to extend this method and resize the image on the fly instead.
		else {
			$size = $this->server->getStorage()->propfind($uri, ['DAV::getcontentlength'], 0);
			$size = count($size) ? current($size) : null;

			if ($size > 1024*1024 || !$size) {
				http_response_code(404);
				return;
			}

			$url = '/remote.php/dav/files/' . $uri;
			$this->server->log('=> Preview: redirect to %s', $url);
			header('Location: ' . $url);
		}
	}

	// END OF ABSTRACT METHODS

	/**
	 * List of routes
	 * Order of array elements is important!
	 */
	const ROUTES = [
		// Chunked API
		// https://docs.nextcloud.com/server/latest/developer_manual/client_apis/WebDAV/chunking.html
		'remote.php/webdav/uploads/' => 'chunked',
		'remote.php/dav/uploads/' => 'chunked',

		// There's just 3 or 4 different endpoints for avatars, this is ridiculous
		'remote.php/dav/avatars/' => 'avatar',

		// Trasbin API
		// https://docs.nextcloud.com/server/19/developer_manual/client_apis/WebDAV/trashbin.html
		'remote.php/dav/trashbin/' => 'trashbin',

		// Main routes
		'remote.php/webdav/' => 'webdav', // desktop client
		'remote.php/dav' => 'webdav', // android client

		// Login v1, for Android app
		'index.php/login/flow' => 'login_v1',
		// Login v2, for desktop app
		'index.php/login/v2/poll' => 'poll',
		'index.php/login/v2' => 'login_v2',

		// NC connectivity check (uploads on Android fail without this)
		// see, e.g., https://github.com/nextcloud/android/issues/10993
		'index.php/204' => 'ping',

		// https://github.com/nextcloud/notes/blob/main/docs/api/v1.md
		'index.php/apps/notes/api/v1/' => 'notes',

		// https://help.nextcloud.com/t/getting-image-preview-with-android-library-or-via-webdav/75743
		'index.php/core/preview.png' => 'preview',
		'index.php/apps/files/api/v1/thumbnail/' => 'thumbnail',

		// New preview API, requires a file ID
		// https://github.com/nextcloud/android/blob/d8c79db19d397df448f7bc79fd3ff5b372ab92f1/app/src/main/java/com/owncloud/android/datamodel/ThumbnailsCacheManager.java#L718C67-L718C99
		'index.php/core/preview' => 'preview_v2',

		// Other API endpoints
		'ocs/v2.php/apps/text/workspace/direct' => 'workspace_edit',
		'ocs/v2.php/core/apppassword' => 'delete_app_password',
		'status.php' => 'status',
		'ocs/v1.php/cloud/capabilities' => 'capabilities',
		'ocs/v2.php/cloud/capabilities' => 'capabilities',
		'ocs/v2.php/cloud/user' => 'user',
		'ocs/v1.php/cloud/user' => 'user',
		'ocs/v1.php/config' => 'config',
		'ocs/v2.php/apps/files_sharing/api/v1/shares' => 'shares',
		'ocs/v2.php/apps/user_status' => 'empty',
		'ocs/v2.php/core/navigation/apps' => 'empty',
		'index.php/avatar' => 'avatar',
		'ocs/v2.php/apps/dav/api/v1/direct' => 'direct_url',
		'remote.php/direct/' => 'direct',
		'avatars/' => 'avatar',
	];

	const AUTH_REDIRECT_URL = 'nc://login/server:%s&user:%s&password:%s';

	// *Every* different NextCloud/ownCloud app uses a different root path
	// this is ridiculous.
	// NC Desktop: /remote.php/dav/files/user// (note the double slash at the end)
	// NC iOS: /remote.php/dav/files/user (note the missing end slash)
	// ownCloud Android: /remote.php/dav/files/ (note the missing user part)
	// -> only at first, then it queries the correct path, WTF
	// See also https://github.com/nextcloud/server/issues/25867
	const WEBDAV_BASE_REGEXP = '~^.*remote\.php/(?:webdav/|dav/files/(?:(?:(?!/).)+(?:/+|$)|/*$))~';

	public function setRootURL(string $url)
	{
		$this->root_url = $url;
	}

	public function setServer(Server $server)
	{
		$this->server = $server;
		$this->storage = $server->getStorage();
	}

	/**
	 * Handle NextCloud specific routes
	 *
	 * @param null|string If left NULL, then REQUEST_URI will be used
	 * @return bool Will return TRUE if no NextCloud route was requested.
	 */
	public function route(?string $uri = null): bool
	{
		if (null === $uri) {
			$uri = $_SERVER['REQUEST_URI'] ?? '/';
		}

		$uri = parse_url($uri, PHP_URL_PATH);

		$uri = ltrim($uri, '/');
		$uri = rawurldecode($uri);

		$route = array_filter(self::ROUTES, fn($k) => 0 === strpos($uri, $k), ARRAY_FILTER_USE_KEY);

		if (count($route) < 1) {
			return false;
		}

		$route = current($route);

		$method = $_SERVER['REQUEST_METHOD'] ?? null;
		$this->server->log('NC <= %s %s => routed to: %s', $method, $uri, $route);

		try {
			$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

			// Currently, iOS apps are broken
			if ($this->block_ios_clients && (stristr($ua, 'nextcloud-ios') || stristr($ua, 'owncloudapp'))) {
				throw new WebDAV_Exception('Your client is not compatible with this server. Consider using a different WebDAV client.', 403);
			}

			$v = $this->{'nc_' . $route}($uri);
		}
		catch (Exception $e) {
			$this->server->log('NC => %d - %s', $e->getCode(), $e->getMessage());
			http_response_code($e->getCode());

			if ($route == 'direct') {
				// Do not return any error message for the direct API endpoint.
				// If you return anything, the client will consider it is part of the file
				// and will generate a corrupted file!
				// so if you return a 20-byte long error message, the client
				// will do a normal GET on the regular URL, but with 'Range: bytes=20-'!
				// see https://github.com/nextcloud/desktop/issues/5170
				header('X-Error: ' . $e->getMessage());
				return true;
			}

			echo json_encode(['error' => $e->getMessage()]);
			return true;
		}

		// This route is XML only
		if ($route == 'shares') {
			http_response_code(200);
			header('Content-Type: text/xml; charset=utf-8', true);
			echo '<?xml version="1.0"?>' . $this->xml($v);
		}
		elseif (is_array($v) || is_object($v)) {
			http_response_code(200);
			header('Content-Type: application/json', true);
			$json = json_encode($v, JSON_PRETTY_PRINT);
			echo $json;
			$this->server->log("NC => Body:\n%s", $json);
		}

		$this->server->log('NC Sent response: %d', http_response_code());

		return true;
	}

	protected function xml(array $array): string
	{
		$out = '';

		foreach ($array as $key => $v) {
			$out .= '<' . $key .'>';

			if (is_array($v)) {
				$out .= $this->xml($v);
			}
			else {
				$out .= htmlspecialchars((string) $v, ENT_XML1);
			}

			$out .= '</' . $key .'>';

		}

		return $out;
	}

	protected function requireAuth(): void
	{
		if (!$this->auth($_SERVER['PHP_AUTH_USER'] ?? null, $_SERVER['PHP_AUTH_PW'] ?? null)) {
			header('WWW-Authenticate: Basic realm="Please login"');
			throw new Exception('Please login to access this resource', 401);
		}
	}

	public function nc_webdav(string $uri): void
	{
		$this->requireAuth();

		// ownCloud-Android is using a different preview API
		// remote.php/dav/files/user/name.jpg?x=224&y=224&c=&preview=1
		if (!empty($_GET['preview'])) {
			$x = (int) $_GET['x'] ?? 0;
			$y = (int) $_GET['y'] ?? 0;
			$this->serveThumbnail($uri, $x, $y, $x == $y);
			return;
		}

		$base_uri = null;

		// Find out which route we are using and replace URI
		foreach (self::ROUTES as $route => $method) {
			if ($method != 'webdav') {
				continue;
			}

			if (0 === strpos($uri, $route)) {
				$base_uri = rtrim($route, '/') . '/';
				break;
			}
		}

		if (!$base_uri) {
			throw new Exception('Invalid WebDAV URL', 404);
		}

		if (preg_match(self::WEBDAV_BASE_REGEXP, $uri, $match)) {
			$base_uri = rtrim($match[0], '/') . '/';
		}

		$this->server->prefix = $this->prefix;

		$this->server->setBaseURI($base_uri);
		$this->server->route($uri);
	}

	public function nc_status(): array
	{
		if (stristr($_SERVER['HTTP_USER_AGENT'] ?? '', 'owncloud')) {
			$name = 'ownCloud';
			$version = '10.11.0';
		}
		else {
			$name = 'NextCloud';
			$version = self::NC_VERSION;
		}

		return [
			'installed'       => true,
			'maintenance'     => false,
			'needsDbUpgrade'  => false,
			'version'         => $version,
			'versionstring'   => $version,
			'edition'         => '',
			'productname'     => $name,
			'extendedSupport' => false,
		];
	}

	public function nc_login_v2(): array
	{
		$method = $_SERVER['REQUEST_METHOD'] ?? null;

		if ($method != 'POST') {
			throw new Exception('Invalid request method', 405);
		}

		$token = $this->generateToken();
		$endpoint = sprintf('%s%s', $this->root_url, array_search('poll', self::ROUTES));

		return [
			'poll' => compact('token', 'endpoint'),
			'login' => $this->getLoginURL($token),
		];
	}

	public function nc_poll(): array
	{
		$method = $_SERVER['REQUEST_METHOD'] ?? null;

		if ($method != 'POST') {
			throw new Exception('Invalid request method', 405);
		}

		if (empty($_POST['token']) || !ctype_alnum($_POST['token'])) {
			throw new Exception('Invalid token', 400);
		}

		$session = $this->validateToken($_POST['token']);

		if (!$session) {
			throw new Exception('No token yet', 404);
		}

		return [
			'server'      => $this->root_url,
			'loginName'   => $session['login'],
			'appPassword' => $session['password'],
		];
	}

	public function nc_ping()
	{
		http_response_code(204);
	}

	public function nc_capabilities()
	{
		$v = explode('.', self::NC_VERSION);

		return $this->nc_ocs([
			'version' => [
				'major' => (int)$v[0],
				'minor' => (int)$v[1],
				'micro' => (int)$v[1],
				'string' => self::NC_VERSION,
				'edition' => '',
				'extendedSupport' => false,
			],
			'capabilities' => [
				'core' => [
					'webdav-root' => array_search('webdav', self::ROUTES),
					'pollinterval' => 60,
					'bruteforce' => ['delay' => 0],
				],
				'dav' => [
					// NG chunking: https://github.com/cernbox/smashbox/blob/master/protocol/chunking.md
					// "1.0" means "NG" actually... lol: https://github.com/owncloud/client/issues/7862#issuecomment-717953394
					"chunking" => "1.0",
				],
				'files' => [
					// old v1 chunking: https://github.com/cernbox/smashbox/blob/master/protocol/protocol.md#chunked-file-upload
					// We don't support it, BUT it is required for OwnCloud client, see
					// https://github.com/owncloud/client/blob/24ca9615f6e8ea765f6c25fb4e009b1acc262a2d/src/libsync/capabilities.cpp#L166
					'bigfilechunking' => true,
					'comments' => false,
					'undelete' => in_array(TrashInterface::class, class_implements($this->storage)),
					'versioning' => false,
				],
				'files_sharing' => [
					'api_enabled' => false,
					'group_sharing' => false,
					'resharing' => false,
					'sharebymail' => ['enabled' => false],
				],
				'user' => [
					'expire_date' => ['enabled' => false],
					'send_mail' => false,
				],
				'public' => [
					'enabled' => false,
					'expire_date' => ['enabled' => false],
					'multiple_links' => false,
					'send_mail' => false,
					'upload' => false,
					'upload_files_drop' => false,
				],
				'checksums' => [
					'supportedTypes' => ['SHA1', 'MD5'],
					'preferredUploadType' => 'MD5',
				],
				'notes' => [
					'api_version' => ['1.3'],
					'version' => '4.11.99',
				],
			],
		]);
	}

	public function nc_login_v1(): void
	{
		http_response_code(303);
		header('Location: ' . $this->getLoginURL(null));
	}

	public function nc_user(): array
	{
		$this->requireAuth();

		$quota = $this->getUserQuota();
		$user = $this->getUserName() ?? 'null';

		return $this->nc_ocs([
			'id' => sha1($user),
			'enabled' => true,
			'email' => null,
			'storageLocation' => '/secret/whoknows/' . sha1($user),
			'role' => '',
			'display-name' => $user,
			'quota' => [
				'quota' => -3, // fixed value
				'relative' => 0, // fixed value
				'free' => $quota['free'] ?? 200000000,
				'total' => $quota['total'] ?? 200000000,
				'used' => $quota['used'] ?? 0,
			],
		]);
	}

	public function nc_shares(): array
	{
		return $this->nc_ocs([]);
	}

	protected function nc_empty(): array
	{
		return $this->nc_ocs([]);
	}

	protected function nc_avatar(): void
	{
		throw new Exception('Not implemented', 404);
	}

	protected function nc_config(): array
	{
		return $this->nc_ocs([
			'contact' => '',
			'host' => $_SERVER['SERVER_NAME'] ?? '',
			'ssl' => !empty($_SERVER['HTTPS']) || $_SERVER['SERVER_PORT'] == 443,
			'version' => '1.7',
			'website' => 'Nextcloud',
		]);
	}

	public function getDirectDownloadURL(string $uri, string $user)
	{
		$uri = trim($uri, '/');
		$expire = intval((time() - strtotime('2022-09-01'))/3600) + 8; // 8 hours
		$hash = Server::hmac([$user, $expire, $uri], $this->getDirectDownloadSecret($uri, $user));
		$hash = $expire . ':' . $hash;

		$uri = rawurlencode($uri);
		$uri = str_replace('%2F', '/', $uri);

		return sprintf('%s%s/%s/%s?h=%s', $this->root_url, trim(array_search('direct', self::ROUTES), '/'), $user, $uri, $hash);
	}

	protected function nc_direct_url(): array
	{
		$method = $_SERVER['REQUEST_METHOD'] ?? null;

		if ($method != 'POST') {
			throw new Exception('Invalid request method', 405);
		}

		$this->requireAuth();

		if (empty($_POST['fileId'])) {
			throw new Exception('Missing fileId', 400);
		}

		$uri = gzuncompress(decbin($_POST['fileId']));

		if (!$uri) {
			throw new Exception('Invalid fileId', 404);
		}

		$user = strtok($uri, ':');
		$uri = strtok('');

		if (!$this->storage->exists($uri)) {
			throw new Exception('Invalid fileId', 404);
		}

		$url = $this->getDirectDownloadURL($uri, $user);

		$this->server->log('NextCloud Direct Download URL is: %s', $url);

		return self::nc_ocs(compact('url'));
	}

	protected function nc_direct(string $uri): void
	{
		$method = $_SERVER['REQUEST_METHOD'] ?? null;

		if ($method != 'GET') {
			throw new Exception('Invalid request method', 405);
		}

		if (empty($_GET['h'])) {
			throw new Exception('Missing hash', 400);
		}

		$uri = substr(trim($uri, '/'), strlen(trim(array_search('direct', self::ROUTES), '/')));

		$user = strtok($uri, '/');
		$uri = trim(strtok(''), '/');

		if (!$user || !$uri) {
			throw new Exception('Invalid URI', 400);
		}

		if (false !== strpos($uri, '..')) {
			throw new Exception(sprintf('Invalid URI: "%s"', $uri), 403);
		}

		$expire = (int) strtok($_GET['h'], ':');
		$hash = strtok('');
		$expire_seconds = $expire * 3600 + strtotime('2022-09-01');

		// Link has expired
		if ($expire_seconds < time()) {
			throw new Exception('Link has expired', 401);
		}

		$verify = Server::hmac([$user, $expire, $uri], $this->getDirectDownloadSecret($uri, $user));

		// Check if the provided hash is correct
		if (!hash_equals($verify, $hash)) {
			throw new Exception('Link hash is invalid', 401);
		}

		if (!$this->setUserName($user)) {
			throw new Exception('Invalid user', 404);
		}

		$this->server->log('Access via NextCloud direct download API');
		$this->server->setBaseURI('/');
		$this->server->original_uri = $uri;
		$this->server->http_get($uri);
	}

	protected function nc_ocs(array $data = []): array
	{
		return ['ocs' => [
			'meta' => ['status' => 'ok', 'statuscode' => 200, 'message' => 'OK'],
			'data' => $data,
		]];
	}

	/**
	 * File preview, new version, requires a file ID
	 * @see https://help.nextcloud.com/t/getting-image-preview-with-android-library-or-via-webdav/75743/5
	 */
	protected function nc_preview_v2(string $uri): void
	{
		http_response_code(404);
	}

	/**
	 * File preview, large
	 * @see https://help.nextcloud.com/t/getting-image-preview-with-android-library-or-via-webdav/75743
	 */
	protected function nc_preview(string $uri): void
	{
		$width = $_GET['x'] ?? null;
		$height = $_GET['y'] ?? null;
		$crop = !($_GET['a'] ?? null);

		$url = str_replace('%2F', '/', rawurldecode($_GET['file'] ?? ''));
		$url = ltrim($url, '/');

		$this->serveThumbnail($url, (int) $width, (int) $height, $crop, true);
	}

	protected function nc_thumbnail(string $uri): void
	{
		// Remove "/index.php/apps/files/api/v1/thumbnail/"
		$uri = str_replace(array_search('thumbnail', self::ROUTES), '', $uri);
		$uri = trim($uri, '/');

		list($width, $height, $uri) = array_pad(explode('/', $uri, 3), 3, null);

		$this->serveThumbnail($uri, (int)$width, (int)$height, $width == $height);
	}

	/**
	 * This is triggered when a user clicks the edit button for the README.md file of a directory
	 * (feature is called "rich workspace direct editing")
	 * A webview will be opened to the 'url' parameter returned.
	 */
	protected function nc_workspace_edit(string $uri): ?array
	{
		http_response_code(501);
		return null;

		$path = json_decode(file_get_contents('php://input'))->path ?? null;
		return self::nc_ocs(['url' => '...']);
	}

	/**
	 * Called when removing an account from Android app
	 */
	protected function nc_delete_app_password(): void
	{
		$method = $_SERVER['REQUEST_METHOD'] ?? null;

		if ($method == 'DELETE') {
			// $_SERVER['PHP_AUTH_USER'] / $_SERVER['PHP_AUTH_PW']
		}
	}

	protected function nc_chunked(string $uri): void
	{
		$this->requireAuth();
		$user = $this->getUserName();

		$r = '!^remote\.php/dav/uploads/([^/]+)/([\w\d_-]+)(?:/([\w\d_-]+))?(?:/\.file)?$!';

		if (!preg_match($r, $uri, $match)) {
			throw new Exception('Invalid URL for chunk upload', 400);
		}

		$method = $_SERVER['REQUEST_METHOD'] ?? null;
		// Clients nowadays send some user ID as login, just ignore it.
		// $login = $match[1] ?? null;
		$dir = $match[2] ?? null;
		$part = $match[3] ?? null;

		if ($method == 'MKCOL') {
			http_response_code(201);
		}
		elseif ($method == 'PUT') {
			$this->server->log('Storing chunk: %s/%s/%s', $user, $dir, $part);
			$this->storeChunk($user, $dir, $part, fopen('php://input', 'rb'));
			http_response_code(201);
		}
		elseif ($method == 'MOVE') {
			$dest = $_SERVER['HTTP_DESTINATION'];
			$dest = preg_replace(self::WEBDAV_BASE_REGEXP, '', $dest);
			$dest = trim(rawurldecode($dest), '/');

			if (false !== strpos($dest, '..') || false !== strpos($dest, '//')) {
				throw new Exception('Invalid destination');
			}

			$this->server->log('Assembling chunks to: %s', $dest);

			$mtime = (int) $_SERVER['HTTP_X_OC_MTIME'] ?: null;

			header('X-OC-MTime: accepted');

			$return = $this->assembleChunks($user, $dir, $dest, $mtime);

			$props = $this->storage->propfind($dest, [self::PROP_OC_ID], 0);

			if (!empty($props)) {
				header('OC-FileId: ' . current($props));
			}

			if (!empty($return['etag'])) {
				header(sprintf('ETag: "%s"', $return['etag']));
				header(sprintf('OC-ETag: "%s"', $return['etag']));
			}

			$this->server->log("=> Chunks assembled to: %s", $dest);

			if (!empty($return['created'])) {
				http_response_code(201);
			}
			else {
				http_response_code(204);
			}
		}
		elseif ($method == 'DELETE' && !$part) {
			$this->deleteChunks($user, $dir);
			$this->server->log("=> Deleted chunks");
		}
		elseif ($method == 'PROPFIND') {
			header('HTTP/1.1 207 Multi-Status', true);
			$out = '<?xml version="1.0" encoding="utf-8"?>' . PHP_EOL;
			$out .= '<d:multistatus xmlns:d="DAV:">' . PHP_EOL;

			foreach ($this->listChunks($user, $dir) as $chunk) {
				$out .= '<d:response>' . PHP_EOL;
				$chunk = '/' . $uri . '/' . $chunk;
				$out .= sprintf('<d:href>%s</d:href>', htmlspecialchars($chunk, ENT_XML1)) . PHP_EOL;
				$out .= '<d:propstat><d:prop><d:getcontenttype>application/octet-stream</d:getcontenttype><d:resoucetype/></d:prop><d:status>HTTP/1.1 200 OK</d:status></d:propstat>' . PHP_EOL;
				$out .= '</d:response>' . PHP_EOL;
			}

			$out .= '</d:multistatus>';

			echo $out;

			$this->server->log("=> Body:\n%s", $out);
		}
		else {
			throw new Exception('Invalid method for chunked upload', 400);
		}
	}

	protected function nc_trashbin(string $uri): ?string
	{
		$this->requireAuth();

		$r = '!^remote\.php/dav/trashbin/([^/]+)/trash(?:/([^/]*))?$!';

		if (!preg_match($r, $uri, $match)) {
			throw new Exception('Invalid URL for trashbin API', 400);
		}

		$method = $_SERVER['REQUEST_METHOD'] ?? null;
		//$login = $match[1] ?? null;
		$path = $match[2] ?? null;

		if ($method === 'DELETE' && empty($path)) {
			$this->storage->emptyTrash();
			http_response_code(204);
		}
		elseif ($method === 'DELETE') {
			$this->storage->deleteFromTrash($path);
			http_response_code(204);
		}
		elseif ($method === 'MOVE' && !empty($path)) {
			$this->storage->restoreFromTrash($path);
			http_response_code(201);
		}
		elseif ($method === 'PROPFIND') {
			header('HTTP/1.1 207 Multi-Status', true);
			header('Content-Type: text/xml; charset=utf-8', true);

			$out = '<?xml version="1.0" encoding="utf-8"?>' . PHP_EOL;
			$out .= '<d:multistatus xmlns:d="DAV:" xmlns:nc="http://nextcloud.org/ns" xmlns:oc="http://owncloud.org/ns">' . PHP_EOL;

			$out .= sprintf('<d:response><d:href>%s</d:href><d:propstat><d:prop><d:resourcetype><d:collection/></d:resourcetype></d:prop><d:status>HTTP/1.1 200 OK</d:status></d:propstat><d:propstat><d:prop><nc:trashbin-deletion-time/><d:getcontenttype/><oc:size/><oc:id/><d:getcontentlength/><nc:trashbin-filename/><nc:trashbin-original-location/></d:prop><d:status>HTTP/1.1 404 Not Found</d:status></d:propstat></d:response>', $match[0]);

			foreach ($this->storage->listTrashFiles() as $file => $props) {
				$out .= '<d:response>' . PHP_EOL;
				$path = '/' . trim($uri, '/') . '/' . rawurlencode($file);
				$out .= sprintf('<d:href>%s</d:href>', htmlspecialchars($path, ENT_XML1)) . PHP_EOL;
				$out .= '<d:propstat><d:prop>';

				foreach ($props as $key => $value) {
					$pos = strrpos($key, ':');
					$ns = substr($key, 0, $pos);
					$tag = substr($key, $pos + 1);
					$out .= sprintf('<%s xmlns="%s">%s</%1$s>', $tag, $ns, htmlspecialchars((string) $value, ENT_XML1));
				}

				$out .= '</d:prop><d:status>HTTP/1.1 200 OK</d:status></d:propstat>' . PHP_EOL;
				$out .= '</d:response>' . PHP_EOL;
			}

			$out .= '</d:multistatus>';

			echo $out;

			$this->server->log("=> Body:\n%s", $out);		}
		else {
			throw new Exception('Invalid method for trashbin', 400);
		}

		return null;
	}
}
