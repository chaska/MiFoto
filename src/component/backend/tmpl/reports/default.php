<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

defined('_JEXEC') || die;

use Chaska\Component\Mifoto\Administrator\Helper\AdminShell as S;
use Chaska\Component\Mifoto\Administrator\Service\Reports;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** @var  \Chaska\Component\Mifoto\Administrator\View\Reports\HtmlView $this */

$t = static fn (string $key): string => S::e(Text::_($key));

/** One report: the photo, who it is about, who reported, why, and the three things a moderator can do. */
$card = function (\stdClass $r, bool $actions) use ($t): string {
	$owner    = (string) ($r->owner_name !== null && $r->owner_name !== '' ? $r->owner_name : ($r->owner_username ?? ''));
	$reporter = (string) ($r->reporter_name !== null && $r->reporter_name !== '' ? $r->reporter_name : ($r->reporter_username ?? ''));
	$reason   = Text::_('COM_MIFOTO_REASON_' . strtoupper((string) $r->reason));
	$h        = '<article class="mfa-report">';

	$h .= '<div class="mfa-report__photo">'
		. ($r->pkey !== null ? '<img src="' . S::e(S::photoUrl((string) $r->pkey, 128)) . '" alt="" width="72" height="72">' : '<span class="mfa-report__gone">' . $t('COM_MIFOTO_A_R_NO_PHOTO') . '</span>')
		. '</div>';
	$h .= '<div class="mfa-report__body">';
	$h .= '<h3>' . S::e(Text::sprintf('COM_MIFOTO_A_R_ABOUT', $owner)) . ' <small>#' . (int) $r->owner_id . '</small></h3>';
	$h .= '<p class="mfa-report__reason"><b>' . S::e($reason) . '</b></p>';

	if ((string) $r->comment !== '')
	{
		$h .= '<p class="mfa-report__comment">' . S::e((string) $r->comment) . '</p>';
	}

	$h .= '<p class="mfa-sub">' . S::e(Text::sprintf('COM_MIFOTO_A_R_BY', $reporter)) . ' · ' . S::e(S::ago((string) $r->created)) . '</p>';

	if ($actions)
	{
		$h .= '<div class="mfa-rail">';

		foreach (['hide' => ['eyeoff', 'COM_MIFOTO_A_R_HIDE', 'mfa-btn-line', ''], 'remove' => ['trash', 'COM_MIFOTO_A_R_REMOVE', 'mfa-btn-danger', 'COM_MIFOTO_A_R_CONFIRM_REMOVE'], 'dismiss' => ['check', 'COM_MIFOTO_A_R_DISMISS', 'mfa-btn-line', '']] as $task => [$icon, $label, $class, $confirm])
		{
			$h .= '<form action="' . S::url('&task=reports.' . $task) . '" method="post"' . ($confirm !== '' ? ' data-confirm="' . $t($confirm) . '"' : '') . '>'
				. '<input type="hidden" name="id" value="' . (int) $r->id . '">'
				. '<button type="submit" class="mfa-btn ' . $class . '">' . S::icon($icon) . $t($label) . '</button>'
				. HTMLHelper::_('form.token') . '</form>';
		}

		$h .= '</div>';
	}
	else
	{
		$h .= '<p class="mfa-sub"><b>' . $t('COM_MIFOTO_A_R_DONE_' . (int) $r->status) . '</b></p>';
	}

	return $h . '</div></article>';
};

echo S::open('reports', $this->totals);
?>
<div class="mfa-cols">
	<div class="mfa-col">
		<section>
			<h2><?php echo $t('COM_MIFOTO_A_R_OPEN'); ?></h2>
			<?php if ($this->restricted) : ?>
				<p class="mfa-empty"><?php echo $t('COM_MIFOTO_A_RESTRICTED'); ?></p>
			<?php elseif (!$this->open) : ?>
				<p class="mfa-empty"><?php echo $t('COM_MIFOTO_A_R_EMPTY'); ?></p>
			<?php else : ?>
				<?php foreach ($this->open as $r) : ?>
					<?php echo $card($r, true); ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</section>

		<?php if (!$this->restricted) : ?>
			<section>
				<h2><?php echo $t('COM_MIFOTO_A_R_DONE'); ?></h2>
				<?php if (!$this->done) : ?>
					<p class="mfa-empty"><?php echo $t('COM_MIFOTO_A_R_EMPTY_DONE'); ?></p>
				<?php else : ?>
					<?php foreach ($this->done as $r) : ?>
						<?php echo $card($r, false); ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</section>
		<?php endif; ?>
	</div>
</div>
<?php echo S::close(); ?>
