<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Plugin\Engage\Mifoto\Extension;

defined('_JEXEC') || die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\User\User;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

/**
 * MiFoto as an avatar source for Akeeba Engage. Engage fires onAkeebaEngageUserAvatarURL with the user and the size in pixels
 * and uses the first non-empty address in "result". This plugin adds the MiFoto address (same site, never a third party) when
 * the person has a photo the reader may see, and says nothing otherwise so Engage moves on to the next plugin.
 *
 * It does not decide where or how Engage shows the avatar: that is Engage's own configuration.
 */
final class Mifoto extends CMSPlugin implements SubscriberInterface
{
	protected $allowLegacyListeners = false;

	public static function getSubscribedEvents(): array
	{
		return ['onAkeebaEngageUserAvatarURL' => 'onAkeebaEngageUserAvatarURL'];
	}

	public function onAkeebaEngageUserAvatarURL(Event $event): void
	{
		$user = $event->getArgument(0);
		$size = (int) ($event->getArgument(1) ?? 48);

		// Engage 3.4.13+ adds a third argument: where the avatar will be used ('page', 'email' or 'static'). The photo depends on WHO
		// is looking, so it must only be given for a live page, never for e-mails or shared caches. Older Engage sends nothing: page.
		$context = $event->getArgument(2);

		if (\is_string($context) && $context !== '' && $context !== 'page')
		{
			return;
		}

		// Guests reach Engage as a fake user with only an e-mail (id 0): MiFoto has no photo for them.
		if (!$user instanceof User || (int) $user->id < 1 || !ComponentHelper::isEnabled('com_mifoto'))
		{
			return;
		}

		try
		{
			$app   = $this->getApplication();
			$photo = $app->bootComponent('com_mifoto')->getPhotoService()->getPhoto((int) $user->id, max(1, min(300, $size)), $app->getIdentity());
		}
		catch (\Throwable $e)
		{
			return; // a photo problem must never break the comments page
		}

		if ($photo === null)
		{
			return;
		}

		$event->setArgument('result', array_merge((array) $event->getArgument('result', []), [$photo->url]));
	}
}
