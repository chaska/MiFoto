<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mifoto\Administrator\Controller;

defined('_JEXEC') || die;

use Chaska\Component\Mifoto\Administrator\Service\PermissionMatrix;
use Chaska\Component\Mifoto\Administrator\Service\PermissionStore;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;

/**
 * One click on one cell of the permissions table. POST + form token, and only a person who may administer the component
 * (core.admin). The answer is JSON: the whole table again, because changing one group can change the groups below it.
 */
final class PermissionsController extends BaseController
{
	public function toggle(): void
	{
		if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST' || !Session::checkToken('post'))
		{
			$this->json(['ok' => false, 'message' => Text::_('JINVALID_TOKEN')], 403);
		}

		if (!Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_mifoto'))
		{
			$this->json(['ok' => false, 'message' => Text::_('COM_MIFOTO_A_ERR_RIGHTS')], 403);
		}

		$group  = (int) $this->input->post->get('g', 0, 'int');
		$key    = (string) $this->input->post->get('k', '', 'cmd');
		$action = (string) array_search($key, PermissionMatrix::ACTIONS, true);
		$store  = new PermissionStore(Factory::getContainer()->get(DatabaseInterface::class));
		$groups = $store->groups();
		$fixed  = $store->fixed($groups);
		$result = PermissionMatrix::toggle($groups, $store->rules(), $fixed, $group, $action);

		if (!$result['ok'])
		{
			$this->json(['ok' => false, 'message' => Text::_($result['reason'] === 'denied' ? 'COM_MIFOTO_A_ERR_DENIED' : 'COM_MIFOTO_A_ERR_SAVE'), 'rows' => PermissionMatrix::rows($groups, $store->rules(), $fixed)], 422);
		}

		if (!$store->save($result['rules']))
		{
			$this->json(['ok' => false, 'message' => Text::_('COM_MIFOTO_A_ERR_SAVE')], 500);
		}

		$this->json(['ok' => true, 'state' => $result['state'], 'rows' => PermissionMatrix::rows($groups, $result['rules'], $fixed)]);
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
		echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
		Factory::getApplication()->close();
		exit;
	}
}
