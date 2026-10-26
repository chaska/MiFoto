<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Plugin\Content\Mifoto\Extension;

defined('_JEXEC') || die;

use Chaska\Plugin\Content\Mifoto\Service\Shortcode;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

/**
 * Swaps {user}, {user 42}, {user size=96} in articles and modules for the person's avatar. What is printed (photo or
 * initials) and whether the reader may see it is decided by com_mifoto; this plugin only finds the tags.
 */
final class Mifoto extends CMSPlugin implements SubscriberInterface
{
	protected $autoloadLanguage = true;

	public static function getSubscribedEvents(): array
	{
		return ['onContentPrepare' => 'onContentPrepare'];
	}

	public function onContentPrepare(Event $event): void
	{
		// Joomla 5.x events carry named arguments (getItem()); older style passes the item second.
		$item = method_exists($event, 'getItem') ? $event->getItem() : $event->getArgument(1);

		if (!\is_object($item) || !isset($item->text) || !\is_string($item->text) || !Shortcode::has($item->text) || !ComponentHelper::isEnabled('com_mifoto'))
		{
			return;
		}

		$app     = $this->getApplication();
		$viewer  = $app->getIdentity();
		$service = $app->bootComponent('com_mifoto')->getPhotoService();
		$default = max(32, min(300, (int) $this->params->get('default_size', 64)));

		// {user} without a number is the reader; a guest has no photo, so it prints nothing.
		$item->text = Shortcode::replace(
			$item->text,
			$default,
			static fn (int $id, int $size): string => $service->avatarHtml($id > 0 ? $id : (int) $viewer->id, $size, $viewer)
		);
	}
}
