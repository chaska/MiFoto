<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mifoto\Administrator\View\Photos;

defined('_JEXEC') || die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\ToolbarHelper;

final class HtmlView extends BaseHtmlView
{
	/** @var  array<int, \stdClass> */
	public array $items = [];

	public ?Pagination $pagination = null;

	public string $search = '';

	public function display($tpl = null): void
	{
		$app     = Factory::getApplication();
		$input   = $app->getInput();
		$user    = $app->getIdentity();
		$service = $app->bootComponent('com_mifoto')->getPhotoService();
		$limit   = 20;

		$this->search = trim((string) $input->getString('filter_search', ''));
		$total        = $service->countForAdmin($this->search);
		$start        = max(0, (int) $input->getInt('limitstart', 0));

		if ($start >= $total)
		{
			$start = 0;
		}

		$this->items      = $service->listForAdmin($this->search, $limit, $start);
		$this->pagination = new Pagination($total, $start, $limit);

		ToolbarHelper::title(Text::_('COM_MIFOTO_PHOTOS_TITLE'), 'users');

		if ($user->authorise('core.admin', 'com_mifoto') || $user->authorise('core.options', 'com_mifoto'))
		{
			ToolbarHelper::preferences('com_mifoto');
		}

		parent::display($tpl);
	}
}
