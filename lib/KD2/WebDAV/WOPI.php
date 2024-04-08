<?php
declare(strict_types=1);

namespace KD2\WebDAV;

/**
 * KD2/WebDAV/WOPI
 * This class implements a basic WOPI server on top of a WebDAV storage.
 * It allows to edit documents with Collabora, OnlyOffice, MS Office Online etc.
 *
 * Note that OnlyOffice is not enabling WOPI by default:
 * docker run -i -t -p 9980:80 --network=host -e WOPI_ENABLED=true onlyoffice/documentserver
 *
 * @author BohwaZ
 * @license GNU AGPL v3
 * @see https://api.onlyoffice.com/editors/wopi/
 * @see https://github.com/CollaboraOnline/collabora-online-sdk-examples/tree/master/webapp/php
 * @see https://interoperability.blob.core.windows.net/files/MS-WOPI/[MS-WOPI].pdf
 * @see https://learn.microsoft.com/en-us/microsoft-365/cloud-storage-partner-program/rest/concepts#access-token
 * @see https://learn.microsoft.com/en-us/microsoft-365/cloud-storage-partner-program/online/wopi-requirements
 * @see https://dzone.com/articles/implementing-wopi-protocol-for-office-integration
 * @see https://sdk.collaboraonline.com/docs/advanced_integration.html
 *
 * ## How this WOPI class interacts with the WebDAV server?
 *
 * 1. The local application will render a HTML page including an iframe
 * calling the editing server WOPI URL (see getEditorHTML).
 *
 * 2. The iframe will be loaded by a POST request to the editing server WOPI URL.
 * This request includes a token and a token TTL, as well as the local WOPI URL
 * (WOPISrc parameter in iframe URL).
 *
 * The security of this token is left to the local application.
 *
 * 3. The editing server will then make a request to the local WOPI URL (WOPISrc),
 * usually something like https://example.org/wopi/files/XXX, where XXX is the file
 * unique identifier.
 *
 * Note: this identifier should not be the file number, or this would allow an attacker
 * to enumerate local files. Instead use a unique string, eg. UUID.
 * It should also not be the local file URI, unless this is signed and verified in the token.
 *
 * 4. This request will be handled by this class, intercepting the request before
 * the WebDAV server routing. This class will then call the **verifyWopiToken**
 * method of the WebDAV storage class:
 *
 *  $props = $this->storage->verifyWopiToken($id, $auth_token);
 *
 * This method:
 * - MUST verify that the file ID matches an existing file,
 * - MUST verify the authenticity of the passed token,
 * and then either return NULL if something fails,
 * or return an array of properties (see ::PROP_* constants).
 *
 * These two properties MUST be returned (others are optional):
 * PROP_FILE_URI: this is the local file URI, as if requested using WebDAV (eg. documents/text.odt)
 *  This must be properly URL-encoded!
 * PROP_READ_ONLY: this MUST be a boolean, TRUE if the file cannot be modified, or FALSE if it can.
 *
 * 5. This class will then see if the editing server requested for file informations,
 * fetching file contents, or saving file contents. For the last two cases, this class
 * will call the WebDAV server http_get() and http_put() methods, which will in turn
 * call the Storage class.
 */
class WOPI
{
	/**
	 * Reusing WebDAV server convention of using a XML namespace
	 * for properties, even though we are not doing XML here
	 */
	const NS = 'https://interoperability.blob.core.windows.net/files/MS-WOPI/';
	const PROP_FILE_URI = self::NS . ':file-uri';
	const PROP_WOPI_URL = self::NS . ':wopi-url';
	const PROP_TOKEN = self::NS . ':token';
	const PROP_TOKEN_TTL = self::NS . ':token-ttl';
	const PROP_READ_ONLY = self::NS . ':ReadOnly';
	const PROP_USER_NAME = self::NS . ':UserFriendlyName';
	const PROP_USER_ID = self::NS . ':UserId';
	const PROP_USER_AVATAR = self::NS . ':UserExtraInfo-Avatar';

	protected AbstractStorage $storage;
	protected Server $server;

