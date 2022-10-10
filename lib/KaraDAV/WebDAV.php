<?php

namespace KaraDAV;

use KD2\WebDAV\Server as WebDAV_Server;

class WebDAV extends WebDAV_Server
{
	protected function html_directory(string $uri, iterable $list): ?string
	{
		$out = parent::html_directory($uri, $list);

		if (null !== $out) {
			$out = str_replace('<body>', sprintf('<body style="opacity: 0"><script type="text/javascript" src="%swebdav.js"></script>', WWW_URL), $out);
		}

		return $out;
	}

	public function http_options(): void
	{
		parent::http_options();

		header('Access-Control-Allow-Origin: *');
		header('Access-Control-Allow-Credentials: true');
		header('Access-Control-Allow-Headers: Authorization, *');
		header('Access-Control-Allow-Methods: GET,HEAD,PUT,DELETE,COPY,MOVE,PROPFIND,MKCOL,LOCK,UNLOCK');
	}
}
