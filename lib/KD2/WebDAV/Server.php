<?php
/*
	This file is part of KD2FW -- <https://kd2.org/>

	Copyright (c) 2001-2022+ BohwaZ <https://bohwaz.net/>
	All rights reserved.

	KD2FW is free software: you can redistribute it and/or modify
	it under the terms of the GNU Affero General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	KD2FW is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU Affero General Public License for more details.

	You should have received a copy of the GNU Affero General Public License
	along with KD2FW.  If not, see <https://www.gnu.org/licenses/>.
*/

namespace KD2\WebDAV;

use KD2\HTTP\Server as HTTP_Server;

/**
 * This is a minimal, lightweight, and self-supported WebDAV server
 * it does not require anything out of standard PHP, not even an XML library.
 * This makes it more secure by design, and also faster and lighter.
 *
 * - supports PROPFIND custom properties
 * - supports HTTP ranges for GET requests
 * - supports GZIP encoding for GET
 *
 * You have to extend the AbstractStorage class and implement all the abstract methods to
 * get a class-1 and 2 compliant server.
 *
 * By default, locking is simulated: nothing is really locked, like
 * in https://docs.rs/webdav-handler/0.2.0/webdav_handler/fakels/index.html
 *
 * You also have to implement the actual storage of properties for
 * PROPPATCH requests, by extending the 'setProperties' method.
 * But it's not required for WebDAV file storage, only for CardDAV/CalDAV.
 *
 * Differences with SabreDAV and RFC:
 * - If-Range are not implemented
 *
 * @author BohwaZ <https://bohwaz.net/>
 */
class Server
{
	// List of basic DAV properties that you should return if $requested_properties is NULL
	const BASIC_PROPERTIES = [
		'DAV::resourcetype', // should be empty for files, and 'collection' for directories
		'DAV::getcontenttype', // MIME type
		'DAV::getlastmodified', // File modification date (must be \DateTimeInterface)
		'DAV::getcontentlength', // file size
		'DAV::displayname', // File name for display
	];

	const EXTENDED_PROPERTIES = [
		'DAV::getetag',
		'DAV::creationdate',
		'DAV::lastaccessed',
		'DAV::ishidden', // Microsoft thingy
		'DAV::quota-used-bytes',
		'DAV::quota-available-bytes',
	];

	/**
	 * File modification time accepted properties
	 * @see https://github.com/mar10/wsgidav/issues/112
	 * @see https://github.com/sabre-io/dav/issues/1277
	 * @see https://github.com/owncloud/core/issues/33502
	 */
	const MODIFICATION_TIME_PROPERTIES = [
		'DAV::lastmodified',
		'DAV::creationdate',
		'DAV::getlastmodified',
		'urn:schemas-microsoft-com::Win32LastModifiedTime',
		'urn:schemas-microsoft-com::Win32CreationTime',
	];

	// Custom properties
	/**
	 * File MD5 hash
	 * Your implementation should return the hexadecimal encoded MD5 hash of the file
	 */
	const PROP_DIGEST_MD5 = 'urn:karadav:digest_md5';

	/**
	 * Empty value if you want to have the property found and empty, return this constant
	 */
	const EMPTY_PROP_VALUE = 'DAV::empty';

	const SHARED_LOCK = 'shared';
	const EXCLUSIVE_LOCK = 'exclusive';

	/**
	 * This is only to get around PHP 8.5 useless depreciation
	 */
	const DATE_RFC7231 = "D, d M Y H:i:s \\G\\M\\T";

	/**
	 * Enable on-the-fly gzip compression
	 * This can use a large amount of resources
	 * @var boolean
	 */
	protected bool $enable_gzip = true;

	/**
	 * Base server URI (eg. "/index.php/webdav/")
	 */
	protected string $base_uri;

	/**
	 * Original URI passed to route() before trim
	 */
	public string $original_uri;

	/**
	 * Prefix, if you want to force some clients inside a path, eg. '/documents/'
	 */
	public string $prefix = '';

	protected AbstractStorage $storage;

	protected array $headers;

	public function __construct()
	{
		$this->headers = apache_request_headers();
		$this->headers = array_change_key_case($this->headers, \CASE_LOWER);
	}

	public function getHeader(string $name): ?string
	{
		return $this->headers[strtolower($name)] ?? null;
	}

	public function setHeader(string $name, string $value): void
	{
		$this->headers[strtolower($name)] = $value;
	}

	public function setStorage(AbstractStorage $storage)
	{
		$this->storage = $storage;
	}

	public function getStorage(): AbstractStorage
	{
		return $this->storage;
	}

	public function setBaseURI(string $uri): void
	{
		$this->base_uri = '/' . ltrim($uri, '/');
		$this->base_uri = rtrim($this->base_uri, '/') . '/';
	}

	/**
	 * Extend max_execution_time so that upload/download of files don't expire if connection is slow
	 */
	protected function extendExecutionTime(): void
	{
		if (false === strpos(@ini_get('disable_functions'), 'set_time_limit')) {
			@set_time_limit(3600);
		}

		@ini_set('max_execution_time', '3600');
		@ini_set('max_input_time', '3600');
	}

	protected function _prefix(string $uri): string
	{
		if (!$this->prefix) {
			return $uri;
		}

		return rtrim(rtrim($this->prefix, '/') . '/' . ltrim($uri, '/'), '/');
	}

