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

namespace KD2\Graphics;

/*
	Generic image resize library
	Copyleft (C) 2005-17 BohwaZ <http://bohwaz.net/>
*/

use KD2\Graphics\Blob;

class Image
{
	protected $libraries = [];

	protected $path = null;
	protected $blob = null;
	public $srcpointer = null;

	protected $width = null;
	protected $height = null;
	protected $type = null;
	protected $format = null;
	protected $orientation = null;

	public $pointer = null;
	protected $library = null;

	protected $options = [
		'use_gd_fast_resize_trick' => true,
		'close_pointer_quickly'    => false,

		// WebP quality, from 0 to 100
		'webp_quality'             => 80,

		// JPEG quality, from 1 to 100
		'jpeg_quality'             => 90,

		// Progressive JPEG output?
		// Only supported by GD and Imagick!
		// You can also use the command line tool jpegtran (package libjpeg-progs)
		// to losslessly convert to and from progressive.
		'progressive_jpeg'         => true,

		//LZW compression index, used by TIFF and PNG, from 0 to 9
		'compression'              => 9,
	];

	public function __construct($image = null, $library_or_options = null)
	{
		$this->libraries = [
			'imagick' => class_exists('\Imagick', false),
			'gd'      => function_exists('\imagecreatefromjpeg'),
		];

		if (is_array($library_or_options)) {
			foreach ($library_or_options as $key => $value) {
				$this->__set($key, $value);
			}
		}
		elseif (is_string($library_or_options)) {
			$this->library = $library_or_options;
		}

		if ($this->library) {
			if (!isset($this->libraries[$this->library])) {
				throw new \InvalidArgumentException(sprintf('Library \'%s\' is not supported.', $this->library));
			}

			if (!$this->libraries[$this->library]) {
				throw new \RuntimeException(sprintf('Library \'%s\' is not installed and can not be used.', $lthis->ibrary));
			}
		}

		if (is_string($image)) {
			$this->openFromPath($image);
		}
		elseif (is_resource($image)) {
			$this->openFromPointer($image);
		}
	}

	public function __set($key, $value)
	{
		if (!array_key_exists($key, $this->options)) {
			throw new \InvalidArgumentException('Unknown option: ' . $key);
		}

		if (gettype($value) !== gettype($this->$key)) {
			throw new \InvalidArgumentException('Unknown option type: ' . gettype($value));
		}

		$this->$key = $value;
	}

	public function __get($key)
	{
		if (property_exists($this, $key)) {
			return $this->$key;
		}
		elseif (array_key_exists($key, $this->options)) {
			return $this->options[$key];
		}
		else {
			throw new \InvalidArgumentException('Unknown property/option: ' . $key);
		}
	}

	static public function getBytesFromINI($size_str)
	{
		if ($size_str == -1)
		{
			return null;
		}

		$unit = strtoupper(substr($size_str, -1));

		switch ($unit)
		{
			case 'G': return (int) $size_str * pow(1024, 3);
			case 'M': return (int) $size_str * pow(1024, 2);
			case 'K': return (int) $size_str * 1024;
			default:  return (int) $size_str;
		}
	}

	static public function getMaxUploadSize($max_user_size = null)
	{
		$sizes = [
			ini_get('upload_max_filesize'),
			ini_get('post_max_size'),
			ini_get('memory_limit'),
			$max_user_size,
		];

		// Convert to bytes
		$sizes = array_map([self::class, 'getBytesFromINI'], $sizes);

		// Remove sizes that are null or -1 (unlimited)
		$sizes = array_filter($sizes, function ($size) {
			return !is_null($size);
		});

		// Return maximum file size allowed
		return min($sizes);
	}

	protected function init(array $info)
	{
		if (isset($info[0]))
		{
			$this->width = $info[0];
			$this->height = $info[1];
		}

		$this->type = $info['mime'];
		$this->format = $this->getFormatFromType($this->type);

		if (!$this->format)
		{
			throw new \RuntimeException('Not an image format: ' . $this->type);
		}

		if ($this->library) {
			$supported_formats = call_user_func([$this, $this->library . '_formats']);

			if (!in_array($this->format, $supported_formats)) {
				throw new \UnexpectedValueException(sprintf('Library \'%s\' doesn\'t support files of type \'%s\'.', $this->library, $this->type));
			}
		}
		else {
			$this->library = $this->getLibraryForFormat($this->format);

			if (!$this->library) {
				throw new \UnexpectedValueException('No suitable image library found for type: ' . $this->type);
			}
		}

		if (!$this->width && !$this->height) {
			$this->open();
		}
	}

