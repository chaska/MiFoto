<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mifoto\Administrator\Controller;

defined('_JEXEC') || die;

use Chaska\Component\Mifoto\Administrator\Service\AccessPolicy;
use Chaska\Component\Mifoto\Administrator\Service\Reports;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;

/**
 * What a moderator does with a report: hide the photo, remove it, or dismiss the report. POST + form token + the "moderate"
 * permission, always. Hiding or removing closes EVERY open report about that photo, because they are about the same thing.
 */
final class ReportsController extends BaseController
{
	public function hide(): void
	{
		$this->act('hide');
	}

	public function remove(): void
	{
		$this->act('remove');
	}

	public function dismiss(): void
	{
		$this->act('dismiss');
	}

	private function act(string $what): void
	{
		$this->checkToken('post');

		$app  = Factory::getApplication();
		$user = $app->getIdentity();
		$back = Route::_('index.php?option=com_mifoto&view=reports', false);

		if (!$user->authorise('mifoto.moderate', 'com_mifoto'))
		{
			$this->setRedirect($back, Text::_('COM_MIFOTO_PHOTOS_NOT_ALLOWED'), 'error');

			return;
		}

		$service = $app->bootComponent('com_mifoto')->getPhotoService();
		$reports = $service->reports();
		$id      = (int) $this->input->post->getInt('id', 0);
		$owner   = $reports->ownerOf($id);

		if ($owner === null)
		{
			$this->setRedirect($back, Text::_('COM_MIFOTO_A_R_ERR'), 'warning');

			return;
		}

		if ($what === 'dismiss')
		{
			$reports->dismiss($id, (int) $user->id);
		}
		elseif ($what === 'hide')
		{
			$service->setState($user, $owner, AccessPolicy::HIDDEN);
			$reports->closeForOwner($owner, Reports::HIDDEN, (int) $user->id);
		}
		else
		{
			$service->remove($user, $owner);
			$reports->closeForOwner($owner, Reports::REMOVED, (int) $user->id);
		}

		$this->setRedirect($back, Text::_('COM_MIFOTO_A_R_OK'));
	}
}
