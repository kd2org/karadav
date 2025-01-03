<?php
/*
    This file is part of KD2FW -- <http://dev.kd2.org/>

    Copyright (c) 2001-2019 BohwaZ <http://bohwaz.net/>
    All rights reserved.

    KD2FW is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    Foobar is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with Foobar.  If not, see <https://www.gnu.org/licenses/>.
*/

namespace KD2;

/**
 * Simple error and exception handler
 *
 * When enabled (with ErrorManager::enable(ErrorManager::DEVELOPMENT)) it will
 * catch any error, warning or exception and display it along with useful debug
 * information. If enabled it will also log the errors to a file and/or send
 * every error by email.
 *
 * In production mode no details are given, but a unique reference to the log
 * or email is displayed.
 *
 * This is similar in a way to http://tracy.nette.org/
 *
 * @author  bohwaz <http://bohwaz.net/>
 */
class ErrorManager
{
	/**
	 * Prod/dev modes
	 */
	const PRODUCTION = 1;
	const DEVELOPMENT = 2;
	const CLI_DEVELOPMENT = 4;

	/**
	 * Term colors
	 */
	const RED = '[1;41m';
	const RED_FAINT = '[1m';
	const YELLOW = '[33m';

	/**
	 * true = catch exceptions, false = do nothing
	 * @var null
	 */
	static protected $enabled = null;

