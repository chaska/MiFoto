<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

defined('_JEXEC') || die;

use Chaska\Component\Mifoto\Administrator\Helper\AdminShell as S;
use Joomla\CMS\Language\Text;

/** @var  \Chaska\Component\Mifoto\Administrator\View\Panel\HtmlView $this */

$t  = static fn (string $key): string => S::e(Text::_($key));
$tt = $this->totals;

$chips = [
	'todos'  => ['COM_MIFOTO_A_F_ALL', $tt['users']],
	'con'    => ['COM_MIFOTO_A_F_WITH', $tt['with']],
	'sin'    => ['COM_MIFOTO_A_F_WITHOUT', $tt['without']],
	'ocultas' => ['COM_MIFOTO_A_F_HIDDEN', $tt['hidden']],
];

echo S::open('panel', $tt);

// Reports waiting for a moderator: said at the top, with the way in.
$pending = $this->restricted ? 0 : S::openReports();

if ($pending > 0) : ?>
	<p class="mfa-msg is-error" role="alert"><?php echo S::e($pending === 1 ? Text::_('COM_MIFOTO_A_R_PENDING_1') : Text::sprintf('COM_MIFOTO_A_R_PENDING', $pending)); ?> <a href="<?php echo S::url('&view=reports'); ?>"><?php echo $t('COM_MIFOTO_A_R_REVIEW'); ?></a></p>
