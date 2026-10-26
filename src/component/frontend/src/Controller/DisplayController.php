<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mifoto\Site\Controller;

defined('_JEXEC') || die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Exception\RouteNotFoundException;

/** The site side has no page of its own: photos come out through the "photo" controller and the profile form. */
final class DisplayController extends BaseController
{
	public function display($cachable = false, $urlparams = [])
	{
		throw new RouteNotFoundException('Not found', 404);
	}
}
