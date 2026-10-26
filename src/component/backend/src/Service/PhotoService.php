<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mifoto\Administrator\Service;

defined('_JEXEC') || die;

use Chaska\Component\Mifoto\Administrator\Security\PhotoStorage;
use Chaska\Component\Mifoto\Administrator\Security\SanitizeException;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * The public face of MiFoto for any other extension:
 *
 *   $service = Factory::getApplication()->bootComponent('com_mifoto')->getPhotoService();
 *   $photo   = $service->getPhoto($userId, 64);   // PhotoData or null
 *
 * null means "no photo, or the person asking may not see it": the caller cannot tell which, and does not need to.
 * Every permission rule lives in AccessPolicy; this class only fetches the facts and acts.
 */
final class PhotoService
{
	private const ACL_ASSET = 'com_mifoto';

	/** Seconds that must pass between two uploads of the same person. */
	private const UPLOAD_GAP = 5;

	private DatabaseInterface $db;
	private Settings $settings;

	public function __construct(DatabaseInterface $db, Settings $settings)
	{
		$this->db       = $db;
		$this->settings = $settings;
	}

	public function settings(): Settings
	{
		return $this->settings;
	}

	/**
	 * @param   int        $ownerId  Joomla user id of the person in the photo.
	 * @param   int        $size     Wanted long side in px; the nearest stored size at or above it is served (32, 64, 128, 300).
	 * @param   User|null  $viewer   Who is asking; the logged-in user when omitted.
	 */
	public function getPhoto(int $ownerId, int $size = 128, ?User $viewer = null): ?PhotoData
	{
		$row = $this->rowFor($ownerId);

		if ($row === null)
		{
			return null;
		}

		$viewer = $viewer ?? Factory::getApplication()->getIdentity();

		if (!$this->viewerMaySee($viewer, $ownerId, (int) $row->state))
		{
			return null;
		}

		return $this->toData($row, $size);
	}

	/**
	 * Many photos with ONE query, for lists (a ranking of 500 people must not make 500 queries). Only people who have a photo
	 * the viewer may see appear in the result; the rest are simply missing from it.
	 *
	 * @param   int[]  $ownerIds
	 *
	 * @return  array<int, PhotoData>  keyed by user id
	 */
	public function getPhotos(array $ownerIds, int $size = 64, ?User $viewer = null): array
	{
		$ids = array_values(array_unique(array_filter(array_map('intval', $ownerIds), static fn (int $i): bool => $i > 0)));

		if ($ids === [])
		{
			return [];
		}

		$viewer = $viewer ?? Factory::getApplication()->getIdentity();
		$mayView = (bool) $viewer->authorise('mifoto.view', self::ACL_ASSET);
		$isMod   = (bool) $viewer->authorise('mifoto.moderate', self::ACL_ASSET);
		$out     = [];

		foreach (array_chunk($ids, 500) as $chunk)
		{
			$query = $this->db->getQuery(true)
				->select('*')
				->from($this->db->quoteName('#__mifoto_photos'))
				->whereIn($this->db->quoteName('user_id'), $chunk);

			foreach ((array) $this->db->setQuery($query)->loadObjectList() as $row)
			{
				$owner = (int) $row->user_id;

				if (AccessPolicy::mayView($viewer->id === $owner && $owner > 0, $mayView, $isMod, (int) $row->state))
				{
					$out[$owner] = $this->toData($row, $size);
				}
			}
		}

		return $out;
	}

	/** The stored row of one person (or null). For admin screens; it holds no file names. */
	public function photoRow(int $ownerId): ?\stdClass
	{
		return $this->rowFor($ownerId);
	}

	private function toData(\stdClass $row, int $size): PhotoData
	{
		$side = PhotoStorage::nearestSize($size);
		$k    = min(1.0, $side / max(1, max((int) $row->width, (int) $row->height)));

		return new PhotoData(
			self::urlFor((string) $row->pkey, (string) $row->token, $side),
			$side,
			max(1, (int) round((int) $row->width * $k)),
			max(1, (int) round((int) $row->height * $k)),
			substr((string) $row->token, 0, 8)
		);
	}

	/** True when the person has a photo, whoever is asking. Use getPhoto() to know whether YOU may show it. */
	public function has(int $ownerId): bool
	{
		return $this->rowFor($ownerId) !== null;
	}