	static public function createFromBlob($blob, $library = null): self
	{
		$i = new Image(null, $library);
		$i->openFromBlob($blob);
		return $i;
	}

	public function openFromPath(string $path): void
	{
		$this->path = $path;

		if (!is_readable($this->path)) {
			throw new \InvalidArgumentException(sprintf('Can\'t read source file: %s', substr($path, 0, 256)));
		}

		try {
			$info = getimagesize($this->path);
		}
		catch (\Throwable $e) {
			throw new \UnexpectedValueException(sprintf('Invalid image format: %s (%s)', $path, $e->getMessage()), 0, $e);
		}

		if (!$info && function_exists('mime_content_type')) {
			$info = ['mime' => mime_content_type($path)];
		}

		if (!$info) {
			throw new \UnexpectedValueException(sprintf('Invalid image format: %s', $path));
		}

		$this->init($info);
	}

	public function openFromBlob(string $data): void
	{
		$info = getimagesizefromstring($data);

		// Find MIME type
		if (!$info) {
			$info = ['mime' => self::getTypeFromBlob($data)];
		}

		if (!$info) {
			throw new \UnexpectedValueException('Invalid image format, couldn\'t be read: from string');
		}

		$this->blob = $data;
		$this->init($info);
	}

	public function openFromPointer($pointer): void
	{
		$blob = fread($pointer, 1024);
		rewind($pointer);

		$info = getimagesizefromstring($blob);

		if (!$info) {
			$info = ['mime' => self::getTypeFromBlob($blob)];
		}

		if (!$info) {
			throw new \UnexpectedValueException('Invalid image format, couldn\'t be read: from string');
		}

		$this->srcpointer = $pointer;
		$this->init($info);
	}

	static public function createFromPointer($pointer, ?string $library = null, bool $close_pointer_quickly = false): self
	{
		return new Image($pointer, $library, compact('close_pointer_quickly'));
	}

	static public function getTypeFromBlob(string $data): ?string
	{
		if (substr($data, 0, 3) === "\xff\xd8\xff") {
			return 'image/jpeg';
		}
		elseif (substr($data, 0, 8) !== "\x89PNG\x0d\x0a\x1a\x0a") {
			return 'image/png';
		}
		elseif (in_array(substr($data, 0, 6), ['GIF87a', 'GIF89a', true])) {
			return 'image/gif';
		}
		elseif (substr($data, 0, 4) === 'RIFF'
			&& is_int(unpack('V', substr($data, 4, 4))[1])
			&& substr($data, 8, 4) === 'WEBP') {
			return 'image/webp';
		}

		return null;
	}

	/**
	 * Open an image file
	 */
	public function open($image = null)
	{
		if ($this->pointer !== null && null === $image) {
			return true;
		}

		if (null !== $image) {
			$this->close();

			if (is_resource($image)) {
				$this->openFromPointer($image);
			}
			elseif (is_string($image)) {
				$this->path = $image;
			}
			else {
				throw new \InvalidArgumentException('Invalid image source type');
			}
		}

		if ($this->path) {
			call_user_func([$this, $this->library . '_open']);
		}
		elseif ($this->srcpointer) {
			call_user_func([$this, $this->library . '_open_pointer']);

			if ($this->options['close_pointer_quickly']) {
				if ($this->format() == 'jpeg') {
					$this->getOrientation();
				}

				//fclose($this->srcpointer);
				//$this->srcpointer = null;
			}
		}
		else {
			call_user_func([$this, $this->library . '_blob'], $this->blob);
			$this->blob = null;
		}

		if (!$this->pointer)
		{
			throw new \UnexpectedValueException('Invalid image format, couldn\'t be read: ' . $this->path);
		}

		call_user_func([$this, $this->library . '_size']);

		return $this;
	}