	/**
	 * Set the local Storage object
	 */
	public function setStorage(AbstractStorage $storage): void
	{
		if (!method_exists($storage, 'verifyWopiToken')) {
			throw new \LogicException('Storage class does not implement verifyWopiToken method');
		}

		$this->storage = $storage;
	}

	/**
	 * Set the local WebDAV server object
	 */
	public function setServer(Server $server): void
	{
		$this->storage = $server->getStorage();
		$this->server = $server;
	}

	/**
	 * Return auth token, either from POST, URL query string, or from Authorization header
	 */
	public function getAuthToken(): string
	{
		// HTTP_AUTHORIZATION might be missing in some installs
		$header = apache_request_headers()['Authorization'] ?? '';

		if ($header && 0 === stripos($header, 'Bearer ')) {
			return trim(substr($header, strlen('Bearer ')));
		}
		elseif (!empty($_REQUEST['access_token'])) {
			return trim($_REQUEST['access_token']);
		}
		else {
			throw new Exception('No access_token was provided', 401);
		}
	}

	/**
	 * Route a WOPI request, returns FALSE if the current URI is not a WOPI URI
	 */
	public function route(?string $uri = null): bool
	{
		if (null === $uri) {
			$uri = $_SERVER['REQUEST_URI'] ?? '/';
		}

		$uri = trim($uri, '/');

		if (0 !== strpos($uri, 'wopi/files/')) {
			return false;
		}

		$uri = substr($uri, strlen('wopi/files/'));

		$this->server->log('WOPI: => %s', $uri);

		try {
			$auth_token = $this->getAuthToken();

			$method = $_SERVER['REQUEST_METHOD'];
			$id = rawurldecode(strtok($uri, '/') ?: '');
			$action = trim(strtok('') ?: '', '/');

			$props = $this->storage->verifyWopiToken($id, $auth_token);

			if (!$props) {
				throw new Exception('Invalid file ID or invalid token', 404);
			}

			if (!isset($props[self::PROP_FILE_URI], $props[self::PROP_READ_ONLY])) {
				throw new \LogicException('Storage::verifyWopiToken method didn\'t return an array, or the array is missing file URI and read only properties');
			}

			$uri = $props[self::PROP_FILE_URI];
			$uri = rawurldecode($uri);

			$this->server->log('WOPI: => Found doc_uri: %s', $uri);

			// GetFile
			if ($action == 'contents' && $method == 'GET') {
				$this->server->log('WOPI: => GetFile');
				$this->server->http_get($uri);
			}
			// PutFile
			elseif ($action == 'contents' && $method == 'POST') {
				// Make sure we can't overwrite a read-only file
				if ($props[self::PROP_READ_ONLY]) {
					throw new Exception('This file is read-only', 403);
				}

				$this->server->log('WOPI: => PutFile');
				$this->server->http_put($uri);

				// In WOPI, HTTP response code 201/204 is not accepted, only 200
				// or Collabora will fail saving
				http_response_code(200);
			}
			// CheckFileInfo
			elseif (!$action && $method == 'GET') {
				$this->server->log('WOPI: => CheckFileInfo');
				$this->getInfo($uri, $props);
			}
			else {
				throw new Exception('Invalid URI', 404);
			}
		}
		catch (Exception $e) {
			$this->server->log('WOPI: => %d: %s', $e->getCode(), $e->getMessage());
			http_response_code($e->getCode());
			header('Content-Type: application/json', true);
			echo json_encode(['error' => $e->getMessage()]);
		}

		return true;
	}

