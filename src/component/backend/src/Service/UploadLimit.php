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
 * The hourly upload limit as plain arithmetic (no Joomla, no database) so it can be tested alone.
 * One window of an hour starts at a person's first attempt; attempts inside it are counted; a new window starts once the hour is over.
 */
final class UploadLimit
{
	public const WINDOW = 3600;

	/**
	 * @param   int  $count        Attempts already counted in the stored window.
	 * @param   int  $windowStart  Unix time the stored window started (0 when the person has none).
	 * @param   int  $now          Unix time of this attempt.
	 * @param   int  $max          Attempts allowed per window; 0 or less means no limit.
	 *
	 * @return  array{allowed: bool, count: int, start: int, retryAfter: int}  What to store, and how many seconds to wait when refused.
	 */
	public static function check(int $count, int $windowStart, int $now, int $max): array
	{
		if ($max < 1)
		{
			return ['allowed' => true, 'count' => $count, 'start' => $windowStart, 'retryAfter' => 0];
		}

		// No window yet, an expired one, or one that starts in the future (clock change): begin a new window with this attempt.
		if ($windowStart <= 0 || $windowStart > $now || $now - $windowStart >= self::WINDOW)
		{
			return ['allowed' => true, 'count' => 1, 'start' => $now, 'retryAfter' => 0];
		}

		if ($count >= $max)
		{
			return ['allowed' => false, 'count' => $count, 'start' => $windowStart, 'retryAfter' => $windowStart + self::WINDOW - $now];
		}

		return ['allowed' => true, 'count' => max(0, $count) + 1, 'start' => $windowStart, 'retryAfter' => 0];
	}
}
