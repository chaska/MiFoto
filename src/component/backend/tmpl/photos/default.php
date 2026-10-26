<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

defined('_JEXEC') || die;

use Chaska\Component\Mifoto\Administrator\Service\AccessPolicy;
use Chaska\Component\Mifoto\Administrator\Service\AvatarRenderer;
use Chaska\Component\Mifoto\Administrator\Service\Placeholder;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/** @var  \Chaska\Component\Mifoto\Administrator\View\Photos\HtmlView $this */

$app     = Factory::getApplication();
$wa      = $this->getDocument()->getWebAssetManager();
$classes = $app->bootComponent('com_mifoto')->getPhotoService()->settings()->cssClasses();
$canMod  = $app->getIdentity()->authorise('mifoto.moderate', 'com_mifoto');
$base    = 'index.php?option=com_mifoto';

$wa->getRegistry()->addExtensionRegistryFile('com_mifoto');
$wa->useStyle('com_mifoto.avatar');

$url = static fn (string $query): string => htmlspecialchars(Route::_($base . $query, false), ENT_QUOTES, 'UTF-8');
?>
<form action="<?php echo $url('&view=photos'); ?>" method="post" name="adminForm" id="adminForm">
	<div class="row mb-3">
		<div class="col-md-6">
			<div class="input-group">
				<input type="search" name="filter_search" class="form-control" value="<?php echo $this->escape($this->search); ?>"
					placeholder="<?php echo $this->escape(Text::_('COM_MIFOTO_PHOTOS_SEARCH')); ?>" aria-label="<?php echo $this->escape(Text::_('COM_MIFOTO_PHOTOS_SEARCH')); ?>">
				<button type="submit" class="btn btn-primary"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
				<a class="btn btn-outline-secondary" href="<?php echo $url('&view=photos&filter_search='); ?>"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></a>
			</div>
		</div>
	</div>

	<?php if (!$this->items) : ?>
		<div class="alert alert-info"><?php echo Text::_('COM_MIFOTO_PHOTOS_EMPTY'); ?></div>
	<?php else : ?>
		<table class="table table-striped align-middle">
			<thead>
				<tr>
					<th scope="col" class="w-1"><?php echo Text::_('COM_MIFOTO_PHOTOS_COL_PHOTO'); ?></th>
					<th scope="col"><?php echo Text::_('COM_MIFOTO_PHOTOS_COL_USER'); ?></th>
					<th scope="col"><?php echo Text::_('COM_MIFOTO_PHOTOS_COL_STATE'); ?></th>
					<th scope="col"><?php echo Text::_('COM_MIFOTO_PHOTOS_COL_WEIGHT'); ?></th>
					<th scope="col"><?php echo Text::_('COM_MIFOTO_PHOTOS_COL_DATE'); ?></th>
					<?php if ($canMod) : ?><th scope="col"><span class="visually-hidden"><?php echo Text::_('COM_MIFOTO_PHOTOS_COL_ACTIONS'); ?></span></th><?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($this->items as $item) : ?>
					<?php
					$hidden = (int) $item->state === AccessPolicy::HIDDEN;
					// Uri::base(true) is the "/administrator" path (starts with a slash), which AvatarRenderer requires.
					$thumb  = Uri::base(true) . '/' . $base . '&task=photos.image&pkey=' . $item->pkey . '&size=64';
					$name   = (string) ($item->name ?? '');
					$user   = (string) ($item->username ?? '');
					?>
					<tr>
						<td>
							<?php
							echo AvatarRenderer::html(
								new \Chaska\Component\Mifoto\Administrator\Service\PhotoData($thumb, 64, 64, 64, ''),
								$classes,
								48,
								Placeholder::initials($name),
								Placeholder::color($user),
								$name
							);
							?>
						</td>
						<td>
							<strong><?php echo $this->escape($name !== '' ? $name : '—'); ?></strong><br>
							<span class="small text-muted"><?php echo $this->escape($user); ?></span>
						</td>
						<td>
							<?php if ($hidden) : ?>
								<span class="badge bg-warning text-dark"><?php echo Text::_('COM_MIFOTO_PHOTOS_STATE_HIDDEN'); ?></span>
							<?php else : ?>
								<span class="badge bg-success"><?php echo Text::_('COM_MIFOTO_PHOTOS_STATE_VISIBLE'); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo (int) round(((int) $item->bytes) / 1024); ?> KB</td>
						<td><?php echo HTMLHelper::_('date', $item->modified, Text::_('DATE_FORMAT_LC5')); ?></td>
						<?php if ($canMod) : ?>
							<td class="text-end text-nowrap">
								<button type="submit" class="btn btn-sm btn-outline-secondary"
									formaction="<?php echo $url('&task=photos.' . ($hidden ? 'show' : 'hide') . '&pkey=' . $item->pkey); ?>">
									<?php echo Text::_($hidden ? 'COM_MIFOTO_PHOTOS_ACTION_SHOW' : 'COM_MIFOTO_PHOTOS_ACTION_HIDE'); ?>
								</button>
								<button type="submit" class="btn btn-sm btn-outline-danger"
									formaction="<?php echo $url('&task=photos.remove&pkey=' . $item->pkey); ?>"
									onclick="return confirm(<?php echo htmlspecialchars(json_encode(Text::_('COM_MIFOTO_PHOTOS_CONFIRM_REMOVE')), ENT_QUOTES, 'UTF-8'); ?>);">
									<?php echo Text::_('COM_MIFOTO_PHOTOS_ACTION_REMOVE'); ?>
								</button>
							</td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php echo $this->pagination->getListFooter(); ?>
	<?php endif; ?>

	<input type="hidden" name="task" value="">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
