<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Plugin\User\Mifoto\Field;

defined('_JEXEC') || die;

use Chaska\Component\Mifoto\Administrator\Service\AccessPolicy;
use Chaska\Component\Mifoto\Administrator\Service\AvatarRenderer;
use Chaska\Component\Mifoto\Administrator\Service\PhotoData;
use Chaska\Component\Mifoto\Administrator\Service\Placeholder;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

/**
 * Moderation widget for the user edit page in the administrator: shows the person's photo and lets a moderator hide, show or
 * remove it without leaving the page. A moderator cannot upload a photo FOR somebody else (only the owner can), which is why
 * there is no upload button here. Nothing is submitted with the user form: the buttons call com_mifoto by themselves.
 */
final class MifotoadminField extends FormField
{
	protected $type = 'Mifotoadmin';

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
		$app   = Factory::getApplication();
		$admin = $app->getIdentity();

		if (!$app->isClient('administrator') || !$admin->authorise('mifoto.moderate', 'com_mifoto'))
		{
			return '';
		}

		$ownerId = (int) $this->form->getValue('id');

		if ($ownerId < 1)
		{
			return '<p class="text-muted">' . htmlspecialchars(Text::_('PLG_USER_MIFOTO_ADMIN_NEW_USER'), ENT_QUOTES, 'UTF-8') . '</p>';
		}

		$service  = $app->bootComponent('com_mifoto')->getPhotoService();
		$row      = $service->photoRow($ownerId);
		$classes  = $service->settings()->cssClasses();
		$name     = (string) $this->form->getValue('name');
		$username = (string) $this->form->getValue('username');
		$hidden   = $row !== null && (int) $row->state === AccessPolicy::HIDDEN;
		$e        = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

		$wa = $app->getDocument()->getWebAssetManager();
		$wa->getRegistry()->addExtensionRegistryFile('com_mifoto');
		$wa->getRegistry()->addExtensionRegistryFile('plg_user_mifoto');
		$wa->useStyle('com_mifoto.avatar')->useScript('plg_user_mifoto.admin');

		$texts = [];

		foreach (['HIDE', 'SHOW', 'REMOVE', 'CONFIRM_REMOVE', 'STATE_VISIBLE', 'STATE_HIDDEN', 'NO_PHOTO', 'DONE_HIDE', 'DONE_SHOW', 'DONE_REMOVE', 'ERROR'] as $key)
		{
			$texts[$key] = Text::_('PLG_USER_MIFOTO_ADMIN_' . $key);
		}

		$base   = Uri::base(true) . '/index.php?option=com_mifoto&task=photos.';
		$thumb  = $row !== null ? new PhotoData($base . 'image&pkey=' . $row->pkey . '&size=64', 64, 64, 64, '') : null;
		$avatar = AvatarRenderer::html($thumb, $classes, 64, Placeholder::initials($name), Placeholder::color($username), $name);

		$h  = '<div class="mifoto-admin" data-token="' . $e(Session::getFormToken()) . '" data-state="' . ($row === null ? 'none' : ($hidden ? 'hidden' : 'visible')) . '"'
			. ' data-classes="' . $e($classes) . '" data-initials="' . $e(Placeholder::initials($name)) . '" data-color="' . $e(Placeholder::color($username)) . '"'
			. ($row !== null ? ' data-hide-url="' . $e($base . 'hide&pkey=' . $row->pkey . '&ajax=1') . '" data-show-url="' . $e($base . 'show&pkey=' . $row->pkey . '&ajax=1') . '" data-remove-url="' . $e($base . 'remove&pkey=' . $row->pkey . '&ajax=1') . '"' : '')
			. ' data-text="' . $e((string) json_encode($texts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)) . '"'
			. ' style="display:flex;gap:1rem;align-items:flex-start;padding:.5rem 0">';
		$h .= '<div data-role="preview">' . $avatar . '</div>';
		$h .= '<div>';
		$h .= '<p class="mb-1"><span class="badge ' . ($row === null ? 'bg-secondary' : ($hidden ? 'bg-warning text-dark' : 'bg-success')) . '" data-role="state">'
			. $e(Text::_($row === null ? 'PLG_USER_MIFOTO_ADMIN_NO_PHOTO' : ($hidden ? 'PLG_USER_MIFOTO_ADMIN_STATE_HIDDEN' : 'PLG_USER_MIFOTO_ADMIN_STATE_VISIBLE'))) . '</span></p>';
		$h .= '<p class="small text-muted mb-2">' . $e(Text::_('PLG_USER_MIFOTO_ADMIN_HELP')) . '</p>';
		$h .= '<div class="d-flex gap-2"' . ($row === null ? ' hidden' : '') . ' data-role="actions">';
		$h .= '<button type="button" class="btn btn-sm btn-outline-secondary" data-role="toggle">' . $e(Text::_($hidden ? 'PLG_USER_MIFOTO_ADMIN_SHOW' : 'PLG_USER_MIFOTO_ADMIN_HIDE')) . '</button>';
		$h .= '<button type="button" class="btn btn-sm btn-outline-danger" data-role="remove">' . $e(Text::_('PLG_USER_MIFOTO_ADMIN_REMOVE')) . '</button>';
		$h .= '</div>';
		$h .= '<p class="small mt-2 mb-0" role="status" aria-live="polite" data-role="status"></p>';
		$h .= '</div></div>';

		return $h;
	}
}
