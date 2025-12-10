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
 * Translate: a drop-in (almost) replacement to gettext functions
 * with no dependency on system locales or gettext
 */

use KD2\MemCache;
use IntlDateFormatter;

class Translate
{
	/**
	 * MemCache object used for caching of translation messages
	 * @var MemCache|null
	 */
	static protected $cache = null;

	/**
	 * Object cache of translation messages
	 * @var array
	 */
	static protected $translations = [];

	/**
	 * List of registered domains
	 * @var array
	 */
	static protected $domains = [];

	/**
	 * Default domain (by default is the first one registered with registerDomain)
	 * @var null|string
	 */
	static protected $default_domain = null;

	/**
	 * Current locale (set with ::setLocale)
	 * @var null
	 */
	static protected $locale = null;

	/**
	 * Set the MemCache object used for caching translation messages
	 *
	 * If no cache is set, messages will be reloaded from .mo or .po file every time
	 *
	 * @param MemCache $cache_engine A MemCache object like MemCache_APCu (recommended)
	 */
	static public function setCacheEngine(MemCache $cache_engine)
	{
		self::$cache = $cache_engine;
	}

	/**
	 * Sets the locale (eg. en_US, fr_BE, etc.)
	 * @param string $locale Locale
	 */
	static public function setLocale(string $locale)
	{
		$locale = strtok($locale, '@.-+=%:; ');
		strtok('');

		self::$locale = $locale;

		return self::$locale;
	}

	/**
	 * Registers a domain to a directory
	 *
	 * If domain is '*' (wild card) it will be used as a default when no domain is set and no default domain has been set
	 *
	 * @param  string $domain    Translation domain (equivalent to a category, in practice will be the name of the file .po/.mo)
	 * @param  string $directory Directory where translations will be stored
	 */
	static public function registerDomain(string $domain, ?string $directory = null): void
	{
		if (!is_null($directory) && !is_readable($directory))
		{
			throw new \InvalidArgumentException('Translations directory \'' . $directory . '\' does not exists or is not readable.');
		}

		self::$domains[$domain] = $directory;
		self::$translations[$domain] = [];

		if (is_null(self::$default_domain)) {
			self::$default_domain = $domain;
		}
	}

	static public function unregisterDomain(string $domain): void
	{
		unset(self::$translations[$domain], self::$domains[$domain]);

		if (self::$default_domain === $domain) {
			self::$default_domain = null;
		}
	}

	/**
	 * Sets the default domain used for translating text
	 * @param  string $domain Domain
	 * @return void
	 */
	static public function setDefaultDomain(string $domain): void
	{
		if (!array_key_exists($domain, self::$domains))
		{
			throw new \InvalidArgumentException('Unknown domain \'' . $domain . '\', did you call ::registerDomain(domain, directory) before?');
		}

		self::$default_domain = $domain;
	}

	/**
	 * Loads translations for this domain and the current locale, either from cache or from the .po/.mo file
	 * @param  string $domain Domain
	 */
	static protected function _loadTranslations(?string $domain = null, ?string $locale = null): ?string
	{
		// Fallback to default domain
		if (is_null($domain)) {
			$domain = self::$default_domain;
		}

		if (is_null($domain)) {
			return null;
		}

		if (is_null($locale)) {
			$locale = self::$locale;
			$locale_short = $locale ? substr($locale, strpos($locale, '_')) : null;
		}

		// Already loaded
		if (isset(self::$translations[$domain][$locale]) || isset(self::$translations[$domain][$locale_short])) {
			return $domain;
		}

		// If this domain exists
		if (array_key_exists($domain, self::$domains)) {
			$dir = self::$domains[$domain];
		}
		// Or if we have a "catch-all" domain
		elseif (array_key_exists('*', self::$domains)) {
			$dir = self::$domains['*'];
		}
		// Or we fail
		else {
			throw new \InvalidArgumentException('Unknown gettext domain: ' . $domain);
		}

		self::$translations[$domain][$locale] = [];

		$cache_key = 'gettext_' . $domain . '_' . $locale;

		// Try to fetch from cache
		if (!is_null(self::$cache) && self::$cache->exists($cache_key)) {
			self::$translations[$domain][$locale] = self::$cache->get($cache_key);
			return $domain;
		}

		$paths = [
			$dir . DIRECTORY_SEPARATOR . $locale,
			$dir . DIRECTORY_SEPARATOR . $locale_short,
			$dir . DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . $domain,
			$dir . DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . 'LC_MESSAGES' . DIRECTORY_SEPARATOR . $domain,
			$dir . DIRECTORY_SEPARATOR . $locale_short . DIRECTORY_SEPARATOR . $domain,
			$dir . DIRECTORY_SEPARATOR . $locale_short . DIRECTORY_SEPARATOR . 'LC_MESSAGES' . DIRECTORY_SEPARATOR . $domain,
		];

		$po = $mo = null;

		foreach ($paths as $path) {
			if (file_exists($path . '.mo')) {
				$mo = $path;
				break;
			}
			elseif (file_exists($path . '.po')) {
				$po = $path;
				break;
			}
		}

		if ($mo) {
			self::$translations[$domain][$locale] = self::parseGettextMOFile($mo . '.mo', true);
		}
		elseif ($po) {
			self::$translations[$domain][$locale] = self::parseGettextPOFile($po . '.po', true);
		}
		else {
			return null;
		}

		return $domain;
	}

