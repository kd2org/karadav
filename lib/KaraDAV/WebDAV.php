<?php

namespace KaraDAV;

use KD2\WebDAV\Server as WebDAV_Server;

class WebDAV extends WebDAV_Server
{
	protected function html_directory(string $uri, iterable $list): ?string
	{
		$out = parent::html_directory($uri, $list);

		if (null !== $out) {
			$options = [
				'wopi_discovery_url' => WOPI_DISCOVERY_URL,
				'server_url'         => WWW_URL,
				'webdav_url'         => $this->storage->getUserURL(),
				'autosave'           => true,
				'has_trash'          => true,
			];

			$uri = $this->storage->getUserURL() . $uri;
			$js = WWW_URL . (DEV ? 'browser/boot.js' : 'browser.min.js');

			$out = str_replace('</head>', sprintf('<script type="text/javascript" src="%s"></script>
				<script type="text/javascript">window.onload = () => { try { browser.init(%s, %s); } catch (e) { document.body.style.opacity = \'unset\'; throw e; } };</script>', $js, json_encode($uri), json_encode($options)), $out);
			$out = str_replace('<body>', '<body>
				<style type="text/css">body { opacity: 0; }</style>
				<noscript>
					<style type="text/css">body { opacity: unset; }</style>
					Javascript is required to manage files, you will only be able to browse and download files.
				</noscript>', $out);
		}

		return $out;
	}

	public function http_options(): void
	{
		parent::http_options();

		if (ACCESS_CONTROL_ALL) {
			header('Access-Control-Allow-Origin: *');
			header('Access-Control-Allow-Credentials: true');
			header('Access-Control-Allow-Headers: Authorization, *');
			header('Access-Control-Allow-Methods: GET,HEAD,PUT,DELETE,COPY,MOVE,PROPFIND,MKCOL,LOCK,UNLOCK');
		}
	}

	public function log(string $message, ...$params)
	{
		http_log('DAV: ' . $message, ...$params);
	}

	/**
	 * Utility function to create HMAC hash of data, useful for NextCloud and WOPI
	 */
	static public function hmac(array $data, string $key = '')
	{
		$key = SECRET_KEY . sha1($key);
		return parent::hmac($data, $key);
	}
}
