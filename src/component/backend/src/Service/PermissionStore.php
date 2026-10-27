<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mifoto\Administrator\Service;

defined('_JEXEC') || die;

use Joomla\CMS\Access\Access;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Asset;
use Joomla\Database\DatabaseInterface;

/**
 * Reads and writes MiFoto's three permissions in Joomla's own permission table (the "rules" of the asset "com_mifoto"), so what
 * is set here is exactly what Joomla's own permissions screen shows. Only the three mifoto.* actions are touched; every other
 * rule of the asset (core.admin, core.manage...) is written back unchanged.
 */
final class PermissionStore
{
	private const ASSET = 'com_mifoto';

	private DatabaseInterface $db;

	public function __construct(DatabaseInterface $db)
	{
		$this->db = $db;
	}

	/** @return array<int, array{id: int, parent: int, title: string}>  in tree order */
	public function groups(): array
	{
		$query = $this->db->getQuery(true)
			->select($this->db->quoteName(['id', 'parent_id', 'title']))
			->from($this->db->quoteName('#__usergroups'))
			->order($this->db->quoteName('lft'));

		$out = [];

		foreach ((array) $this->db->setQuery($query)->loadAssocList() as $row)
		{
			$out[] = ['id' => (int) $row['id'], 'parent' => (int) $row['parent_id'], 'title' => (string) $row['title']];
		}

		return $out;
	}

	/** @return array<string, array<int, int>> */
	public function rules(): array
	{
		$asset = new Asset($this->db);

		if (!$asset->loadByName(self::ASSET))
		{
			return [];
		}

		return PermissionMatrix::normalize(json_decode((string) $asset->rules, true));
	}

	/**
	 * Groups that can do everything (super users): the screen shows them as "always" and never changes them.
	 *
	 * @param   array<int, array{id: int, parent: int, title: string}>  $groups
	 *
	 * @return  int[]
	 */
	public function fixed(array $groups): array
	{
		$out = [];

		foreach ($groups as $g)
		{
			if (Access::checkGroup($g['id'], 'core.admin'))
			{
				$out[] = $g['id'];
			}
		}

		return $out;
	}

	/**
	 * Reads the current rules, lets $change work out the new ones and writes them, all inside one transaction with the asset row
	 * locked, so two clicks at the same moment cannot overwrite each other. Creates the asset when Joomla has not yet.
	 *
	 * @param   callable(array<string, array<int, int>>): array{ok: bool, rules?: array<string, array<int, int>>}  $change  gets the rules read UNDER the lock
	 *
	 * @return  array{saved: bool, result: array<string, mixed>}  saved is false when nothing was written (refused change or a database problem)
	 */
	public function mutate(callable $change): array
	{
		$none = ['saved' => false, 'result' => ['ok' => false, 'reason' => 'save']];

		if (!$this->ensureAsset())
		{
			return $none;
		}

		try
		{
			$this->db->transactionStart();
			$raw = (string) $this->db->setQuery(
				'SELECT ' . $this->db->quoteName('rules') . ' FROM ' . $this->db->quoteName('#__assets')
				. ' WHERE ' . $this->db->quoteName('name') . ' = ' . $this->db->quote(self::ASSET) . ' FOR UPDATE'
			)->loadResult();
			$result = $change(PermissionMatrix::normalize(json_decode($raw, true)));

			if (empty($result['ok']))
			{
				$this->db->transactionRollback();

				return ['saved' => false, 'result' => $result];
			}

			$asset = new Asset($this->db);

			if (!$asset->loadByName(self::ASSET))
			{
				$this->db->transactionRollback();

				return $none;
			}

			$asset->rules = self::mergeRules($raw, (array) $result['rules']);

			if (!$asset->check() || !$asset->store())
			{
				$this->db->transactionRollback();

				return $none;
			}

			$this->db->transactionCommit();
		}
		catch (\Throwable $e)
		{
			try
			{
				$this->db->transactionRollback();
			}
			catch (\Throwable $ignored)
			{
			}

			return $none;
		}

		Access::clearStatics();

		try
		{
			Factory::getContainer()->get(CacheControllerFactoryInterface::class)->createCacheController('callback', ['defaultgroup' => '_system'])->clean();
		}
		catch (\Throwable $e)
		{
			// The rules are saved; a stale cache only delays them until it expires.
		}

		return ['saved' => true, 'result' => $result];
	}

	/**
	 * The asset row is normally created by Joomla the first time someone saves the component's options. On a clean install nobody
	 * has, so make it here, as com_config would (under the root asset), or the permissions table could never save.
	 */
	private function ensureAsset(): bool
	{
		try
		{
			$asset = new Asset($this->db);

			if ($asset->loadByName(self::ASSET))
			{
				return true;
			}

			$root = new Asset($this->db);

			if (!$root->loadByName('root.1'))
			{
				return false;
			}

			$asset        = new Asset($this->db);
			$asset->name  = self::ASSET;
			$asset->title = self::ASSET;
			$asset->rules = '{}';
			$asset->setLocation((int) $root->id, 'last-child');

			return $asset->check() && $asset->store();
		}
		catch (\Throwable $e)
		{
			return false;
		}
	}

	/**
	 * Puts the three mifoto.* actions of $rules into the asset's stored rules ($raw, JSON) and returns the JSON to store. Every other
	 * action (core.admin, core.manage...) is kept exactly as it was, and entries are objects, never lists, even for group ids 0, 1, 2.
	 * Values that are not strictly 0 or 1 in the OTHER actions are not touched.
	 *
	 * @param array<string, array<int, int>> $rules
	 */
	public static function mergeRules(string $raw, array $rules): string
	{
		$all = json_decode($raw, true);
		$all = \is_array($all) ? $all : [];

		foreach (array_keys(PermissionMatrix::ACTIONS) as $action)
		{
			unset($all[$action]);

			if (!empty($rules[$action]))
			{
				$map = [];

				foreach ($rules[$action] as $group => $allow)
				{
					$map[(string) (int) $group] = (int) $allow;
				}

				$all[$action] = $map;
			}
		}

		$objects = [];

		foreach ($all as $action => $map)
		{
			$objects[$action] = (object) (\is_array($map) ? $map : []);
		}

		return json_encode((object) $objects);
	}
}
