<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mifoto\Site\Controller;

defined('_JEXEC') || die;

use Chaska\Component\Mifoto\Administrator\Security\SanitizeException;
use Chaska\Component\Mifoto\Administrator\Service\Consent;
use Chaska\Component\Mifoto\Administrator\Service\PhotoServer;
use Chaska\Component\Mifoto\Administrator\Service\Reports;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\CMS\User\User;

/** index.php?option=com_mifoto&task=photo.show&u=<16 hex>&s=<size>&v=<cache buster> */
final class PhotoController extends BaseController
{
	public function show(): void
	{
		$app  = Factory::getApplication();
		$key  = strtolower((string) $this->input->get('u', '', 'alnum'));
		$size = (int) $this->input->get('s', 128, 'int');

		// Never returns: it ends with the image or with an empty 404.
		PhotoServer::send($app, $app->bootComponent('com_mifoto')->getPhotoService(), $app->getIdentity(), $key, max(1, min(300, $size)));
	}

	/**
	 * POST index.php?option=com_mifoto&task=photo.upload  (multipart: "photo" + the form token). Answers JSON.
	 * The owner is ALWAYS the logged-in user: no user id is read from the request.
	 */
	public function upload(): void
	{
		$user = $this->guard();
		$file = $this->input->files->get('photo', [], 'array');

		// One plain file only: a "photo[]" array or a missing field is refused before anything is read.
		if (!\is_array($file) || !isset($file['tmp_name']) || !\is_string($file['tmp_name']))
		{
			$this->json(['ok' => false, 'message' => Text::_('COM_MIFOTO_ERR_IMAGE_UPLOAD')], 422);
		}

		try
		{
			// The two consent boxes travel with the photo; the server refuses the upload unless both say exactly "1".
			$consented = Consent::accepted($this->input->post->get('consent_owner', '', 'raw'), $this->input->post->get('consent_data', '', 'raw'));
			$photo     = Factory::getApplication()->bootComponent('com_mifoto')->getPhotoService()->upload($user, (int) $user->id, $file, $consented);
		}
		catch (SanitizeException $e)
		{
			$key = 'COM_MIFOTO_ERR_' . strtoupper($e->getReason());
			$msg = Text::_($key);

			$this->json(['ok' => false, 'message' => $msg !== $key ? $msg : Text::_('COM_MIFOTO_ERR_IMAGE_UNREADABLE')], $e->getReason() === 'not_allowed' ? 403 : 422);
		}

		$this->json(['ok' => true, 'url' => $photo->url, 'width' => $photo->width, 'height' => $photo->height]);
	}

	/**
	 * POST index.php?option=com_mifoto&task=photo.report  (form token, u = the photo's public key, reason, comment).
	 * Only a logged-in person who is allowed to SEE that photo can report it, so reporting cannot be used to find out who has one.
	 */
	public function report(): void
	{
		$user    = $this->guard();
		$service = Factory::getApplication()->bootComponent('com_mifoto')->getPhotoService();
		$key     = strtolower((string) $this->input->post->get('u', '', 'alnum'));
		$reason  = Reports::cleanReason($this->input->post->get('reason', '', 'raw'));
		$comment = Reports::cleanComment($this->input->post->get('comment', '', 'raw'));
		$row     = $reason !== null ? $service->rowByKey($key) : null;

		if ($row === null || !$service->viewerMaySee($user, (int) $row->user_id, (int) $row->state))
		{
			$this->json(['ok' => false, 'message' => Text::_('COM_MIFOTO_W_REPORT_ERR')], 422);
		}

		$done = $service->reports()->create((int) $user->id, (int) $row->user_id, $reason, $comment);

		if ($done === 'ok')
		{
			$this->json(['ok' => true, 'message' => Text::_('COM_MIFOTO_W_REPORT_SENT')]);
		}

		$this->json(['ok' => false, 'message' => Text::_($done === 'duplicate' ? 'COM_MIFOTO_W_REPORT_DUP' : ($done === 'too_many' ? 'COM_MIFOTO_W_REPORT_MANY' : 'COM_MIFOTO_W_REPORT_ERR'))], $done === 'too_many' ? 429 : 422);
	}

	/** POST index.php?option=com_mifoto&task=photo.remove  (the form token). Removes the logged-in user's photo. */
	public function remove(): void
	{
		$user = $this->guard();

		Factory::getApplication()->bootComponent('com_mifoto')->getPhotoService()->remove($user, (int) $user->id);

		$this->json(['ok' => true]);
	}

	/** POST only, valid form token, logged in. Anything else ends here with a JSON error. */
	private function guard(): User
	{
		$app = Factory::getApplication();

		if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST')
		{
			$this->json(['ok' => false, 'message' => Text::_('COM_MIFOTO_ERR_NOT_ALLOWED')], 405);
		}

		if (!Session::checkToken('post'))
		{
			$this->json(['ok' => false, 'message' => Text::_('JINVALID_TOKEN')], 403);
		}

		$user = $app->getIdentity();

		if ($user === null || $user->guest)
		{
			$this->json(['ok' => false, 'message' => Text::_('COM_MIFOTO_ERR_NOT_ALLOWED')], 403);
		}

		return $user;
	}

	/** @param  array<string, mixed>  $data */
	private function json(array $data, int $status = 200): never
	{
		$app = Factory::getApplication();

		while (ob_get_level() > 0)
		{
			@ob_end_clean();
		}

		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: no-store');
		header('X-Content-Type-Options: nosniff');
		echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
		$app->close();
		exit;
	}
}
