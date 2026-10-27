<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mifoto\Administrator\Helper;

defined('_JEXEC') || die;

use Chaska\Component\Mifoto\Administrator\Service\AccessPolicy;
use Chaska\Component\Mifoto\Administrator\Service\Segments;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * The frame every administrator screen of MiFoto shares: the album cover (title, one sentence, a strip with one block per user)
 * and the four tabs, plus the sticker and the empty slot that Panel and Photos draw. Everything printed is escaped here.
 */
final class AdminShell
{
	/** Drawn icons (24 px grid, 2 px stroke). */
	private const ICONS = [
		'album'  => '<path d="M6 3h11a3 3 0 013 3v15H9a3 3 0 01-3-3z"/><path d="M6 18a3 3 0 013-3h11"/><circle cx="13" cy="9" r="2.3"/>',
		'image'  => '<rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="9" cy="10" r="2"/><path d="M4 18l5-5 4 4 3-3 4 4"/>',
		'cog'    => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9L7 7M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1"/>',
		'key'    => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M16 7l3 3"/>',
		'check'  => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
		'x'      => '<path d="M6 6l12 12M18 6L6 18"/>',
		'info'   => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
		'eye'    => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>',
		'eyeoff' => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/><path d="M4 4l16 16"/>',
		'trash'  => '<path d="M4 7h16M9 7V4h6v3M6.5 7l1 13h9l1-13M10 11v6M14 11v6"/>',
		'user'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/>',
		'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/>',
		'search' => '<circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/>',
		'left'   => '<path d="M15 5l-7 7 7 7"/>',
		'right'  => '<path d="M9 5l7 7-7 7"/>',
		'lock'   => '<rect x="5" y="11" width="14" height="10" rx="2.5"/><path d="M8 11V8a4 4 0 018 0v3"/>',
	];

	public static function icon(string $name): string
	{
		return '<svg class="mfa-i" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . (self::ICONS[$name] ?? '') . '</svg>';
	}

