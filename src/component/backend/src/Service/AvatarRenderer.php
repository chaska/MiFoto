<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mifoto\Administrator\Service;

defined('_JEXEC') || die;

/**
 * The HTML of one avatar: the photo, or the initials on a colour when there is none. Pure (no Joomla calls) so every
 * value that reaches an attribute can be tested: nothing is printed unescaped and nothing from outside is trusted
 * (class names, colour, size and URL are each checked here, even though our own code already makes them safe).
 */
final class AvatarRenderer
{
	public static function html(?PhotoData $photo, string $classes, int $size, string $initials, string $color, string $alt = ''): string
	{
		$size    = max(16, min(300, $size));
		$classes = self::cleanClasses($classes);
		$alt     = trim($alt);

		if ($photo !== null && self::safeUrl($photo->url))
		{
			return '<img class="' . $classes . '" src="' . self::e($photo->url) . '" width="' . $size . '" height="' . $size . '" alt="' . self::e($alt)
				. '" loading="lazy" decoding="async">';
		}

		if (!preg_match('/^#[0-9A-Fa-f]{6}$/D', $color))
		{
			$color = '#5F5E5A';
		}

		$initials = mb_substr($initials, 0, 2) ?: '?';
		$label    = $alt !== '' ? ' role="img" aria-label="' . self::e($alt) . '"' : ' aria-hidden="true"';

		return '<span class="' . $classes . '" style="width:' . $size . 'px;height:' . $size . 'px;font-size:' . (int) round($size * 0.38) . 'px;background:' . $color . '"' . $label . '>'
			. self::e($initials) . '</span>';
	}

	/** Only plain class tokens survive: letters, digits, "-" and "_" separated by single spaces. */
	public static function cleanClasses(string $classes): string
	{
		$out = [];

		foreach (preg_split('/\s+/', trim($classes)) ?: [] as $token)
		{
			if (preg_match('/^[A-Za-z0-9_-]{1,40}$/D', $token))
			{
				$out[] = $token;
			}
		}

		return implode(' ', $out);
	}

	/** Same-site paths ("/...") and plain http(s) URLs, with nothing that could close an attribute. */
	public static function safeUrl(string $url): bool
	{
		if ($url === '' || preg_match('/["\'<>\s\\\\\x00-\x1F]/', $url))
		{
			return false;
		}

		return str_starts_with($url, '/') && !str_starts_with($url, '//') || (bool) preg_match('~^https?://~i', $url);
	}

	private static function e(string $s): string
	{
		return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}