	/**
	 * Stores translations internally from an external source (eg. could be a PHP file, a INI file, YAML, JSON, etc.)
	 * @param  string $domain       Domain
	 * @param  string $locale       Locale
	 * @param  array  $translations List of translations, in format array(msgid => array(0 => msgstr, 1 => plural form, 10 => plural form 10...))
	 * @return void
	 */
	static public function importTranslations(string $domain, string $locale, array $translations): void
	{
		if (!array_key_exists($domain, self::$translations)) {
			self::registerDomain($domain);
		}

		self::$translations[$domain][$locale] = $translations;
	}

	/**
	 * Returns array of loaded Translations for specified domain and locale
	 * @param  string $domain Domain
	 * @param  string $locale Locale
	 * @return array
	 */
	static public function exportTranslations(string $domain, ?string $locale = null): array
	{
		$locale = is_null($locale) ? self::$locale : $locale;
		self::_loadTranslations($domain, $locale);
		return self::$translations[$domain][$locale];
	}

	/**
	 * Guesses the gettext plural form from a 'Plural-Form' header (this is C code)
	 * @param  string $rule C-code describing a plural rule
	 * @param  integer $n   Number to use for the translation
	 * @return integer The number of the plural msgstr
	 */
	static protected function _parseGettextPlural(string $rule, int $n): int
	{
		strtok($rule, '='); // Skip
		$nplurals = (int) strtok(';');
		strtok('='); // skip
		$rule = strtok(''); // Get plural expression

		// Sanitizing input, just in case
		$rule = preg_replace('@[^n_:;\(\)\?\|\&=!<>+*/\%-]@i', '', $rule);

		// Add parenthesis for ternary operators
		$rule = preg_replace('/(.*?)\?(.*?):(.*)1/', '($1) ? ($2) : ($3)', $rule);
		$rule = rtrim($rule, ';');
		$rule = str_replace('n', '$n', $rule);

		// Dirty trick, but this is the easiest way
		$plural = eval('return ' . $rule . ';');

		if ($plural > $nplurals)
		{
			return $nplurals - 1;
		}

		return (int) $plural;
	}

