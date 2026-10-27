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
 * Who can do what, per user group, as plain logic with no Joomla calls so it can be tested alone.
 *
 * It mirrors Joomla's rule for the three MiFoto actions: a group gets a permission when it (or a group above it) is explicitly
 * allowed, and an explicit "denied" anywhere in the chain wins over every "allowed". The screen only ever ADDS or REMOVES an
 * explicit "allowed"; a deliberate "denied" is shown but left to Joomla's own permissions screen. Joomla itself still decides
 * every real request: this class only draws the table and prepares the change.
 */
final class PermissionMatrix
{
	/** Joomla action name => short key used by the screen and the script. */
	public const ACTIONS = ['mifoto.upload' => 'up', 'mifoto.view' => 'ver', 'mifoto.moderate' => 'mod'];

	public const OWN      = 'own';       // allowed to this group on purpose
	public const INHERIT  = 'inh';       // allowed because a group above is
	public const NONE     = '';          // nothing set: the group cannot
	public const FIXED    = 'fixed';     // super users: always, not editable
	public const DENY     = 'deny';      // denied to this group on purpose
	public const DENY_INH = 'deny-inh';  // denied by a group above

	/**
	 * @param   array<int, array{id: int, parent: int, title: string}>  $groups  in tree order (parents before children)
	 * @param   array<string, array<int, int>>                          $rules   action => [group id => 1 allowed | 0 denied]
	 * @param   int[]                                                   $fixed   groups that can do everything (super users)
	 *
	 * @return  array<int, array{id: int, title: string, level: int, cells: array<string, string>}>
	 */
	public static function rows(array $groups, array $rules, array $fixed): array
	{
		$parent = self::parents($groups);
		$rows   = [];

		foreach ($groups as $g)
		{
			$cells = [];

			foreach (self::ACTIONS as $action => $key)
			{
				$cells[$key] = self::state($parent, (int) $g['id'], $action, $rules, $fixed);
			}

			$rows[] = ['id' => (int) $g['id'], 'title' => (string) $g['title'], 'level' => self::level($parent, (int) $g['id']), 'cells' => $cells];
		}

		return $rows;
	}

	/**
	 * @param   array<int, int>                 $parent  group id => parent id
	 * @param   array<string, array<int, int>>  $rules
	 * @param   int[]                           $fixed
	 */
	public static function state(array $parent, int $group, string $action, array $rules, array $fixed): string
	{
		if (\in_array($group, $fixed, true))
		{
			return self::FIXED;
		}

		$map   = $rules[$action] ?? [];
		$chain = self::chain($parent, $group);

		// A deliberate "denied" in the group or anywhere above beats every "allowed".
		foreach ($chain as $i => $id)
		{
			if (isset($map[$id]) && (int) $map[$id] === 0)
			{
				return $i === 0 ? self::DENY : self::DENY_INH;
			}
		}

		if (isset($map[$group]) && (int) $map[$group] === 1)
		{
			return self::OWN;
		}

		foreach (\array_slice($chain, 1) as $id)
		{
			if (isset($map[$id]) && (int) $map[$id] === 1)
			{
				return self::INHERIT;
			}
		}

		return self::NONE;
	}

	/**
	 * One click on one cell: allowed on purpose -> removed; anything else that can be changed -> allowed on purpose.
	 *
	 * @param   array<int, array{id: int, parent: int, title: string}>  $groups
	 * @param   array<string, array<int, int>>                          $rules
	 * @param   int[]                                                   $fixed
	 *
	 * @return  array{ok: bool, reason: string, rules: array<string, array<int, int>>, state: string}
	 */
	public static function toggle(array $groups, array $rules, array $fixed, int $group, string $action): array
	{
		$ids = array_map(static fn (array $g): int => (int) $g['id'], $groups);

		if (!isset(self::ACTIONS[$action]))
		{
			return ['ok' => false, 'reason' => 'unknown_action', 'rules' => $rules, 'state' => ''];
		}

		if (!\in_array($group, $ids, true))
		{
			return ['ok' => false, 'reason' => 'unknown_group', 'rules' => $rules, 'state' => ''];
		}

		$parent = self::parents($groups);
		$now    = self::state($parent, $group, $action, $rules, $fixed);

		if ($now === self::FIXED)
		{
			return ['ok' => false, 'reason' => 'fixed', 'rules' => $rules, 'state' => $now];
		}

		if ($now === self::DENY || $now === self::DENY_INH)
		{
			return ['ok' => false, 'reason' => 'denied', 'rules' => $rules, 'state' => $now];
		}

		if ($now === self::OWN)
		{
			unset($rules[$action][$group]);

			if (empty($rules[$action]))
			{
				unset($rules[$action]);
			}
		}
		else
		{
			$rules[$action][$group] = 1;
		}

		return ['ok' => true, 'reason' => '', 'rules' => $rules, 'state' => self::state($parent, $group, $action, $rules, $fixed)];
	}

	/**
	 * Joomla stores rules as {"mifoto.upload":{"2":1}}; this keeps only MiFoto's actions with integer keys and 0/1 values.
	 *
	 * @param   mixed  $decoded  the json_decode(..., true) of the asset's rules
	 *
	 * @return  array<string, array<int, int>>
	 */
	public static function normalize($decoded): array
	{
		$out = [];

		if (!\is_array($decoded))
		{
			return $out;
		}

		foreach (array_keys(self::ACTIONS) as $action)
		{
			foreach (\is_array($decoded[$action] ?? null) ? $decoded[$action] : [] as $group => $allow)
			{
				if ((int) $group > 0 && ($allow === 0 || $allow === 1 || $allow === '0' || $allow === '1'))
				{
					$out[$action][(int) $group] = (int) $allow;
				}
			}
		}

		return $out;
	}

	/** @param array<int, array{id: int, parent: int, title: string}> $groups  @return array<int, int> */
	private static function parents(array $groups): array
	{
		$parent = [];

		foreach ($groups as $g)
		{
			$parent[(int) $g['id']] = (int) $g['parent'];
		}

		return $parent;
	}

	/** The group, then its parent, then its grandparent... (never loops, never longer than the tree). @return int[] */
	private static function chain(array $parent, int $group): array
	{
		$chain = [];

		for ($id = $group, $guard = 0; $id > 0 && $guard < 64 && !\in_array($id, $chain, true); $guard++)
		{
			$chain[] = $id;
			$id      = $parent[$id] ?? 0;
		}

		return $chain;
	}

	private static function level(array $parent, int $group): int
	{
		return max(0, \count(self::chain($parent, $group)) - 1);
	}
}
