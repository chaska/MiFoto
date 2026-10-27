<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mifoto\Administrator\Service;

defined('_JEXEC') || die;

use Chaska\Component\Mifoto\Administrator\Security\ImageProcessor;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * "Is everything in order?" in plain words: each check says what is fine or exactly what is missing. Read-only; it never
 * changes anything. Status: ok, warn (counts as a warning), info (worth knowing, not a problem).
 */
final class Health
{
	/** @return array{items: array<int, array{status: string, title: string, detail: string, url: string, action: string}>, warnings: int} */
	public static function run(DatabaseInterface $db, PhotoService $service): array
	{
		$items = [];

		// 1. plugins
		$enabled = [];

		try
		{
			$query = $db->getQuery(true)
				->select($db->quoteName(['folder', 'enabled']))
				->from($db->quoteName('#__extensions'))
				->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
				->where($db->quoteName('element') . ' = ' . $db->quote('mifoto'))
				->whereIn($db->quoteName('folder'), ['user', 'content', 'engage'], ParameterType::STRING);

			foreach ((array) $db->setQuery($query)->loadAssocList() as $row)
			{
				$enabled[(string) $row['folder']] = (int) $row['enabled'] === 1;
			}
		}
		catch (\Throwable $e)
		{
			$enabled = [];
		}

		$count = \count(array_filter($enabled));
		$items[] = empty($enabled['user'])
			? self::item('warn', 'COM_MIFOTO_A_H_PLUGINS', 'COM_MIFOTO_A_H_PLUGINS_BAD', 'index.php?option=com_plugins&view=plugins&filter[search]=mifoto', 'COM_MIFOTO_A_H_FIX')
			: self::item('ok', 'COM_MIFOTO_A_H_PLUGINS', 'COM_MIFOTO_A_H_PLUGINS_D', '', '', $count . ' / 3');

		// 1b. can visitors see the photos in Engage comments? (the plugin must be on AND open to Public)
		$engage = self::engage($db);

		if ($engage !== null)
		{
			$items[] = $engage;
		}

		// 2. can the server make WebP?
		$items[] = ImageProcessor::isSupported()
			? self::item('ok', 'COM_MIFOTO_A_H_WEBP', 'COM_MIFOTO_A_H_WEBP_D', '', '', Text::_('COM_MIFOTO_A_H_YES'))
			: self::item('warn', 'COM_MIFOTO_A_H_WEBP', 'COM_MIFOTO_A_H_WEBP_BAD');

		// 3. is the folder closed to the outside?
		$root = $service->settings()->storage()->root();

		if (!is_dir($root))
		{
			$items[] = self::item('info', 'COM_MIFOTO_A_H_FOLDER', 'COM_MIFOTO_A_H_FOLDER_NEW');
		}
		elseif (stripos((string) ($_SERVER['SERVER_SOFTWARE'] ?? ''), 'nginx') !== false)
		{
			// nginx ignores .htaccess: the files could be fetched directly unless the server configuration blocks the folder.
			$items[] = self::item('warn', 'COM_MIFOTO_A_H_FOLDER', 'COM_MIFOTO_A_H_FOLDER_NGINX');
		}
		elseif (is_file($root . '/.htaccess') || is_file($root . '/web.config'))
		{
			$items[] = self::item('ok', 'COM_MIFOTO_A_H_FOLDER', '', '', '', Text::_('COM_MIFOTO_A_H_YES'), Text::sprintf('COM_MIFOTO_A_H_FOLDER_D', $service->settings()->path()));
		}
		else
		{
			$items[] = self::item('warn', 'COM_MIFOTO_A_H_FOLDER', 'COM_MIFOTO_A_H_FOLDER_BAD');
		}

		// 4. can the people who register upload and see photos?
		$group   = (int) ComponentHelper::getParams('com_users')->get('new_usertype', 2);
		$canBoth = Access::checkGroup($group, 'mifoto.upload', 'com_mifoto') && Access::checkGroup($group, 'mifoto.view', 'com_mifoto');
		$items[] = $canBoth
			? self::item('ok', 'COM_MIFOTO_A_H_PERMS', 'COM_MIFOTO_A_H_PERMS_D', '', '', Text::_('COM_MIFOTO_A_H_YES'))
			: self::item('warn', 'COM_MIFOTO_A_H_PERMS', 'COM_MIFOTO_A_H_PERMS_BAD', 'index.php?option=com_mifoto&view=permissions', 'COM_MIFOTO_A_H_FIX');

		// 5. the optional module
		$placed = 0;

		try
		{
			$placed = (int) $db->setQuery(
				$db->getQuery(true)->select('COUNT(*)')->from($db->quoteName('#__modules'))
					->where($db->quoteName('module') . ' = ' . $db->quote('mod_mifoto_photo'))
					->where($db->quoteName('published') . ' = 1')
					->where($db->quoteName('client_id') . ' = 0')
			)->loadResult();
		}
		catch (\Throwable $e)
		{
			$placed = 0;
		}

		$items[] = $placed > 0
			? self::item('ok', 'COM_MIFOTO_A_H_MODULE_OK', 'COM_MIFOTO_A_H_MODULE_OK_D')
			: self::item('info', 'COM_MIFOTO_A_H_MODULE', 'COM_MIFOTO_A_H_MODULE_D', 'index.php?option=com_modules&view=select&client_id=0', 'COM_MIFOTO_A_H_PLACE');

		return ['items' => $items, 'warnings' => \count(array_filter($items, static fn (array $i): bool => $i['status'] === 'warn'))];
	}

	/**
	 * Joomla loads a plugin only for people whose access level matches the plugin's. If "Engage - MiFoto" is off or is not Public,
	 * visitors without a session get no photo in the comments. Null when the plugin is not installed at all.
	 *
	 * @return array{status: string, title: string, detail: string, url: string, action: string}|null
	 */
	public static function engage(DatabaseInterface $db): ?array
	{
		try
		{
			$query = $db->getQuery(true)
				->select($db->quoteName(['enabled', 'access']))
				->from($db->quoteName('#__extensions'))
				->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
				->where($db->quoteName('element') . ' = ' . $db->quote('mifoto'))
				->where($db->quoteName('folder') . ' = ' . $db->quote('engage'));
			$row = $db->setQuery($query)->loadAssoc();
		}
		catch (\Throwable $e)
		{
			return null;
		}

		if (!\is_array($row))
		{
			return null;
		}

		return ((int) $row['enabled'] === 1 && (int) $row['access'] === 1)
			? self::item('ok', 'COM_MIFOTO_A_H_ENGAGE', 'COM_MIFOTO_A_H_ENGAGE_D', '', '', Text::_('COM_MIFOTO_A_H_YES'))
			: self::item('warn', 'COM_MIFOTO_A_H_ENGAGE', 'COM_MIFOTO_A_H_ENGAGE_BAD', 'index.php?option=com_plugins&view=plugins&filter[search]=mifoto', 'COM_MIFOTO_A_H_FIX');
	}

	/** @return array{status: string, title: string, detail: string, url: string, action: string} */
	private static function item(string $status, string $title, string $detail, string $url = '', string $action = '', string $value = '', string $detailText = ''): array
	{
		return [
			'status' => $status,
			'title'  => Text::_($title),
			'detail' => $detailText !== '' ? $detailText : ($detail !== '' ? Text::_($detail) : ''),
			'url'    => $url,
			'action' => $action !== '' ? Text::_($action) : $value,
		];
	}
}
