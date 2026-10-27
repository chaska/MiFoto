<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mifoto\Administrator\View\Panel;

defined('_JEXEC') || die;

use Chaska\Component\Mifoto\Administrator\Service\AdminData;
use Chaska\Component\Mifoto\Administrator\Service\Health;
use Chaska\Component\Mifoto\Administrator\Service\PermissionMatrix;
use Chaska\Component\Mifoto\Administrator\Service\PermissionStore;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;

/** The dashboard: the album page by page, the latest photos, "is everything in order?" and who can do what. Read-only. */
final class HtmlView extends BaseHtmlView
{
	public const PER_PAGE = 24;

	/** @var array{users: int, visible: int, hidden: int, with: int, without: int} */
	public array $totals = ['users' => 0, 'visible' => 0, 'hidden' => 0, 'with' => 0, 'without' => 0];

	/** @var array<int, \stdClass> */
	public array $album = [];

	/** @var array<int, \stdClass> */
	public array $recent = [];

	/** @var array{items: array<int, array<string, string>>, warnings: int} */
	public array $health = ['items' => [], 'warnings' => 0];

	/** @var array<int, array{id: int, title: string, level: int, cells: array<string, string>}> */
	public array $matrix = [];

	/** True when this person may open the component but not moderate: they get the numbers, not the people. */
	public bool $restricted = false;

	public string $filter = 'todos';

	public int $page = 1;

	public int $pages = 1;

	public function display($tpl = null): void
	{
		$app     = Factory::getApplication();
		$input   = $app->getInput();
		$db      = Factory::getContainer()->get(DatabaseInterface::class);
		$data    = new AdminData($db);
		$service = $app->bootComponent('com_mifoto')->getPhotoService();

		$filter       = (string) $input->getCmd('filter', 'todos');
		$this->filter = \in_array($filter, AdminData::FILTERS, true) ? $filter : 'todos';
		$this->totals = $data->totals();

		$total       = $data->count($this->filter);
		$this->pages = max(1, (int) ceil($total / self::PER_PAGE));
		$this->page  = max(1, min($this->pages, (int) $input->getInt('pagina', 1)));
		$this->restricted = !$app->getIdentity()->authorise('mifoto.moderate', 'com_mifoto');

		// Names and usernames of everybody are for moderators only; the totals are not personal data.
		if (!$this->restricted)
		{
			$this->album  = $data->album($this->filter, ($this->page - 1) * self::PER_PAGE, self::PER_PAGE);
			$this->recent = $data->recent(6);
		}

		$this->health = Health::run($db, $service);

		$store  = new PermissionStore($db);
		$groups = $store->groups();
		$rows   = PermissionMatrix::rows($groups, $store->rules(), $store->fixed($groups));
		$reg    = (int) ComponentHelper::getParams('com_users')->get('new_usertype', 2);

		// Public, the group people join, and the groups that have something; at most six, in tree order.
		$this->matrix = array_slice(array_values(array_filter(
			$rows,
			static fn (array $r): bool => $r['id'] === 1 || $r['id'] === $reg || array_filter($r['cells'], static fn (string $c): bool => $c !== '') !== []
		)), 0, 6);

		ToolbarHelper::title(Text::_('COM_MIFOTO'), 'image');

		parent::display($tpl);
	}
}