	/**
	 * Returns a plural form from a locale code
	 *
	 * Contains all known plural rules to this day.
	 *
	 * @link https://www.gnu.org/software/libc/manual/html_node/Advanced-gettext-functions.html
	 * @param  string $locale Locale
	 * @param  integer $n     Number used to determine the plural form to use
	 * @return integer The number of the plural msgstr
	 */
	static protected function _guessPlural(string $locale, int $n): int
	{
		if ($locale != 'pt_BR') {
			$locale = substr($locale, 0, 2);
		}

		switch ($locale) {
			// Romanic family: french, brazilian portugese
			case 'fr':
			case 'pt_BR':
				return (int) $n > 1;
			// Asian family: Japanese, Vietnamese, Korean
			// Tai-Kadai family: Thai
			case 'ja':
			case 'th':
			case 'ko':
			case 'vi':
				return 0;
			// Slavic family: Russian, Ukrainian, Belarusian, Serbian, Croatian
			case 'ru':
			case 'uk':
			case 'be':
			case 'sr':
			case 'hr':
				return ($n % 100 / 10 == 1) ? 2 : (($n % 10) == 1 ? 0 : (($n + 9) % 10 > 3 ? 2 : 1));
			// Irish (Gaeilge)
			case 'ga':
				return $n == 1 ? 0 : ($n == 2 ? 1 : 2);
			// Latvian
			case 'lv':
				return ($n % 10 == 1 && $n % 100 != 11) ? 0 : ($n != 0 ? 1 : 2);
			// Lithuanian
			case 'lt':
				return ($n % 10 == 1 && $n % 100 != 11) ? 0 : ($n % 10 >= 2 && ($n % 100 < 10 || $n % 100 >= 20) ? 1 : 2);
				break;
			// Polish
			case 'pl':
				return ($n == 1) ? 0 : ($n % 10 >= 2 && $n % 10 <= 4 && (($n % 100 < 10 || $n % 100 >= 20) ? 1 : 2));
				break;
			// Slovenian
			case 'sl':
				return ($n % 100 == 1) ? 0 : ($n % 100 == 2 ? 1 : (($n % 100 == 3 || $n % 100 == 4) ? 2 : 3));
				break;
			// Slovak, Czech
			case 'sk':
			case 'cs':
				return ($n == 1) ? 1 : (($n >= 2 && $n <= 4) ? 2 : 0);
				break;
			// Arabic: 6 forms
			case 'ar':
				return ($n == 0) ? 0 : (($n == 1) ? 1 : (($n == 2) ? 2 : (($n % 100 >= 3 && $n %100 <= 10) ? 3 : (($n % 100 >= 11) ? 4 : 5))));

			// Germanic family: Danish, Dutch, English, German, Norwegian, Swedish
			// Finno-Ugric family: Estonian, Finnish
			// Latin/Greek family: Greek
			// Semitic family: Hebrew
			// Romance family: Italian, Portuguese, Spanish
			// Artificial: Esperanto
			// Turkic/Altaic family: Turkish
			default:
				return (int) $n != 1;
		}
	}

	/**
	 * Translates a string
	 * @param  string $msgid1  Singular message to translate (or fallback to if no translation is found)
	 * @param  string $msgid2  Plural message to translate (or fallback)
	 * @param  integer $n      Number used to determine plural form of translation
	 * @param  string $domain  Optional domain
	 * @param  string $context Optional translation context (msgctxt in gettext)
	 * @return string
	 */
	static public function gettext(string $msgid1, ?string $msgid2 = null, ?int $n = null, ?string $domain = null, ?string $context = null): string
	{
		$domain = self::_loadTranslations($domain);

		$id = $msgid1;

		if (null !== $msgid2) {
			$id .= chr(0) . $msgid2;
		}

		// Append context of the msgid
		if (null !== $context) {
			$id = $context . chr(4) . $id;
		}

		$locale_short = strtok(self::$locale, '_');
		strtok('');
		$str = null;

		$domain ??= '';

		if (isset(self::$translations[$domain][self::$locale][$id])) {
			$str = self::$translations[$domain][self::$locale][$id];
		}
		elseif (isset(self::$translations[$domain][$locale_short][$id])) {
			$str = self::$translations[$domain][$locale_short][$id];
		}

		// No translations for this id
		if ($str === null) {
			if ($msgid2 !== null && $n !== null) {
				// Use english plural rule here
				return ($n != 1) ? $msgid2 : $msgid1;
			}

			return $msgid1;
		}

		$plural = !is_null($n) && !is_null($msgid2) ? self::_guessPlural(self::$locale, $n) : 0;

		if (!isset($str[$plural])) {
			// No translation for this plural form: fallback to first form
			$plural = 0;
		}

		if (!isset($str[$plural])) {
			// No translation for plural form, even after fallback, return msgid
			return $plural ? $msgid2 : $msgid1;
		}

		return $str[$plural];
	}

	/**
	 * Simple translation of a string
	 * @param  string      $msgid        Message ID to translate (will be used as fallback if no translation is found)
	 * @param  array       $args         Optional arguments to replace in translated string
	 * @param  string      $domain       Optional domain
	 * @param  string      $context      Optional translation context (msgctxt in gettext)
	 * @return string
	 */
	static public function string(string $msgid, ?array $args = null, ?string $domain = null, ?string $context = null): string
	{
		$args ??= [];

		if (is_array($msgid)) {
			if (count($msgid) !== 3) {
				throw new \InvalidArgumentException('Invalid plural msgid: array should be [msgid, msgid_plural, count]');
			}

			$str = self::gettext($msgid[0], $msgid[1], $msgid[2], $domain, $context);
			$args['count'] = $msgid[2];
		}
		else {
			$str = self::gettext($msgid, null, null, $domain, $context);
		}

		return self::named_sprintf($str, $args);
	}

