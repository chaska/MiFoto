<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Module\MifotoPhoto\Site\Dispatcher;

defined('_JEXEC') || die;

use Chaska\Component\Mifoto\Administrator\Service\PhotoWidget;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Factory;

/**
 * The same photo widget as the profile form, wherever the module is placed. Nothing for a guest. All the logic is in com_mifoto.
 */
class Dispatcher extends AbstractModuleDispatcher
{
	protected function getLayoutData(): array
	{
		$data = parent::getLayoutData();
		$app  = Factory::getApplication();
		$user = $app->getIdentity();
		$html = '';

		if (!$user->guest && ComponentHelper::isEnabled('com_mifoto'))
		{
			try
			{
				$html = PhotoWidget::html($app->bootComponent('com_mifoto')->getPhotoService(), $user);
			}
			catch (\Throwable $e)
			{
				$html = ''; // a photo problem never breaks the page
			}
		}

		$data['html'] = $html;

		return $data;
	}
}
