<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Plugin\Content\Mifoto\Service;

defined('_JEXEC') || die;

/**
 * Finds the {user} tags in a text and swaps each for whatever the renderer returns. Pure, so it is tested without Joomla.
 *
 *   {user}               the photo of the person reading the page
 *   {user 42}            the photo of user number 42
 *   {user size=96}       the reader's photo, 96 px
 *   {user 42 size=96}    both
 */
final class Shortcode
{
	public const PATTERN = '/\{user(?:\s+(\d{1,10}))?(?:\s+size=(\d{2,3}))?\}/';

	public static function has(string $text): bool
	{
		return str_contains($text, '{user');
	}

	/**
	 * @param   callable(int, int): string  $render  Receives (user id or 0 for "the reader", size in px) and returns safe HTML.
	 */
	public static function replace(string $text, int $defaultSize, callable $render): string
	{
		return preg_replace_callback(
			self::PATTERN,
			static function (array $m) use ($defaultSize, $render): string {
				$size = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : $defaultSize;

				return $render((int) ($m[1] ?? 0), max(16, min(300, $size)));
			},
			$text
		) ?? $text;
	}
}
