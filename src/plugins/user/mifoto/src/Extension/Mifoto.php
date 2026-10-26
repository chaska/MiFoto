<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Plugin\User\Mifoto\Extension;

defined('_JEXEC') || die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

/**
 * Puts the photo widget in the Joomla profile form (the page where people change their name, e-mail and password) and cleans
 * up when a user is deleted. The upload itself is done by com_mifoto (POST + form token), not by this form, so a photo problem
 * can never stop the person from saving the rest of their profile.
 */
final class Mifoto extends CMSPlugin implements SubscriberInterface
{
	protected $autoloadLanguage = true;

	public static function getSubscribedEvents(): array
	{
		return [
			'onContentPrepareForm' => 'onContentPrepareForm',
			'onUserAfterDelete'    => 'onUserAfterDelete',
		];
	}

	public function onContentPrepareForm(Event $event): void
	{
		// Joomla 5.x events carry named arguments (getForm()); older style passes the form first.
		$form = method_exists($event, 'getForm') ? $event->getForm() : $event->getArgument(0);

		if (!$form instanceof Form || $form->getName() !== 'com_users.profile' || !ComponentHelper::isEnabled('com_mifoto'))
		{
			return;
		}

		$app = $this->getApplication();

		if (!$app->isClient('site') || $app->getIdentity()->guest)
		{
			return;
		}

		Form::addFormPath(\dirname(__DIR__, 2) . '/forms');
		$form->loadFile('mifoto', false);
	}

	/** A deleted account takes its photo with it (files and row). Never lets a photo problem break the deletion. */
	public function onUserAfterDelete(Event $event): void
	{
		$user    = $event->getArgument('subject') ?? $event->getArgument(0);
		$success = $event->getArgument('deletingResult') ?? $event->getArgument(1);
		$id      = \is_array($user) ? (int) ($user['id'] ?? 0) : 0;

		if (!$success || $id < 1 || !ComponentHelper::isEnabled('com_mifoto'))
		{
			return;
		}

		try
		{
			$this->getApplication()->bootComponent('com_mifoto')->getPhotoService()->deleteForUser($id);
		}
		catch (\Throwable $e)
		{
			// The account is already gone; a leftover photo is not worth an error page.
		}
	}
}
