<?php

namespace KaraDAV;

use KD2\WebDAV\Server as WebDAV_Server;

class WebDAV extends WebDAV_Server
{
	protected function html_directory(string $uri, iterable $list, array $strings = self::LANGUAGE_STRINGS): ?string
	{
		$out = parent::html_directory($uri, $list, $strings);

		if (null !== $out) {
			$out = str_replace('</head>', sprintf('<link rel="stylesheet" type="text/css" href="%sfiles.css" /></head>', WWW_URL), $out);
			$out = str_replace('</body>', sprintf('<script type="text/javascript" src="%sfiles.js"></script></body>', WWW_URL), $out);
		}

		return $out;
	}
}
