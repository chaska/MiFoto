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
use Chaska\Component\Mifoto\Administrator\Service\PhotoServer;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/** Moderation: look at the photos, hide or show one, remove one. Every change needs the moderate permission and a form token. */
final class PhotosController extends BaseController
{
	/** GET: the photo as the moderator sees it (the same delivery as the site, with the same headers). Never returns. */
	public function image(): void
	{
		$app  = Factory::getApplication();
		$key  = strtolower((string) $this->input->get('pkey', '', 'alnum'));
		$size = (int) $this->input->get('size', 64, 'int');

		PhotoServer::send($app, $app->bootComponent('com_mifoto')->getPhotoService(), $app->getIdentity(), $key, max(1, min(300, $size)));
	}

	public function hide(): void
	{
		$this->act('hide');
	}

	public function show(): void
	{
		$this->act('show');
	}

	public function remove(): void
	{
		$this->act('remove');
	}

	/**
	 * With "&ajax=1" (the buttons on the user edit page) the answer is JSON and nobody is redirected; without it (the list of
	 * photos) it is the usual redirect with a message.
	 */
	private function act(string $what): void
	{
		$ajax = $this->input->getInt('ajax', 0) === 1;

		if ($ajax)
		{
			if (!Session::checkToken('post'))
			{
				$this->json(['ok' => false, 'message' => Text::_('JINVALID_TOKEN')], 403);
			}
		}
		else
		{
			$this->checkToken('post');
		}

		$app  = Factory::getApplication();
		$back = Route::_('index.php?option=com_mifoto&view=photos', false);
		$user = $app->getIdentity();

		if (!$user->authorise('mifoto.moderate', 'com_mifoto'))
		{
			$ajax ? $this->json(['ok' => false, 'message' => Text::_('COM_MIFOTO_PHOTOS_NOT_ALLOWED')], 403) : $this->setRedirect($back, Text::_('COM_MIFOTO_PHOTOS_NOT_ALLOWED'), 'error');

			return;
		}

		$service = $app->bootComponent('com_mifoto')->getPhotoService();
		$row     = $service->rowByKey(strtolower((string) $this->input->get('pkey', '', 'alnum')));

		if ($row === null)
		{
			$ajax ? $this->json(['ok' => false, 'message' => Text::_('COM_MIFOTO_PHOTOS_GONE')], 404) : $this->setRedirect($back, Text::_('COM_MIFOTO_PHOTOS_GONE'), 'warning');

			return;
		}

		$owner = (int) $row->user_id;

		if ($what === 'remove')
		{
			$service->remove($user, $owner);
		}
		else
		{
			$service->setState($user, $owner, $what === 'show' ? AccessPolicy::VISIBLE : AccessPolicy::HIDDEN);
		}

		if ($ajax)
		{
			$this->json(['ok' => true, 'action' => $what]);
		}

		$this->setRedirect($back, Text::_('COM_MIFOTO_PHOTOS_DONE_' . strtoupper($what)));
	}

	/** @param array<string, mixed> $data */
	private function json(array $data, int $status = 200): never
	{
		while (ob_get_level() > 0)
		{
			@ob_end_clean();
		}

		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store');
		header('X-Content-Type-Options: nosniff');
		echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
		Factory::getApplication()->close();
		exit;
	}
}
