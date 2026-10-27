<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mifoto\Administrator\View\Settings;

defined('_JEXEC') || die;

use Chaska\Component\Mifoto\Administrator\Service\AdminData;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Chaska\Component\Mifoto\Administrator\Service\Health;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

/**
 * The settings screen. It only DRAWS the form: saving is done by Joomla's own com_config (task component.save), which checks
 * the form token, the permission (core.admin or core.options) and validates every value against config.xml.
 */
final class HtmlView extends BaseHtmlView
{
	public Registry $params;

	/** @var array{users: int, visible: int, hidden: int, with: int, without: int} */
	public array $totals = ['users' => 0, 'visible' => 0, 'hidden' => 0, 'with' => 0, 'without' => 0];

	public int $extensionId = 0;

	public bool $canEdit = false;

	/** @var array<string, string>|null  the Engage plugin check, shown only when it is a warning */
	public ?array $engage = null;

	/** Where com_config sends the person after saving: back to this screen (base64, as com_config expects). */
	public string $return = '';

	public function display($tpl = null): void
	{
		$user = Factory::getApplication()->getIdentity();

		$this->params      = ComponentHelper::getParams('com_mifoto');
		$db                = Factory::getContainer()->get(DatabaseInterface::class);
		$this->totals      = (new AdminData($db))->totals();
		$engage            = Health::engage($db);
		$this->engage      = $engage !== null && $engage['status'] === 'warn' ? $engage : null;
		$this->extensionId = (int) ComponentHelper::getComponent('com_mifoto')->id;
		$this->canEdit     = $user->authorise('core.admin', 'com_mifoto') || $user->authorise('core.options', 'com_mifoto');
		$this->return      = base64_encode('index.php?option=com_mifoto&view=settings');

		ToolbarHelper::title(Text::_('COM_MIFOTO'), 'image');

		parent::display($tpl);
	}
}