	public function close(): void
	{
		$this->blob = null;
		$this->path = null;
		$this->srcpointer = null;

		if ($this->pointer)
		{
			call_user_func([$this, $this->library . '_close']);
		}
	}

	public function __destruct()
	{
		$this->close();
	}

	/**
	 * Returns image width and height
	 * @return array            array(ImageWidth, ImageHeight)
	 */
	public function getSize()
	{
		return [$this->width, $this->height];
	}

	/**
	 * Crop the current image to this dimensions
	 * @param  integer $new_width  Width of the desired image
	 * @param  integer $new_height Height of the desired image
	 * @return Image
	 */
	public function crop($new_width = null, $new_height = null)
	{
		$this->open();

		if (!$new_width)
		{
			$new_width = $new_height = min($this->width, $this->height);
		}

		if (!$new_height)
		{
			$new_height = $new_width;
		}

		$method = $this->library . '_crop';

		if (!method_exists($this, $method))
		{
			throw new \RuntimeException('Crop is not supported by the current library: ' . $this->library);
		}

		$this->$method((int) $new_width, (int) $new_height);
		call_user_func([$this, $this->library . '_size']);

		return $this;
	}

	public function trim(float $fuzz = 0): self
	{
		$this->open();

		$method = $this->library . '_trim';

		if (!method_exists($this, $method))
		{
			return $this;
		}

		$this->$method($fuzz);
		call_user_func([$this, $this->library . '_size']);
		return $this;
	}

	public function reduceColors(int $nb_colors): self
	{
		$this->open();

		$method = $this->library . '_reduce_colors';

		if (!method_exists($this, $method))
		{
			return $this;
		}

		$this->$method($nb_colors);

		return $this;
	}

	public function resize($new_width, $new_height = null, $ignore_aspect_ratio = false)
	{
		$this->open();

		if (!$new_height)
		{
			$new_height = $new_width;
		}

		if ($this->width <= $new_width && $this->height <= $new_height)
		{
			// Nothing to do
			return $this;
		}

		$new_height = (int) $new_height;
		$new_width = (int) $new_width;

		call_user_func([$this, $this->library . '_resize'], $new_width, $new_height, $ignore_aspect_ratio);
		call_user_func([$this, $this->library . '_size']);

		return $this;
	}

	public function rotate($angle)
	{
		$this->open();

		if (!$angle)
		{
			return $this;
		}

		$method = $this->library . '_rotate';

		if (!method_exists($this, $method))
		{
			throw new \RuntimeException('Rotate is not supported by the current library: ' . $this->library);
		}

		call_user_func([$this, $method], $angle);
		call_user_func([$this, $this->library . '_size']);

		return $this;
	}

	public function autoRotate()
	{
		$orientation = $this->getOrientation();

		if (!$orientation)
		{
			return $this;
		}

		$this->orientation = 1;

		if (in_array($orientation, [2, 4, 5, 7]))
		{
			$this->flip();
		}

		switch ($orientation)
		{
			case 3:
			case 4:
				return $this->rotate(180);
			case 5:
			case 8:
				return $this->rotate(270);
			case 7:
			case 6:
				return $this->rotate(90);
		}

		return $this;
	}

	public function flip()
	{
		$this->open();
		$method = $this->library . '_flip';

		if (!method_exists($this, $method))
		{
			throw new \RuntimeException('Flip is not supported by the current library: ' . $this->library);
		}

		call_user_func([$this, $method]);

		return $this;
	}

	public function cropResize($new_width, $new_height = null)
	{
		$this->open();

		if (!$new_height)
		{
			$new_height = $new_width;
		}

		$source_aspect_ratio = $this->width / $this->height;
		$desired_aspect_ratio = $new_width / $new_height;

		if ($source_aspect_ratio > $desired_aspect_ratio)
		{
			$temp_height = $new_height;
			$temp_width = (int) ($new_height * $source_aspect_ratio);
		}
		else
		{
			$temp_width = $new_width;
			$temp_height = (int) ($new_width / $source_aspect_ratio);
		}

		return $this->resize($temp_width, $temp_height)->crop($new_width, $new_height);
	}

