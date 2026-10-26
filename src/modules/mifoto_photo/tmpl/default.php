<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

defined('_JEXEC') || die;

/** @var  string  $html  Ready-made, already escaped HTML from com_mifoto (empty for a guest). */

if (!empty($html))
{
	echo '<div class="mod-mifoto-photo">' . $html . '</div>';
}
