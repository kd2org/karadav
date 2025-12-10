<?php

namespace KaraDAV;

use KD2\WebDAV\NextCloud as WebDAV_NextCloud;
use KD2\WebDAV\Exception as WebDAV_Exception;
use KD2\Graphics\SVG\Avatar;
use KD2\Graphics\Image;

class NextCloud extends WebDAV_NextCloud
{
	protected Users $users;
	protected ?\stdClass $user;
	protected string $temporary_chunks_path;

	public function __construct(Users $users)
	{
		$this->users = $users;
		$this->temporary_chunks_path =  sprintf(STORAGE_PATH, '_chunks');
		$this->setRootURL(WWW_URL);

		// KaraDAV users are allowed to try iOS clients if they wish
		// @see https://github.com/kd2org/karadav/issues/22
		$this->block_ios_clients = BLOCK_IOS_APPS;

		$this->theme = [
			'name'                 => 'KaraDAV',
			'url'                  => 'https://fossil.kd2.org/karadav/',
			'slogan'               => 'lighter than NextCloud',
			'color'                => '#6f918a',
			'color-text'           => '#ffffff',
			'color-element'        => '#6f918a',
			'color-element-bright' => '#6f918a',
			'color-element-dark'   => '#6f918a',
			'logo'                 => '',
			'background'           => '#d3dddb',
			'background-text'      => '#000000',
			'background-plain'     => '',
			'background-default'   => '',
			'logoheader'           => $this->root_url . '/logo.svg',
			'favicon'              => $this->root_url . '/logo.svg',
		];
	}

	public function auth(?string $login, ?string $password): bool
	{
		$user = $this->users->login($login, $password);

		if (!$user) {
			// Try app session
			$user = $this->users->appSessionLogin($login, $password);
		}

		if (!$user) {
			return false;
		}

		$this->user = $user;

		return true;
	}

	public function getUserName(): ?string
	{
		return $this->users->current()->login ?? null;
	}

	public function setUserName(string $login): bool
	{
		$ok = $this->users->setCurrent($login);

		if ($ok) {
			$this->user  = $this->users->current();
		}

		return $ok;
	}

	public function getUserQuota(): array
	{
		return (array) $this->users->quota($this->users->current());
	}

	public function generateToken(): string
	{
		return sha1(random_bytes(16));
	}

	public function validateToken(string $token): ?array
	{
		$session = $this->users->appSessionValidateToken($token);

		if (!$session) {
			return null;
		}

		return ['login' => $session->user->login, 'password' => $session->password];
	}

	public function getLoginURL(?string $token): string
	{
		if ($token) {
			return sprintf('%slogin.php?nc=%s', WWW_URL, $token);
		}
		else {
			return sprintf('%slogin.php?nc=redirect', WWW_URL);
		}
	}

	public function getDirectDownloadSecret(string $uri, string $login): string
	{
		$user = $this->users->get($login);

		if (!$user) {
			throw new WebDAV_Exception('No user with that name', 401);
		}

		return WebDAV::hmac([$uri, $user->login, $user->password]);
	}

	protected function cleanChunks(): void
	{
		$expire = time() - 36*3600;

		foreach (glob($this->temporary_chunks_path . '/*/*') as $dir) {
			$first_file = current(glob($dir . '/*'));

			if (filemtime($first_file) < $expire) {
				Storage::deleteDirectory($dir);
			}
		}
	}

	public function storeChunk(string $login, string $name, string $part, $pointer): void
	{
		$this->cleanChunks();

		$path = $this->temporary_chunks_path . '/' . $login . '/' . $name;
		@mkdir($path, 0777, true);

		$file_path = $path . '/' . $part;
		$out = fopen($file_path, 'wb');
		$quota = $this->getUserQuota();
		$used = $quota['used'] + Storage::getDirectorySize($path);

		while (!feof($pointer)) {
			$data = fread($pointer, 8192);
			$used += strlen($used);

			if ($used > $quota['free']) {
				$this->deleteChunks($login, $name);
				throw new WebDAV_Exception('Your quota does not allow for the upload of this file', 403);
			}

			fwrite($out, $data);
		}

		fclose($out);
		fclose($pointer);
	}

