<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mifoto\Administrator\View\Settings;

defined('_JEXEC') || die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Registry\Registry;

/**
 * The settings screen. It only DRAWS the form: saving is done by Joomla's own com_config (task component.save), which checks
 * the form token, the permission (core.admin or core.options) and validates every value against config.xml.
 */
final class HtmlView extends BaseHtmlView
{
	public Registry $params;

	public int $extensionId = 0;

	public bool $canEdit = false;

	/** Where com_config sends the person after saving: back to this screen (base64, as com_config expects). */
	public string $return = '';

	public function display($tpl = null): void
	{
		$user = Factory::getApplication()->getIdentity();

		$this->params      = ComponentHelper::getParams('com_mifoto');
		$this->extensionId = (int) ComponentHelper::getComponent('com_mifoto')->id;
		$this->canEdit     = $user->authorise('core.admin', 'com_mifoto') || $user->authorise('core.options', 'com_mifoto');
		$this->return      = base64_encode('index.php?option=com_mifoto&view=settings');

		ToolbarHelper::title(Text::_('COM_MIFOTO_SETTINGS_TITLE'), 'cog');
		ToolbarHelper::link('index.php?option=com_mifoto&view=photos', 'COM_MIFOTO_MENU_PHOTOS', 'users');

		parent::display($tpl);
	}
}
