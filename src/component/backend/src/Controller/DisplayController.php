<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mifoto\Administrator\Controller;

defined('_JEXEC') || die;

use Joomla\CMS\MVC\Controller\BaseController;

/** The component opens on the list of photos. */
final class DisplayController extends BaseController
{
	protected $default_view = 'photos';
}