	/**
	 * Stores a new photo for $ownerId from one entry of $_FILES. The previous photo is replaced.
	 *
	 * @param   array<string, mixed>  $file
	 *
	 * @throws  SanitizeException  Short code (image_type, image_too_big, ...) that maps to COM_MIFOTO_ERR_<CODE>.
	 */
	public function upload(User $actor, int $ownerId, array $file): PhotoData
	{
		if (!AccessPolicy::mayUpload($actor->id === $ownerId && $ownerId > 0, (bool) $actor->guest, $actor->authorise('mifoto.upload', self::ACL_ASSET)))
		{
			throw new SanitizeException('not_allowed');
		}

		$existing = $this->rowFor($ownerId);

		if ($existing !== null && (time() - (int) strtotime((string) $existing->modified . ' UTC')) < self::UPLOAD_GAP)
		{
			throw new SanitizeException('too_fast');
		}

		// Every attempt counts (also the ones that fail), so nobody can use the processor as a free image-eater.
		$this->consumeUploadAttempt($ownerId);

		$storage = $this->settings->storage();

		try
		{
			$storage->prepare();
			$storage->sweep();
		}
		catch (\RuntimeException $e)
		{
			throw new SanitizeException('image_server');
		}

		$tmp = $storage->newTemporaryPath();

		try
		{
			$info  = $this->settings->processor()->process($file, $tmp);
			$token = $storage->commit($tmp, $ownerId);
		}
		catch (SanitizeException $e)
		{
			$storage->discard($tmp);

			throw $e;
		}
		catch (\Throwable $e)
		{
			$storage->discard($tmp);

			throw new SanitizeException('image_server');
		}

		$now = Factory::getDate()->toSql();

		if ($existing === null)
		{
			$row = (object) [
				'user_id'  => $ownerId,
				'pkey'     => bin2hex(random_bytes(8)),
				'token'    => $token,
				'width'    => $info['width'],
				'height'   => $info['height'],
				'bytes'    => $info['bytes'],
				'state'    => AccessPolicy::VISIBLE,
				'created'  => $now,
				'modified' => $now,
			];
			$this->db->insertObject('#__mifoto_photos', $row);
		}
		else
		{
			$row = (object) [
				'user_id'  => $ownerId,
				'token'    => $token,
				'width'    => $info['width'],
				'height'   => $info['height'],
				'bytes'    => $info['bytes'],
				'modified' => $now,
			];
			$this->db->updateObject('#__mifoto_photos', $row, 'user_id');
			$row->pkey = (string) $existing->pkey;
		}

		$photo = $this->getPhoto($ownerId, 300, $actor);

		if ($photo === null)
		{
			throw new SanitizeException('image_server');
		}

		return $photo;
	}

	/** The owner removes their own photo; a moderator can remove anybody's. */
	public function remove(User $actor, int $ownerId): bool
	{
		if (!AccessPolicy::mayRemove($actor->id === $ownerId && $ownerId > 0, (bool) $actor->guest, $actor->authorise('mifoto.moderate', self::ACL_ASSET)))
		{
			return false;
		}

		// Removing a photo does NOT reset the hourly counter: remove + upload in a loop must not get around the limit.
		$this->deletePhoto($ownerId);

		return true;
	}

	/** Hide (0) or show (1) a photo; moderators only. */
	public function setState(User $actor, int $ownerId, int $state): bool
	{
		if (!AccessPolicy::mayModerate($actor->authorise('mifoto.moderate', self::ACL_ASSET)) || $this->rowFor($ownerId) === null)
		{
			return false;
		}

		$row = (object) ['user_id' => $ownerId, 'state' => $state === AccessPolicy::VISIBLE ? AccessPolicy::VISIBLE : AccessPolicy::HIDDEN, 'modified' => Factory::getDate()->toSql()];
		$this->db->updateObject('#__mifoto_photos', $row, 'user_id');

		return true;
	}

	/** The account is gone (the "user deleted" event): photo, row and upload counter. No permission check. */
	public function deleteForUser(int $ownerId): void
	{
		if ($ownerId < 1)
		{
			return;
		}

		$this->deletePhoto($ownerId);

		$query = $this->db->getQuery(true)
			->delete($this->db->quoteName('#__mifoto_limits'))
			->where($this->db->quoteName('user_id') . ' = :id')
			->bind(':id', $ownerId, ParameterType::INTEGER);
		$this->db->setQuery($query)->execute();
	}

	/** Files and row of the photo; the upload counter stays. */
	private function deletePhoto(int $ownerId): void
	{
		if ($ownerId < 1)
		{
			return;
		}

		$this->settings->storage()->deleteUser($ownerId);

		$query = $this->db->getQuery(true)
			->delete($this->db->quoteName('#__mifoto_photos'))
			->where($this->db->quoteName('user_id') . ' = :id')
			->bind(':id', $ownerId, ParameterType::INTEGER);
		$this->db->setQuery($query)->execute();
	}

	/** Counts one upload attempt in the person's hourly window; refuses with "too_many" when the limit is used up. */
	private function consumeUploadAttempt(int $ownerId): void
	{
		$max = $this->settings->maxUploadsPerHour();

		if ($max < 1)
		{
			return; // 0 = no limit
		}

		$query = $this->db->getQuery(true)
			->select('*')
			->from($this->db->quoteName('#__mifoto_limits'))
			->where($this->db->quoteName('user_id') . ' = :id')
			->bind(':id', $ownerId, ParameterType::INTEGER);
		$row = $this->db->setQuery($query)->loadObject();

		$now   = time();
		$start = $row instanceof \stdClass ? (int) strtotime((string) $row->window_start . ' UTC') : 0;
		$check = UploadLimit::check($row instanceof \stdClass ? (int) $row->uploads : 0, $start, $now, $max);

		if (!$check['allowed'])
		{
			throw new SanitizeException('too_many');
		}

		$data = (object) ['user_id' => $ownerId, 'window_start' => gmdate('Y-m-d H:i:s', $check['start']), 'uploads' => $check['count']];

		if ($row instanceof \stdClass)
		{
			$this->db->updateObject('#__mifoto_limits', $data, 'user_id');
		}
		else
		{
			$this->db->insertObject('#__mifoto_limits', $data);
		}
	}

