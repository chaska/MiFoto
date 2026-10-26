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
 * Who may do what with a photo, as plain yes/no logic with no Joomla calls so it can be tested alone. The callers feed it
 * the Joomla ACL answers (mifoto.upload, mifoto.view, mifoto.moderate); the rules live here and nowhere else.
 */
final class AccessPolicy
{
	public const VISIBLE = 1;
	public const HIDDEN  = 0;

	/**
	 * The owner always sees their own photo (even one a moderator hid, so they know about it); a moderator sees all;
	 * everybody else needs the "view" permission AND the photo not hidden.
	 */
	public static function mayView(bool $isOwner, bool $viewerMayView, bool $viewerIsModerator, int $state): bool
	{
		if ($isOwner || $viewerIsModerator)
		{
			return true;
		}

		return $state === self::VISIBLE && $viewerMayView;
	}

	/** Only the owner, only when logged in, only with the "upload" permission. A moderator can hide or remove, not upload for others. */
	public static function mayUpload(bool $isOwner, bool $isGuest, bool $hasUploadPermission): bool
	{
		return $isOwner && !$isGuest && $hasUploadPermission;
	}

	/** Removing is allowed for the owner even if they lost the upload permission (privacy), and for moderators. */
	public static function mayRemove(bool $isOwner, bool $isGuest, bool $viewerIsModerator): bool
	{
		return ($isOwner && !$isGuest) || $viewerIsModerator;
	}

	public static function mayModerate(bool $viewerIsModerator): bool
	{
		return $viewerIsModerator;
	}
}
