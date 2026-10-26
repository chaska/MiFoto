<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Plugin\User\Mifoto\Field;

defined('_JEXEC') || die;

use Chaska\Component\Mifoto\Administrator\Service\PhotoWidget;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;

/**
 * The photo widget inside the profile form. The widget itself (HTML, script, styles, texts) belongs to com_mifoto and is shared
 * with the "MiFoto - photo" module; this field only places it in the form. Nothing is submitted with the profile form.
 */
final class MifotophotoField extends FormField
{
	protected $type = 'Mifotophoto';

	/** The widget has its own heading. */
	protected function getLabel()
	{
		return '';
	}

	public function renderField($options = [])
	{
		$options['hiddenLabel'] = true;

		return parent::renderField($options);
	}

	protected function getInput()
	{
		$app = Factory::getApplication();

		return PhotoWidget::html($app->bootComponent('com_mifoto')->getPhotoService(), $app->getIdentity());
	}
}