	public static function e(string $s): string
	{
		return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	public static function url(string $query): string
	{
		return self::e(Route::_('index.php?option=com_mifoto' . $query, false));
	}

	/** Address of a photo for the administrator (same checked delivery as the site, with the administrator's session). */
	public static function photoUrl(string $pkey, int $size): string
	{
		return Uri::base(true) . '/index.php?option=com_mifoto&task=photos.image&pkey=' . rawurlencode($pkey) . '&size=' . $size;
	}

	/** Number of reports nobody has dealt with yet; 0 if it cannot be read (the tables do not exist yet, say). */
	public static function openReports(): int
	{
		try
		{
			return Factory::getApplication()->bootComponent('com_mifoto')->getPhotoService()->reports()->countOpen();
		}
		catch (\Throwable $e)
		{
			return 0;
		}
	}

	/**
	 * @param   string                                                                       $active  panel|photos|reports|settings|permissions
	 * @param   array{users: int, visible: int, hidden: int, with: int, without: int}        $totals
	 */
	public static function open(string $active, array $totals): string
	{
		$app  = Factory::getApplication();
		$user = $app->getIdentity();
		$wa   = $app->getDocument()->getWebAssetManager();

		$wa->getRegistry()->addExtensionRegistryFile('com_mifoto');
		$wa->useStyle('com_mifoto.admin')->useScript('com_mifoto.admin');

		$tabs = [
			'panel'       => ['COM_MIFOTO_MENU_PANEL', 'album', ''],
			'photos'      => ['COM_MIFOTO_MENU_PHOTOS', 'image', (string) $totals['with']],
			'permissions' => ['COM_MIFOTO_MENU_PERMISSIONS', 'key', ''],
		];

		// Reports are for moderators; the tab carries the number of open ones so a new report is noticed.
		if ($user->authorise('mifoto.moderate', 'com_mifoto'))
		{
			$open = self::openReports();
			$tabs = array_slice($tabs, 0, 2, true) + ['reports' => ['COM_MIFOTO_MENU_REPORTS', 'shield', $open > 0 ? (string) $open : '']] + array_slice($tabs, 2, null, true);
		}

		if ($user->authorise('core.admin', 'com_mifoto') || $user->authorise('core.options', 'com_mifoto'))
		{
			$tabs = array_slice($tabs, 0, 2, true) + ['settings' => ['COM_MIFOTO_MENU_SETTINGS', 'cog', '']] + array_slice($tabs, 2, null, true);
		}

		$blocks = Segments::build($totals['users'], $totals['visible'], $totals['hidden'], 50);
		$bar    = '';

		foreach ($blocks as $b)
		{
			$bar .= '<i' . ($b !== '' ? ' class="' . $b . '"' : '') . '></i>';
		}

		$sentence = $totals['users'] > 0
			? Text::sprintf('COM_MIFOTO_A_SENTENCE', '<em>' . (int) $totals['with'] . '</em>', (int) $totals['users'])
			: self::e(Text::_('COM_MIFOTO_A_SENTENCE_EMPTY'));

		$h  = '<div class="mfa" data-mfa>';
		$h .= '<header class="mfa-cover">';
		$h .= '<div class="mfa-top">';
		$h .= '<div class="mfa-brand"><span class="mfa-mark">' . self::icon('album') . '</span><div><b>MiFoto</b><span>' . self::e(Text::_('COM_MIFOTO_A_SUB')) . '</span></div></div>';

		if ($active !== 'photos' && $user->authorise('mifoto.moderate', 'com_mifoto'))
		{
			$h .= '<a class="mfa-btn mfa-btn-gold" href="' . self::url('&view=photos') . '">' . self::icon('image') . self::e(Text::_('COM_MIFOTO_A_BTN_MODERATE')) . '</a>';
		}

		$h .= '</div>';
		$h .= '<h1 class="mfa-sentence">' . $sentence . '</h1>';
		$h .= '<div class="mfa-bar" role="img" aria-label="' . self::e(Text::sprintf('COM_MIFOTO_A_BAR_LABEL', $totals['with'], $totals['users'], $totals['hidden'], $totals['without'])) . '">' . $bar . '</div>';
		$h .= '<div class="mfa-legend"><span><i class="on"></i>' . self::e(Text::sprintf('COM_MIFOTO_A_LEG_VISIBLE', $totals['visible'])) . '</span>'
			. '<span><i class="hid"></i>' . self::e(Text::sprintf('COM_MIFOTO_A_LEG_HIDDEN', $totals['hidden'])) . '</span>'
			. '<span><i></i>' . self::e(Text::sprintf('COM_MIFOTO_A_LEG_MISSING', $totals['without'])) . '</span></div>';
		$h .= '<nav class="mfa-tabs" aria-label="' . self::e(Text::_('COM_MIFOTO_A_TABS')) . '">';

		foreach ($tabs as $key => [$label, $icon, $count])
		{
			$h .= '<a class="mfa-tab" href="' . self::url('&view=' . $key) . '"' . ($key === $active ? ' aria-current="page"' : '') . '>' . self::icon($icon) . self::e(Text::_($label))
				. ($count !== '' ? '<span class="mfa-count">' . self::e($count) . '</span>' : '') . '</a>';
		}

		$h .= '</nav></header><div class="mfa-page">';

		return $h;
	}

	public static function close(): string
	{
		return '</div></div>';
	}

	/** A pasted sticker: the photo, its number (the user id), the name. $href makes it a link. */
	public static function sticker(\stdClass $u, string $href, bool $selected = false, string $when = ''): string
	{
		$hidden = (int) $u->state === AccessPolicy::HIDDEN;
		$name   = (string) ($u->name !== '' ? $u->name : $u->username);
		$tag    = $hidden ? '<span class="mfa-tag">' . self::icon('eyeoff') . self::e(Text::_('COM_MIFOTO_A_HIDDEN_TAG')) . '</span>' : '';

		return '<a class="mfa-sticker' . ($hidden ? ' is-hidden' : '') . '" href="' . $href . '" style="--r:' . ((((int) $u->id * 37) % 7 - 3) * 0.35) . 'deg"'
			. ($selected ? ' aria-current="true"' : '') . ' aria-label="' . self::e(Text::sprintf('COM_MIFOTO_A_STICKER_LABEL', (int) $u->id, $name) . ($hidden ? ', ' . Text::_('COM_MIFOTO_A_HIDDEN_TAG') : '')) . '">'
			. '<span class="mfa-pic"><img src="' . self::e(self::photoUrl((string) $u->pkey, 128)) . '" width="128" height="128" alt="" decoding="async"></span>'
			. '<span class="mfa-num">' . str_pad((string) (int) $u->id, 2, '0', STR_PAD_LEFT) . '</span>' . $tag
			. '<span class="mfa-name">' . self::e($name) . '</span>'
			. ($when !== '' ? '<span class="mfa-when">' . self::e($when) . '</span>' : '') . '</a>';
	}

	/** The dotted hole of a user who has no photo yet. */
	public static function slot(\stdClass $u): string
	{
		$name = (string) ($u->name !== '' ? $u->name : $u->username);

		return '<div class="mfa-slot" role="img" aria-label="' . self::e(Text::sprintf('COM_MIFOTO_A_SLOT_LABEL', (int) $u->id, $name)) . '">'
			. '<span class="mfa-num">' . str_pad((string) (int) $u->id, 2, '0', STR_PAD_LEFT) . '</span>'
			. '<span class="mfa-hole">' . self::icon('user') . '</span><span class="mfa-name">' . self::e($name) . '</span></div>';
	}

	/**
	 * One cell of the permissions table. With $edit (group, key, title, label) and a state that can be changed, it is a button;
	 * otherwise it is only the mark plus a text for screen readers. The script draws the same cells again after each click.
	 *
	 * @param   array{group: int, key: string, title: string, label: string}|null  $edit
	 */
	public static function cell(string $state, ?array $edit = null): string
	{
		$names = ['own' => 'S_OWN', 'inh' => 'S_INH', '' => 'S_NONE', 'fixed' => 'S_FIXED', 'deny' => 'S_DENY', 'deny-inh' => 'S_DENY_INH'];
		$text  = Text::_('COM_MIFOTO_A_' . ($names[$state] ?? 'S_NONE'));

		switch ($state)
		{
			case 'own':
				$mark = '<span class="mfa-yes">' . self::icon('check') . '</span>';
				break;
			case 'inh':
				$mark = '<span class="mfa-yes is-inh">' . self::icon('check') . '</span>';
				break;
			case 'fixed':
				$mark = '<span class="mfa-yes is-lock">' . self::icon('check') . '</span>';
				break;
			case 'deny':
			case 'deny-inh':
				$mark = '<span class="mfa-no is-deny">' . self::icon('x') . '</span>';
				break;
			default:
				$mark = '<span class="mfa-no" aria-hidden="true">—</span>';
		}

		if ($edit === null || \in_array($state, ['fixed', 'deny', 'deny-inh'], true))
		{
			return $mark . '<span class="mfa-sr">' . self::e($text) . '</span>';
		}

		$press = Text::_($state === 'own' ? 'COM_MIFOTO_A_PRESS_REMOVE' : 'COM_MIFOTO_A_PRESS_ADD');

		return '<button type="button" class="mfa-cell" data-g="' . (int) $edit['group'] . '" data-k="' . self::e($edit['key']) . '" aria-label="'
			. self::e($edit['title'] . ', ' . $edit['label'] . ': ' . $text . '. ' . $press) . '">' . $mark . '</button>';
	}

	/** "hace 5 h", "ayer"... from a database date (UTC). */
	public static function ago(?string $utc, ?int $now = null): string
	{
		$t = $utc !== null && $utc !== '' ? strtotime($utc . ' UTC') : false;

		if ($t === false)
		{
			return '';
		}

		$d = max(0, ($now ?? time()) - $t);

		if ($d < 3600)
		{
			return Text::sprintf('COM_MIFOTO_A_AGO_MIN', max(1, intdiv($d, 60)));
		}

		if ($d < 86400)
		{
			return Text::sprintf('COM_MIFOTO_A_AGO_H', intdiv($d, 3600));
		}

		if ($d < 172800)
		{
			return Text::_('COM_MIFOTO_A_AGO_YESTERDAY');
		}

		return $d < 60 * 86400 ? Text::sprintf('COM_MIFOTO_A_AGO_DAYS', intdiv($d, 86400)) : gmdate('d/m/Y', $t);
	}
}