	/**
	 * HTML template used for displaying production errors
	 * @var string
	 */
	static protected $production_error_template = '<!DOCTYPE html><html><head><title>Internal server error</title>
		<style type="text/css">
		body {font-family: sans-serif; }
		code, p, h1 { max-width: 400px; margin: 1em auto; display: block; }
		code { text-align: right; color: #666; }
		a { color: blue; }
		form { text-align: center; }
		input { padding: .3em; }
		</style></head><body><h1>Server error</h1><p>Sorry but the server encountered an internal error and was unable
		to complete your request. Please try again later.</p>
		<if(sent)><p>The webmaster has been noticed and this will be fixed ASAP.</p></if>
		<if(logged)><code>Error reference: <b>{$ref}</b></code></if>
		<if(report)><form method="post" action="{$report_url}"><input type="hidden" value="{$report_json}" /><input type="submit" value="Report this error" /></report_form></if>
		<p><a href="/">&larr; Go back to the homepage</a></p>
		</body></html>';

	/**
	 * E-Mail address where to send errors
	 * @var boolean
	 */
	static protected $email_errors = false;

	/**
	 * Reporting URL
	 */
	static protected $report_url = null;

	/**
	 * Reporting automatically?
	 */
	static protected $report_auto = true;

	/**
	 * Custom context
	 */
	static protected $context = [];

	/**
	 * Custom exception handlers
	 * @var array
	 */
	static protected $custom_handlers = [];

	/**
	 * Does the terminal support ANSI colors
	 * @var boolean
	 */
	static protected $term_color = false;

	/**
	 * Will be incremented when catching an exception to avoid double catching
	 * with the shutdown function
	 */
	static protected int $catching = 0;

	/**
	 * Used to store timers and memory consumption
	 * @var array
	 */
	static protected $run_trace = [];

	/**
	 * Handles PHP shutdown on fatal error to be able to catch the error
	 * @return void
	 */
	static public function shutdownHandler()
	{
		// Stop here if disabled or if the script ended with an exception
		if (!self::$enabled || self::$catching) {
			return;
		}

		$error = error_get_last();

		if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_RECOVERABLE_ERROR, E_USER_ERROR], TRUE))
		{
			// Don't exit at the end, as there might be other shutdown handlers
			// after this one
			self::exceptionHandler(new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']), false);
		}
	}

	/**
	 * Internal error handler to throw them as exceptions
	 * (private use)
	 */
	static public function errorHandler($severity, $message, $file, $line)
	{
		if (!(error_reporting() & $severity)) {
			// Don't report this error (for example @unlink)
			return;
		}

		$types = [
			E_ERROR             => 'Fatal error',
			E_USER_ERROR        => 'User error',
			E_RECOVERABLE_ERROR => 'Recoverable error',
			E_CORE_ERROR        => 'Core error',
			E_COMPILE_ERROR     => 'Compile error',
			E_PARSE             => 'Parse error',
			E_WARNING           => 'Warning',
			E_CORE_WARNING      => 'Core warning',
			E_COMPILE_WARNING   => 'Compile warning',
			E_USER_WARNING      => 'User warning',
			E_NOTICE            => 'Notice',
			E_USER_NOTICE       => 'User notice',
			E_DEPRECATED        => 'Deprecated',
			E_USER_DEPRECATED   => 'User deprecated',
		];

		$type = array_key_exists($severity, $types) ? $types[$severity] : 'Unknown error';
		$message = $type . ': ' . $message;

		// Catch ASSERT_BAIL errors differently because throwing an exception
		// in this case results in an execution shutdown, and shutdown handler
		// isn't even called. See https://bugs.php.net/bug.php?id=53619
		if (PHP_VERSION_ID < 80000 && assert_options(ASSERT_ACTIVE) && assert_options(ASSERT_BAIL) && substr($message, 0, 18) == 'Warning: assert():')
		{
			$message .= ' (ASSERT_BAIL detected)';
			self::exceptionHandler(new \ErrorException($message, 0, $severity, $file, $line));
			return;
		}

		throw new \ErrorException($message, 0, $severity, $file, $line);
		return;
	}

	/**
	 * Main exception handler
	 */
	static public function exceptionHandler(\Throwable $e, bool $exit = true): void
	{
		try {
			self::reportException($e, $exit);
		}
		catch (\Throwable|\Exception $e2) {
			echo $e2;
			echo PHP_EOL . PHP_EOL . $e;
			exit(1);
		}
	}

	/**
	 * Main exception handler
	 */
	static public function reportException(\Throwable $e, bool $exit = true): void
	{
		self::$catching++;

		if (self::$catching === 1) {
			foreach (self::$custom_handlers as $class => $callback) {
				if ($e instanceOf $class) {
					call_user_func($callback, $e);
					$e = false;
					break;
				}
			}
		}

		extract(self::buildExceptionReport($e, false));
		unset($e);

		// Log exception to file
		if (ini_get('log_errors')) {
			error_log($log);
		}

		// Disable any output if it was buffering
		if (ob_get_level()) {
			ob_end_clean();
		}

		$is_curl = 0 === strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'curl/');
		$is_cli = PHP_SAPI == 'cli';

		if (!$is_cli) {
			@http_response_code(500);
		}

		if ($is_curl && !headers_sent()) {
			header('Content-Type: text/plain; charset=utf-8', true);
		}

		$text_mode_dev = ($is_curl && self::$enabled & self::DEVELOPMENT)
			|| ($is_cli && self::$enabled & self::DEVELOPMENT)
			|| ($is_cli && self::$enabled & self::CLI_DEVELOPMENT);

		if ($text_mode_dev)
		{
			foreach ($report->errors as $e)
			{
				self::termPrint(sprintf(' /!\\ %s ', $e->type), self::RED);
				self::termPrint($e->message, self::RED_FAINT);

				if (isset($e->line))
				{
					self::termPrint(sprintf('Line %d in %s', $e->line, $e->file), self::
						YELLOW);
				}

				// Ignore the error stack belonging to ErrorManager
				foreach ($e->backtrace as $i=>$t)
				{
					$file = !empty($t->file) ? $t->file : '[internal function]';
					$line = !empty($t->line) ? '(' . $t->line . ')' : '';

					if (isset($t->args))
					{
						$args = $t->args;

						foreach ($args as &$arg)
						{
							if (strlen($arg) > 20)
							{
								$arg = substr($arg, 0, 19) . '…';
							}
						}

						unset($arg);

						self::termPrint(sprintf('#%d %s%s: %s(%s)', $i, $file, $line, $t->function, implode(', ', $args)));
					}
					else
					{
						self::termPrint(sprintf('#%d %s%s', $i, $file, $line));
					}
				}
			}
		}
		else if (($is_cli || $is_curl) && self::$enabled & self::PRODUCTION) {
			self::termPrint(' /!\\ An internal server error occurred ', self::RED);
			self::termPrint(' Error reference was: ' . $report->context->id, self::YELLOW);
		}
		else if (self::$enabled & self::PRODUCTION)
		{
			@header_remove('Content-Disposition');
			@header('Content-Type: text/html; charset=utf-8', true);
			self::htmlProduction($report);
		}
		else
		{
			if (!headers_sent()) {
				header_remove();
				header('Content-Type: text/html; charset=UTF-8', true);
				header('HTTP/1.1 500 Internal Server Error', true);
			}

			echo $html_report;
		}

		// Log exception to email
		if (self::$email_errors) {
			self::sendEmail($title, $report, $log, $html_report);
		}

		// Send report to URL
		if (self::$report_auto && self::$report_url) {
			self::sendReport($report, self::$report_url);
		}

		if ($exit)
		{
			exit(1);
		}
	}

	static public function reportExceptionSilent(\Throwable $e): void
	{
		$report = self::logException($e);
		extract($report);

		if (self::$email_errors) {
			self::sendEmail($title, $report, $log, $html_report);
		}
	}

	static public function logException(\Throwable $e): array
	{
		$report = self::buildExceptionReport($e);

		// Log exception to file
		if (ini_get('log_errors')) {
			error_log($report['log']);
		}

		// Send report to URL
		if (self::$report_auto && self::$report_url) {
			self::sendReport($report['report'], self::$report_url);
		}

		return $report;
	}

	static protected function sendEmail(string $title, \stdClass $report, string $log, string $html): void
	{
		// From: sender
		$from = !empty($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : basename($report->context->root_directory ?? __FILE__);
		$msgid = $report->context->id . '@' . $from;

		$boundary = sprintf('-----=%s', md5(uniqid(rand())));

		$header = sprintf("MIME-Version: 1.0\r\nFrom: \"%s\" <%s>\r\nIn-Reply-To: <%s>\r\nMessage-Id: <%s>\r\n", $from, self::$email_errors, $msgid, $msgid);
		$header.= sprintf("Content-Type: multipart/alternative; boundary=\"%s\"\r\n", $boundary);
		$header.= "\r\n";

		$msg = "This message contains multiple MIME parts.\r\n\r\n";
		$msg.= sprintf("--%s\r\n", $boundary);
		$msg.= "Content-Type: text/plain; charset=\"utf-8\"\r\n";
		$msg.= "Content-Transfer-Encoding: 8bit\r\n\r\n";
		$msg.= wordwrap($log, 990) . "\r\n\r\n";
		$msg.= sprintf("--%s\r\n", $boundary);
		$msg.= "Content-Type: text/html; charset=\"utf-8\"\r\n";
		$msg.= "Content-Transfer-Encoding: 8bit\r\n\r\n";
		$msg.= wordwrap($html, 990) . "\r\n\r\n";
		$msg.= sprintf("--%s--", $boundary);

		$msg = str_replace("\0", "", $msg);
		$header = str_replace("\0", "", $header);

		mail(self::$email_errors, sprintf('Error #%s: %s', $report->context->id, $title), $msg, $header);
	}

	/**
	 * Prints a line to STDERR, eventually using a color
	 */
	static public function termPrint($message, $color = null)
	{
		if (!defined('\STDERR')) {
			echo $message . PHP_EOL;
			return;
		}

		if ($color && self::$term_color)
		{
			$message = chr(27) . $color . $message . chr(27) . "[0m";
		}

		fwrite(\STDERR, $message . PHP_EOL);
	}

	/**
	 * Return file location without the document root
	 */
	static protected function getFileLocation($file)
	{
		if (!empty(self::$context['root_directory']) && strpos($file, self::$context['root_directory']) === 0)
		{
			return '...' . substr($file, strlen(self::$context['root_directory']));
		}

		return $file;
	}

	static public function buildExceptionReport(\Throwable $e, bool $force_html = false): array
	{
		$report = self::makeReport($e);
		$log = sprintf('=========== Error ref. %s ===========', $report->context->id)
			. PHP_EOL . PHP_EOL . (string) $e . PHP_EOL . PHP_EOL
			. '<errorReport>' . PHP_EOL . json_encode($report, \JSON_PRETTY_PRINT)
			. PHP_EOL . '</errorReport>' . PHP_EOL;

		$html_report = null;

		if ($force_html || self::$enabled & self::DEVELOPMENT || self::$email_errors) {
			$html_report = self::htmlReport($report);
		}

		$title = $e->getMessage();

		return compact('report', 'log', 'html_report', 'title');
	}

	/**
	 * Generates a report from an exception
	 */
	static public function makeReport($e): \stdClass
	{
		$report = (object) [
			'errors' => [],
		];

		while ($e !== null)
		{
			$class = get_class($e);

			$error = (object) [
				'message'   => $e->getMessage(),
				'errorCode' => $e->getCode(),
				'type'      => in_array($class, ['ErrorException', 'Error']) ? 'PHP error' : $class,
				'backtrace' => [
					(object) [
						'file' => $e->getFile() ? self::getFileLocation($e->getFile()) : null,
						'line' => $e->getLine(),
						'code' => $e->getFile() && $e->getLine() ? self::getSource($e->getFile(), $e->getLine()) : null,
					],
				],
			];

			foreach ($e->getTrace() as $t)
			{
				// Ignore the error stack from ErrorManager
				if (isset($t['class']) && $t['class'] === __CLASS__
					&& ($t['function'] === 'shutdownHandler' || $t['function'] === 'errorHandler'))
				{
					continue;
				}

				$args = [];

				// Display call arguments
				if (!empty($t['args']))
				{
					// Find arguments variables names via reflection
					try {
						if (isset($t['class']))
						{
							$r = new \ReflectionMethod($t['class'], $t['function']);
						}
						else
						{
							$r = new \ReflectionFunction($t['function']);
						}

						$params = $r->getParameters();
					}
					catch (\Exception $_ignore) {
						$params = [];
					}

					foreach ($t['args'] as $name => $value)
					{
						if (array_key_exists($name, $params))
						{
							$name = '$' . $params[$name]->name;
						}

						if (is_string($value))
						{
							$value = self::getFileLocation($value);
						}

						$args[$name] = self::dump($value);

						if (strlen($args[$name]) > 2000)
						{
							$args[$name] = substr($args[$name], 0, 1999) . '…';
						}
					}
				}

				$trace = (object) [
					// Add class name to function
					'function' => isset($t['class']) ? $t['class'] . $t['type'] . $t['function'] : $t['function'],
				];

				if (isset($t['file']))
				{
					$trace->file = self::getFileLocation($t['file']);
				}

				if (isset($t['line']))
				{
					$trace->line = (int) $t['line'];
				}

				if (count($args))
				{
					$trace->args = $args;
				}

				if (isset($trace->file) && isset($trace->line))
				{
					$trace->code = self::getSource($t['file'], $t['line']);
				}

				$error->backtrace[] = $trace;
			}

			$report->errors[] = $error;
			$e = $e->getPrevious();
		}

		unset($error, $e, $params, $t);

		$context = array_merge([
			'id'           => base_convert(substr(sha1(json_encode($report->errors)), 0, 10), 16, 36),
			'date'         => date(DATE_ATOM),
			'os'           => PHP_OS,
			'language'     => 'PHP ' . PHP_VERSION,
			'environment'  => self::$enabled & self::DEVELOPMENT ? 'development' : 'production:' . self::$enabled,
			'php_sapi'     => PHP_SAPI,
			'remote_ip'    => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null,
			'http_method'  => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : null,
			'http_files'   => self::dump($_FILES),
			'http_post'    => self::dump($_POST, true),
			'duration'     => isset(self::$context['request_started']) ? (microtime(true) - self::$context['request_started'])*1000 : null,
			'memory_peak'  => memory_get_peak_usage(true),
			'memory_used'  => memory_get_usage(true),
		], self::$context);

		ksort($context);

		unset($context['request_started']);

		$report->context = (object) $context;

		if (!empty($_SERVER['HTTP_HOST']) && !empty($_SERVER['REQUEST_URI']))
		{
			$proto = empty($_SERVER['HTTPS']) ? 'http' : 'https';
			$report->context->url = $proto . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		}

		return $report;
	}

	/**
	 * Displays an exception as HTML debug page
	 */
	static public function htmlException(\stdClass $e): string
	{
		$out = sprintf('<section><header><h1>%s</h1><h2>%s</h2></header>',
			$e->type, nl2br(htmlspecialchars($e->message)));

		foreach ($e->backtrace as $t)
		{
			$out .= '<article>';

			if (isset($t->file) && isset($t->line))
			{
				$dir = dirname($t->file);
				$dir = $dir == '/' ? $dir : $dir . '/';

				$out .= sprintf('<h3>in %s<b>%s</b>:<i>%d</i></h3>', htmlspecialchars($dir), htmlspecialchars(basename($t->file)), $t->line);
			}

			if (isset($t->function))
			{
				$out .= sprintf('<h4>&rarr; %s <i>(%d arg.)</i></h4>', htmlspecialchars($t->function), isset($t->args) ? count($t->args) : 0);

				// Display call arguments
				if (!empty($t->args))
				{
					$out .= '<table>';

					foreach ($t->args as $name => $value)
					{
						$out .= sprintf('<tr><th>%s</th><td><pre>%s</pre></td>', htmlspecialchars($name), htmlspecialchars($value));
					}

					$out .= '</table>';
				}
			}

			// Display source code
			if (isset($t->code) && isset($t->line))
			{
				$out .= self::htmlSource($t->code, $t->line);
			}

			$out .= '</article>';
		}

		$out .= '</section>';

		return $out;
	}


	static public function htmlSource(array $source, $line)
	{
		$out = '';

		foreach ($source as $i => $code)
		{
			$html = '<b>' . ($i) . '</b>' . htmlspecialchars($code, ENT_QUOTES);

			if ($i == $line)
			{
				$html = '<u>' . $html . '</u>';
			}

			$out .= $html . PHP_EOL;
		}

		return '<pre><code>' . $out . '</code></pre>';
	}

	/**
	 * Get source code
	 * @param  string $file File location
	 * @param  integer $line Line to highlight
	 * @return array
	 */
	static public function getSource($file, $line)
	{
		$out = [];
		$start = max(0, $line - 5);

		if (!file_exists($file)) {
			return [$line => 'Source file not found'];
		}

		$file = new \SplFileObject($file);
		$file->seek($start);

		for ($i = $start + 1; $i < $start+10; $i++)
		{
			if ($file->eof())
			{
				break;
			}

			$out[$i] = trim($file->current(), "\r\n");
			$file->next();
		}

		unset($file);

		return $out;
	}

	static public function htmlProduction(\stdClass $report)
	{
		if (!headers_sent()) {
			header_remove();
			header('HTTP/1.1 500 Internal Server Error', true, 500);
			header('Content-Type: text/html; charset=UTF-8', true);
		}

		echo self::htmlTemplate(self::$production_error_template, $report);
	}

	static public function htmlTemplate($str, \stdClass $report)
	{
		$str = strtr($str, [
			'{$ref}' => $report->context->id,
			'{$report_json}' => htmlspecialchars(base64_encode(json_encode($report)), ENT_QUOTES),
			'{$report_url}' => htmlspecialchars((string) self::$report_url),
		]);

		$str = preg_replace_callback('!<if\((sent|logged|report|email|log)\)>(.*?)</if>!is', function ($match) {
			switch ($match[1]) {
				case 'sent':
				case 'email':
					return self::$email_errors || (self::$report_auto && self::$report_url) ? $match[2] : '';
				case 'logged':
				case 'log':
					return ini_get('error_log') ? $match[2] : '';
				case 'report':
					return (!self::$report_auto && self::$report_url) ? $match[2] : '';
			}
		}, $str);

		return $str;
	}

	static public function htmlReport(\stdClass $report): string
	{
		$out = '';

		// Display debug
		$out .= self::htmlTemplate(ini_get('error_prepend_string'), $report);

		foreach ($report->errors as $e)
		{
			$out .= self::htmlException($e);
		}

		$out .= '<section><article><h2>Context</h2><table>';

		foreach ($report->context as $name => $value)
		{
			$out .= sprintf('<tr><th>%s</th><td>%s</td></tr>',
				htmlspecialchars($name),
				htmlspecialchars($value ?? ''));
		}

		$out .= '</table></article></section>';

		$out .= self::htmlTemplate(ini_get('error_append_string'), $report);

		return $out;
	}

	static public function setEnvironment(int $environment): void
	{
		self::$enabled = $environment;
		error_reporting($environment & self::DEVELOPMENT ? -1 : E_ALL & ~E_DEPRECATED);

		if ($environment & self::DEVELOPMENT && PHP_SAPI != 'cli') {
			self::setHtmlHeader('<!DOCTYPE html><meta charset="utf-8" /><style type="text/css">
			body { font-family: sans-serif; } * { margin: 0; padding: 0; }
			u, code b, i, h3 { font-style: normal; font-weight: normal; text-decoration: none; }
			#icn { color: #fff; font-size: 2em; float: right; margin: 1em; padding: 1em; background: #900; border-radius: 50%; }
			section header { background: #fdd; padding: 1em; }
			section article { margin: 1em; }
			section article h3, section article h4 { font-size: 1em; font-family: mono; }
			code { border: 1px dotted #ccc; display: block; }
			code b { margin-right: 1em; color: #999; }
			code u { background: #fcc; display: inline-block; width: 100%; }
			table { border-collapse: collapse; margin: 1em; } td, th { border: 1px solid #ccc; padding: .2em .5em; text-align: left;
			vertical-align: top; }
			</style>
			<pre id="icn"> \__/<br /> (xx)<br />//||\\\\</pre>');
		}
	}

	/**
	 * Enable error manager
	 * @param  integer $environment Type of error management (ErrorManager::PRODUCTION or ErrorManager::DEVELOPMENT)
	 * You can also use ErrorManager::PRODUCTION | ErrorManager::CLI_DEVELOPMENT to get error messages in CLI but still hide errors
	 * on web front-end.
	 * @return void
	 */
	static public function enable(int $environment = self::DEVELOPMENT): void
	{
		if (self::$enabled) {
			return;
		}

		self::$context['request_started'] = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);

		self::$term_color = function_exists('posix_isatty') && defined('\STDOUT') && @posix_isatty(\STDOUT);

		ini_set('display_errors', false);
		ini_set('log_errors', false);
		ini_set('html_errors', false);
		ini_set('zend.exception_ignore_args', false); // We want to get the args in exceptions (since PHP 7.4)

		self::setEnvironment($environment);

		register_shutdown_function([self::class, 'shutdownHandler']);
		set_exception_handler([__CLASS__, 'exceptionHandler']);
		set_error_handler([__CLASS__, 'errorHandler']);

		if ($environment & self::DEVELOPMENT) {
			self::startTimer('_global');
		}

		// Assign default context
		static $defaults = [
			'hostname'        => 'SERVER_NAME',
			'http_user_agent' => 'HTTP_USER_AGENT',
			'http_referrer'   => 'HTTP_REFERER',
			'user_addr'       => 'REMOTE_ADDR',
			'server_addr'     => 'SERVER_ADDR',
			'root_directory'  => 'DOCUMENT_ROOT',
		];

		foreach ($defaults as $a => $b) {
			if (isset($_SERVER[$b]) && !isset(self::$context[$a])) {
				self::$context[$a] = $_SERVER[$b];
			}
		}
	}

	/**
	 * Reset error management to PHP defaults
	 * @return boolean
	 */
	static public function disable()
	{
		self::$enabled = false;

		ini_set('error_prepend_string', null);
		ini_set('error_append_string', null);
		ini_set('log_errors', false);
		ini_set('display_errors', false);
		ini_set('error_reporting', E_ALL & ~E_DEPRECATED);

		restore_error_handler();
		return restore_exception_handler();
	}

	/**
	 * Sets a microsecond timer to track time and memory usage
	 * @param string $name Timer name
	 */
	static public function startTimer($name)
	{
		self::$run_trace[$name] = [microtime(true), memory_get_usage()];
	}

	/**
	 * Stops a timer and return time spent and memory used
	 * @param string $name Timer name
	 */
	static public function stopTimer($name)
	{
		self::$run_trace[$name] = [
			microtime(true) - self::$run_trace[$name][0],
			memory_get_usage() - self::$run_trace[$name][1],
		];
		return self::$run_trace[$name];
	}

	/**
	 * Sets a log file to record errors
	 * @param string $file Error log file
	 */
	static public function setLogFile($file)
	{
		ini_set('log_errors', true);
		return ini_set('error_log', $file);
	}

	/**
	 * Sets an email address that should receive the logs
	 * Set to FALSE to disable email sending (default)
	 * @param string $email Email address
	 */
	static public function setEmail($email)
	{
		self::$email_errors = $email;
	}

	/**
	 * @deprecated
	 */
	static public function setExtraDebugEnv($env)
	{
		self::setContext($env);
	}

	/**
	 * Set the report context
	 * @param mixed $env Variable content, could be application version, or an array of information...
	 */
	static public function setContext(array $context)
	{
		self::$context = array_merge(self::$context, $context);
	}

	/**
	 * Enable or disable reporting of errors to a remote URL
	 * @param null|string $url Reporting URL
	 * @param boolean $auto Automatic reporting? If not users will be able to report the error by clicking a button on the error page
	 */
	static public function setRemoteReporting($url, $auto)
	{
		self::$report_url = empty($url) ? null : $url;
		self::$report_auto = (bool) $auto;
	}

	/**
	 * Set the HTML header used by the debug error page
	 * @param string $html HTML header
	 */
	static public function setHtmlHeader($html)
	{
		ini_set('error_prepend_string', $html);
	}

	/**
	 * Set the HTML footer used by the debug error page
	 * @param string $html HTML footer
	 */
	static public function setHtmlFooter($html)
	{
		ini_set('error_append_string', $html);
	}

	/**
	 * Set the content of the HTML template used to display an error in production
	 * {$ref} will be replaced by the error reference if log or email is enabled
	 * <if(email)>...</if> block will be removed if email reporting is disabled
	 * <if(log)>...</if> block will be removed if log reporting is disabled
	 * @param string $html HTML template
	 */
	static public function setProductionErrorTemplate($html)
	{
		self::$production_error_template = $html;
	}

	static public function setCustomExceptionHandler($class, Callable $callback)
	{
		self::$custom_handlers[$class] = $callback;
	}

	static public function debug(...$vars)
	{
		echo '<pre>';
		foreach ($vars as $var) {
			echo self::dump($var);
			echo '<hr />';
		}
		echo '</pre>';
	}

	/**
	 * Copy of var_dump but returns a string instead of a variable
	 * @param  mixed  $var   variable to dump
	 * @param  bool $hide_values Do not return values if set to TRUE
	 * @param  integer $level Indentation level (internal use)
	 * @return string
	 */
	static public function dump($var, $hide_values = false, $level = 0)
	{
		if ($level > 20)
		{
			return '*RECURSION*';
		}

		switch (gettype($var))
		{
			case 'boolean':
				return 'bool(' . ($var ? 'true' : 'false') . ')';
			case 'integer':
				return 'int(' . $var . ')';
			case 'double':
				return 'float(' . $var . ')';
			case 'string':
				return 'string(' . strlen($var) . ') "' . ($hide_values ? '***HIDDEN***' : $var) . '"';
			case 'NULL':
				return 'NULL';
			case 'resource':
				return 'resource(' . (int)$var . ') of type (' . get_resource_type($var) . ')';
			case 'array':
			case 'object':
				if (is_object($var))
				{
					$out = 'object(' . get_class($var) . ') (' . count((array) $var) . ') {' . PHP_EOL;
				}
				else
				{
					$out = 'array(' . count((array) $var) . ') {' . PHP_EOL;
				}

				$level++;

				if ($var instanceof \Traversable && method_exists($var, 'valid')) {
					$var2 = [];

					try {
						// Iterate as long as we can
						while (@$var->valid()) {
							$var2[] = $var->current();
							$var->next();
						}
					}
					catch (\Exception $e) {
						$var2[] = '**' . $e->getMessage() . '**';
					}

					$var = $var2;
				}

				foreach ((array)$var as $key=>$value)
				{
					$out .= str_repeat(' ', $level * 2);
					$out .= is_string($key) ? '["' . $key . '"]' : '[' . $key . ']';

					if ($value === $var) {
						$out .= '=> *RECURSION*' . PHP_EOL;
					}
					else {
						$out .= '=> ' . self::dump($value, $hide_values, $level + 1) . PHP_EOL;
					}
				}

				$out .= str_repeat(' ', $level * 2) . '}';
				return $out;
			default:
				return gettype($var);
		}
	}

	/**
	 * Upload a report to a remote errbit-compatible API
	 * @see https://airbrake.io/docs/api/#create-notice-v3
	 */
	static public function sendReport(\stdClass $report, $url)
	{
		$data = json_encode($report);

		$headers = [
			'Content-Type: application/json',
			'Content-Lenth: ' . strlen($data),
		];

		if (function_exists('curl_init'))
		{
			$ch = curl_init($url);

			curl_setopt_array($ch, [
				CURLOPT_HTTPHEADER     => $headers,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => false,
				CURLOPT_MAXREDIRS      => 3,
				CURLOPT_CUSTOMREQUEST  => 'POST',
				CURLOPT_TIMEOUT        => 10,
				CURLOPT_POSTFIELDS     => $data,
			]);

			$body = curl_exec($ch);
			$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
		}
		else
		{
			$opts = ['http' => [
				'method'        => 'POST',
				'header'        => $headers,
				'content'       => $data,
				'max_redirects' => 3,
				'timeout'       => 10,
				'ignore_errors' => true,
			]];

			$body = file_get_contents($url, false, stream_context_create($opts));
			$code = null;

			foreach ($http_response_header as $header)
			{
				$a = substr($header, 0, 7);

				if ($a == 'HTTP/1.')
				{
					$code = substr($header, 11, 3);
				}
			}

			unset($http_response_header);
		}

		return [
			'code' => (int) $code,
			'body' => $body,
			'data' => json_decode($body),
		];
	}

	/**
	 * Returns list of reports from error log
	 *
	 * @param string|null $log_file Log file to use, if NULL then the log file set in error_log will be used
	 * @param string|null $filter_id Only return errors matching with this ID
	 */
	static public function getReportsFromLog($log_file = null, $filter_id = null)
	{
		if (!$log_file)
		{
			$log_file = ini_get('error_log');
		}

		if (!file_exists($log_file))
		{
			return [];
		}

		$reports = [];
		$report = null;

		foreach (file($log_file) as $line)
		{
			$line = trim($line);

			if ($line == '<errorReport>')
			{
				$report = '';
			}
			elseif ($line == '</errorReport>')
			{
				$report = json_decode($report);

				if (!is_null($report) && isset($report->context->id) && (!$filter_id || $filter_id == $report->context->id))
				{
					$reports[] = $report;
				}

				$report = null;
			}
			elseif ($report !== null)
			{
				$report .= $line;
			}
		}

		unset($line, $report, $log_file);

		return $reports;
	}
}
