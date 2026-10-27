<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mifoto\Administrator\View\Reports;

defined('_JEXEC') || die;

use Chaska\Component\Mifoto\Administrator\Service\AdminData;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;

/** Reports of photos made by the people of the site: the open ones first, then the last ones that were resolved. */
final class HtmlView extends BaseHtmlView
{
	/** @var array{users: int, visible: int, hidden: int, with: int, without: int} */
	public array $totals = ['users' => 0, 'visible' => 0, 'hidden' => 0, 'with' => 0, 'without' => 0];

	/** @var array<int, \stdClass> */
	public array $open = [];

	/** @var array<int, \stdClass> */
	public array $done = [];

	/** The person may open the component but not moderate: names and comments of other people stay hidden. */
	public bool $restricted = false;

	public function display($tpl = null): void
	{
		$app = Factory::getApplication();
		$db  = Factory::getContainer()->get(DatabaseInterface::class);

		$this->totals     = (new AdminData($db))->totals();
		$this->restricted = !$app->getIdentity()->authorise('mifoto.moderate', 'com_mifoto');

		if (!$this->restricted)
		{
			$reports    = $app->bootComponent('com_mifoto')->getPhotoService()->reports();
			$this->open = $reports->listing(true, 100, 0);
			$this->done = $reports->listing(false, 20, 0);
		}

		ToolbarHelper::title(Text::_('COM_MIFOTO'), 'image');

		parent::display($tpl);
	}
}