	/**
	 * Plural translation
	 * @param  string      $msgid        Message ID to translate (will be used as fallback)
	 * @param  string      $msgid_plural Optional plural ID
	 * @param  integer     $count        Number used to determine which plural form should be returned
	 * @param  array       $args         Optional arguments to replace in translated string
	 * @param  string      $domain       Optional domain
	 * @param  string      $context      Optional translation context (msgctxt in gettext)
	 * @return string
	 */
	static public function plural(string $msgid, string $msgid_plural, int $count, ?array $args = null, ?string $domain = null, ?string $context = null): string
	{
		$str = self::gettext($msgid, $msgid_plural, $count, $domain, $context);
		return self::named_sprintf($str, $args);
	}

	/**
	 * vsprintf + replace named arguments too (eg. %name)
	 * @param  string $str  String to format
	 * @param  array  $args Arguments
	 * @return string
	 */
	static public function named_sprintf(string $str, array $args): string
	{
		foreach ($args as $k => $v) {
			$str = preg_replace('/%' . preg_quote($k, '/') . '(?=\s|[^\w\d_]|$)/', $v, $str);
		}

		if (strpos($str, '%') !== false && count($args)) {
			return vsprintf($str, $args);
		}

		return $str;
	}

	/**
	 * Parses a gettext compiled .mo file and returns an array
	 * @link http://include-once.org/upgradephp-17.tgz Source
	 * @param  string $path .mo file path
	 * @param  boolean $one_msgid_only If set to true won't return an entry for msgid_plural
	 * (used internally to reduce cache size)
	 * @return null|array        array of translations
	 */
	static public function parseGettextMOFile(string $path, bool $one_msgid_only = false): ?array
	{
		$fp = fopen($path, 'rb');

		// Read header
		$data = fread($fp, 20);
		$header = unpack('L1magic/L1version/L1count/L1o_msg/L1o_trn', $data);
		extract($header);

		if ((dechex($magic) != '950412de') || ($version != 0)) {
			return null;
		}

		// Read the rest of the file
		$data .= fread($fp, 1<<20);

		if (!$data) {
			return null;
		}

		$translations = [];

		// fetch all entries
		for ($n = 0; $n < $count; $n++) {
			// msgid
			$r = unpack('L1length/L1offset', substr($data, $o_msg + $n * 8, 8));
			$msgid = substr($data, $r['offset'], $r['length']);

			if (strpos($msgid, "\000")) {
				list($msgid, $msgid_plural) = explode("\000", $msgid);
			}

			// translation(s)
			$r = unpack('L1length/L1offset', substr($data, $o_trn + $n * 8, 8));
			$msgstr = explode(chr(0), substr($data, $r['offset'], $r['length']));

			$translations[$msgid] = $msgstr;

			if (isset($msgid_plural) && !$one_msgid_only) {
				$translations[$msgid_plural] =& $translations[$msgid];
			}
		}

		return $translations;
	}