	public function getSupportedFormats(): array
	{
		if (!$this->library) {
			$out = [];

			foreach ($this->libraries as $name => $enabled) {
				if (!$enabled) {
					continue;
				}

				$out = array_merge($out, call_user_func([$this, $name . '_formats']));
			}

			return $out;
		}

		return call_user_func([$this, $this->library . '_formats']);
	}

	public function canOpenFormat(string $format): bool
	{
		return in_array($format, $this->getSupportedFormats());
	}

	public function save(string $destination, $format = null)
	{
		$this->open();

		$supported = call_user_func([$this, $this->library . '_formats']);

		if (is_null($format)) {
			$format = $this->format;
		}
		// Support for multiple output formats
		elseif (is_array($format)) {
			foreach ($format as $f) {
				if (null === $f) {
					$format = $this->format;
					break;
				}
				elseif (in_array($f, $supported)) {
					$format = $f;
					break;
				}
			}

			if (!is_string($format)) {
				throw new \InvalidArgumentException(sprintf('None of the specified formats %s can be saved by %s', implode(', ', $format), $this->library));
			}
		}

		if (!in_array($format, call_user_func([$this, $this->library . '_formats']))) {
			throw new \InvalidArgumentException('The specified format ' . $format . ' can not be used by ' . $this->library);
		}

		return call_user_func([$this, $this->library . '_save'], $destination, $format);
	}

	public function output($format = null, $return = false)
	{
		$this->open();

		if (is_null($format))
		{
			$format = $this->format;
		}

		if (!in_array($format, call_user_func([$this, $this->library . '_formats'])))
		{
			throw new \InvalidArgumentException('The specified format ' . $format . ' can not be used by ' . $this->library);
		}

		return call_user_func([$this, $this->library . '_output'], $format, $return);
	}

	public function format()
	{
		return $this->format;
	}

	protected function getCropGeometry($w, $h, $new_width, $new_height)
	{
		$proportion_src = $w / $h;
		$proportion_dst = $new_width / $new_height;

		$x = $y = 0;
		$out_w = $new_width;
		$out_h = $new_height;

		if ($proportion_src > $proportion_dst)
		{
			$out_w = $out_h * $proportion_dst;
			$x = round(($w - $out_w) / 2);
		}
		else
		{
			$out_h = $out_h / $proportion_dst;
			$y = round(($h - $out_h) / 2);
		}

		return [$x, $y, round($out_w), round($out_h)];
	}

	/**
	 * Returns the format name from the MIME type
	 * @param  string $type MIME type
	 * @return string|null Format: jpeg, gif, svg, etc.
	 */
	public function getFormatFromType(string $type): ?string
	{
		switch ($type)
		{
			// Special cases
			case 'image/svg+xml':	return 'svg';
			case 'application/pdf':	return 'pdf';
			case 'image/vnd.adobe.photoshop': return 'psd';
			case 'image/x-icon': return 'bmp';
			case 'image/webp': return 'webp';
			default:
				if (preg_match('!^image/([\w\d]+)$!', $type, $match))
				{
					return $match[1];
				}

				return null;
		}
	}

	static public function getLibrariesForFormat($format)
	{
		$im = new Image;

		$libraries = [];

		foreach ($im->libraries as $name => $enabled)
		{
			if (!$enabled)
			{
				continue;
			}

			if (in_array($format, call_user_func([$im, $name . '_formats'])))
			{
				$libraries[] = $name;
			}
		}

		return $libraries;
	}

	public function getLibraryForFormat(string $format)
	{
		foreach ($this->libraries as $name => $enabled)
		{
			if (!$enabled) {
				continue;
			}

			$supported_formats = call_user_func([$this, $name . '_formats']);

			if (in_array($format, $supported_formats)) {
				return $name;
			}
		}
	}

