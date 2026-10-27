<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mifoto\Administrator\Service;

defined('_JEXEC') || die;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\QueryInterface;

/**
 * What the administrator screens show: how many users have a photo, the album page by page (a row for EVERY active user, with or
 * without a photo), and the latest uploads. Read-only. The "N.º" of a sticker is the user id: it is stable, and only an
 * administrator sees it.
 */
final class AdminData
{
	public const FILTERS = ['todos', 'con', 'visibles', 'ocultas', 'sin'];

	private DatabaseInterface $db;

	public function __construct(DatabaseInterface $db)
	{
		$this->db = $db;
	}

	/** @return array{users: int, visible: int, hidden: int, with: int, without: int}  active (not blocked) users only */
	public function totals(): array
	{
		$db    = $this->db;
		$users = (int) $db->setQuery(
			$db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__users'))->where($db->quoteName('block') . ' = 0')
		)->loadResult();

		$query = $db->getQuery(true)
			->select([$db->quoteName('p.state'), 'COUNT(*) AS ' . $db->quoteName('c')])
			->from($db->quoteName('#__mifoto_photos', 'p'))
			->join('INNER', $db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('p.user_id'))
			->where($db->quoteName('u.block') . ' = 0')
			->group($db->quoteName('p.state'));

		$visible = 0;
		$hidden  = 0;

		foreach ((array) $db->setQuery($query)->loadAssocList() as $row)
		{
			if ((int) $row['state'] === AccessPolicy::VISIBLE)
			{
				$visible = (int) $row['c'];
			}
			else
			{
				$hidden += (int) $row['c'];
			}
		}

		$with = $visible + $hidden;

		return ['users' => $users, 'visible' => $visible, 'hidden' => $hidden, 'with' => $with, 'without' => max(0, $users - $with)];
	}

	/**
	 * @param   string  $filter  todos|con|visibles|ocultas|sin
	 * @param   string  $order   id (the album) or recent (latest upload first)
	 *
	 * @return  array<int, \stdClass>  id, name, username, pkey (null: no photo), state, modified, bytes, width, height
	 */
	public function album(string $filter, int $offset, int $limit, string $search = '', string $order = 'id'): array
	{
		$db    = $this->db;
		$query = $this->base($filter, $search)
			->select($db->quoteName(['u.id', 'u.name', 'u.username', 'p.pkey', 'p.state', 'p.modified', 'p.bytes', 'p.width', 'p.height']));

		if ($order === 'recent')
		{
			$query->order($db->quoteName('p.modified') . ' DESC')->order($db->quoteName('u.id') . ' DESC');
		}
		else
		{
			$query->order($db->quoteName('u.id'));
		}

		return (array) $db->setQuery($query, max(0, $offset), max(1, min(200, $limit)))->loadObjectList();
	}

	public function count(string $filter, string $search = ''): int
	{
		return (int) $this->db->setQuery($this->base($filter, $search)->select('COUNT(*)'))->loadResult();
	}

	/** @return array<int, \stdClass>  the latest visible photos: id, name, pkey, state, modified */
	public function recent(int $n): array
	{
		return $this->album('visibles', 0, $n, '', 'recent');
	}

	private function base(string $filter, string $search): QueryInterface
	{
		$db    = $this->db;
		$query = $db->getQuery(true)
			->from($db->quoteName('#__users', 'u'))
			->join('LEFT', $db->quoteName('#__mifoto_photos', 'p') . ' ON ' . $db->quoteName('p.user_id') . ' = ' . $db->quoteName('u.id'))
			->where($db->quoteName('u.block') . ' = 0');

		switch ($filter)
		{
			case 'con':
				$query->where($db->quoteName('p.user_id') . ' IS NOT NULL');
				break;
			case 'visibles':
				$query->where($db->quoteName('p.state') . ' = ' . AccessPolicy::VISIBLE);
				break;
			case 'ocultas':
				$query->where($db->quoteName('p.state') . ' = ' . AccessPolicy::HIDDEN);
				break;
			case 'sin':
				$query->where($db->quoteName('p.user_id') . ' IS NULL');
				break;
		}

		$search = trim($search);

		if ($search !== '')
		{
			// "%" and "_" typed by the moderator are literal characters, not wildcards.
			$like = '%' . addcslashes(mb_substr($search, 0, 100), '%_\\') . '%';
			$query->where('(' . $db->quoteName('u.name') . ' LIKE :s1 OR ' . $db->quoteName('u.username') . ' LIKE :s2 OR ' . $db->quoteName('u.email') . ' LIKE :s3)')
				->bind(':s1', $like)
				->bind(':s2', $like)
				->bind(':s3', $like);
		}

		return $query;
	}
}