	/**
	 * Parses a gettext raw .po file and returns an array
	 * @link http://include-once.org/upgradephp-17.tgz Source
	 * @param  string $path .po file path
	 * @return array        array of translations
	 */
	static public function parseGettextPOFile(string $path): array
	{
		static $c_esc = ["\\n"=>"\n", "\\r"=>"\r", "\\\\"=>"\\", "\\f"=>"\f", "\\t"=>"\t", "\\"=>""];

		$fp = fopen($path, 'r');
		$l = 0;
		$translations = [];

		$context = null;
		$msgid = null;
		$msgstr = [];
		$msgctxt = null;

		$append_translation = function ($msgid, $msgstr, $msgctxt) use (&$translations, $c_esc) {
			if (trim($msgid) === '') {
				return;
			}

			$msgid = strtr($msgid, $c_esc);

			// context: link to msgid with a EOF character
			// see https://secure.php.net/manual/fr/book.gettext.php#89975
			if ($msgctxt !== null) {
				$msgid = $msgctxt . chr(4) . $msgid;
			}

			$translations[$msgid] = [];

			foreach ($msgstr as $v) {
				$translations[$msgid][] = strtr($v, $c_esc);
			}
		};

		do {
			$line = trim(fgets($fp));
			$l++;
			$space = strpos($line, ' ');
			$word = false !== $space ? substr($line, 0, $space) : null;

			// Ignore comments
			if (substr($line, 0, 1) === "#") {
				continue;
			}
			// append msgid_plural
			elseif ($word === 'msgid') {
				if (null !== $msgid) {
					$append_translation($msgid, $msgstr, $msgctxt);
					$msgid = null;
					$msgstr = [];
					$msgctxt = null;
					$context = null;
				}

				$v = trim(substr($line, $space + 1), '"');
				$msgid = $v;
				$context = 'msgid';
			}
			elseif ($word === 'msgid_plural') {
				if ($context !== 'msgid') {
					throw new \LogicException(sprintf('Line %d: msgid_plural must follow msgid', $l));
				}

				$v = trim(substr($line, $space + 1), '"');
				$msgid .= chr(0) . $v;
			}
			// translation
			elseif (null !== $word && substr($word, 0, 6) === 'msgstr') {
				if ($context !== 'msgid' && !count($msgstr)) {
					throw new \LogicException(sprintf('Line %d: msgstr must follow msgid (%s): %s', $l, $context, $line));
				}

				$v = trim(substr($line, $space + 1), '"');
				$msgstr[] = $v;
				$context = 'msgstr';
			}
			// Context
			elseif ($word === 'msgctxt') {
				$v = trim(substr($line, $space + 1), '"');
				$msgctxt = $v;
				$context = 'msgctxt';
			}
			// continued (could be _id or _str)
			elseif (substr($line, 0, 1) === '"') {
				$line = trim($line, '"');

				if ($context === 'msgstr') {
					$msgstr[key($msgstr)] .= $line;
				}
				elseif ($context === 'msgid') {
					$msgid .= $line;
				}
				elseif ($context === 'msgctxt') {
					$msgctxt .= $line;
				}
			}
		}
		while (!feof($fp));

		if (null !== $msgid) {
			$append_translation($msgid, $msgstr, $msgctxt);
		}

		fclose($fp);

		return $translations;
	}

	/**
	 * Returns the preferred language of the client from its HTTP Accept-Language header
	 * @param  boolean $full_locale Set to TRUE to get the real locale ('en_AU' for example), false will return only the lang ('en')
	 */
	static public function getHttpLang(bool $full_locale = false): ?string
	{
		if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
			return null;
		}

		// Convenient PECL Intl function
		if (function_exists('locale_accept_from_http')) {
			$default = ini_get('intl.use_exceptions');
			ini_set('intl.use_exceptions', 1);

			try {
				$locale = locale_accept_from_http($_SERVER['HTTP_ACCEPT_LANGUAGE']);
				return ($full_locale || !$locale) ? $locale : substr($locale, 0, 2);
			}
			catch (\IntlException $e) {
				return null;
			}
			finally {
				ini_set('intl.use_exceptions', $default);
			}
		}

		// Let's do the same thing by hand
		$http_langs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
		$locale = null;
		$locale_priority = 0;

		// For each locale extract its priority
		foreach ($http_langs as $lang) {
			if (preg_match('/;q=([0-9.,]+)/', $lang, $match)) {
				$q = (int) $match[1] * 10;
				$lang = str_replace($match[0], '', $lang);
			}
			else {
				$q = 10;
			}

			$lang = strtolower(trim($lang));

			if (strlen($lang) > 2) {
				$lang = explode('-', $lang);
				$lang = array_slice($lang, 0, 2);
				$lang = $lang[0] . '_' . strtoupper($lang[1]);
			}

			// Higher priority than the previous one?
			// Let's use it then!
			if ($q > $locale_priority) {
				$locale = $lang;
			}
		}

