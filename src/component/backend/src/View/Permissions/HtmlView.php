<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mifoto\Administrator\View\Permissions;

defined('_JEXEC') || die;

use Chaska\Component\Mifoto\Administrator\Service\AdminData;
use Chaska\Component\Mifoto\Administrator\Service\PermissionMatrix;
use Chaska\Component\Mifoto\Administrator\Service\PermissionStore;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;

/** Who can do what, per user group. Anyone who may open MiFoto sees it; only core.admin can click the cells. */
final class HtmlView extends BaseHtmlView
{
	/** @var array{users: int, visible: int, hidden: int, with: int, without: int} */
	public array $totals = ['users' => 0, 'visible' => 0, 'hidden' => 0, 'with' => 0, 'without' => 0];

	/** @var array<int, array{id: int, title: string, level: int, cells: array<string, string>}> */
	public array $rows = [];

	public bool $canEdit = false;

	public string $token = '';

	public function display($tpl = null): void
	{
		$db    = Factory::getContainer()->get(DatabaseInterface::class);
		$store = new PermissionStore($db);
		$groups = $store->groups();

		$this->totals  = (new AdminData($db))->totals();
		$this->rows    = PermissionMatrix::rows($groups, $store->rules(), $store->fixed($groups));
		$this->canEdit = Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_mifoto');
		$this->token   = Session::getFormToken();

		ToolbarHelper::title(Text::_('COM_MIFOTO'), 'image');

		parent::display($tpl);
	}
}
