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
 * What to show for a person without a photo: their initials on a colour that never changes for the same seed.
 * No external service, no request, nothing leaves the site.
 */
final class Placeholder
{
	/** Dark enough for white text (contrast 4.5:1 or better on each). */
	private const COLORS = ['#534AB7', '#0F6E56', '#993C1D', '#993556', '#185FA5', '#854F0B', '#3B6D11', '#5F5E5A'];

	/** First letter of the first two words, upper case. "?" when there is nothing usable. */
	public static function initials(string $name): string
	{
		$name = trim(preg_replace('/[^\p{L}\p{N}\s]+/u', '', $name) ?? '');

		if ($name === '')
		{
			return '?';
		}

		$words = preg_split('/\s+/u', $name) ?: [];
		$out   = '';

		foreach (\array_slice($words, 0, 2) as $word)
		{
			$out .= mb_strtoupper(mb_substr($word, 0, 1));
		}

		return $out !== '' ? $out : '?';
	}

	/** One of eight fixed colours, chosen from the seed (any stable string: the user name, for instance). */
	public static function color(string $seed): string
	{
		return self::COLORS[crc32($seed) % \count(self::COLORS)];
	}
}
