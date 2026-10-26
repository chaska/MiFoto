<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Plugin\User\Mifoto\Field;

defined('_JEXEC') || die;

use Chaska\Component\Mifoto\Administrator\Service\Placeholder;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

/**
 * The photo widget. It does not submit anything with the profile form (no <input name>): the buttons talk to com_mifoto by
 * themselves. Who the photo belongs to is never printed in a field or sent back: the server uses the logged-in session.
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
		$app  = Factory::getApplication();
		$user = $app->getIdentity();

		if ($user === null || $user->guest)
		{
			return '';
		}

		$service  = $app->bootComponent('com_mifoto')->getPhotoService();
		$settings = $service->settings();
		$photo    = $service->getPhoto((int) $user->id, 128, $user);
		$canUp    = (bool) $user->authorise('mifoto.upload', 'com_mifoto');
		$classes  = $settings->cssClasses();
		$shape    = (string) preg_replace('/\s+mifoto-(frame|shadow)-\S+/', '', $classes);
		$maxMb    = (int) round($settings->maxBytes() / 1048576);

		$wa = $app->getDocument()->getWebAssetManager();
		$wa->getRegistry()->addExtensionRegistryFile('com_mifoto');
		$wa->getRegistry()->addExtensionRegistryFile('plg_user_mifoto');
		$wa->useStyle('com_mifoto.avatar')->useStyle('plg_user_mifoto.widget')->useScript('plg_user_mifoto.widget');

		$root = Uri::root(true) . '/index.php?option=com_mifoto&task=photo.';

		$texts = [];

		foreach (['CHOOSE', 'CHANGE', 'REMOVE', 'REMOVE_CONFIRM', 'UPLOADING', 'UPLOADED', 'REMOVED', 'TOO_BIG', 'BAD_TYPE', 'CANT_READ', 'GENERIC_ERROR', 'CROP_TITLE', 'CROP_HELP', 'CROP_ZOOM', 'CROP_SAVE', 'CROP_WHOLE', 'CROP_CANCEL'] as $key)
		{
			$texts[$key] = Text::_('PLG_USER_MIFOTO_JS_' . $key);
		}

		$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

		$initials = Placeholder::initials((string) $user->name);
		$color    = Placeholder::color((string) $user->username);

		if ($photo !== null)
		{
			$avatar = '<img class="' . $e($classes) . ' mifoto-widget__avatar" src="' . $e($photo->url) . '" width="96" height="96" alt="" decoding="async">';
		}
		else
		{
			$avatar = '<span class="' . $e($classes) . ' mifoto-widget__avatar" style="background:' . $e($color) . '" aria-hidden="true">' . $e($initials) . '</span>';
		}

		$help = $canUp ? Text::sprintf('PLG_USER_MIFOTO_HELP', $maxMb) : Text::_('PLG_USER_MIFOTO_NO_PERMISSION');

		$h  = '<div class="mifoto-widget" data-upload-url="' . $e($root . 'upload') . '" data-remove-url="' . $e($root . 'remove') . '"'
			. ' data-token="' . $e(Session::getFormToken()) . '" data-crop="' . ($settings->cropEnabled() ? '1' : '0') . '"'
			. ' data-can-upload="' . ($canUp ? '1' : '0') . '" data-max-bytes="' . (int) $settings->maxBytes() . '"'
			. ' data-classes="' . $e($classes) . '" data-initials="' . $e($initials) . '" data-color="' . $e($color) . '"'
			. ' data-text="' . $e((string) json_encode($texts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)) . '">';
		$h .= '<div class="mifoto-widget__preview" data-role="avatar">' . $avatar . '</div>';
		$h .= '<div class="mifoto-widget__body">';
		$h .= '<p class="mifoto-widget__title">' . $e(Text::_('PLG_USER_MIFOTO_CURRENT')) . '</p>';
		$h .= '<p class="mifoto-widget__help">' . $e($help) . '</p>';
		$h .= '<div class="mifoto-widget__actions">';

		if ($canUp)
		{
			$h .= '<button type="button" class="btn btn-primary" data-role="choose">' . $e(Text::_($photo !== null ? 'PLG_USER_MIFOTO_JS_CHANGE' : 'PLG_USER_MIFOTO_JS_CHOOSE')) . '</button>';
			$h .= '<input type="file" class="mifoto-widget__file" data-role="file" accept="image/jpeg,image/png,image/webp" tabindex="-1" aria-hidden="true">';
		}

		$h .= '<button type="button" class="btn btn-outline-danger" data-role="remove"' . ($photo !== null ? '' : ' hidden') . '>' . $e(Text::_('PLG_USER_MIFOTO_JS_REMOVE')) . '</button>';
		$h .= '</div>';
		$h .= '<p class="mifoto-widget__status" data-role="status" role="status" aria-live="polite"></p>';
		$h .= '</div>';

		$h .= '<dialog class="mifoto-crop" data-role="dialog" aria-labelledby="mifoto-crop-title">';
		$h .= '<h3 class="mifoto-crop__title" id="mifoto-crop-title">' . $e(Text::_('PLG_USER_MIFOTO_JS_CROP_TITLE')) . '</h3>';
		$h .= '<p class="mifoto-crop__help">' . $e(Text::_('PLG_USER_MIFOTO_JS_CROP_HELP')) . '</p>';
		$h .= '<div class="mifoto-crop__viewport ' . $e($shape) . '" data-role="viewport" tabindex="0"><img data-role="image" alt=""></div>';
		$h .= '<label class="mifoto-crop__zoom"><span>' . $e(Text::_('PLG_USER_MIFOTO_JS_CROP_ZOOM')) . '</span><input type="range" min="1" max="4" step="0.01" value="1" data-role="zoom"></label>';
		$h .= '<div class="mifoto-crop__actions">';
		$h .= '<button type="button" class="btn btn-primary" data-role="crop-save">' . $e(Text::_('PLG_USER_MIFOTO_JS_CROP_SAVE')) . '</button>';
		$h .= '<button type="button" class="btn btn-secondary" data-role="crop-whole">' . $e(Text::_('PLG_USER_MIFOTO_JS_CROP_WHOLE')) . '</button>';
		$h .= '<button type="button" class="btn btn-link" data-role="crop-cancel">' . $e(Text::_('PLG_USER_MIFOTO_JS_CROP_CANCEL')) . '</button>';
		$h .= '</div></dialog>';
		$h .= '</div>';

		return $h;
	}
}