	public function listChunks(string $login, string $name): array
	{
		$path = $this->temporary_chunks_path . '/' . $name;
		$list = glob($path . '/*');
		$list = array_map(fn($a) => str_replace($path . '/', '', $a), $list);
		return $list;
	}

	public function deleteChunks(string $login, string $name): void
	{
		$path = $this->temporary_chunks_path . '/' . $login . '/' . $name;
		Storage::deleteDirectory($path);
	}

	public function assembleChunks(string $login, string $name, string $target, ?int $mtime): array
	{
		$target = $this->users->current()->path . $target;
		$parent = dirname($target);

		if (!is_dir($parent)) {
			throw new WebDAV_Exception('Target parent directory does not exist', 409);
		}

		$path = $this->temporary_chunks_path . '/' . $login . '/' . $name;
		$exists = file_exists($target);

		if ($exists && is_dir($target)) {
			throw new WebDAV_Exception('Target exists and is a directory', 409);
		}

		$out = fopen($target, 'wb');

		foreach (glob($path . '/*') as $file) {
			$in = fopen($file, 'rb');

			while (!feof($in)) {
				fwrite($out, fread($in, 8192));
			}

			fclose($in);
		}

		fclose($out);
		$this->deleteChunks($login, $name);

		if ($mtime) {
			touch($target, $mtime);
		}

		return ['created' => !$exists, 'etag' => md5(filemtime($target) . filesize($target))];
	}

	protected function nc_avatar(): void
	{
		header('Content-Type: image/svg+xml; charset=utf-8');
		echo Avatar::beam($_SERVER['REQUEST_URI'] ?? '', ['colors' => ['#009', '#ccf', '#9cf']]);
	}

	/**
	 * File preview, new version, requires a file ID
	 */
	protected function nc_preview_v2(): void
	{
		$id = $_GET['fileId'] ?? null;
		$w = $_GET['x'] ?? null;
		$h = $_GET['y'] ?? null;

		if (!ENABLE_THUMBNAILS_OK || !$id) {
			http_response_code(404);
			return;
		}

		$this->requireAuth();
		$uri = $this->storage->getFilePathFromId((int)$id);

		if (!$uri) {
			http_response_code(404);
			return;
		}

		$this->serveThumbnail($uri, $w, $h, false, true);
	}

	public function serveThumbnail(string $uri, int $width, int $height, bool $crop = false, bool $preview = false): void
	{
		if (!ENABLE_THUMBNAILS_OK
			|| !preg_match('/\.(?:jpe?g|gif|png|webp)$/', $uri)) {
			http_response_code(404);
			return;
		}

		$this->requireAuth();
		$uri = preg_replace(self::WEBDAV_BASE_REGEXP, '', $uri);

		if (!$this->storage->exists($uri)) {
			throw new WebDAV_Exception('Not found', 404);
		}

		if ($crop || $width < 300 || $height < 300) {
			$size = 150;
		}
		elseif ($width <= 600 || $height <= 600) {
			$size = 500;
		}
		else {
			$size = 1200;
		}

		$id = $this->storage->getFileId($uri);

		if (!$id) {
			throw new WebDAV_Exception('Not found', 404);
		}

		$cache_path = $this->storage->getThumbnailCachePath($id, $size);

		if (!file_exists($cache_path)) {
			$this->server->log('NC Creating thumbnail (%d): %s', $size, basename($cache_path));
			try {
				$i = new Image;
				$i->openFromBlob($this->storage->fetch($uri));

				if ($size === 150) {
					$i->cropResize($size);
				}
				else {
					$i->resize($size);
				}

				$perms = @fileperms(dirname(dirname(dirname($cache_path)))) ?: 0777;
				@mkdir(dirname($cache_path), $perms, true);
				$i->save($cache_path, 'webp');
				unset($i);
			}
			catch (\UnexpectedValueException $e) {
				throw new WebDAV_Exception('Not an image', 404);
			}
		}
		else {
			$this->server->log('NC Cached thumbnail (%d)', $size);
		}

		header('Content-Type: image/webp');
		readfile($cache_path);
	}
}