	protected function html_directory(string $uri, iterable $list): ?string
	{
		// Not a file: let's serve a directory listing if you are browsing with a web browser
		if (substr($this->original_uri, -1) != '/') {
			http_response_code(301);
			header(sprintf('Location: /%s/', trim($this->base_uri . $uri, '/')), true);
			return null;
		}

		$out = sprintf('<!DOCTYPE html><html data-webdav-url="%s"><head><meta name="viewport" content="width=device-width, initial-scale=1.0, target-densitydpi=device-dpi" /><style>
			body { font-size: 1.1em; font-family: Arial, Helvetica, sans-serif; }
			table { border-collapse: collapse; }
			th, td { padding: .5em; text-align: left; border: 2px solid #ccc; }
			span { font-size: 40px; line-height: 40px; }
			</style>', '/' . ltrim($this->base_uri, '/'));

		$out .= sprintf('<title>%s</title></head><body><h1>%1$s</h1><table>', htmlspecialchars($uri ? str_replace('/', ' / ', $uri) . ' - Files' : 'Files'));

		if (trim($uri)) {
			$out .= '<tr><th colspan=3><a href="../"><b>Back</b></a></th></tr>';
		}

		$props = null;

		foreach ($list as $file => $props) {
			if (null === $props) {
				$props = $this->storage->propfind(trim($uri . '/' . $file, '/'), self::BASIC_PROPERTIES, 0);
			}

			$collection = !empty($props['DAV::resourcetype']) && $props['DAV::resourcetype'] == 'collection';

			if ($collection) {
				$out .= sprintf('<tr><td>[DIR]</td><th><a href="%s/"><b>%s</b></a></th></tr>', rawurlencode($file), htmlspecialchars($file));
			}
			else {
				$size = $props['DAV::getcontentlength'];

				if ($size > 1024*1024) {
					$size = sprintf('%d MB', $size / 1024 / 1024);
				}
				elseif ($size) {
					$size = sprintf('%d KB', $size / 1024);
				}

				$date = $props['DAV::getlastmodified'];

				if ($date instanceof \DateTimeInterface) {
					$date = $date->format('d/m/Y H:i');
				}

				$out .= sprintf('<tr><td></td><th><a href="%s">%s</a></th><td>%s</td><td>%s</td></tr>',
					rawurlencode($file),
					htmlspecialchars($file),
					$size,
					$date
				);
			}
		}

		$out .= '</table>';

		if (null === $props) {
			$out .= '<p>This directory is empty.</p>';
		}

		$out .= '</body></html>';

		return $out;
	}


	public function http_delete(string $uri): ?string
	{
		// check RFC 2518 Section 9.2, last paragraph
		if (isset($_SERVER['HTTP_DEPTH']) && $_SERVER['HTTP_DEPTH'] != 'infinity') {
			throw new Exception('We can only delete to infinity', 400);
		}

		$uri = $this->_prefix($uri);

		$this->checkLock($uri);

		$this->storage->delete($uri);

		if ($token = $this->getLockToken()) {
			$this->storage->unlock($uri, $token);
		}

		http_response_code(204);
		header('Content-Length: 0', true);
		return null;
	}

	public function http_put(string $uri): ?string
	{
		$content_type = $this->getHeader('Content-Type');

		if ($content_type && !strncmp($content_type, 'multipart/', 10)) {
			throw new Exception('Multipart PUT requests are not supported', 501);
		}

		$content_encoding = $this->getHeader('Content-Encoding');

		if ($content_encoding) {
			if (false !== strpos($content_encoding, 'gzip')) {
				// Might be supported later?
				throw new Exception('Content Encoding is not supported', 501);
			}
			else {
				throw new Exception('Content Encoding is not supported', 501);
			}
		}

		if ($this->getHeader('Content-Range')) {
			throw new Exception('Content Range is not supported', 501);
		}

		// See SabreDAV CorePlugin for reason why OS/X Finder is buggy
		if ($this->getHeader('X-Expected-Entity-Length')) {
			throw new Exception('This server is not compatible with OS/X finder. Consider using a different WebDAV client or webserver.', 403);
		}

		$hash = null;
		$hash_algo = null;

		// Support for checksum matching
		// https://dcache.org/old/manuals/UserGuide-6.0/webdav.shtml#checksums
		if ($hash = $this->getHeader('Content-MD5')) {
			$hash = bin2hex(base64_decode($hash));
			$hash_algo = 'MD5';
		}
		// Support for ownCloud/NextCloud checksum
		// https://github.com/owncloud-archive/documentation/issues/2964
		elseif (($checksum = $this->getHeader('OC-Checksum'))
			&& preg_match('/MD5:[a-f0-9]{32}|SHA1:[a-f0-9]{40}/', $checksum, $match)) {
			$hash_algo = strtok($match[0], ':');
			$hash = strtok('');
		}

		$uri = $this->_prefix($uri);

		$this->checkLock($uri);

		if ($match = $this->getHeader('If-Match')) {
			$etag = trim($match, '" ');
			$prop = $this->storage->propfind($uri, ['DAV::getetag'], 0);

			if (!empty($prop['DAV::getetag']) && $prop['DAV::getetag'] != $etag) {
				throw new Exception('ETag did not match condition', 412);
			}
		}

		if ($date = $this->getHeader('If-Unmodified-Since')) {
			$date = \DateTime::createFromFormat(self::DATE_RFC7231, $date);
			$prop = $this->storage->propfind($uri, ['DAV::getlastmodified'], 0);
			if ($date && $prop && $prop instanceof \DateTimeInterface) {
				if ($date != $prop) {
					throw new Exception('File was modified since "If-Unmodified-Since" condition', 412);
				}
			}
		}

		// Specific to NextCloud/ownCloud, to allow setting file mtime
		// This expects a UNIX timestamp
		$mtime = intval($this->getHeader('X-OC-MTime')) ?: null;

		$this->extendExecutionTime();

		$stream = fopen('php://input', 'r');

		// mod_fcgid <= 2.3.9 doesn't handle chunked transfer encoding for PUT requests
		// see https://github.com/kd2org/picodav/issues/6
		if (strstr($this->getHeader('Transfer-Encoding') ?? '', 'chunked') && PHP_SAPI == 'fpm-fcgi') {
			// We can't seek here
			// see https://github.com/php/php-src/issues/9441
			$l = strlen(fread($stream, 1));

			if ($l === 0) {
				throw new Exception('This server cannot accept "Transfer-Encoding: chunked" uploads (please upgrade to mod_fcgid >= 2.3.10).', 500);
			}

			// reset stream
			fseek($stream, 0, SEEK_SET);
		}

		$created = $this->storage->put($uri, $stream, $hash_algo, $hash);

		if ($mtime) {
			$mtime = new \DateTime('@' . $mtime);

			if ($this->storage->touch($uri, $mtime)) {
				header('X-OC-MTime: accepted');
			}
		}


		$prop = $this->storage->propfind($uri, ['DAV::getetag'], 0);

		if (!empty($prop['DAV::getetag'])) {
			$value = $prop['DAV::getetag'];

			if (substr($value, 0, 1) != '"') {
				$value = '"' . $value . '"';
			}

			header(sprintf('ETag: %s', $value));
		}

		http_response_code($created ? 201 : 204);
		return null;
	}

	public function http_head(string $uri, array &$props = []): ?string
	{
		$uri = $this->_prefix($uri);

		$requested_props = self::BASIC_PROPERTIES;
		$requested_props[] = 'DAV::getetag';

		// RFC 3230 https://www.rfc-editor.org/rfc/rfc3230.html
		if (!empty($_SERVER['HTTP_WANT_DIGEST'])) {
			$requested_props[] = self::PROP_DIGEST_MD5;
		}

		$props = $this->storage->propfind($uri, $requested_props, 0);

		if (!$props) {
			throw new Exception('Resource Not Found', 404);
		}

		http_response_code(200);

		if (isset($props['DAV::getlastmodified'])
			&& $props['DAV::getlastmodified'] instanceof \DateTimeInterface) {
			header(sprintf('Last-Modified: %s', $props['DAV::getlastmodified']->format(self::DATE_RFC7231)));
		}

		if (!empty($props['DAV::getetag'])) {
			$value = $props['DAV::getetag'];

			if (substr($value, 0, 1) != '"') {
				$value = '"' . $value . '"';
			}

			header(sprintf('ETag: %s', $value));
		}

		if (empty($props['DAV::resourcetype']) || $props['DAV::resourcetype'] != 'collection') {
			if (!empty($props['DAV::getcontenttype'])) {
				header(sprintf('Content-Type: %s', $props['DAV::getcontenttype']));
			}

			if (!empty($props['DAV::getcontentlength'])) {
				header(sprintf('Content-Length: %d', $props['DAV::getcontentlength']));
				header('Accept-Ranges: bytes');
			}
		}

		if (!empty($props[self::PROP_DIGEST_MD5])) {
			header(sprintf('Digest: md5=%s', base64_encode(hex2bin($props[self::PROP_DIGEST_MD5]))));
		}

		return null;
	}

	public function http_get(string $uri): ?string
	{
		$props = [];
		$this->http_head($uri, $props);

		$uri = $this->_prefix($uri);

		$is_collection = !empty($props['DAV::resourcetype']) && $props['DAV::resourcetype'] == 'collection';

		if ($is_collection) {
			$list = $this->storage->list($uri, self::BASIC_PROPERTIES);

			if (!isset($_SERVER['HTTP_ACCEPT']) || false === strpos($_SERVER['HTTP_ACCEPT'], 'html')) {
				$list = is_array($list) ? $list : iterator_to_array($list);

				if (!count($list)) {
					return "Nothing in this collection\n";
				}

				return implode("\n", array_keys($list));
			}

			header('Content-Type: text/html; charset=utf-8', true);

			return $this->html_directory($uri, $list);
		}

		$file = $this->storage->get($uri);

		if (!$file) {
			throw new Exception('File Not Found', 404);
		}

		// If the file was returned to the client by the storage backend, stop here
		if (!empty($file['stop'])) {
			return null;
		}

		try {
			HTTP_Server::serveFile(
				$file['content'] ?? null,
				$file['path'] ?? null,
				$file['resource'] ?? null,
				[
					'gzip'      => $this->enable_gzip,
					'ranges'    => true,
					'xsendfile' => false,
					'name'      => $uri,
					'size'      => $props['DAV::getcontentlength'],
				]
			);
		}
		catch (\LogicException $e) {
			throw new Exception($e->getMessage(), $e->getCode());
		}
		finally {
			if (isset($file['resource'])) {
				fclose($file['resource']);
			}
		}

		return null;
	}

	public function http_copy(string $uri): ?string
	{
		return $this->_http_copymove($uri, 'copy');
	}

	public function http_move(string $uri): ?string
	{
		return $this->_http_copymove($uri, 'move');
	}

	protected function _http_copymove(string $uri, string $method): ?string
	{
		$uri = $this->_prefix($uri);

		$destination = $_SERVER['HTTP_DESTINATION'] ?? null;
		$depth = $_SERVER['HTTP_DEPTH'] ?? 1;

		if (!$destination) {
			throw new Exception('Destination not supplied', 400);
		}

		$destination = $this->getURI($destination);

		if (trim($destination, '/') == trim($uri, '/')) {
			throw new Exception('Cannot move file to itself', 403);
		}

		// A value of "F" states that the server must not perform the COPY or MOVE
		// operation if the destination URL does map to a resource. If the overwrite
		// header is not included in a COPY or MOVE request, then the resource MUST
		// treat the request as if it has an overwrite header of value "T".
		$overwrite = !(($_SERVER['HTTP_OVERWRITE'] ?? null) === 'F');

		// Dolphin is removing the file name when moving to root directory
		if (empty($destination)) {
			$destination = basename($uri);
		}

		$this->log('<= Destination: %s', $destination);
		$this->log('<= Overwrite: %s (%s)', $overwrite ? 'Yes' : 'No', $_SERVER['HTTP_OVERWRITE'] ?? null);

		if (!$overwrite && $this->storage->exists($destination)) {
			throw new Exception('File already exists and overwriting is disabled', 412);
		}

		if ($method == 'move') {
			$this->checkLock($uri);
		}

		$this->checkLock($destination);

		// Moving/copy of directory to an existing destination and depth=0
		// should do just nothing, see 'depth_zero_copy' test in litmus
		if ($depth == 0
			&& $this->storage->exists($destination)
			&& current($this->storage->propfind($destination, ['DAV::resourcetype'], 0)) == 'collection') {
			$overwritten = $this->storage->exists($uri);
		}
		else {
			$overwritten = $this->storage->$method($uri, $destination);
		}

		if ($method == 'move' && ($token = $this->getLockToken())) {
			$this->storage->unlock($uri, $token);
		}

		http_response_code($overwritten ? 204 : 201);
		return null;
	}

	public function http_mkcol(string $uri): ?string
	{
		if (!empty($_SERVER['CONTENT_LENGTH'])) {
			throw new Exception('Unsupported body for MKCOL', 415);
		}

		$uri = $this->_prefix($uri);
		$this->storage->mkcol($uri);

		http_response_code(201);
		return null;
	}

	/**
	 * Return a list of requested properties, if any.
	 * We are using regexp as we don't want to depend on a XML module here.
	 * Your are free to re-implement this using a XML parser if you wish
	 */
	protected function extractRequestedProperties(string $body): ?array
	{
		// We only care about properties if the client asked for it
		// If not, we consider that the client just requested to get everything
		if (!preg_match('!<(?:\w+:)?propfind!', $body)) {
			return null;
		}

		$ns = [];
		$dav_ns = null;
		$default_ns = null;

		if (preg_match('/<propfind[^>]+xmlns="DAV:"/', $body)) {
			$default_ns = 'DAV:';
		}

		preg_match_all('!xmlns:(\w+)\s*=\s*"([^"]+)"!', $body, $match, PREG_SET_ORDER);

		// Find all aliased xmlns
		foreach ($match as $found) {
			$ns[$found[2]] = $found[1];
		}

		if (isset($ns['DAV:'])) {
			$dav_ns = $ns['DAV:'] . ':';
		}

		$regexp = '/<(' . $dav_ns . 'prop(?!find))[^>]*?>(.*?)<\/\1\s*>/s';
		if (!preg_match($regexp, $body, $match)) {
			return null;
		}

		// Find all properties
		// Allow for empty namespace, see Litmus FAQ for propnullns
		// https://github.com/tolsen/litmus/blob/master/FAQ
		preg_match_all('!<([\w-]+)[^>]*xmlns="([^"]*)"|<(?:([\w-]+):)?([\w-]+)!', $match[2], $match, PREG_SET_ORDER);

		$properties = [];

		foreach ($match as $found) {
			if (isset($found[4])) {
				$url = array_search($found[3], $ns) ?: $default_ns;
				$name = $found[4];
			}
			else {
				$url = $found[2];
				$name = $found[1];
			}

			$properties[$url . ':' . $name] = [
				'name' => $name,
				'ns_alias' => $found[3] ?? null,
				'ns_url' => $url,
			];
		}

		return $properties;
	}

	public function http_propfind(string $uri): ?string
	{
		// We only support depth of 0 and 1
		$depth = isset($_SERVER['HTTP_DEPTH']) && empty($_SERVER['HTTP_DEPTH']) ? 0 : 1;

		$uri = $this->_prefix($uri);
		$body = file_get_contents('php://input');

		if (false !== strpos($body, '<!DOCTYPE ')) {
			throw new Exception('Invalid XML', 400);
		}

		$this->log('<= Requested depth: %s', $depth);

		// We don't really care about having a correct XML string,
		// but we can get better WebDAV compliance if we do
		if (isset($_SERVER['HTTP_X_LITMUS'])) {
			if (false !== strpos($body, '<!DOCTYPE ')) {
				throw new Exception('Invalid XML', 400);
			}

			$xml = @simplexml_load_string($body);

			if ($e = libxml_get_last_error()) {
				throw new Exception('Invalid XML', 400);
			}
		}

		$requested = $this->extractRequestedProperties($body);
		$requested_keys = $requested ? array_keys($requested) : null;

		// Find root element properties
		$properties = $this->storage->propfind($uri, $requested_keys, $depth);

		if (null === $properties) {
			throw new Exception('This does not exist', 404);
		}

		if (isset($properties['DAV::getlastmodified'])) {
			foreach (self::MODIFICATION_TIME_PROPERTIES as $name) {
				$properties[$name] = $properties['DAV::getlastmodified'];
			}
		}

		$items = [$uri => $properties];

		if ($depth) {
			foreach ($this->storage->list($uri, $requested) as $file => $properties) {
				$path = trim($uri . '/' . $file, '/');
				$properties = $properties ?? $this->storage->propfind($path, $requested_keys, 0);

				if (!$properties) {
					$this->log('!! Cannot find "%s"', $path);
					continue;
				}

				$items[$path] = $properties;
			}
		}

		// http_response_code doesn't know the 207 status code
		header('HTTP/1.1 207 Multi-Status', true);
		$this->dav_header();
		header('Content-Type: application/xml; charset=utf-8');

		$root_namespaces = [
			'DAV:' => 'd',
			// Microsoft Clients need this special namespace for date and time values (from PEAR/WebDAV)
			'urn:uuid:c2f41010-65b3-11d1-a29f-00aa00c14882/' => 'ns0',
		];

		$i = 0;
		$requested ??= [];

		foreach ($requested as $prop) {
			if ($prop['ns_url'] == 'DAV:' || !$prop['ns_url']) {
				continue;
			}

			if (!array_key_exists($prop['ns_url'], $root_namespaces)) {
				$root_namespaces[$prop['ns_url']] = $prop['ns_alias'] ?: 'rns' . $i++;
			}
		}

		foreach ($items as $properties) {
			foreach ($properties as $name => $value) {
				$pos = strrpos($name, ':');
				$ns = substr($name, 0, strrpos($name, ':'));

				// NULL namespace, see Litmus FAQ for propnullns
				if (!$ns) {
					continue;
				}

				if (!array_key_exists($ns, $root_namespaces)) {
					$root_namespaces[$ns] = 'rns' . $i++;
				}
			}
		}

		$out = '<?xml version="1.0" encoding="utf-8"?>';
		$out .= '<d:multistatus';

		foreach ($root_namespaces as $url => $alias) {
			$out .= sprintf(' xmlns:%s="%s"', $alias, $url);
		}

		$out .= '>';

		foreach ($items as $uri => $item) {
			$e = '<d:response>';

			if ($this->prefix) {
				$uri = substr($uri, strlen($this->prefix));
			}

			$uri = trim(rtrim($this->base_uri, '/') . '/' . ltrim($uri, '/'), '/');
			$path = '/' . str_replace('%2F', '/', rawurlencode($uri));

			if (($item['DAV::resourcetype'] ?? null) == 'collection' && $path != '/') {
				$path .= '/';
			}

			$e .= sprintf('<d:href>%s</d:href>', htmlspecialchars($path, ENT_XML1));
			$e .= '<d:propstat><d:prop>';

			foreach ($item as $name => $value) {
				if (null === $value) {
					continue;
				}

				$pos = strrpos($name, ':');
				$ns = substr($name, 0, $pos);
				$tag_name = substr($name, $pos + 1);

				$alias = $root_namespaces[$ns] ?? null;
				$attributes = '';

				// The ownCloud/OpenCloud Android app doesn't like formatted dates, it makes it crash.
				// so force it to have a timestamp
				// see https://github.com/opencloud-eu/android/issues/74
				if ($name == 'DAV::creationdate'
					&& ($value instanceof \DateTimeInterface)
					&& false !== preg_match('/owncloud|opencloud/', $_SERVER['HTTP_USER_AGENT'] ?? '')) {
					$value = $value->getTimestamp();
				}
				// ownCloud app crashes if mimetype is provided for a directory
				// https://github.com/owncloud/android/issues/3768
				elseif ($name == 'DAV::getcontenttype'
					&& ($item['DAV::resourcetype'] ?? null) == 'collection') {
					$value = null;
				}

				if ($name == 'DAV::resourcetype' && $value == 'collection') {
					$value = '<d:collection />';
				}
				elseif ($name == 'DAV::getetag' && strlen($value) && $value[0] != '"') {
					$value = '"' . $value . '"';
				}
				elseif ($value instanceof \DateTimeInterface) {
					// Change value to GMT
					$value = clone $value;
					$value->setTimezone(new \DateTimeZone('GMT'));
					$value = $value->format(self::DATE_RFC7231);
				}
				elseif (is_array($value)) {
					$attributes = $value['attributes'] ?? '';
					$value = $value['xml'] ?? null;
				}
				else {
					$value = htmlspecialchars($value, ENT_XML1);
				}

				// NULL namespace, see Litmus FAQ for propnullns
				if (!$ns) {
					$attributes .= ' xmlns=""';
				}
				else {
					$tag_name = $alias . ':' . $tag_name;
				}

				if (null === $value || self::EMPTY_PROP_VALUE === $value) {
					$e .= sprintf('<%s%s />', $tag_name, $attributes ? ' ' . $attributes : '');
				}
				else {
					$e .= sprintf('<%s%s>%s</%1$s>', $tag_name, $attributes ? ' ' . $attributes : '', $value);
				}
			}

			$e .= '</d:prop><d:status>HTTP/1.1 200 OK</d:status></d:propstat>' . "\n";

			// Append missing properties
			if (!empty($requested)) {
				$missing_properties = array_diff($requested_keys, array_keys($item));

				if (count($missing_properties)) {
					$e .= '<d:propstat><d:prop>';

					foreach ($missing_properties as $name) {
						$pos = strrpos($name, ':');
						$ns = substr($name, 0, $pos);
						$name = substr($name, $pos + 1);
						$alias = $root_namespaces[$ns] ?? null;

						// NULL namespace, see Litmus FAQ for propnullns
						if (!$alias) {
							$e .= sprintf('<%s xmlns="" />', $name);
						}
						else {
							$e .= sprintf('<%s:%s />', $alias, $name);
						}
					}

					$e .= '</d:prop><d:status>HTTP/1.1 404 Not Found</d:status></d:propstat>';
				}
			}

			$e .= '</d:response>' . "\n";
			$out .= $e;
		}

		$out .= '</d:multistatus>';

		return $out;
	}

	static public function parsePropPatch(string $body): array
	{
		if (false !== strpos($body, '<!DOCTYPE ')) {
			throw new Exception('Invalid XML', 400);
		}

		$xml = @simplexml_load_string($body);

		if (false === $xml) {
			throw new Exception('Invalid XML', 400);
		}

		$_ns = null;

		// Select correct namespace if required
		if (!empty(key($xml->getDocNameSpaces()))) {
			$_ns = 'DAV:';
		}

		$out = [];

		// Process set/remove instructions in order (important)
		foreach ($xml->children($_ns) as $child) {
			foreach ($child->children($_ns) as $prop) {
				$prop = $prop->children();
				if ($child->getName() == 'set') {
					$ns = $prop->getNamespaces(true);
					$ns = array_flip($ns);
					$name = key($ns) . ':' . $prop->getName();

					$attributes = $prop->attributes();
					$attributes = $attributes === null ? null : iterator_to_array($attributes);

					foreach ($ns as $xmlns => $alias) {
						foreach (iterator_to_array($prop->attributes($alias)) as $key => $v) {
							$attributes[$xmlns . ':' . $key] = $value;
						}
					}

					if ($prop->count() > 1) {
						$text = '';

						foreach ($prop->children() as $c) {
							$text .= $c->asXML();
						}
					}
					else {
						$text = (string)$prop;
					}

					$out[$name] = ['action' => 'set', 'attributes' => $attributes ?: null, 'content' => $text ?: null];
				}
				else {
					$ns = $prop->getNamespaces();
					$name = current($ns) . ':' . $prop->getName();
					$out[$name] = ['action' => 'remove'];
				}
			}
		}

		return $out;
	}

	public function http_proppatch(string $uri): ?string
	{
		$uri = $this->_prefix($uri);
		$this->checkLock($uri);

		$prefix = '<?xml version="1.0" encoding="utf-8"?>' . "\n";
		$prefix.= '<d:multistatus xmlns:d="DAV:"';
		$suffix = "</d:multistatus>\n";

		$body = file_get_contents('php://input');

		$properties = $this->parsePropPatch($body);
		$root_namespaces = [];
		$i = 0;
		$set_time = null;
		$set_time_name = null;

		foreach ($properties as $name => $value) {
			$pos = strrpos($name, ':');
			$ns = substr($name, 0, $pos);

			if (!array_key_exists($ns, $root_namespaces)) {
				$alias = 'rns' . $i++;
				$root_namespaces[$ns] = $alias;
				$prefix .= sprintf(' xmlns:%s="%s"', $alias, htmlspecialchars($ns, ENT_XML1));
			}
		}

		// See if the client wants to set the modification time
		foreach (self::MODIFICATION_TIME_PROPERTIES as $name) {
			if (!array_key_exists($name, $properties) || $value['action'] !== 'set' || empty($value['content'])) {
				continue;
			}

			$ts = $value['content'];

			if (ctype_digit($ts)) {
				$ts = '@' . $ts;
			}

			$set_time = new \DateTime($ts);
			$set_time_name = $name;
		}

		$prefix .= sprintf(">\n<d:response>\n  <d:href>%s</d:href>\n", htmlspecialchars($uri, ENT_XML1));

		// http_response_code doesn't know the 207 status code
		header('HTTP/1.1 207 Multi-Status', true);
		header('Content-Type: application/xml; charset=utf-8', true);

		if (!count($properties)) {
			return $prefix . $suffix;
		}

		if ($set_time) {
			unset($properties[$set_time_name]);
		}

		$return = $this->storage->proppatch($uri, $properties);

		if ($set_time && $this->storage->touch($uri, $set_time)) {
			$return[$set_time_name] = 200;
		}

		$out = '';

		static $messages = [
			200 => 'OK',
			403 => 'Forbidden',
			409 => 'Conflict',
			427 => 'Failed Dependency',
			507 => 'Insufficient Storage',
		];

		foreach ($return as $name => $status) {
			$pos = strrpos($name, ':');
			$ns = substr($name, 0, $pos);
			$name = substr($name, $pos + 1);

			$out .= "  <d:propstat>\n    <d:prop>";
			$out .= sprintf("<%s:%s /></d:prop>\n    <d:status>HTTP/1.1 %d %s</d:status>",
				$root_namespaces[$ns],
				$name,
				$status,
				$messages[$status] ?? ''
			);
			$out .= "\n  </d:propstat>\n";
		}

		$out .= "</d:response>\n";

		return $prefix . $out . $suffix;
	}

	public function http_lock(string $uri): ?string
	{
		$uri = $this->_prefix($uri);
		// We don't use this currently, but maybe later?
		//$depth = !empty($this->_SERVER['HTTP_DEPTH']) ? 1 : 0;
		//$timeout = isset($_SERVER['HTTP_TIMEOUT']) ? explode(',', $_SERVER['HTTP_TIMEOUT']) : [];
		//$timeout = array_map('trim', $timeout);

		if (empty($_SERVER['CONTENT_LENGTH']) && !empty($_SERVER['HTTP_IF'])) {
			$token = $this->getLockToken();

			if (!$token) {
				throw new Exception('Invalid If header', 400);
			}

			$ns = 'D';
			$scope = self::EXCLUSIVE_LOCK;

			$this->checkLock($uri, $token);
			$this->log('Requesting LOCK refresh: %s = %s', $uri, $scope);
		}
		else {
			$locked_scope = $this->storage->getLock($uri);

			if ($locked_scope == self::EXCLUSIVE_LOCK) {
				throw new Exception('Cannot acquire another lock, resource is locked for exclusive use', 423);
			}

			if ($locked_scope) {
				$token = $this->getLockToken();

				if (!$token) {
					throw new Exception('Missing lock token', 423);
				}

				$this->checkLock($uri, $token);
			}

			$xml = file_get_contents('php://input');

			if (!preg_match('!<((?:(\w+):)?lockinfo)[^>]*>(.*?)</\1>!is', $xml, $match)) {
				throw new Exception('Invalid XML', 400);
			}

			$ns = $match[2];
			$info = $match[3];

			// Quick and dirty UUID
			$uuid = random_bytes(16);
			$uuid[6] = chr(ord($uuid[6]) & 0x0f | 0x40); // set version to 0100
			$uuid[8] = chr(ord($uuid[8]) & 0x3f | 0x80); // set bits 6-7 to 10
			$uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($uuid), 4));

			$token = 'opaquelocktoken:' . $uuid;
			$scope = false !== stripos($info, sprintf('<%sexclusive', $ns ? $ns . ':' : '')) ? self::EXCLUSIVE_LOCK : self::SHARED_LOCK;

			$this->log('Requesting LOCK: %s = %s', $uri, $scope);
		}

		$this->storage->lock($uri, $token, $scope);

		$timeout = 60*5;
		$info = sprintf('
			<d:lockscope><d:%s /></d:lockscope>
			<d:locktype><d:write /></d:locktype>
			<d:owner>unknown</d:owner>
			<d:depth>%d</d:depth>
			<d:timeout>Second-%d</d:timeout>
			<d:locktoken><d:href>%s</d:href></d:locktoken>
		', $scope, 1, $timeout, $token);

		http_response_code(200);
		header('Content-Type: application/xml; charset=utf-8');
		header(sprintf('Lock-Token: <%s>', $token));

		$out = '<?xml version="1.0" encoding="utf-8"?>' . "\n";
		$out .= '<d:prop xmlns:d="DAV:">';
		$out .= '<d:lockdiscovery><d:activelock>';

		$out .= $info;

		$out .= '</d:activelock></d:lockdiscovery></d:prop>';

		if ($ns != 'D') {
			$out = str_replace('D:', $ns ? $ns . ':' : '', $out);
			$out = str_replace('xmlns:D', $ns ? 'xmlns:' . $ns : 'xmlns', $out);
		}

		return $out;
	}

	public function http_unlock(string $uri): ?string
	{
		$uri = $this->_prefix($uri);
		$token = $this->getLockToken();

		if (!$token) {
			throw new Exception('Invalid Lock-Token header', 400);
		}

		$this->log('<= Lock Token: %s', $token);

		$this->checkLock($uri, $token);

		$this->storage->unlock($uri, $token);

		http_response_code(204);
		return null;
	}

	/**
	 * Return current lock token supplied by client
	 */
	protected function getLockToken(): ?string
	{
		if (isset($_SERVER['HTTP_LOCK_TOKEN'])
			&& preg_match('/<(.*?)>/', trim($_SERVER['HTTP_LOCK_TOKEN']), $match)) {
			return $match[1];
		}
		elseif (isset($_SERVER['HTTP_IF'])
			&& preg_match('/\(<(.*?)>\)/', trim($_SERVER['HTTP_IF']), $match)) {
			return $match[1];
		}
		else {
			return null;
		}
	}

	/**
	 * Check if the resource is protected
	 * @throws Exception if the resource is locked
	 */
	protected function checkLock(string $uri, ?string $token = null): void
	{
		if ($token === null) {
			$token = $this->getLockToken();
		}

		// Trying to access using a parent directory
		if (isset($_SERVER['HTTP_IF'])
			&& preg_match('/<([^>]+)>\s*\(<[^>]*>\)/', $_SERVER['HTTP_IF'], $match)) {
			$root = $this->getURI($match[1]);

			if (0 !== strpos($uri, $root)) {
				throw new Exception('Invalid "If" header path: ' . $root, 400);
			}

			$uri = $root;
		}
		// Try to validate token
		elseif (isset($_SERVER['HTTP_IF'])
			&& preg_match('/\(<([^>]*)>\s+\["([^""]+)"\]\)/', $_SERVER['HTTP_IF'], $match)) {
			$token = $match[1];
			$request_etag = $match[2];
			$etag = current($this->storage->propfind($uri, ['DAV::getetag'], 0));

			if ($request_etag != $etag) {
				throw new Exception('Resource is locked and etag does not match', 412);
			}
		}

		if ($token == 'DAV:no-lock') {
			throw new Exception('Resource is locked', 412);
		}

		// Token is valid
		if ($token && $this->storage->getLock($uri, $token)) {
			return;
		}
		elseif ($token) {
			throw new Exception('Invalid token', 400);
		}
		// Resource is locked
		elseif ($this->storage->getLock($uri)) {
			throw new Exception('Resource is locked', 423);
		}
	}

	protected function dav_header()
	{
		header('DAV: 1, 2, 3');
	}

	public function http_options(): void
	{
		http_response_code(200);
		$methods = 'GET HEAD PUT DELETE COPY MOVE PROPFIND MKCOL LOCK UNLOCK';

		$this->dav_header();

		header('Allow: ' . $methods);
		header('Content-length: 0');
		header('Accept-Ranges: bytes');
		header('MS-Author-Via: DAV');
	}

	public function log(string $message, ...$params)
	{
		if (PHP_SAPI == 'cli-server') {
			file_put_contents('php://stderr', vsprintf($message, $params) . "\n");
		}
	}

	public function validateURI(string $uri): string
	{
		$uri = preg_replace('!/{2,}!', '/', $uri);
		$uri = str_replace('\\', '/', $uri);

		// Protect against path traversal
		if (preg_match('!(?:^|/)\.\.(?:$|/)!', $uri)) {
			throw new Exception(sprintf('Invalid URI: "%s"', $uri), 403);
		}

		return $uri;
	}

	protected function getURI(string $source): string
	{
		$uri = parse_url($source, PHP_URL_PATH);
		$uri = rawurldecode($uri);
		$uri = trim($uri, '/');
		$uri = '/' . $uri;

		if ($uri . '/' === $this->base_uri) {
			$uri .= '/';
		}

		if (strpos($uri, $this->base_uri) !== 0) {
			throw new Exception(sprintf('Invalid URI, "%s" is outside of scope "%s"', $uri, $this->base_uri), 400);
		}

		$uri = $this->validateURI($uri);

		$uri = substr($uri, strlen($this->base_uri));
		$uri = $this->_prefix($uri);
		return $uri;
	}

	public function route(?string $uri = null): bool
	{
		if (null === $uri) {
			$uri = $_SERVER['REQUEST_URI'] ?? '/';
		}

		$uri = '/' . ltrim($uri, '/');
		$this->original_uri = $uri;

		if ($uri . '/' == $this->base_uri) {
			$uri .= '/';
		}

		if (0 === strpos($uri, $this->base_uri)) {
			$uri = substr($uri, strlen($this->base_uri));
		}
		else {
			$this->log('=> %s is not a managed URL (%s)', $uri, $this->base_uri);
			return false;
		}

		// Add some extra-logging for Litmus tests
		if (isset($_SERVER['HTTP_X_LITMUS']) || isset($_SERVER['HTTP_X_LITMUS_SECOND'])) {
			$this->log('X-Litmus: %s', $_SERVER['HTTP_X_LITMUS'] ?? $_SERVER['HTTP_X_LITMUS_SECOND']);
		}

		$method = $_SERVER['REQUEST_METHOD'] ?? null;

		header_remove('Expires');
		header_remove('Pragma');
		header_remove('Cache-Control');
		header('X-Server: KD2', true);

		// Stop and send reply to OPTIONS before anything else
		if ($method == 'OPTIONS') {
			$this->log('<= OPTIONS');
			$this->http_options();
			return true;
		}

		$uri = rawurldecode($uri);
		$uri = trim($uri, '/');
		$uri = preg_replace('!/{2,}!', '/', $uri);

		$this->log('<= %s /%s', $method, $uri);

		try {
			$uri = $this->validateURI($uri);

			// Call 'http_method' class method
			$method = 'http_' . strtolower($method);

			if (!method_exists($this, $method)) {
				throw new Exception('Invalid request method', 405);
			}

			$out = $this->$method($uri);

			$this->log('=> %d', http_response_code());

			if (null !== $out) {
				$this->log('=> %s', $out);
			}

			echo $out;
		}
		catch (Exception $e) {
			$this->error($e);
		}

		return true;
	}

	function error(Exception $e)
	{
		$this->log('=> %d - %s', $e->getCode(), $e->getMessage());

		if ($e->getCode() == 423) {
			// http_response_code doesn't know about 423 Locked
			header('HTTP/1.1 423 Locked');
		}
		else {
			http_response_code($e->getCode());
		}

		header('Content-Type: application/xml; charset=utf-8', true);

		printf('<?xml version="1.0" encoding="utf-8"?><d:error xmlns:d="DAV:" xmlns:s="http://sabredav.org/ns"><s:message>%s</s:message></d:error>', htmlspecialchars($e->getMessage(), ENT_XML1));
	}

	/**
	 * Utility function to create HMAC hash of data, useful for NextCloud and WOPI
	 */
	static public function hmac(array $data, string $key = '')
	{
		// Protect against length attacks by pre-hashing data
		$data = array_map('sha1', $data);
		$data = implode(':', $data);

		return hash_hmac('sha1', $data, sha1($key));
	}
}