		return ($full_locale || !$locale) ? $locale : substr($locale, 0, 2);
	}

	/**
	 * Locale-formatted strftime using \IntlDateFormatter (PHP 8.1 compatible)
	 * @param  string $format Date format
	 * @param  integer|string|\DateTime $timestamp Timestamp
	 * @return string
	 * @see https://github.com/alphp/strftime
	 * @see https://gist.github.com/bohwaz/42fc223031e2b2dd2585aab159a20f30
	 */
	static public function strftime(string $format, $timestamp = null, ?string $locale = null): string
	{
		if (!($timestamp instanceof \DateTimeInterface)) {
			$timestamp = is_int($timestamp) ? '@' . $timestamp : (string) $timestamp;

			try {
				$timestamp = new \DateTime($timestamp);
			} catch (Exception $e) {
				throw new \InvalidArgumentException('$timestamp argument is neither a valid UNIX timestamp, a valid date-time string or a DateTime object.', 0, $e);
			}

			$timestamp->setTimezone(new \DateTimeZone(date_default_timezone_get()));
		}

		$locale = \Locale::canonicalize($locale ?? (self::$locale ?? (\Locale::getDefault() ?? setlocale(LC_TIME, '0'))));

		$intl_formats = [
			'%a' => 'ccc',	// An abbreviated textual representation of the day	Sun through Sat
			'%A' => 'EEEE',	// A full textual representation of the day	Sunday through Saturday
			'%b' => 'LLL',	// Abbreviated month name, based on the locale	Jan through Dec
			'%B' => 'MMMM',	// Full month name, based on the locale	January through December
			'%h' => 'MMM',	// Abbreviated month name, based on the locale (an alias of %b)	Jan through Dec
		];

		static $intl_formatter = function (\DateTimeInterface $timestamp, string $format) use ($intl_formats, $locale) {
			$tz = $timestamp->getTimezone();
			$date_type = \IntlDateFormatter::FULL;
			$time_type = \IntlDateFormatter::FULL;
			$pattern = '';

			switch ($format) {
				// %c = Preferred date and time stamp based on locale
				// Example: Tue Feb 5 00:45:10 2009 for February 5, 2009 at 12:45:10 AM
				case '%c':
					$date_type = \IntlDateFormatter::LONG;
					$time_type = \IntlDateFormatter::SHORT;
					break;

				// %x = Preferred date representation based on locale, without the time
				// Example: 02/05/09 for February 5, 2009
				case '%x':
					$date_type = \IntlDateFormatter::SHORT;
					$time_type = \IntlDateFormatter::NONE;
					break;

				// Localized time format
				case '%X':
					$date_type = \IntlDateFormatter::NONE;
					$time_type = \IntlDateFormatter::MEDIUM;
					break;

				default:
					$pattern = $intl_formats[$format];
			}

			// In October 1582, the Gregorian calendar replaced the Julian in much of Europe, and
			//  the 4th October was followed by the 15th October.
			// ICU (including IntlDateFormattter) interprets and formats dates based on this cutover.
			// Posix (including strftime) and timelib (including DateTimeImmutable) instead use
			//  a "proleptic Gregorian calendar" - they pretend the Gregorian calendar has existed forever.
			// This leads to the same instants in time, as expressed in Unix time, having different representations
			//  in formatted strings.
			// To adjust for this, a custom calendar can be supplied with a cutover date arbitrarily far in the past.
			$calendar = \IntlGregorianCalendar::createInstance();
			// NOTE: IntlGregorianCalendar::createInstance DOES NOT return an IntlGregorianCalendar instance when
			// using a non-Gregorian locale (e.g. fa_IR)! In that case, setGregorianChange will not exist.
			if ($calendar instanceof \IntlGregorianCalendar) {
				$calendar->setGregorianChange(PHP_INT_MIN);
			}

			return (new \IntlDateFormatter($locale, $date_type, $time_type, $tz, $calendar, $pattern))->format($timestamp);
		};

		// Same order as https://www.php.net/manual/en/function.strftime.php
		$translation_table = [
			// Day
			'%a' => $intl_formatter,
			'%A' => $intl_formatter,
			'%d' => 'd',
			'%e' => function ($timestamp) {
				return sprintf('% 2u', $timestamp->format('j'));
			},
			'%j' => function ($timestamp) {
				// Day number in year, 001 to 366
				return sprintf('%03d', $timestamp->format('z')+1);
			},
			'%u' => 'N',
			'%w' => 'w',

			// Week
			'%U' => function ($timestamp) {
				// Number of weeks between date and first Sunday of year
				$day = new DateTime(sprintf('%d-01 Sunday', $timestamp->format('Y')));
				return sprintf('%02u', 1 + ($timestamp->format('z') - $day->format('z')) / 7);
			},
			'%V' => 'W',
			'%W' => function ($timestamp) {
				// Number of weeks between date and first Monday of year
				$day = new DateTime(sprintf('%d-01 Monday', $timestamp->format('Y')));
				return sprintf('%02u', 1 + ($timestamp->format('z') - $day->format('z')) / 7);
			},

			// Month
			'%b' => $intl_formatter,
			'%B' => $intl_formatter,
			'%h' => $intl_formatter,
			'%m' => 'm',

			// Year
			'%C' => function ($timestamp) {
				// Century (-1): 19 for 20th century
				return floor($timestamp->format('Y') / 100);
			},
			'%g' => function ($timestamp) {
				return substr($timestamp->format('o'), -2);
			},
			'%G' => 'o',
			'%y' => 'y',
			'%Y' => 'Y',

			// Time
			'%H' => 'H',
			'%k' => function ($timestamp) {
				return sprintf('% 2u', $timestamp->format('G'));
			},
			'%I' => 'h',
			'%l' => function ($timestamp) {
				return sprintf('% 2u', $timestamp->format('g'));
			},
			'%M' => 'i',
			'%p' => 'A', // AM PM (this is reversed on purpose!)
			'%P' => 'a', // am pm
			'%r' => 'h:i:s A', // %I:%M:%S %p
			'%R' => 'H:i', // %H:%M
			'%S' => 's',
			'%T' => 'H:i:s', // %H:%M:%S
			'%X' => $intl_formatter, // Preferred time representation based on locale, without the date

			// Timezone
			'%z' => 'O',
			'%Z' => 'T',

			// Time and Date Stamps
			'%c' => $intl_formatter,
			'%D' => 'm/d/Y',
			'%F' => 'Y-m-d',
			'%s' => 'U',
			'%x' => $intl_formatter,
		];

		$out = preg_replace_callback('/(?<!%)%([_#-]?)([a-zA-Z])/', function ($match) use ($translation_table, $timestamp) {
			$prefix = $match[1];
			$char = $match[2];
			$pattern = '%'.$char;
			if ($pattern == '%n') {
				return "\n";
			} elseif ($pattern == '%t') {
				return "\t";
			}

			if (!isset($translation_table[$pattern])) {
				throw new \InvalidArgumentException(sprintf('Format "%s" is unknown in time format', $pattern));
			}

			$replace = $translation_table[$pattern];

			if (is_string($replace)) {
				$result = $timestamp->format($replace);
			} else {
				$result = $replace($timestamp, $pattern);
			}

			switch ($prefix) {
				case '_':
					// replace leading zeros with spaces but keep last char if also zero
					return preg_replace('/\G0(?=.)/', ' ', $result);
				case '#':
				case '-':
					// remove leading zeros but keep last char if also zero
					return preg_replace('/^[0\s]+(?=.)/', '', $result);
			}

			return $result;
		}, $format);

		$out = str_replace('%%', '%', $out);
		return $out;
	}

	/**
	 * Returns an associative array list of countries (ISO-3166:2013)
	 *
	 * @param  string $lang Language to use (only 'fr' and 'en' are available)
	 * @return array
	 */
	static public function getCountriesList(?string $lang = null): array
	{
		if (null === $lang) {
			$lang = substr(self::$locale, 0, 2);
		}

		if ($lang != 'fr') {
			$lang = 'en';
		}

		$path = sprintf('%s/data/countries.%s.json', __DIR__, $lang);
		$file = file_get_contents($path);

		return json_decode($file, true);
	}

	/**
	 * Register a new template block in Smartyer to call KD2Intl::gettext()
	 * @param  Smartyer &$tpl Smartyer instance
	 * @return Smartyer
	 */
	static public function extendSmartyer(Smartyer &$tpl)
	{
		$tpl->register_modifier('date_format', function ($date, $format = '%c') {
			if (!is_object($date) && !is_numeric($date)) {
				$date = strtotime($date);
			}

			if (strpos('DATE_', $format) === 0 && defined($format)) {
				if (is_object($date)) {
					return $date->format(constant($format));
				}
				else {
					return date(constant($format), $date);
				}
			}

			return \KD2\Translate::strftime($format, $date);
		});

		return $tpl->register_compile_function('KD2\Translate', [self::class, '_smartyerBlock']);
	}

	/**
	 * Trying to get around the static limitation of closures in PHP < 7
	 * @link   https://bugs.php.net/bug.php?id=68792
	 * @param  Smartyer $tpl Smartyer instance
	 */
	static public function _smartyerBlock(Smartyer &$s, $pos, $block, $name, $raw_args)
	{
		$block = trim($block);

		if ($block[0] != '{') {
			return false;
		}

		// Extract strings from arguments
		$block = preg_split('#\{((?:[^\{\}]|(?R))*?)\}#i', $block, 0, PREG_SPLIT_DELIM_CAPTURE);
		$raw_args = '';
		$strings = [];

		foreach ($block as $k => $v) {
			if ($k % 2 == 0) {
				$raw_args .= $v;
			}
			else {
				$strings[] = trim($v);
			}
		}

		$nb_strings = count($strings);

		if ($nb_strings < 1) {
			$s->parseError($pos, 'No string found in translation block: ' . $block);
		}

		// Only one plural is allowed
		if ($nb_strings > 2) {
			$s->parseError($pos, 'Maximum number of translation strings is 2, found ' . $nb_strings . ' in: ' . $block);
		}

		$args = $s->parseArguments($raw_args);

		$code = sprintf('\KD2\Translate::gettext(%s, ', var_export($strings[0], true));

		if ($nb_strings > 1) {
			if (!isset($args['n'])) {
				$s->parseError($pos, 'Multiple strings in translation block, but no \'n\' argument.');
			}

			$code .= sprintf('%s, (int) %s, ', var_export($strings[1], true), $args['n']);
		}
		else {
			$code .= 'null, null, ';
		}

		// Add domain and context
		$code .= sprintf('%s, %s)',
			isset($args['domain']) ? $args['domain'] : 'null',
			isset($args['context']) ? $args['context'] : 'null');

		$escape = $s->getEscapeType();

		if (isset($args['escape'])) {
			$escape = strtolower($args['escape']);
		}

		$assign = $args['assign'] ?? null;

		if ($assign) {
			$assign = trim($assign, '"\'');
			$s->validateVariableName($assign);
		}

		unset($args['escape'], $args['domain'], $args['context'], $args['assign']);

		// Use named arguments: %name, %nb_apples...
		// This will cause weird bugs if you use %s, or %d etc. before or between named arguments
		if (!empty($args)) {
			$code = sprintf('\KD2\Translate::named_sprintf(%s, %s)', $code, $s->exportArguments($args));
		}

		if ($escape != 'false' && $escape != 'off' && $escape !== '') {
			$code = sprintf('self::escape(%s, %s)', $code, var_export($escape, true));
		}

		if ($assign) {
			$code = sprintf('${%s} = %s;', var_export($assign, true), $code);
		}
		else {
			$code = sprintf('echo %s;', $code);
		}

		return $code;
	}
}

