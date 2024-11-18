<?php

namespace KD2\HTTP;

class Server
{
	static public function isXSendFileEnabled(): bool
	{
		if (!isset($_SERVER['SERVER_SOFTWARE'])) {
			return false;
		}

		if (stristr($_SERVER['SERVER_SOFTWARE'], 'apache')
			&& function_exists('apache_get_modules')
			&& in_array('mod_xsendfile', apache_get_modules())) {
			return true;
		}
		else if (stristr($_SERVER['SERVER_SOFTWARE'], 'lighttpd')) {
			return true;
		}

		return false;
	}

	/**
	 * Serve a file over HTTP, supporting HTTP ranges and gzip-compression,
	 * from a string, a path, or a file resource (fopen)
	 * @param  null|string $content File contents, as a binary string
	 * @param  null|string $path File path string
	 * @param  null|resource $resource File resource (fopen)
	 * @param  array  $options  List of options:
	 * name => file name
	 * size => file size
	 * gzip => allow/forbid GZIP compression
	 * xsendfile => allow/forbid use of X-SendFile (Apache/Lighttpd)
	 * ranges => allow/forbid HTTP ranges requests
	 */
	static public function serveFile(?string $content, ?string $path, $resource, array $options = []): void
	{
		if (!is_null($resource) && !is_resource($resource)) {
			throw new \InvalidArgumentException('$resource must be a valid resource');
		}

		$source = [&$content, &$path, &$resource];
		$source = array_filter($source);

		if (count($source) !== 1) {
			throw new \InvalidArgumentException('No valid file resource was passed');
		}

		unset($source);

		if (!empty($options['xsendfile']) && self::isXSendFileEnabled() && $path) {
			header('X-Sendfile: ' . $path);
			return;
		}

		// Don't return Content-Length on OVH, as their HTTP 2.0 proxy is buggy
		// @see https://fossil.kd2.org/paheko/tktview/8b342877cda6ef7023b16277daa0ec8e39d949f8
		$disable_length = !empty($_SERVER['HTTP_X_OVHREQUEST_ID']);

		$size = $options['size'] ?? null;
		$name = $options['name'] ?? null;
		$allow_gzip = boolval($options['gzip'] ?? true);
		$allow_ranges = !$disable_length && boolval($options['ranges'] ?? true);

		if (null === $size && null !== $content) {
			$size = strlen($content);
		}
		elseif (null === $size && null !== $path) {
			$size = filemtime($path);
		}

		if (null === $name && null !== $path) {
			$name = basename($path);
		}

		// Extend execution time, serving the file might be slow (eg. slow connection)
		if (false === strpos(@ini_get('disable_functions'), 'set_time_limit')) {
			@set_time_limit(3600);
		}

		@ini_set('max_execution_time', '3600');
		@ini_set('max_input_time', '3600');

		$length = $start = $end = null;
		$gzip = false;

		if ($allow_ranges
			&& isset($_SERVER['HTTP_RANGE'])
			&& preg_match('/^bytes=(\d*)-(\d*)$/i', $_SERVER['HTTP_RANGE'], $match)
			&& $match[1] . $match[2] !== '') {
			$start = $match[1] === '' ? null : (int) $match[1];
			$end   = $match[2] === '' ? null : (int) $match[2];

			if (null !== $start && $start < 0) {
				throw new \LogicException('Start range cannot be satisfied', 416);
			}

			if (isset($size) && $start > $size) {
				throw new \LogicException('End range cannot be satisfied', 416);
			}
		}
		elseif ($allow_gzip
			&& isset($_SERVER['HTTP_ACCEPT_ENCODING'])
			&& false !== strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')
			&& isset($size, $name)
			// Don't compress if size is larger than 8 MiB
			&& $size < 8*1024*1024
			// Don't compress already compressed content
			&& !preg_match('/\.(?:cbz|cbr|cb7|mp4|m4a|zip|docx|xlsx|pptx|ods|odt|odp|7z|gz|bz2|lzma|lz|xz|apk|dmg|jar|rar|webm|ogg|mp3|ogm|flac|ogv|mkv|avi)$/i', $name)) {
			$gzip = true;
			header('Content-Encoding: gzip', true);
		}

		// Try to avoid common issues with output buffering and stuff
		if (function_exists('apache_setenv')) {
			@apache_setenv('no-gzip', 1);
		}

		@ini_set('zlib.output_compression', 'Off');

		// Clean buffer, just in case
		if (@ob_get_length()) {
			@ob_clean();
		}

		if (isset($content)) {
			$length = strlen($content);

			if ($start || $end) {
				if (null !== $end && $end > $length) {
					header('Content-Range: bytes */' . $length, true);
					throw new \LogicException('End range cannot be satisfied', 416);
				}

				if ($start === null) {
					$start = $length - $end;
					$end = $start + $end;
				}
				elseif ($end === null) {
					$end = $length;
				}


				http_response_code(206);
				header(sprintf('Content-Range: bytes %s-%s/%s', $start, $end - 1, $length));
				$content = substr($content, $start, $end - $start);
				$length = $end - $start;
			}

			if ($gzip) {
				$content = gzencode($content, 9);
				$length = strlen($content);
			}

			if (!$disable_length) {
				header('Content-Length: ' . $length, true);
				header('Accept-Ranges: bytes');
			}

			echo $content;
			return;
		}

		if (isset($path)) {
			$resource = fopen($path, 'rb');
		}

		$seek = fseek($resource, 0, SEEK_END);

		if ($seek === 0) {
			$length = ftell($resource);
			fseek($resource, 0, SEEK_SET);
		}

		http_response_code(200);

		if (($start || $end) && $seek === 0) {
			if (null !== $end && $end > $length) {
				header('Content-Range: bytes */' . $length, true);
				throw new \LogicException('End range cannot be satisfied', 416);
			}

			if ($start === null) {
				$start = $length - $end;
				$end = $start + $end;
			}
			elseif ($end === null) {
				$end = $length;
			}

			fseek($resource, $start, SEEK_SET);

			http_response_code(206);
			header(sprintf('Content-Range: bytes %s-%s/%s', $start, $end - 1, $length), true);

			$length = $end - $start;
			$end -= $start;
		}
		elseif (null === $length && isset($path)) {
			$end = $length = filesize($path);
		}

		if ($gzip) {
			$gzip = deflate_init(ZLIB_ENCODING_GZIP);

			$fp = fopen('php://temp', 'wb');

			while (!feof($resource)) {
				fwrite($fp, deflate_add($gzip, fread($resource, 8192), ZLIB_NO_FLUSH));
			}

			fwrite($fp, deflate_add($gzip, '', ZLIB_FINISH));
			$length = ftell($fp);
			rewind($fp);
			unset($resource);

			$resource = $fp;
			unset($fp);
		}

		if (null !== $length && !$disable_length) {
			header('Content-Length: ' . $length, true);
			header('Accept-Ranges: bytes');
		}

		$block_size = 8192*4;

		while (!feof($resource) && ($end === null || $end > 0)) {
			$l = $end !== null ? min($block_size, $end) : $block_size;

			echo fread($resource, $l);
			flush();

			if (null !== $end) {
				$end -= $block_size;
			}
		}
	}
}
