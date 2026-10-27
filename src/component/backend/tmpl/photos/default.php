<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

defined('_JEXEC') || die;

use Chaska\Component\Mifoto\Administrator\Helper\AdminShell as S;
use Chaska\Component\Mifoto\Administrator\Service\AccessPolicy;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** @var  \Chaska\Component\Mifoto\Administrator\View\Photos\HtmlView $this */

$t  = static fn (string $key): string => S::e(Text::_($key));
$tt = $this->totals;
$q  = $this->search !== '' ? '&q=' . rawurlencode($this->search) : '';
$u  = $this->selected;

$chips = [
	'con'      => ['COM_MIFOTO_A_F_ALL', $tt['with']],
	'visibles' => ['COM_MIFOTO_A_F_VISIBLE', $tt['visible']],
	'ocultas'  => ['COM_MIFOTO_A_F_HIDDEN', $tt['hidden']],
	'sin'      => ['COM_MIFOTO_A_F_WITHOUT', $tt['without']],
];

echo S::open('photos', $tt);
?>
<div class="mfa-cols">
	<div class="mfa-col">
		<section>
			<h2><?php echo $t('COM_MIFOTO_A_PHOTOS_TITLE'); ?></h2>

			<form class="mfa-search" action="<?php echo S::url('&view=photos'); ?>" method="get" role="search">
				<input type="hidden" name="option" value="com_mifoto">
				<input type="hidden" name="view" value="photos">
				<input type="hidden" name="filter" value="<?php echo S::e($this->filter); ?>">
				<label><?php echo S::icon('search'); ?><span class="mfa-sr"><?php echo $t('COM_MIFOTO_PHOTOS_SEARCH'); ?></span>
					<input type="search" name="q" value="<?php echo S::e($this->search); ?>" placeholder="<?php echo $t('COM_MIFOTO_PHOTOS_SEARCH'); ?>"></label>
			</form>

			<nav class="mfa-chips" aria-label="<?php echo $t('COM_MIFOTO_A_FILTERS'); ?>">
				<?php foreach ($chips as $key => [$label, $count]) : ?>
					<a class="mfa-chip" href="<?php echo S::url('&view=photos&filter=' . $key . $q); ?>"<?php echo $this->filter === $key ? ' aria-current="true"' : ''; ?>><?php echo $t($label); ?> <b><?php echo (int) $count; ?></b></a>
				<?php endforeach; ?>
			</nav>

			<?php if ($this->restricted) : ?>
				<p class="mfa-empty"><?php echo $t('COM_MIFOTO_A_RESTRICTED'); ?></p>
			<?php elseif (!$this->items) : ?>
				<p class="mfa-empty"><?php echo $t($this->search !== '' || $this->filter !== 'con' ? 'COM_MIFOTO_A_EMPTY_FILTER' : 'COM_MIFOTO_PHOTOS_EMPTY'); ?></p>
			<?php else : ?>
				<div class="mfa-album is-big">
					<?php foreach ($this->items as $row) : ?>
						<?php echo $row->pkey !== null
							? S::sticker($row, S::url('&view=photos&filter=' . $this->filter . $q . '&pagina=' . $this->page . '&pkey=' . $row->pkey), $u !== null && $u->pkey === $row->pkey, S::ago($row->modified))
							: S::slot($row); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ($this->pages > 1) : ?>
				<nav class="mfa-pager is-end" aria-label="<?php echo $t('COM_MIFOTO_A_PAGER'); ?>">
					<?php if ($this->page > 1) : ?>
						<a href="<?php echo S::url('&view=photos&filter=' . $this->filter . $q . '&pagina=' . ($this->page - 1)); ?>" aria-label="<?php echo $t('COM_MIFOTO_A_PREV'); ?>"><?php echo S::icon('left'); ?></a>
					<?php else : ?>
						<span aria-hidden="true"><?php echo S::icon('left'); ?></span>
					<?php endif; ?>
					<?php echo S::e(Text::sprintf('COM_MIFOTO_A_PAGER_OF', $this->page, $this->pages)); ?>
					<?php if ($this->page < $this->pages) : ?>
						<a href="<?php echo S::url('&view=photos&filter=' . $this->filter . $q . '&pagina=' . ($this->page + 1)); ?>" aria-label="<?php echo $t('COM_MIFOTO_A_NEXT'); ?>"><?php echo S::icon('right'); ?></a>
					<?php else : ?>
						<span aria-hidden="true"><?php echo S::icon('right'); ?></span>
					<?php endif; ?>
				</nav>
			<?php endif; ?>
		</section>
	</div>

	<aside class="mfa-col mfa-viewer" aria-label="<?php echo $t('COM_MIFOTO_A_V_ASIDE'); ?>">
		<section class="mfa-box">
			<?php if ($u === null) : ?>
				<p class="mfa-empty"><?php echo $t($this->filter === 'sin' ? 'COM_MIFOTO_A_V_NOPHOTO' : 'COM_MIFOTO_A_V_NONE'); ?></p>
			<?php else : ?>
				<?php
				$hidden = (int) $u->state === AccessPolicy::HIDDEN;
				$name   = (string) ($u->name !== '' ? $u->name : $u->username);
				$back   = '&view=photos&filter=' . $this->filter . $q . '&pagina=' . $this->page . '&pkey=' . $u->pkey;
				?>
				<div class="mfa-big">
					<div class="mfa-photo"><img src="<?php echo S::e(S::photoUrl((string) $u->pkey, 300)); ?>" alt="<?php echo S::e($name); ?>" width="220" height="220"></div>
					<?php if ($this->canModerate) : ?>
						<div class="mfa-rail">
							<form action="<?php echo S::url('&task=photos.' . ($hidden ? 'show' : 'hide') . '&pkey=' . $u->pkey); ?>" method="post">
								<button type="submit" class="mfa-btn mfa-btn-line"><?php echo S::icon($hidden ? 'eye' : 'eyeoff') . $t($hidden ? 'COM_MIFOTO_PHOTOS_ACTION_SHOW' : 'COM_MIFOTO_PHOTOS_ACTION_HIDE'); ?></button>
								<?php echo HTMLHelper::_('form.token'); ?>
							</form>
							<form action="<?php echo S::url('&task=photos.remove&pkey=' . $u->pkey); ?>" method="post"
								data-confirm="<?php echo $t('COM_MIFOTO_PHOTOS_CONFIRM_REMOVE'); ?>">
								<button type="submit" class="mfa-btn mfa-btn-danger"><?php echo S::icon('trash') . $t('COM_MIFOTO_PHOTOS_ACTION_REMOVE'); ?></button>
								<?php echo HTMLHelper::_('form.token'); ?>
							</form>
							<a class="mfa-btn mfa-btn-line" href="<?php echo S::e(\Joomla\CMS\Router\Route::_('index.php?option=com_users&task=user.edit&id=' . (int) $u->id, false)); ?>"><?php echo S::icon('user') . $t('COM_MIFOTO_A_V_USER'); ?></a>
						</div>
					<?php endif; ?>
				</div>
				<h3 class="mfa-vname"><?php echo S::e(Text::sprintf('COM_MIFOTO_A_STICKER_LABEL', (int) $u->id, $name)); ?></h3>
				<dl class="mfa-meta">
					<dt><?php echo $t('COM_MIFOTO_A_M_USER'); ?></dt><dd><?php echo S::e((string) $u->username); ?></dd>
					<dt><?php echo $t('COM_MIFOTO_A_M_UPLOADED'); ?></dt><dd><?php echo S::e(S::ago($u->modified)); ?></dd>
					<dt><?php echo $t('COM_MIFOTO_A_M_WEIGHT'); ?></dt><dd><?php echo (int) round(((int) $u->bytes) / 1024); ?> KB</dd>
					<dt><?php echo $t('COM_MIFOTO_A_M_SIZE'); ?></dt><dd><?php echo (int) $u->width; ?> × <?php echo (int) $u->height; ?></dd>
					<dt><?php echo $t('COM_MIFOTO_A_M_STATE'); ?></dt><dd><?php echo $t($hidden ? 'COM_MIFOTO_PHOTOS_STATE_HIDDEN' : 'COM_MIFOTO_PHOTOS_STATE_VISIBLE'); ?></dd>
				</dl>
			<?php endif; ?>
		</section>
	</aside>
</div>
<?php echo S::close(); ?>
