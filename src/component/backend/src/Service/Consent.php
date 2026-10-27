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
 * What the person accepted when they uploaded a photo, and the proof that they did. Two boxes, both required:
 *  - "I am the person in the photo, or its author, and I have the right to publish it";
 *  - "I consent to my photo being processed and shown as the notice says" (data protection).
 * One row per upload records WHO accepted, WHEN (UTC), WHICH version of the text and for WHICH photo. It stores nothing else
 * about the person (no IP address, no browser): the proof is "this account ticked both boxes at this moment".
 * When the person removes the photo the row is marked withdrawn (the consent is taken back); it is deleted with the account.
 */
final class Consent
{
	/** Bump it when the wording of the two boxes changes in a way that needs a new acceptance. */
	public const VERSION = 1;

	private DatabaseInterface $db;

	public function __construct(DatabaseInterface $db)
	{
		$this->db = $db;
	}

	/** Both boxes ticked: the form sends exactly "1" for each. Anything else (missing, "0", "on", arrays) is a no. */
	public static function accepted(mixed $owner, mixed $data): bool
	{
		return $owner === '1' && $data === '1';
	}

	public function record(int $userId, string $token): void
	{
		$row = (object) [
			'user_id'  => $userId,
			'version'  => self::VERSION,
			'token'    => $token,
			'accepted' => Factory::getDate()->toSql(),
		];
		$this->db->insertObject('#__mifoto_consents', $row);
	}

	/** The upload this record belonged to failed: the record goes too. */
	public function forget(int $userId, string $token): void
	{
		$query = $this->db->getQuery(true)
			->delete($this->db->quoteName('#__mifoto_consents'))
			->where($this->db->quoteName('user_id') . ' = :id')
			->where($this->db->quoteName('token') . ' = :token')
			->bind(':id', $userId, ParameterType::INTEGER)
			->bind(':token', $token);
		$this->db->setQuery($query)->execute();
	}

	/** The person took the photo (and with it the consent) back: every open acceptance of theirs is closed. */
	public function withdraw(int $userId): void
	{
		if ($userId < 1)
		{
			return;
		}

		$now   = Factory::getDate()->toSql();
		$query = $this->db->getQuery(true)
			->update($this->db->quoteName('#__mifoto_consents'))
			->set($this->db->quoteName('withdrawn') . ' = :now')
			->where($this->db->quoteName('user_id') . ' = :id')
			->where($this->db->quoteName('withdrawn') . ' IS NULL')
			->bind(':now', $now)
			->bind(':id', $userId, ParameterType::INTEGER);
		$this->db->setQuery($query)->execute();
	}

	/** Newest acceptance of the person, or null. @return \stdClass|null  version, token, accepted, withdrawn */
	public function latest(int $userId): ?\stdClass
	{
		$query = $this->db->getQuery(true)
			->select($this->db->quoteName(['version', 'token', 'accepted', 'withdrawn']))
			->from($this->db->quoteName('#__mifoto_consents'))
			->where($this->db->quoteName('user_id') . ' = :id')
			->order($this->db->quoteName('id') . ' DESC')
			->bind(':id', $userId, ParameterType::INTEGER);
		$row = $this->db->setQuery($query, 0, 1)->loadObject();

		return $row instanceof \stdClass ? $row : null;
	}

	/** Everything stored about this person's acceptances (for the "download my data" request). @return array<int, array<string, string>> */
	public function forUser(int $userId): array
	{
		$query = $this->db->getQuery(true)
			->select($this->db->quoteName(['version', 'accepted', 'withdrawn']))
			->from($this->db->quoteName('#__mifoto_consents'))
			->where($this->db->quoteName('user_id') . ' = :id')
			->order($this->db->quoteName('id'))
			->bind(':id', $userId, ParameterType::INTEGER);

		$out = [];

		foreach ((array) $this->db->setQuery($query)->loadAssocList() as $row)
		{
			$out[] = ['version' => (string) $row['version'], 'accepted' => (string) $row['accepted'], 'withdrawn' => (string) ($row['withdrawn'] ?? '')];
		}

		return $out;
	}

	public function deleteForUser(int $userId): void
	{
		if ($userId < 1)
		{
			return;
		}

		$query = $this->db->getQuery(true)
			->delete($this->db->quoteName('#__mifoto_consents'))
			->where($this->db->quoteName('user_id') . ' = :id')
			->bind(':id', $userId, ParameterType::INTEGER);
		$this->db->setQuery($query)->execute();
	}
}