	/**
	 * Returns orientation of a JPEG file according to its EXIF tag
	 * @link  http://magnushoff.com/jpeg-orientation.html See to interpret the orientation value
	 * @return integer|null An integer between 1 and 8 or false if no orientation tag have been found
	 */
	public function getOrientation(): ?int
	{
		if (null !== $this->orientation) {
			return $this->orientation;
		}

		$this->open();

		if ($this->format() != 'jpeg') {
			return null;
		}

		if (null !== $this->blob) {
			return Blob::getOrientationJPEG($this->blob);
		}

		$file = $this->srcpointer ?? fopen($this->path, 'rb');
		rewind($file);

		// Get length of file
		fseek($file, 0, SEEK_END);
		$length = ftell($file);
		rewind($file);

		if (fread($file, 2) !== "\xff\xd8")
		{
			if (!$this->srcpointer) {
				fclose($file);
			}

			return null;
		}

		$sign = 'n';

		while (!feof($file))
		{
			$marker = fread($file, 2);
			$l = fread($file, 2);

			if (strlen($marker) != 2 || strlen($l) != 2) {
				break;
			}

			$info = unpack('nlength', $l);
			$section_length = $info['length'];

			if ($marker == "\xff\xe1")
			{
				if (fread($file, 6) != "Exif\x00\x00")
				{
					break;
				}

				if (fread($file, 2) == "\x49\x49")
				{
					$sign = 'v';
				}

				fseek($file, 2, SEEK_CUR);

				$info = unpack(strtoupper($sign) . 'offset', fread($file, 4));
				fseek($file, $info['offset'] - 8, SEEK_CUR);

				$info = unpack($sign . 'tags', fread($file, 2));
				$tags = $info['tags'];

				for ($i = 0; $i < $tags; $i++)
				{
					$data = fread($file, 2);

					if (strlen($data) < 2) {
						break;
					}

					$info = unpack(sprintf('%stag', $sign), $data);

					if ($info['tag'] == 0x0112)
					{
						fseek($file, 6, SEEK_CUR);
						$info = unpack(sprintf('%sorientation', $sign), fread($file, 2));
						$this->orientation = $info['orientation'];
						break(2);
					}
					else
					{
						fseek($file, 10, SEEK_CUR);
					}
				}
			}
			else if (is_numeric($marker) && $marker & 0xFF00 && $marker != "\xFF\x00")
			{
				break;
			}
			else
			{
				fseek($file, $section_length - 2, SEEK_CUR);
			}
		}

		if (!$this->srcpointer) {
			fclose($file);
		}

		return $this->orientation;
	}


	// Imagick methods ////////////////////////////////////////////////////////
	protected function imagick_open()
	{
		try {
			$this->pointer = new \Imagick($this->path);
		}
		catch (\ImagickException $e)
		{
			throw new \RuntimeException('Unable to open file: ' . $this->path, false, $e);
		}

		$this->format = strtolower($this->pointer->getImageFormat());
	}

	protected function imagick_open_pointer()
	{
		try {
			$this->pointer = new \Imagick;
			$this->pointer->readImageFile($this->srcpointer);
		}
		catch (\ImagickException $e)
		{
			throw new \RuntimeException('Unable to open file: ' . $this->srcpointer, false, $e);
		}

		$this->format = strtolower($this->pointer->getImageFormat());
	}

	protected function imagick_formats()
	{
		return array_map('strtolower', (new \Imagick)->queryFormats());
	}

	protected function imagick_blob($data)
	{
		try {
			$this->pointer = new \Imagick;
			$this->pointer->readImageBlob($data);
		}
		catch (\ImagickException $e)
		{
			throw new \RuntimeException('Unable to open data string of length ' . strlen($data), false, $e);
		}

		$this->format = strtolower($this->pointer->getImageFormat());
	}

	protected function imagick_size()
	{
		$this->width = $this->pointer->getImageWidth();
		$this->height = $this->pointer->getImageHeight();
	}

	protected function imagick_close()
	{
		$this->pointer->destroy();
	}

