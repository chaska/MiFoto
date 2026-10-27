<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

defined('_JEXEC') || die;

use Chaska\Component\Mifoto\Administrator\Helper\AdminShell as S;
use Chaska\Component\Mifoto\Administrator\Service\PermissionMatrix;
use Joomla\CMS\Language\Text;

/** @var  \Chaska\Component\Mifoto\Administrator\View\Permissions\HtmlView $this */

$t = static fn (string $key): string => S::e(Text::_($key));

$texts = [];

foreach (['SAVED', 'UNDO', 'ERR_SAVE', 'S_OWN', 'S_INH', 'S_NONE', 'S_FIXED', 'S_DENY', 'S_DENY_INH', 'PRESS_ADD', 'PRESS_REMOVE', 'P_UP', 'P_VIEW', 'P_MOD'] as $k)
{
	$texts[$k] = Text::_('COM_MIFOTO_A_' . $k);
}

echo S::open('permissions', $this->totals);
?>
<div class="mfa-pagehead">
	<div>
		<h2><?php echo $t('COM_MIFOTO_SET_PERMS'); ?></h2>
		<p class="mfa-sub"><?php echo $t($this->canEdit ? 'COM_MIFOTO_A_PERM_HELP_EDIT' : 'COM_MIFOTO_A_PERM_HELP_READ'); ?></p>
	</div>
	<?php if ($this->canEdit) : ?>
		<a class="mfa-btn mfa-btn-line" href="<?php echo S::e(\Joomla\CMS\Router\Route::_('index.php?option=com_config&view=component&component=com_mifoto', false)); ?>"><?php echo S::icon('lock') . $t('COM_MIFOTO_A_PERM_ADV'); ?></a>
	<?php endif; ?>
</div>

<div class="mfa-perm-grid">
	<div><h3><?php echo $t('COM_MIFOTO_A_PD_UP_T'); ?></h3><p><?php echo $t('COM_MIFOTO_A_PD_UP'); ?></p></div>
	<div><h3><?php echo $t('COM_MIFOTO_A_PD_VIEW_T'); ?></h3><p><?php echo $t('COM_MIFOTO_A_PD_VIEW'); ?></p></div>
	<div><h3><?php echo $t('COM_MIFOTO_A_PD_MOD_T'); ?></h3><p><?php echo $t('COM_MIFOTO_A_PD_MOD'); ?></p></div>
</div>

<section class="mfa-box mfa-wide"
	data-mfa-matrix="<?php echo $this->canEdit ? '1' : '0'; ?>"
	data-url="<?php echo S::url('&task=permissions.toggle&format=json'); ?>"
	data-token="<?php echo S::e($this->token); ?>"
	data-text="<?php echo S::e((string) json_encode($texts, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG)); ?>">
	<table class="mfa-mx">
		<thead>
			<tr>
				<th scope="col"><?php echo $t('COM_MIFOTO_A_GROUP'); ?></th>
				<th scope="col"><?php echo $t('COM_MIFOTO_A_P_UP'); ?></th>
				<th scope="col"><?php echo $t('COM_MIFOTO_A_P_VIEW'); ?></th>
				<th scope="col"><?php echo $t('COM_MIFOTO_A_P_MOD'); ?></th>
			</tr>
		</thead>
		<tbody data-role="rows">
			<?php foreach ($this->rows as $r) : ?>
				<tr>
					<th scope="row" style="padding-left:<?php echo (int) $r['level'] * 14 + 6; ?>px"><?php echo S::e($r['title']); ?></th>
					<?php foreach (PermissionMatrix::ACTIONS as $action => $k) : ?>
						<td><?php echo S::cell($r['cells'][$k], $this->canEdit ? ['group' => $r['id'], 'key' => $k, 'title' => $r['title'], 'label' => Text::_('COM_MIFOTO_A_' . ($k === 'up' ? 'P_UP' : ($k === 'ver' ? 'P_VIEW' : 'P_MOD')))] : null); ?></td>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<div class="mfa-legend-light">
		<span><?php echo S::cell('own'); ?><?php echo $t('COM_MIFOTO_A_L_OWN'); ?></span>
		<span><?php echo S::cell('inh'); ?><?php echo $t('COM_MIFOTO_A_L_INH'); ?></span>
		<span><?php echo S::cell(''); ?><?php echo $t('COM_MIFOTO_A_L_NONE'); ?></span>
		<span><?php echo S::cell('fixed'); ?><?php echo $t('COM_MIFOTO_A_L_FIXED'); ?></span>
		<span><?php echo S::cell('deny'); ?><?php echo $t('COM_MIFOTO_A_L_DENY'); ?></span>
	</div>
	<p class="mfa-msg" data-role="msg" role="status" aria-live="polite" hidden></p>
</section>
<?php echo S::close(); ?>
