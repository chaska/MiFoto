<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mifoto\Administrator\Service;

defined('_JEXEC') || die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Reports ("this photo should not be here") made by logged-in people, and what the moderators did about them.
 * The person reporting must be allowed to SEE the photo (so reporting cannot be used to probe who has one), cannot report their
 * own, cannot report the same photo twice while the first report is open and can send a limited number per day.
 */
final class Reports
{
	public const REASONS = ['not_me', 'no_consent', 'inappropriate', 'minor', 'other'];

	public const OPEN      = 0;
	public const HIDDEN    = 1;
	public const REMOVED   = 2;
	public const DISMISSED = 3;

	public const MAX_PER_DAY = 10;

	public const MAX_COMMENT = 500;

	private DatabaseInterface $db;

	public function __construct(DatabaseInterface $db)
	{
		$this->db = $db;
	}

	/** One of REASONS, or null for anything else. */
	public static function cleanReason(mixed $reason): ?string
	{
		return \is_string($reason) && \in_array($reason, self::REASONS, true) ? $reason : null;
	}

	/** Plain text, no control characters, at most MAX_COMMENT characters. */
	public static function cleanComment(mixed $comment): string
	{
		if (!\is_string($comment))
		{
			return '';
		}

		$comment = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $comment);

		return mb_substr(trim($comment), 0, self::MAX_COMMENT);
	}

	/**
	 * @return string  'ok' | 'self' | 'duplicate' | 'too_many'
	 */
	public function create(int $reporterId, int $ownerId, string $reason, string $comment): string
	{
		if ($reporterId < 1 || $ownerId < 1 || $reporterId === $ownerId)
		{
			return 'self';
		}

		if ($this->openFor($reporterId, $ownerId))
		{
			return 'duplicate';
		}

		if ($this->sentSince($reporterId, gmdate('Y-m-d H:i:s', time() - 86400)) >= self::MAX_PER_DAY)
		{
			return 'too_many';
		}

		$row = (object) [
			'owner_id'    => $ownerId,
			'reporter_id' => $reporterId,
			'reason'      => $reason,
			'comment'     => $comment,
			'created'     => Factory::getDate()->toSql(),
			'status'      => self::OPEN,
		];
		$this->db->insertObject('#__mifoto_reports', $row);

		return 'ok';
	}

	public function countOpen(): int
	{
		$query = $this->db->getQuery(true)
			->select('COUNT(*)')
			->from($this->db->quoteName('#__mifoto_reports'))
			->where($this->db->quoteName('status') . ' = ' . self::OPEN);

		return (int) $this->db->setQuery($query)->loadResult();
	}

	/**
	 * Newest first, with the names of both people and the photo's key. $open = true: only the open ones; false: only the closed.
	 *
	 * @return array<int, \stdClass>
	 */
	public function listing(bool $open, int $limit, int $offset): array
	{
		$db    = $this->db;
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('r.id'), $db->quoteName('r.owner_id'), $db->quoteName('r.reason'), $db->quoteName('r.comment'),
				$db->quoteName('r.created'), $db->quoteName('r.status'), $db->quoteName('r.handled'),
				$db->quoteName('o.name', 'owner_name'), $db->quoteName('o.username', 'owner_username'),
				$db->quoteName('w.name', 'reporter_name'), $db->quoteName('w.username', 'reporter_username'),
				$db->quoteName('p.pkey'), $db->quoteName('p.state', 'photo_state'),
			])
			->from($db->quoteName('#__mifoto_reports', 'r'))
			->join('LEFT', $db->quoteName('#__users', 'o') . ' ON ' . $db->quoteName('o.id') . ' = ' . $db->quoteName('r.owner_id'))
			->join('LEFT', $db->quoteName('#__users', 'w') . ' ON ' . $db->quoteName('w.id') . ' = ' . $db->quoteName('r.reporter_id'))
			->join('LEFT', $db->quoteName('#__mifoto_photos', 'p') . ' ON ' . $db->quoteName('p.user_id') . ' = ' . $db->quoteName('r.owner_id'))
			->where($db->quoteName('r.status') . ($open ? ' = ' : ' <> ') . self::OPEN)
			->order($db->quoteName('r.id') . ' DESC');

		return (array) $db->setQuery($query, max(0, $offset), max(1, min(200, $limit)))->loadObjectList();
	}

	public function countListing(bool $open): int
	{
		$query = $this->db->getQuery(true)
			->select('COUNT(*)')
			->from($this->db->quoteName('#__mifoto_reports'))
			->where($this->db->quoteName('status') . ($open ? ' = ' : ' <> ') . self::OPEN);

		return (int) $this->db->setQuery($query)->loadResult();
	}

	/** The moderator acted on the photo (hid it or removed it): every open report about it is closed with that outcome. */
	public function closeForOwner(int $ownerId, int $status, int $by): void
	{
		$now   = Factory::getDate()->toSql();
		$open  = self::OPEN;
		$query = $this->db->getQuery(true)
			->update($this->db->quoteName('#__mifoto_reports'))
			->set($this->db->quoteName('status') . ' = :status')
			->set($this->db->quoteName('handled_by') . ' = :by')
			->set($this->db->quoteName('handled') . ' = :now')
			->where($this->db->quoteName('owner_id') . ' = :owner')
			->where($this->db->quoteName('status') . ' = :open')
			->bind(':status', $status, ParameterType::INTEGER)
			->bind(':by', $by, ParameterType::INTEGER)
			->bind(':now', $now)
			->bind(':owner', $ownerId, ParameterType::INTEGER)
			->bind(':open', $open, ParameterType::INTEGER);
		$this->db->setQuery($query)->execute();
	}

	/** The person a report is about, or null when the report does not exist. */
	public function ownerOf(int $id): ?int
	{
		if ($id < 1)
		{
			return null;
		}

		$query = $this->db->getQuery(true)
			->select($this->db->quoteName('owner_id'))
			->from($this->db->quoteName('#__mifoto_reports'))
			->where($this->db->quoteName('id') . ' = :id')
			->bind(':id', $id, ParameterType::INTEGER);
		$owner = $this->db->setQuery($query)->loadResult();

		return $owner === null ? null : (int) $owner;
	}

	/** One report is dismissed (nothing wrong with the photo). */
	public function dismiss(int $id, int $by): void
	{
		$now    = Factory::getDate()->toSql();
		$open   = self::OPEN;
		$status = self::DISMISSED;
		$query  = $this->db->getQuery(true)
			->update($this->db->quoteName('#__mifoto_reports'))
			->set($this->db->quoteName('status') . ' = :status')
			->set($this->db->quoteName('handled_by') . ' = :by')
			->set($this->db->quoteName('handled') . ' = :now')
			->where($this->db->quoteName('id') . ' = :id')
			->where($this->db->quoteName('status') . ' = :open')
			->bind(':status', $status, ParameterType::INTEGER)
			->bind(':by', $by, ParameterType::INTEGER)
			->bind(':now', $now)
			->bind(':id', $id, ParameterType::INTEGER)
			->bind(':open', $open, ParameterType::INTEGER);
		$this->db->setQuery($query)->execute();
	}

	/** Reports THIS person made (their own data, for the "download my data" request). @return array<int, array<string, string>> */
	public function madeBy(int $userId): array
	{
		$query = $this->db->getQuery(true)
			->select($this->db->quoteName(['reason', 'comment', 'created', 'status']))
			->from($this->db->quoteName('#__mifoto_reports'))
			->where($this->db->quoteName('reporter_id') . ' = :id')
			->order($this->db->quoteName('id'))
			->bind(':id', $userId, ParameterType::INTEGER);

		$out = [];

		foreach ((array) $this->db->setQuery($query)->loadAssocList() as $row)
		{
			$out[] = ['reason' => (string) $row['reason'], 'comment' => (string) $row['comment'], 'created' => (string) $row['created'], 'status' => (string) $row['status']];
		}

		return $out;
	}

	/** The account is gone: reports about its photo and reports it made. */
	public function deleteForUser(int $userId): void
	{
		if ($userId < 1)
		{
			return;
		}

		$query = $this->db->getQuery(true)
			->delete($this->db->quoteName('#__mifoto_reports'))
			->where('(' . $this->db->quoteName('owner_id') . ' = :a OR ' . $this->db->quoteName('reporter_id') . ' = :b)')
			->bind(':a', $userId, ParameterType::INTEGER)
			->bind(':b', $userId, ParameterType::INTEGER);
		$this->db->setQuery($query)->execute();
	}

	private function openFor(int $reporterId, int $ownerId): bool
	{
		$open  = self::OPEN;
		$query = $this->db->getQuery(true)
			->select('COUNT(*)')
			->from($this->db->quoteName('#__mifoto_reports'))
			->where($this->db->quoteName('reporter_id') . ' = :w')
			->where($this->db->quoteName('owner_id') . ' = :o')
			->where($this->db->quoteName('status') . ' = :s')
			->bind(':w', $reporterId, ParameterType::INTEGER)
			->bind(':o', $ownerId, ParameterType::INTEGER)
			->bind(':s', $open, ParameterType::INTEGER);

		return (int) $this->db->setQuery($query)->loadResult() > 0;
	}

	private function sentSince(int $reporterId, string $since): int
	{
		$query = $this->db->getQuery(true)
			->select('COUNT(*)')
			->from($this->db->quoteName('#__mifoto_reports'))
			->where($this->db->quoteName('reporter_id') . ' = :w')
			->where($this->db->quoteName('created') . ' >= :t')
			->bind(':w', $reporterId, ParameterType::INTEGER)
			->bind(':t', $since);

		return (int) $this->db->setQuery($query)->loadResult();
	}
}