	/**
	 * Output file informations in JSON
	 */
	protected function getInfo(string $uri, array $props): bool
	{
		$modified = !empty($props['DAV::getlastmodified']) ? $props['DAV::getlastmodified']->format(DATE_ISO8601) : null;
		$size = $props['DAV::getcontentlength'] ?? null;
		$readonly = (bool) $props[self::PROP_READ_ONLY];

		$data = [
			'BaseFileName'            => basename($uri),
			'UserFriendlyName'        => $props[self::PROP_USER_NAME] ?? 'User',
			'OwnerId'                 => 0,
			'UserId'                  => $props[self::PROP_USER_ID] ?? 0,
			'Version'                 => $props['DAV::getetag'] ?? md5($uri . $size . $modified),
			'ReadOnly'                => $readonly,
			'UserCanWrite'            => !$readonly,
			'UserCanRename'           => !$readonly,
			'DisableCopy'             => $readonly,
			'UserCanNotWriteRelative' => true, // This requires you to implement file name UI
		];

		if (isset($props[self::PROP_USER_AVATAR])) {
			$data['UserExtraInfo'] = ['avatar' => $props[self::PROP_USER_AVATAR]];
		}

		if (isset($size)) {
			$data['Size'] = $size;
		}

		if ($modified) {
			$data['LastModifiedTime'] = $modified;
		}


		$json = json_encode($data, JSON_PRETTY_PRINT);
		$this->server->log('WOPI: => Info: %s', $json);

		http_response_code(200);
		header('Content-Type: application/json', true);
		echo $json;
		return true;
	}

	/**
	 * Return list of available editors
	 * @param  string $url WOPI client discovery URL (eg. http://localhost:8080/hosting/discovery for OnlyOffice)
	 * @return an array containing a list of extensions and (eventually) a list of mimetypes
	 * that can be handled by the editor server:
	 * ['extensions' => [
	 *   'odt' => ['edit' => 'http://...', 'embedview' => 'http://'],
	 *   'ods' => ...
	 * ], 'mimetypes' => [
	 *   'application/vnd.oasis.opendocument.presentation' => ['edit' => 'http://'...],
	 * ]]
	 */
	static public function discover(string $url): array
	{
		if (function_exists('curl_init')) {
			$c = curl_init($url);
			curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
			$r = curl_exec($c);
			$code = curl_getinfo($c, CURLINFO_HTTP_CODE);

			if ($code != 200) {
				throw new \RuntimeException(sprintf("Discovery URL returned an error: %d\n%s", $code, $r));
			}

			curl_close($c);
		}
		else {
			$r = file_get_contents($url);
			$ok = false;

			foreach ($http_response_header as $h) {
				if (0 === strpos($h, 'HTTP/') && false !== strpos($h, '200')) {
					$ok = true;
					break;
				}
			}

			if (!$ok || empty($r)) {
				throw new \RuntimeException(sprintf("Discovery URL returned an error:\n%s", $r));
			}
		}

		if (false !== strpos($r, '<!DOCTYPE ')) {
			throw new \RuntimeException('Invalid XML returned by discovery');
		}

		$xml = @simplexml_load_string($r);

		if (!is_object($xml)) {
			throw new \RuntimeException('Invalid XML returned by discovery');
		}

		$extensions = [];
		$mimetypes = [];

		foreach ($xml->xpath('/wopi-discovery/net-zone/app') as $app) {
			$name = (string) $app['name'];
			$mime = null;

			if (strpos($name, '/')) {
				$mime = $name;

				if (!isset($mimetypes[$mime])) {
					$mimetypes[$mime] = [];
				}
			}

			foreach ($app->children() as $child) {
				if ($child->getName() != 'action') {
					continue;
				}

				$ext = (string) $child['ext'];
				$action = (string) $child['name'];
				$url = (string) $child['urlsrc'];

				if ($mime) {
					$mimetypes[$mime][$action] = $url;
				}
				else {
					if (!isset($extensions[$ext])) {
						$extensions[$ext] = [];
					}

					$extensions[$ext][$action] = $url;
				}
			}
		}

		unset($xml, $app, $child);

		return compact('extensions', 'mimetypes');
	}

	/**
	 * Return list of available options for editor URL
	 * This is called "Discovery query parameters" by OnlyOffice:
	 * https://api.onlyoffice.com/editors/wopi/discovery#wopi-standart
	 */
	public function getEditorAvailableOptions(string $url): array
	{
		$query = parse_url($url, PHP_URL_QUERY);

		preg_match_all('/<(\w+)=(\w+)&>/i', $query, $match, PREG_SET_ORDER);
		$options = [];

		foreach ($match as $m) {
			$options[$m[1]] = $m[2];
		}

		return $options;
	}

