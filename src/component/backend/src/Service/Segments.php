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
 * The strip of the album cover: one little block per slice of users, so the eye reads "how much is stuck" before any number.
 * With more or fewer users than blocks, each block stands for the same share of users. Pure arithmetic, tested alone.
 */
final class Segments
{
	public const ON      = 'on';   // has a photo, visible
	public const HIDDEN  = 'hid';  // has a photo, hidden by a moderator
	public const MISSING = '';     // no photo yet

	/**
	 * @return  string[]  exactly $blocks entries: ON..., HIDDEN..., MISSING...
	 */
	public static function build(int $users, int $visible, int $hidden, int $blocks = 50): array
	{
		$blocks  = max(1, min(200, $blocks));
		$users   = max(0, $users);
		$visible = max(0, min($visible, $users));
		$hidden  = max(0, min($hidden, $users - $visible));

		if ($users === 0)
		{
			return array_fill(0, $blocks, self::MISSING);
		}

		$on  = (int) round($visible / $users * $blocks);
		$hid = (int) round($hidden / $users * $blocks);

		// A real photo is never rounded away to nothing, and the strip never claims more than it has.
		if ($visible > 0 && $on === 0)
		{
			$on = 1;
		}

		if ($hidden > 0 && $hid === 0)
		{
			$hid = 1;
		}

		$on  = min($on, $blocks);
		$hid = min($hid, $blocks - $on);

		// Everybody has a photo: the strip is full, whatever the rounding said.
		if ($visible + $hidden === $users)
		{
			$hid = min($hid, $blocks - $on);
			$on  = $blocks - $hid;
		}

		// Somebody is still without a photo: rounding must not make the strip look full (999 of 1000 is not "everybody").
		if ($visible + $hidden < $users && $on + $hid >= $blocks)
		{
			if ($on >= $hid)
			{
				$on--;
			}
			else
			{
				$hid--;
			}
		}

		return array_merge(array_fill(0, $on, self::ON), array_fill(0, $hid, self::HIDDEN), array_fill(0, $blocks - $on - $hid, self::MISSING));
	}
}
