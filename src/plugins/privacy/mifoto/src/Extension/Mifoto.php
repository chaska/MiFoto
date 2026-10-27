<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Plugin\Privacy\Mifoto\Extension;

defined('_JEXEC') || die;

use Joomla\CMS\Event\Privacy\ExportRequestEvent;
use Joomla\CMS\Event\Privacy\RemoveDataEvent;
use Joomla\CMS\Language\Text;
use Joomla\Component\Privacy\Administrator\Plugin\PrivacyPlugin;
use Joomla\Event\SubscriberInterface;

/**
 * MiFoto's part of Joomla's own privacy tools (Users > Privacy): when somebody asks for "a copy of my data" it adds what MiFoto
 * keeps about them (the photo's details, when they accepted, the reports they made); when an administrator processes a
 * "remove my data" request it deletes the photo, the acceptances and the reports. Nothing here decides who may ask: com_privacy does.
 * The photo file itself is not put into the export (it is theirs; they have it), only its details.
 */
final class Mifoto extends PrivacyPlugin implements SubscriberInterface
{
	protected $autoloadLanguage = true;

	public static function getSubscribedEvents(): array
	{
		return [
			'onPrivacyExportRequest' => 'onPrivacyExportRequest',
			'onPrivacyRemoveData'    => 'onPrivacyRemoveData',
		];
	}

	public function onPrivacyExportRequest(ExportRequestEvent $event)
	{
		$user = $event->getUser();

		if (!$user)
		{
			return;
		}

		$service = $this->getApplication()->bootComponent('com_mifoto')->getPhotoService();
		$id      = (int) $user->id;
		$domains = [];

		$row = $service->photoRow($id);

		if ($row !== null)
		{
			$domain = $this->createDomain('mifoto_photo', Text::_('PLG_PRIVACY_MIFOTO_PHOTO'));
			$domain->addItem($this->createItemFromArray([
				'uploaded' => (string) $row->created,
				'changed'  => (string) $row->modified,
				'width'    => (int) $row->width,
				'height'   => (int) $row->height,
				'bytes'    => (int) $row->bytes,
				'visible'  => (int) $row->state === 1 ? 'yes' : 'no',
			], $id));
			$domains[] = $domain;
		}

		$accepted = $service->consents()->forUser($id);

		if ($accepted !== [])
		{
			$domain = $this->createDomain('mifoto_consents', Text::_('PLG_PRIVACY_MIFOTO_CONSENTS'));

			foreach ($accepted as $i => $item)
			{
				$domain->addItem($this->createItemFromArray($item, $i + 1));
			}

			$domains[] = $domain;
		}

		$made = $service->reports()->madeBy($id);

		if ($made !== [])
		{
			$domain = $this->createDomain('mifoto_reports', Text::_('PLG_PRIVACY_MIFOTO_REPORTS'));

			foreach ($made as $i => $item)
			{
				$domain->addItem($this->createItemFromArray($item, $i + 1));
			}

			$domains[] = $domain;
		}

		$event->addResult($domains);
	}

	public function onPrivacyRemoveData(RemoveDataEvent $event)
	{
		$user = $event->getUser();

		if (!$user)
		{
			return;
		}

		$this->getApplication()->bootComponent('com_mifoto')->getPhotoService()->deleteForUser((int) $user->id);
	}
}
