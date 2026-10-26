<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mifoto\Administrator\Extension;

defined('_JEXEC') || die;

use Chaska\Component\Mifoto\Administrator\Service\PhotoService;
use Chaska\Component\Mifoto\Administrator\Service\Settings;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Joomla\Database\DatabaseInterface;

/**
 * Any extension reaches MiFoto through this object:
 *
 *   Factory::getApplication()->bootComponent('com_mifoto')->getPhotoService();
 */
final class MifotoComponent extends MVCComponent
{
	use HTMLRegistryAwareTrait;

	private ?PhotoService $photoService = null;

	public function getPhotoService(): PhotoService
	{
		return $this->photoService ??= new PhotoService(Factory::getContainer()->get(DatabaseInterface::class), Settings::load());
	}
}
