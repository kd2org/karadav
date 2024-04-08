<?php
declare(strict_types=1);

namespace KD2\Graphics\SVG;

/**
 * PHP port of https://github.com/boringdesigners/boring-avatars/
 */
class Avatar
{
	static protected function hashCode(string $name): int
	{
		return hexdec(substr(md5($name), 0, 8));
	}

	static protected function getRandomColor(int $number, array $colors, int $range): string
	{
		return $colors[($number) % $range];
	}

	static protected function getUnit(int $number, int $range, int $index = 0): int
	{
		$value = $number % $range;

		if ($index && ((self::getDigit($number, $index) % 2) === 0)) {
			return -($value);
		}

		return $value;
	}

	static protected function getDigit(int $number, int $ntn): int
	{
		$a = intval($number / pow(10, $ntn));
		return (int)floor($a % 10);
	}

	static protected function getBoolean(int $number, int $ntn): bool
	{
		return (!((self::getDigit($number, $ntn)) % 2));
	}


	static protected function getAngle(int $x, int $y): float
	{
		return atan2($y, $x) * 180 / PI;
	}


	static protected function getContrast(string $hexcolor): string
	{
		// If a leading # is provided, remove it
		if (substr($hexcolor, 0, 1) === '#') {
			$hexcolor = substr($hexcolor, 1);
		}

		if (strlen($hexcolor) === 3) {
			$hexcolor = $hexcolor[0] . $hexcolor[0] . $hexcolor[1] . $hexcolor[1] . $hexcolor[2] . $hexcolor[2];
		}

		// Convert to RGB value
		$r = hexdec(substr($hexcolor, 0, 2));
		$g = hexdec(substr($hexcolor, 2, 2));
		$b = hexdec(substr($hexcolor, 4, 2));

		// Get YIQ ratio
		$yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;

		// Check contrast
		return ($yiq >= 128) ? '#000000' : '#FFFFFF';
	}

	static public function beam(string $name, array $options = []): string
	{
		$colors = $options['colors'] ?? ['#0c9', '#9c0', '#6f0'];
		$w = intval($options['size'] ?? 36);
		$options['square'] ??= false;

		$size = 36;
		$numFromName = self::hashCode($name);
		$range = count($colors);
		$wrapperColor = self::getRandomColor($numFromName, $colors, $range);
		$preTranslateX = self::getUnit($numFromName, 10, 1);
		$wrapperTranslateX = $preTranslateX < 5 ? $preTranslateX + $size / 9 : $preTranslateX;
		$preTranslateY = self::getUnit($numFromName, 10, 2);
		$wrapperTranslateY = $preTranslateY < 5 ? $preTranslateY + $size / 9 : $preTranslateY;

		$faceColor = self::getContrast($wrapperColor);
		$backgroundColor = self::getRandomColor($numFromName + 13, $colors, $range);
		$wrapperRotate = self::getUnit($numFromName, 360);
		$wrapperScale = 1 + self::getUnit($numFromName, intval($size / 12)) / 10;
		$isMouthOpen = self::getBoolean($numFromName, 2);
		$isCircle = self::getBoolean($numFromName, 1);
		$eyeSpread = self::getUnit($numFromName, 5);
		$mouthSpread = self::getUnit($numFromName, 3);
		$faceRotate = self::getUnit($numFromName, 10, 3);
		$faceTranslateX = $wrapperTranslateX > $size / 6 ? $wrapperTranslateX / 2 : self::getUnit($numFromName, 6, 1);
		$faceTranslateY = $wrapperTranslateY > $size / 6 ? $wrapperTranslateY / 2 : self::getUnit($numFromName, 5, 2);

		$maskID = 'mask-' . md5(random_bytes(8));

		$rx1 = $options['square'] ? 0 : $size * 2;
		$rx2 = $isCircle ? $size : $size / 6;
		$rx3 = 1 + self::getUnit($numFromName, 6, 2);
		$half_size = $size / 2;
		$spread = 22 + $mouthSpread;

		if (!$isMouthOpen) {
			$mouth = "<path d=\"M13 {$spread}c4 2 8 2 12 0\" stroke=\"{$faceColor}\" fill=\"none\" strokeLinecap=\"round\" />";
		}
		else {
			$mouth = "<path d=\"M12,{$spread} a2,1.5 0 0,0 14,0\" fill=\"{$faceColor}\" />";
		}

		$x1 = 14 - $eyeSpread;
		$x2 = 20 + $eyeSpread;

		return <<<EOF
		<svg
			viewBox="0 0 {$size} {$size}"
			fill="none"
			role="img"
			xmlns="http://www.w3.org/2000/svg"
			width="{$w}"
			height="{$w}"
		>
			<mask id="{$maskID}" maskUnits="userSpaceOnUse" x="0" y="0" width="{$size}" height="{$size}">
				<rect width="{$size}" height="{$size}" rx="{$rx1}" fill="#FFFFFF" />
			</mask>
			<g mask="url('#{$maskID}')">
				<rect width="{$size}" height="{$size}" fill="{$backgroundColor}" />
				<rect
					x="0"
					y="0"
					width="{$size}"
					height="{$size}"
					transform="translate({$wrapperTranslateX} {$wrapperTranslateY}) rotate({$wrapperRotate} {$half_size} {$half_size}) scale({$wrapperScale})"
					fill="{$wrapperColor}"
					rx="{$rx2}"
				/>
				<g transform="translate({$faceTranslateY} {$faceTranslateY}) rotate({$faceRotate} $half_size $half_size)">
					{$mouth}
					<rect
						x="{$x1}"
						y="14"
						width="4"
						height="6"
						rx="{$rx3}"
						stroke="none"
						fill="{$faceColor}"
					/>
					<rect
						x="{$x2}"
						y="14"
						width="4"
						height="6"
						rx="{$rx3}"
						stroke="none"
						fill="{$faceColor}"
					/>
				</g>
			</g>
		</svg>
EOF;
	}
}