<?php endif;
?>
<div class="mfa-cols">
	<div class="mfa-col">
		<section>
			<div class="mfa-pagehead">
				<div>
					<h2><?php echo S::e(Text::sprintf('COM_MIFOTO_A_ALBUM_PAGE', $this->page)); ?></h2>
					<p class="mfa-sub"><?php echo $t('COM_MIFOTO_A_ALBUM_HELP'); ?></p>
				</div>
				<?php if ($this->pages > 1) : ?>
					<nav class="mfa-pager" aria-label="<?php echo $t('COM_MIFOTO_A_PAGER'); ?>">
						<?php if ($this->page > 1) : ?>
							<a href="<?php echo S::url('&view=panel&filter=' . $this->filter . '&pagina=' . ($this->page - 1)); ?>" aria-label="<?php echo $t('COM_MIFOTO_A_PREV'); ?>"><?php echo S::icon('left'); ?></a>
						<?php else : ?>
							<span aria-hidden="true"><?php echo S::icon('left'); ?></span>
						<?php endif; ?>
						<?php echo S::e(Text::sprintf('COM_MIFOTO_A_PAGER_OF', $this->page, $this->pages)); ?>
						<?php if ($this->page < $this->pages) : ?>
							<a href="<?php echo S::url('&view=panel&filter=' . $this->filter . '&pagina=' . ($this->page + 1)); ?>" aria-label="<?php echo $t('COM_MIFOTO_A_NEXT'); ?>"><?php echo S::icon('right'); ?></a>
						<?php else : ?>
							<span aria-hidden="true"><?php echo S::icon('right'); ?></span>
						<?php endif; ?>
					</nav>
				<?php endif; ?>
			</div>

			<nav class="mfa-chips" aria-label="<?php echo $t('COM_MIFOTO_A_FILTERS'); ?>">
				<?php foreach ($chips as $key => [$label, $count]) : ?>
					<a class="mfa-chip" href="<?php echo S::url('&view=panel&filter=' . $key); ?>"<?php echo $this->filter === $key ? ' aria-current="true"' : ''; ?>><?php echo $t($label); ?> <b><?php echo (int) $count; ?></b></a>
				<?php endforeach; ?>
			</nav>

			<?php if ($this->restricted) : ?>
				<p class="mfa-empty"><?php echo $t('COM_MIFOTO_A_RESTRICTED'); ?></p>
			<?php elseif (!$this->album) : ?>
				<p class="mfa-empty"><?php echo $t('COM_MIFOTO_A_EMPTY_FILTER'); ?></p>
			<?php else : ?>
				<div class="mfa-album">
					<?php foreach ($this->album as $u) : ?>
						<?php echo $u->pkey !== null ? S::sticker($u, S::url('&view=photos&pkey=' . $u->pkey)) : S::slot($u); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>

		<section>
			<h2><?php echo $t('COM_MIFOTO_A_FRESH'); ?></h2>
			<p class="mfa-sub"><?php echo $t('COM_MIFOTO_A_FRESH_HELP'); ?></p>
			<?php if (!$this->recent) : ?>
				<p class="mfa-empty"><?php echo $t('COM_MIFOTO_A_FRESH_EMPTY'); ?></p>
			<?php else : ?>
				<div class="mfa-fresh">
					<?php foreach ($this->recent as $u) : ?>
						<?php echo S::sticker($u, S::url('&view=photos&pkey=' . $u->pkey), false, S::ago($u->modified)); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
	</div>

	<aside class="mfa-col" aria-label="<?php echo $t('COM_MIFOTO_A_STATUS'); ?>">
		<section class="mfa-box">
			<div class="mfa-head">
				<h3><?php echo $t('COM_MIFOTO_A_STATUS'); ?></h3>
				<?php if ($this->health['warnings'] > 0) : ?>
					<span class="mfa-stamp"><?php echo S::icon('shield') . S::e($this->health['warnings'] === 1 ? Text::_('COM_MIFOTO_A_WARNINGS_1') : Text::sprintf('COM_MIFOTO_A_WARNINGS_N', $this->health['warnings'])); ?></span>
				<?php else : ?>
					<span class="mfa-stamp is-ok"><?php echo S::icon('check') . $t('COM_MIFOTO_A_STATUS_OK'); ?></span>
				<?php endif; ?>
			</div>
			<ul class="mfa-checks">
				<?php foreach ($this->health['items'] as $i) : ?>
					<li>
						<span class="mfa-dot is-<?php echo S::e($i['status']); ?>"><?php echo S::icon($i['status'] === 'ok' ? 'check' : ($i['status'] === 'warn' ? 'x' : 'info')); ?></span>
						<div><?php echo S::e($i['title']); ?><?php echo $i['detail'] !== '' ? '<small>' . S::e($i['detail']) . '</small>' : ''; ?></div>
						<?php if ($i['url'] !== '') : ?>
							<a class="mfa-link" href="<?php echo S::e(\Joomla\CMS\Router\Route::_($i['url'], false)); ?>"><?php echo S::e($i['action']); ?></a>
						<?php else : ?>
							<span class="mfa-val"><?php echo S::e($i['action']); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>

		<section class="mfa-box">
			<div class="mfa-head">
				<h3><?php echo $t('COM_MIFOTO_SET_PERMS'); ?></h3>
				<a class="mfa-link" href="<?php echo S::url('&view=permissions'); ?>"><?php echo $t('COM_MIFOTO_A_PERMS_ALL'); ?></a>
			</div>
			<table class="mfa-mx">
				<thead><tr><th scope="col"><span class="mfa-sr"><?php echo $t('COM_MIFOTO_A_GROUP'); ?></span></th><th scope="col"><?php echo $t('COM_MIFOTO_A_P_UP'); ?></th><th scope="col"><?php echo $t('COM_MIFOTO_A_P_VIEW'); ?></th><th scope="col"><?php echo $t('COM_MIFOTO_A_P_MOD'); ?></th></tr></thead>
				<tbody>
					<?php foreach ($this->matrix as $r) : ?>
						<tr>
							<th scope="row"><?php echo S::e($r['title']); ?></th>
							<?php foreach (['up', 'ver', 'mod'] as $k) : ?>
								<td><?php echo S::cell($r['cells'][$k]); ?></td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</section>
	</aside>
</div>
<?php echo S::close(); ?>
