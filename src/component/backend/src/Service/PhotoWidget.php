<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mifoto\Administrator\Service;

defined('_JEXEC') || die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;

/**
 * The profile photo widget (choose, crop, upload, change, remove) as ready-to-print HTML. It lives here, not in a plugin, so that
 * the user plugin (inside the profile form) and the module (anywhere on the site) draw exactly the same thing. It prints no user id
 * and submits nothing with any form: the buttons talk to com_mifoto by themselves and the server uses the logged-in session.
 */
final class PhotoWidget
{
	private const TEXT_KEYS = ['CHOOSE', 'CHANGE', 'REMOVE', 'REMOVE_CONFIRM', 'UPLOADING', 'UPLOADED', 'REMOVED', 'TOO_BIG', 'BAD_TYPE', 'CANT_READ', 'GENERIC_ERROR', 'CROP_TITLE', 'CROP_HELP', 'CROP_ZOOM', 'CROP_SAVE', 'CROP_WHOLE', 'CROP_CANCEL'];

	/** Empty for a guest. */
	public static function html(PhotoService $service, User $user): string
	{
		if ($user->guest || (int) $user->id < 1)
		{
			return '';
		}

		$app = Factory::getApplication();
		$app->getLanguage()->load('com_mifoto', JPATH_SITE);

		$settings = $service->settings();
		$photo    = $service->getPhoto((int) $user->id, 128, $user);
		$canUp    = (bool) $user->authorise('mifoto.upload', 'com_mifoto');
		$classes  = $settings->cssClasses();
		$shape    = (string) preg_replace('/\s+mifoto-(frame|shadow)-\S+/', '', $classes);
		$maxMb    = (int) round($settings->maxBytes() / 1048576);
		$initials = Placeholder::initials((string) $user->name);
		$color    = Placeholder::color((string) $user->username);
		$e        = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

		$wa = $app->getDocument()->getWebAssetManager();
		$wa->getRegistry()->addExtensionRegistryFile('com_mifoto');
		$wa->useStyle('com_mifoto.avatar')->useStyle('com_mifoto.widget')->useScript('com_mifoto.widget');

		$texts = [];

		foreach (self::TEXT_KEYS as $key)
		{
			$texts[$key] = Text::_('COM_MIFOTO_W_' . $key);
		}

		$root = Uri::root(true) . '/index.php?option=com_mifoto&task=photo.';

		if ($photo !== null)
		{
			$avatar = '<img class="' . $e($classes) . ' mifoto-widget__avatar" src="' . $e($photo->url) . '" width="96" height="96" alt="" decoding="async">';
		}
		else
		{
			$avatar = '<span class="' . $e($classes) . ' mifoto-widget__avatar" style="background:' . $e($color) . '" aria-hidden="true">' . $e($initials) . '</span>';
		}

		$help = $canUp ? Text::sprintf('COM_MIFOTO_W_HELP', $maxMb) : Text::_('COM_MIFOTO_W_NO_PERMISSION');

		$h  = '<div class="mifoto-widget" data-upload-url="' . $e($root . 'upload') . '" data-remove-url="' . $e($root . 'remove') . '"'
			. ' data-token="' . $e(Session::getFormToken()) . '" data-crop="' . ($settings->cropEnabled() ? '1' : '0') . '"'
			. ' data-can-upload="' . ($canUp ? '1' : '0') . '" data-max-bytes="' . (int) $settings->maxBytes() . '"'
			. ' data-classes="' . $e($classes) . '" data-initials="' . $e($initials) . '" data-color="' . $e($color) . '"'
			. ' data-text="' . $e((string) json_encode($texts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)) . '">';
		$h .= '<div class="mifoto-widget__preview" data-role="avatar">' . $avatar . '</div>';
		$h .= '<div class="mifoto-widget__body">';
		$h .= '<p class="mifoto-widget__title">' . $e(Text::_('COM_MIFOTO_W_CURRENT')) . '</p>';
		$h .= '<p class="mifoto-widget__help">' . $e($help) . '</p>';
		$h .= '<div class="mifoto-widget__actions">';

		if ($canUp)
		{
			$h .= '<button type="button" class="btn btn-primary" data-role="choose">' . $e(Text::_($photo !== null ? 'COM_MIFOTO_W_CHANGE' : 'COM_MIFOTO_W_CHOOSE')) . '</button>';
			$h .= '<input type="file" class="mifoto-widget__file" data-role="file" accept="image/jpeg,image/png,image/webp" tabindex="-1" aria-hidden="true">';
		}

		$h .= '<button type="button" class="btn btn-outline-danger" data-role="remove"' . ($photo !== null ? '' : ' hidden') . '>' . $e(Text::_('COM_MIFOTO_W_REMOVE')) . '</button>';
		$h .= '</div>';
		$h .= '<p class="mifoto-widget__status" data-role="status" role="status" aria-live="polite"></p>';
		$h .= '</div>';

		$h .= '<dialog class="mifoto-crop" data-role="dialog" aria-labelledby="mifoto-crop-title">';
		$h .= '<h3 class="mifoto-crop__title" id="mifoto-crop-title">' . $e(Text::_('COM_MIFOTO_W_CROP_TITLE')) . '</h3>';
		$h .= '<p class="mifoto-crop__help">' . $e(Text::_('COM_MIFOTO_W_CROP_HELP')) . '</p>';
		$h .= '<div class="mifoto-crop__viewport ' . $e($shape) . '" data-role="viewport" tabindex="0"><img data-role="image" alt=""></div>';
		$h .= '<label class="mifoto-crop__zoom"><span>' . $e(Text::_('COM_MIFOTO_W_CROP_ZOOM')) . '</span><input type="range" min="1" max="4" step="0.01" value="1" data-role="zoom"></label>';
		$h .= '<div class="mifoto-crop__actions">';
		$h .= '<button type="button" class="btn btn-primary" data-role="crop-save">' . $e(Text::_('COM_MIFOTO_W_CROP_SAVE')) . '</button>';
		$h .= '<button type="button" class="btn btn-secondary" data-role="crop-whole">' . $e(Text::_('COM_MIFOTO_W_CROP_WHOLE')) . '</button>';
		$h .= '<button type="button" class="btn btn-link" data-role="crop-cancel">' . $e(Text::_('COM_MIFOTO_W_CROP_CANCEL')) . '</button>';
		$h .= '</div></dialog>';
		$h .= '</div>';

		return $h;
	}
}
