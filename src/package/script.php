<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

defined('_JEXEC') || die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

/**
 * Joomla installs every new plugin switched OFF, and people then wonder why the photo block does not show. This script switches
 * the three MiFoto plugins ON, but only the first time:
 *  - on a fresh install, and
 *  - once when upgrading from a version older than 0.3.0 (the first one with this script).
 * Any later update leaves the plugins exactly as the administrator set them. Options, permissions and photos are never touched.
 * Nothing here can stop the installation: every problem is swallowed and the plugins can still be switched on by hand.
 */
return new class () implements InstallerScriptInterface {
	private const FIRST_VERSION_WITH_SCRIPT = '0.3.0';

	/** The version that was installed before this one ('' on a fresh install). */
	private string $previous = '';

	public function install(InstallerAdapter $adapter): bool
	{
		return true;
	}

	public function update(InstallerAdapter $adapter): bool
	{
		return true;
	}

	public function uninstall(InstallerAdapter $adapter): bool
	{
		return true;
	}

	public function preflight(string $type, InstallerAdapter $adapter): bool
	{
		try
		{
			$db    = Factory::getContainer()->get(DatabaseInterface::class);
			$query = $db->getQuery(true)
				->select($db->quoteName('manifest_cache'))
				->from($db->quoteName('#__extensions'))
				->where($db->quoteName('type') . ' = ' . $db->quote('package'))
				->where($db->quoteName('element') . ' = ' . $db->quote('pkg_mifoto'));
			$cache = json_decode((string) $db->setQuery($query)->loadResult(), true);

			$this->previous = \is_array($cache) && isset($cache['version']) ? (string) $cache['version'] : '';
		}
		catch (\Throwable $e)
		{
			$this->previous = '';
		}

		return true;
	}

	public function postflight(string $type, InstallerAdapter $adapter): bool
	{
		$firstTime = $type === 'install'
			|| ($type === 'update' && $this->previous !== '' && version_compare($this->previous, self::FIRST_VERSION_WITH_SCRIPT, '<'));

		if (!$firstTime)
		{
			return true;
		}

		try
		{
			$db    = Factory::getContainer()->get(DatabaseInterface::class);
			$query = $db->getQuery(true)
				->update($db->quoteName('#__extensions'))
				->set($db->quoteName('enabled') . ' = 1')
				->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
				->where($db->quoteName('element') . ' = ' . $db->quote('mifoto'))
				->whereIn($db->quoteName('folder'), ['user', 'content', 'engage'], ParameterType::STRING);
			$db->setQuery($query)->execute();
		}
		catch (\Throwable $e)
		{
			// Not worth failing the installation: the plugins can be switched on in Extensions > Plugins.
		}

		return true;
	}
};
