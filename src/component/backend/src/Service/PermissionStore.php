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

	/** @param array<string, array<int, int>> $rules  only the mifoto.* actions are written; false when the asset does not exist */
	public function save(array $rules): bool
	{
		$asset = new Asset($this->db);

		if (!$asset->loadByName(self::ASSET))
		{
			return false;
		}

		$all = json_decode((string) $asset->rules, true);
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

		// {"action":{"2":1}}: objects, never lists, even when a group id happens to be 0, 1, 2...
		$objects = [];

		foreach ($all as $action => $map)
		{
			$objects[$action] = (object) (\is_array($map) ? $map : []);
		}

		$asset->rules = json_encode((object) $objects);

		if (!$asset->store())
		{
			return false;
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

		return true;
	}
}