	protected function imagick_save($destination, $format)
	{
		$this->pointer->setImageFormat($format);

		if ($format == 'png')
		{
			$this->pointer->setOption('png:compression-level', 9);
			$this->pointer->setImageCompression(\Imagick::COMPRESSION_LZW);
			$this->pointer->setImageCompressionQuality($this->options['compression'] * 10);
		}
		elseif ($format == 'jpeg')
		{
			$this->pointer->setImageCompression(\Imagick::COMPRESSION_JPEG);
			$this->pointer->setImageCompressionQuality($this->options['jpeg_quality']);
			$this->pointer->setInterlaceScheme($this->options['progressive_jpeg'] ? \Imagick::INTERLACE_PLANE : \Imagick::INTERLACE_NO);
		}
		elseif ($format == 'webp') {
			$this->pointer->setImageCompressionQuality($this->options['webp_quality']);
		}

		$this->pointer->stripImage();

		if ($format == 'gif' && $this->pointer->getNumberImages() > 1) {
			// writeImages is buggy in old versions of Imagick
			return file_put_contents($destination, $this->pointer->getImagesBlob());
		}
		else {
			return $this->pointer->writeImage($destination);
		}
	}

	protected function imagick_output($format, $return)
	{
		$this->pointer->setImageFormat($format);

		if ($format == 'png')
		{
			$this->pointer->setOption('png:compression-level', 9);
			$this->pointer->setImageCompression(\Imagick::COMPRESSION_LZW);
			$this->pointer->setImageCompressionQuality($this->options['compression'] * 10);
			$this->pointer->stripImage();
		}
		else if ($format == 'jpeg')
		{
			$this->pointer->setImageCompression(\Imagick::COMPRESSION_JPEG);
			$this->pointer->setImageCompressionQuality($this->options['jpeg_quality']);
			$this->pointer->setInterlaceScheme($this->options['progressive_jpeg'] ? \Imagick::INTERLACE_PLANE : \Imagick::INTERLACE_NO);
		}
		elseif ($format == 'webp') {
			$this->pointer->setImageCompressionQuality($this->options['webp_quality']);
		}

		if ($format == 'gif' && $this->pointer->getNumberImages() > 1) {
			$res = $this->pointer->getImagesBlob();
		}
		else {
			$res = (string) $this->pointer;
		}

		if ($return) {
			return $res;
		}

		echo $res;
		return true;
	}

	protected function imagick_crop($new_width, $new_height)
	{
		$src_x = floor(($this->width - $new_width) / 2);
		$src_y = floor(($this->height - $new_height) / 2);

		// Detect animated GIF
		if ($this->format == 'gif')
		{
			$this->pointer = $this->pointer->coalesceImages();

			do {
				$this->pointer->cropImage($new_width, $new_height, $src_x, $src_y);
				$this->pointer->setImagePage($new_width, $new_height, 0, 0);
			} while ($this->pointer->nextImage());

			$this->pointer = $this->pointer->deconstructImages();
		}
		else
		{
			$this->pointer->cropImage($new_width, $new_height, $src_x, $src_y);
			$this->pointer->setImagePage($new_width, $new_height, 0, 0);
		}
	}

	protected function imagick_resize($new_width, $new_height, $ignore_aspect_ratio = false)
	{
		// Detect animated GIF
		if ($this->format == 'gif' && $this->pointer->getNumberImages() > 1)
		{
			$image = $this->pointer->coalesceImages();

			foreach ($image as $frame)
			{
				$frame->thumbnailImage($new_width, $new_height, !$ignore_aspect_ratio);
				$frame->setImagePage($new_width, $new_height, 0, 0);
			}

			$this->pointer = $image->deconstructImages();
		}
		else
		{
			$this->pointer->resizeImage($new_width, $new_height, \Imagick::FILTER_CATROM, 1, !$ignore_aspect_ratio, false);
		}
	}

	protected function imagick_rotate($angle)
	{
		$pixel = new \ImagickPixel('#00000000');

		if ($this->format == 'gif' && $this->pointer->getNumberImages() > 1) {
			$image = $this->pointer->coalesceImages();

			foreach ($image as $frame) {
				$frame->rotateImage($pixel, $angle);
				$frame->setImageOrientation(\Imagick::ORIENTATION_UNDEFINED);
			}

			$this->pointer = $image->deconstructImages();
		}
		else {
			$this->pointer->rotateImage($pixel, $angle);
			$this->pointer->setImageOrientation(\Imagick::ORIENTATION_UNDEFINED);
		}
	}