/*
	Gettext compatible functions
	Just prefix calls to gettext functions by \KD2\
	eg _("Hi!") => \KD2\_("Hi!")
	Or add at the top of your files:

	// PHP 7+
	use function \KD2\{_, gettext, ngettext, dgettext, dngettext, bindtextdomain, textdomain, setlocale}
*/

function _($id, array $args = [], $domain = null)
{
	return Translate::string($id, $args, $domain);
}

function gettext($id)
{
	return Translate::gettext($id);
}

function ngettext($id, $plural, $count)
{
	return Translate::gettext($id, $plural, $count);
}

function dgettext($domain, $id)
{
	return Translate::gettext($id, null, null, $domain);
}

function dngettext($domain, $id, $id_plural, $count)
{
	return Translate::gettext($id, $id_plural, $count, $domain);
}

function dcgettext($domain, $id, $category)
{
	return Translate::gettext($id, null, null, $domain);
}

function dcngettext($domain, $id, $id_plural, $count, $category)
{
	return Translate::gettext($id, $id_plural, $count, $domain);
}

function bind_textdomain_codeset($domain, $codeset)
{
	// Not used
}

function bindtextdomain($domain_name, $dir)
{
	return Translate::registerDomain($domain_name, $dir);
}

function textdomain($domain)
{
	return Translate::setDefaultDomain($domain);
}

function setlocale($category, $locale)
{
	return Translate::setLocale($locale);
}


// Context aware gettext functions
// see https://github.com/azatoth/php-pgettext/blob/master/pgettext.php
function pgettext($context, $msgid)
{
	return Translate::gettext($msgid, null, null, null, $context);
}

function dpgettext($domain, $context, $msgid)
{
	return Translate::gettext($msgid, null, null, $domain, $context);
}

function dcpgettext($domain, $context, $msgid, $category)
{
	return Translate::gettext($msgid, null, null, $domain, $context);
}

function npgettext($context, $msgid, $msgid_plural, $count)
{
	return Translate::gettext($msgid, $msgid_plural, $count, null, $context);
}

function dnpgettext($domain, $context, $msgid, $msgid_plural, $count)
{
	return Translate::gettext($msgid, $msgid_plural, $count, $domain, $context);
}

function dcnpgettext($domain, $context, $msgid, $msgid_plural, $count, $category)
{
	return Translate::gettext($msgid, $msgid_plural, $count, $domain, $context);
}