	/**
	 * Set query parameters for editor URL
	 */
	public function setEditorOptions(string $url, array $options = []): string
	{
		$url = parse_url($url);

		// Remove available options from URL
		$url['query'] = preg_replace('/<(\w+)=(\w+)&>/i', '', $url['query'] ?? '');

		// Set options
		parse_str($url['query'], $params);
		$params = array_merge($params, $options);

		$host = $url['host'] . (!empty($url['port']) ? ':' . $url['port'] : '');
		$query = count($params) ? '?' . http_build_query($params) : '';
		$url = sprintf('%s://%s%s%s', $url['scheme'], $host, $url['path'], $query);
		return $url;
	}

	/**
	 * Return an HTML page for the WOPI editor
	 *
	 * WOPI properties (token, token TTL and local WOPI URL) will be discovered
	 * using the storage PROPFIND method.
	 *
	 * This is an quick and dirty method. To have a different way of generating tokens,
	 * use rawEditorHTML instead.
	 */
	public function getEditorHTML(string $editor_url, string $document_uri, string $title = 'Document'): string
	{
		// Trying to find properties from WebDAV propfind
		$props = $this->storage->propfind($document_uri, [self::PROP_TOKEN, self::PROP_TOKEN_TTL, self::PROP_WOPI_URL], 0);

		if (count($props) != 3) {
			throw new Exception('Missing properties for document', 500);
		}

		$src = $props[self::PROP_WOPI_URL] ?? null;
		$token = $props[self::PROP_TOKEN] ?? null;
		$token_ttl = $props[self::PROP_TOKEN_TTL] ?? (time() + 10 * 3600);

		if (!$token) {
			throw new Exception('Access forbidden: no token was created', 403);
		}

		return $this->rawEditorHTML($url, $token, $token_ttl, $title);
	}

	public function getEditorFrameHTML(string $editor_url, string $src, string $token, int $token_ttl)
	{
		// access_token_TTL: A 64-bit integer containing the number of milliseconds since January 1, 1970 UTC and representing the expiration date and time stamp of the access_token.
		$token_ttl *= 1000;

		// Append WOPI host URL
		$url = $this->setEditorOptions($editor_url, ['WOPISrc' => $src]);

		return <<<EOF
		<form target="frame" action="{$url}" method="post">
			<input name="access_token" value="{$token}" type="hidden" />
			<input name="access_token_ttl" value="{$token_ttl}" type="hidden" />
		</form>

		<iframe id="frame" name="frame" allow="autoplay camera microphone display-capture" allowfullscreen="true"></iframe>

		<script type="text/javascript">
		document.forms[0].submit();
		</script>
EOF;
	}

	public function rawEditorHTML(string $editor_url, string $src, string $token, int $token_ttl, string $title = 'Document'): string
	{
		$frame = $this->getEditorFrameHTML($editor_url, $src, $token, $token_ttl);

		return <<<EOF
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="utf-8" />
			<meta http-equiv="X-UA-Compatible" content="IE=edge" />
			<meta name="viewport" content="width=device-width" />
			<title>{$title}</title>
			<style type="text/css">
				body {
					margin: 0;
					padding: 0;
					overflow: hidden;
					-ms-content-zooming: none;
				}
				#frame {
					width: 100%;
					height: 100%;
					position: absolute;
					top: 0;
					left: 0;
					right: 0;
					bottom: 0;
					margin: 0;
					border: none;
					display: block;
				}
			</style>
		</head>

		<body>
			{$frame}
		</body>
		</html>
EOF;
	}

	/**
	 * Returns a base64 string safe for URLs
	 * @param  string $str
	 * @return string
	 */
	static public function base64_encode_url_safe($str)
	{
		return rtrim(strtr(base64_encode($str), '+/', '-_'), '=');
	}

	/**
	 * Decodes a URL safe base64 string
	 * @param  string $str
	 * @return string
	 */
	static public function base64_decode_url_safe($str)
	{
		return base64_decode(str_pad(strtr($str, '-_', '+/'), strlen($str) % 4, '=', STR_PAD_RIGHT));
	}
}