	protected function imagick_flip()
	{
		if ($this->format == 'gif' && $this->pointer->getNumberImages() > 1) {
			$image = $this->pointer->coalesceImages();

			foreach ($image as $frame) {
				$frame->flopImage();
			}

			$this->pointer = $image->deconstructImages();
		}
		else {
			$this->pointer->flopImage();
		}
	}

	protected function imagick_trim(float $fuzz)
	{
		$this->pointer->trimImage($fuzz);
	}

	protected function imagick_reduce_colors(int $nb_colors)
	{
		$this->pointer->quantizeImage($nb_colors, \Imagick::COLORSPACE_RGB, 0, false, false);
	}

	// GD methods /////////////////////////////////////////////////////////////
	protected function gd_open()
	{
		$this->pointer = call_user_func('imagecreatefrom' . $this->format, $this->path);

		if ($this->format === 'webp' || $this->format === 'png' || $this->format === 'gif') {
			imagealphablending($this->pointer, false);
			imagesavealpha($this->pointer, true);
		}
	}

	protected function gd_open_pointer()
	{
		$this->path = tempnam(sys_get_temp_dir(), 'php-gd');

		$fp = fopen($this->path, 'wb');
		stream_copy_to_stream($this->srcpointer, $fp);
		fclose($fp);

		$this->gd_open();
	}

	protected function gd_formats()
	{
		$supported = imagetypes();
		$formats = [];

		if (\IMG_PNG & $supported)
			$formats[] = 'png';

		if (\IMG_GIF & $supported)
			$formats[] = 'gif';

		if (\IMG_JPEG & $supported)
			$formats[] = 'jpeg';

		if (\IMG_WBMP & $supported)
			$formats[] = 'wbmp';

		if (\IMG_XPM & $supported)
			$formats[] = 'xpm';

		if (function_exists('imagecreatefromwebp'))
			$formats[] = 'webp';

		return $formats;
	}

	protected function gd_blob($data)
	{
		$this->pointer = imagecreatefromstring($data);

		if ($this->format === 'webp' || $this->format === 'png' || $this->format === 'gif') {
			imagealphablending($this->pointer, false);
			imagesavealpha($this->pointer, true);
		}
	}

	protected function gd_size()
	{
		$this->width = imagesx($this->pointer);
		$this->height = imagesy($this->pointer);
	}

	protected function gd_close()
	{
		return imagedestroy($this->pointer);
	}

	protected function gd_save($destination, $format)
	{
		if ($format == 'jpeg')
		{
			imageinterlace($this->pointer, (int)$this->options['progressive_jpeg']);
		}

		switch ($format)
		{
			case 'png':
				return imagepng($this->pointer, $destination, $this->options['compression'], PNG_NO_FILTER);
			case 'gif':
				return imagegif($this->pointer, $destination);
			case 'jpeg':
				return imagejpeg($this->pointer, $destination, $this->options['jpeg_quality']);
			case 'webp':
				return imagewebp($this->pointer, $destination, $this->options['webp_quality']);
			default:
				throw new \InvalidArgumentException('Image format ' . $format . ' is unknown.');
		}
	}

	protected function gd_output($format, $return)
	{
		if ($return)
		{
			ob_start();
		}

		$res = $this->gd_save(null, $format);

		if ($return)
		{
			return ob_get_clean();
		}

		return $res;
	}

	protected function gd_create($w, $h)
	{
		$new = imagecreatetruecolor((int)$w, (int)$h);

		if ($this->format === 'webp' || $this->format === 'png' || $this->format === 'gif') {
			imagealphablending($new, false);
			imagesavealpha($new, true);
			imagefilledrectangle($new, 0, 0, (int)$w, (int)$h, imagecolorallocatealpha($new, 255, 255, 255, 127));
		}

		return $new;
	}

	protected function gd_crop($new_width, $new_height)
	{
		$new = $this->gd_create($new_width, $new_height);

		$src_x = floor(($this->width - $new_width) / 2);
		$src_y = floor(($this->height - $new_height) / 2);

		imagecopy($new, $this->pointer, 0, 0, $src_x, $src_y, (int)$new_width, (int)$new_height);
		imagedestroy($this->pointer);
		$this->pointer = $new;
	}