	/** The row behind an opaque public key (used by the delivery controller), or null. */
	public function rowByKey(string $pkey): ?\stdClass
	{
		if (preg_match('/^[a-f0-9]{16}$/D', $pkey) !== 1)
		{
			return null;
		}

		$query = $this->db->getQuery(true)
			->select('*')
			->from($this->db->quoteName('#__mifoto_photos'))
			->where($this->db->quoteName('pkey') . ' = :k')
			->bind(':k', $pkey);

		$row = $this->db->setQuery($query)->loadObject();

		return $row instanceof \stdClass ? $row : null;
	}

	/** The decision, for a given viewer, as used by getPhoto() and by the delivery controller. */
	public function viewerMaySee(User $viewer, int $ownerId, int $state): bool
	{
		return AccessPolicy::mayView(
			$viewer->id === $ownerId && $ownerId > 0,
			$viewer->authorise('mifoto.view', self::ACL_ASSET),
			$viewer->authorise('mifoto.moderate', self::ACL_ASSET),
			$state
		);
	}

	/**
	 * The ready-to-print HTML of a person's avatar (photo, or initials on a colour), in the admin's chosen look.
	 * Empty when the person does not exist or $viewer may not see it. This is what the {user} tag and any module should print.
	 */
	public function avatarHtml(int $ownerId, int $size = 64, ?User $viewer = null, string $alt = ''): string
	{
		if ($ownerId < 1)
		{
			return '';
		}

		$viewer = $viewer ?? Factory::getApplication()->getIdentity();
		$row    = $this->rowFor($ownerId);

		if (!$this->viewerMaySee($viewer, $ownerId, $row !== null ? (int) $row->state : AccessPolicy::VISIBLE))
		{
			return '';
		}

		$owner = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($ownerId);

		if ((int) $owner->id < 1)
		{
			return '';
		}

		$size = max(16, min(300, $size));
		$this->loadAvatarStyle();

		return AvatarRenderer::html(
			$this->getPhoto($ownerId, $size, $viewer),
			$this->settings->cssClasses(),
			$size,
			Placeholder::initials((string) $owner->name),
			Placeholder::color((string) $owner->username),
			$alt
		);
	}

	/**
	 * Rows for the moderation screen, newest first, with the name and user name of each owner.
	 *
	 * @return  array<int, \stdClass>
	 */
	public function listForAdmin(string $search, int $limit, int $offset): array
	{
		$query = $this->adminQuery($search)
			->select($this->db->quoteName(['p.user_id', 'p.pkey', 'p.bytes', 'p.state', 'p.modified', 'u.name', 'u.username']))
			->order($this->db->quoteName('p.modified') . ' DESC');

		return (array) $this->db->setQuery($query, max(0, $offset), max(1, min(200, $limit)))->loadObjectList();
	}

	public function countForAdmin(string $search): int
	{
		$query = $this->adminQuery($search)->select('COUNT(*)');

		return (int) $this->db->setQuery($query)->loadResult();
	}

	private function adminQuery(string $search): \Joomla\Database\QueryInterface
	{
		$db    = $this->db;
		$query = $db->getQuery(true)
			->from($db->quoteName('#__mifoto_photos', 'p'))
			->join('LEFT', $db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('p.user_id'));

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

	private function loadAvatarStyle(): void
	{
		try
		{
			$doc = Factory::getApplication()->getDocument();

			if (method_exists($doc, 'getWebAssetManager'))
			{
				$wa = $doc->getWebAssetManager();
				$wa->getRegistry()->addExtensionRegistryFile('com_mifoto');
				$wa->useStyle('com_mifoto.avatar');
			}
		}
		catch (\Throwable $e)
		{
			// Without the stylesheet the avatar is still there, only unstyled.
		}
	}

	public static function urlFor(string $pkey, string $token, int $size): string
	{
		return Uri::root(true) . '/index.php?option=com_mifoto&task=photo.show&u=' . $pkey . '&s=' . $size . '&v=' . substr($token, 0, 8);
	}

	private function rowFor(int $ownerId): ?\stdClass
	{
		if ($ownerId < 1)
		{
			return null;
		}

		$query = $this->db->getQuery(true)
			->select('*')
			->from($this->db->quoteName('#__mifoto_photos'))
			->where($this->db->quoteName('user_id') . ' = :id')
			->bind(':id', $ownerId, ParameterType::INTEGER);

		$row = $this->db->setQuery($query)->loadObject();

		return $row instanceof \stdClass ? $row : null;
	}
}
