<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mifoto\Administrator\View\Photos;

defined('_JEXEC') || die;

use Chaska\Component\Mifoto\Administrator\Service\AdminData;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;

/** Photos: the album in big, a search, four filters, and the photo that is selected with its moderation buttons. */
final class HtmlView extends BaseHtmlView
{
	public const PER_PAGE = 24;

	/** @var array{users: int, visible: int, hidden: int, with: int, without: int} */
	public array $totals = ['users' => 0, 'visible' => 0, 'hidden' => 0, 'with' => 0, 'without' => 0];

	/** @var array<int, \stdClass> */
	public array $items = [];

	public ?\stdClass $selected = null;

	public string $filter = 'con';

	public string $search = '';

	public int $page = 1;

	public int $pages = 1;

	public int $count = 0;

	public bool $canModerate = false;

	/** Names and usernames of everybody are for moderators only. */
	public bool $restricted = false;

	public function display($tpl = null): void
	{
		$app   = Factory::getApplication();
		$input = $app->getInput();
		$data  = new AdminData(Factory::getContainer()->get(DatabaseInterface::class));

		$filter       = (string) $input->getCmd('filter', 'con');
		$this->filter = \in_array($filter, ['con', 'visibles', 'ocultas', 'sin'], true) ? $filter : 'con';
		$this->search = trim((string) $input->getString('q', ''));
		$this->totals = $data->totals();

		$this->count = $data->count($this->filter, $this->search);
		$this->pages = max(1, (int) ceil($this->count / self::PER_PAGE));
		$this->page  = max(1, min($this->pages, (int) $input->getInt('pagina', 1)));
		$this->restricted = !$app->getIdentity()->authorise('mifoto.moderate', 'com_mifoto');
		$this->items = $this->restricted ? [] : $data->album($this->filter, ($this->page - 1) * self::PER_PAGE, self::PER_PAGE, $this->search, $this->filter === 'sin' ? 'id' : 'recent');

		// The selected photo is one of the page's; with none chosen (or a stale link) the first one is.
		$key = strtolower((string) $input->getAlnum('pkey', ''));

		foreach ($this->items as $u)
		{
			if ($u->pkey !== null && ($u->pkey === $key || $this->selected === null))
			{
				$this->selected = $u;

				if ($u->pkey === $key)
				{
					break;
				}
			}
		}

		$this->canModerate = $app->getIdentity()->authorise('mifoto.moderate', 'com_mifoto');

		ToolbarHelper::title(Text::_('COM_MIFOTO'), 'image');

		parent::display($tpl);
	}
}