	protected function gd_resize($new_width, $new_height, $ignore_aspect_ratio)
	{
		if (!$ignore_aspect_ratio)
		{
			$in_ratio = $this->width / $this->height;

			$out_ratio = $new_width / $new_height;

			if ($in_ratio >= $out_ratio)
			{
				$new_height = $new_width / $in_ratio;
			}
			else
			{
				$new_width = $new_height * $in_ratio;
			}
		}

		$new = $this->gd_create((int)$new_width, (int)$new_height);

		if ($this->options['use_gd_fast_resize_trick']) {
			$this->gd_fastimagecopyresampled($new, $this->pointer, 0, 0, 0, 0, (int)$new_width, (int)$new_height, $this->width, $this->height, 2);
		}
		else
		{
			imagecopyresampled($new, $this->pointer, 0, 0, 0, 0, (int)$new_width, (int)$new_height, $this->width, $this->height);
		}

		imagedestroy($this->pointer);
		$this->pointer = $new;
	}

	protected function gd_flip()
	{
		imageflip($this->pointer, IMG_FLIP_HORIZONTAL);
	}

	protected function gd_rotate($angle)
	{
		// GD is using counterclockwise
		$angle = -($angle);

		$this->pointer = imagerotate($this->pointer, (int)$angle, 0);
	}

	protected function gd_fastimagecopyresampled(&$dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h, $quality = 3)
	{
		// Plug-and-Play fastimagecopyresampled function replaces much slower imagecopyresampled.
		// Just include this function and change all "imagecopyresampled" references to "fastimagecopyresampled".
		// Typically from 30 to 60 times faster when reducing high resolution images down to thumbnail size using the default quality setting.
		// Author: Tim Eckel - Date: 09/07/07 - Version: 1.1 - Project: FreeRingers.net - Freely distributable - These comments must remain.
		//
		// Optional "quality" parameter (defaults is 3). Fractional values are allowed, for example 1.5. Must be greater than zero.
		// Between 0 and 1 = Fast, but mosaic results, closer to 0 increases the mosaic effect.
		// 1 = Up to 350 times faster. Poor results, looks very similar to imagecopyresized.
		// 2 = Up to 95 times faster.  Images appear a little sharp, some prefer this over a quality of 3.
		// 3 = Up to 60 times faster.  Will give high quality smooth results very close to imagecopyresampled, just faster.
		// 4 = Up to 25 times faster.  Almost identical to imagecopyresampled for most images.
		// 5 = No speedup. Just uses imagecopyresampled, no advantage over imagecopyresampled.

		if (empty($src_image) || empty($dst_image) || $quality <= 0)
		{
			return false;
		}

		if ($quality < 5 && (($dst_w * $quality) < $src_w || ($dst_h * $quality) < $src_h))
		{
			$temp_w = intval($dst_w * $quality + 1);
			$temp_h = intval($dst_h * $quality + 1);

			$temp = imagecreatetruecolor($temp_w, $temp_h);

			if ($this->format === 'webp' || $this->format === 'png' || $this->format === 'gif') {
				imagealphablending($temp, false);
				imagesavealpha($temp, true);
				imagefilledrectangle($temp, 0, 0, $temp_w, $temp_h, imagecolorallocatealpha($temp, 255, 255, 255, 127));
			}

			imagecopyresized($temp, $src_image, 0, 0, (int)$src_x, (int)$src_y, $temp_w, $temp_h, (int)$src_w, (int)$src_h);
			imagecopyresampled($dst_image, $temp, (int)$dst_x, (int)$dst_y, 0, 0, (int)$dst_w, (int)$dst_h, intval($dst_w * $quality), intval($dst_h * $quality));
			imagedestroy($temp);
		}
		else
		{
			imagecopyresampled($dst_image, $src_image, (int) $dst_x, (int) $dst_y, (int) $src_x, (int) $src_y, (int) $dst_w, (int) $dst_h, (int) $src_w, (int) $src_h);
		}

		return true;
	}
}
