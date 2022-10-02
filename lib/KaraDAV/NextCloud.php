<?php

namespace KaraDAV;

use KD2\WebDAV\NextCloud as WebDAV_NextCloud;
use KD2\WebDAV\Exception as WebDAV_Exception;

class NextCloud extends WebDAV_NextCloud
{
	protected Users $users;
	protected string $temporary_chunks_path;

	public function __construct(Users $users, string $temporary_chunks_path)
	{
		$this->users = $users;
		$this->temporary_chunks_path = $temporary_chunks_path;
		$this->setRootURL(WWW_URL);
	}

	public function auth(?string $login, ?string $password): bool
	{
		$user = $this->users->appSessionLogin($login, $password);

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

		return ['login' => $session->user, 'password' => $session->password];
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

		return hash('sha256', $uri . $user->login . $user->password);
	}

	protected function cleanChunks(): void
	{
		$expire = time() - 36*3600;

		foreach (glob($this->temporary_chunks_path . '/*/*/*') as $file) {
			if (filemtime($file) < $expire) {
				Storage::deleteDirectory(dirname($file));
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

}
