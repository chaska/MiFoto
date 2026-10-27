<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

defined('_JEXEC') || die;

use Chaska\Component\Mifoto\Administrator\Helper\AdminShell as S;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var  \Chaska\Component\Mifoto\Administrator\View\Settings\HtmlView $this */

$p   = $this->params;
$t   = static fn (string $key): string => S::e(Text::_($key));
$cur = static fn (string $key, string $default): string => (string) $p->get($key, $default);

$shape  = $cur('shape', 'circle');
$frame  = $cur('frame', 'none');
$shadow = $cur('shadow', 'none');
$crop   = $cur('crop', '1') === '0' ? '0' : '1';

/** One segmented control: radio buttons that look like a row of buttons, still reachable with the keyboard. */
$segmented = static function (string $name, string $legend, string $current, array $options) use ($t): string {
	$h = '<fieldset class="mfa-seg"><legend>' . $t($legend) . '</legend><div class="mfa-seg-row">';

	foreach ($options as $value => $labelKey)
	{
		$h .= '<label><input type="radio" name="jform[' . S::e($name) . ']" value="' . S::e((string) $value) . '"' . ($current === (string) $value ? ' checked' : '') . '><span>' . $t($labelKey) . '</span></label>';
	}

	return $h . '</div></fieldset>';
};

$field = static function (string $id, string $labelKey, string $descKey, string $input) use ($t): string {
	return '<div class="mfa-field"><label for="' . $id . '">' . $t($labelKey) . '</label>' . $input . ($descKey !== '' ? '<p>' . $t($descKey) . '</p>' : '') . '</div>';
};

$sample = static fn (string $extra, string $text = ''): string => '<span class="mfa-av mfa-sample mfa-s-' . S::e($shape) . ' mfa-f-' . S::e($frame) . ' mfa-sh-' . S::e($shadow) . ' ' . $extra . '" aria-hidden="true">' . $text . '</span>';

echo S::open('settings', $this->totals);
?>
<?php if ($this->engage) : ?>
	<p class="mfa-msg is-error" role="alert"><?php echo S::e($this->engage['detail']); ?> <a href="<?php echo S::e($this->engage['url']); ?>"><?php echo $t('COM_MIFOTO_A_H_ENGAGE_LINK'); ?></a></p>
<?php endif; ?>
<?php if (!$this->canEdit) : ?>
	<p class="mfa-empty"><?php echo $t('COM_MIFOTO_SET_NO_RIGHTS'); ?></p>
<?php else : ?>
<form action="<?php echo S::e(Route::_('index.php?option=com_config', false)); ?>" method="post" id="mifoto-settings" class="mfa-cols" data-mfa-settings>
	<div class="mfa-pages">
		<section>
			<h2><?php echo $t('COM_MIFOTO_SET_LOOK'); ?></h2>
			<p class="mfa-sub"><?php echo $t('COM_MIFOTO_SET_LOOK_HELP'); ?></p>
			<div class="mfa-gap">
				<?php
				echo $segmented('shape', 'COM_MIFOTO_CONFIG_SHAPE', $shape, ['circle' => 'COM_MIFOTO_SHAPE_CIRCLE', 'square' => 'COM_MIFOTO_SHAPE_SQUARE', 'soft' => 'COM_MIFOTO_SHAPE_SOFT']);
				echo $segmented('frame', 'COM_MIFOTO_CONFIG_FRAME', $frame, ['none' => 'COM_MIFOTO_FRAME_NONE', 'thin' => 'COM_MIFOTO_FRAME_THIN', 'thick' => 'COM_MIFOTO_FRAME_THICK']);
				echo $segmented('shadow', 'COM_MIFOTO_CONFIG_SHADOW', $shadow, ['none' => 'COM_MIFOTO_SHADOW_NONE', 'soft' => 'COM_MIFOTO_SHADOW_SOFT', 'strong' => 'COM_MIFOTO_SHADOW_STRONG']);
				?>
			</div>
		</section>

		<section>
			<h2><?php echo $t('COM_MIFOTO_SET_UPLOAD'); ?></h2>
			<p class="mfa-sub"><?php echo $t('COM_MIFOTO_SET_UPLOAD_HELP'); ?></p>
			<div class="mfa-grid mfa-gap">
				<?php
				echo $field('mifoto-max-mb', 'COM_MIFOTO_CONFIG_MAX_MB', 'COM_MIFOTO_CONFIG_MAX_MB_DESC', '<input type="number" id="mifoto-max-mb" name="jform[max_mb]" min="1" max="20" step="1" value="' . S::e($cur('max_mb', '10')) . '">');
				echo $field('mifoto-quality', 'COM_MIFOTO_CONFIG_QUALITY', '', '<input type="number" id="mifoto-quality" name="jform[quality]" min="50" max="95" step="1" value="' . S::e($cur('quality', '82')) . '">');
				echo $field('mifoto-uploads', 'COM_MIFOTO_CONFIG_MAX_UPLOADS', 'COM_MIFOTO_CONFIG_MAX_UPLOADS_DESC', '<input type="number" id="mifoto-uploads" name="jform[max_uploads_hour]" min="0" max="1000" step="1" value="' . S::e($cur('max_uploads_hour', '10')) . '">');
				?>
			</div>
			<input type="hidden" name="jform[long_side]" value="300">
			<div class="mfa-gap">
				<?php echo $segmented('crop', 'COM_MIFOTO_CONFIG_CROP', $crop, ['1' => 'COM_MIFOTO_SET_YES', '0' => 'COM_MIFOTO_SET_NO']); ?>
				<p class="mfa-sub"><?php echo $t('COM_MIFOTO_CONFIG_CROP_DESC'); ?></p>
			</div>
		</section>

		<section>
			<h2><?php echo $t('COM_MIFOTO_SET_STORAGE'); ?></h2>
			<div class="mfa-grid mfa-gap">
				<?php
				echo $field('mifoto-path', 'COM_MIFOTO_CONFIG_STORAGE_PATH', 'COM_MIFOTO_CONFIG_STORAGE_PATH_DESC', '<input type="text" id="mifoto-path" name="jform[storage_path]" value="' . S::e($cur('storage_path', 'images/MiFoto')) . '" autocomplete="off" spellcheck="false">');
				echo $field('mifoto-cache', 'COM_MIFOTO_CONFIG_CACHE', 'COM_MIFOTO_CONFIG_CACHE_DESC', '<input type="number" id="mifoto-cache" name="jform[cache_minutes]" min="0" max="1440" step="1" value="' . S::e($cur('cache_minutes', '10')) . '">');
				?>
			</div>
		</section>

		<div class="mfa-savebar">
			<button type="submit" class="mfa-btn mfa-btn-gold"><?php echo $t('COM_MIFOTO_SET_SAVE'); ?></button>
			<span class="mfa-stamp" data-role="dirty" hidden><?php echo $t('COM_MIFOTO_SET_UNSAVED'); ?></span>
		</div>
	</div>

	<aside class="mfa-col mfa-viewer" aria-label="<?php echo $t('COM_MIFOTO_SET_PREVIEW'); ?>">
		<section class="mfa-box mfa-preview">
			<h3><?php echo $t('COM_MIFOTO_SET_PREVIEW'); ?></h3>
			<div class="mfa-av-row"><?php echo $sample('mfa-sample--photo mfa-sample--xl'); ?><?php echo $sample('mfa-sample--photo mfa-sample--md'); ?><?php echo $sample('mfa-sample--photo mfa-sample--sm'); ?></div>
			<p class="mfa-sub"><?php echo $t('COM_MIFOTO_SET_PREVIEW_PHOTO'); ?></p>
			<div class="mfa-av-row"><?php echo $sample('mfa-sample--md mfa-c1', 'ML'); ?><?php echo $sample('mfa-sample--md mfa-c2', 'JR'); ?><?php echo $sample('mfa-sample--md mfa-c3', 'AS'); ?></div>
			<p class="mfa-sub"><?php echo $t('COM_MIFOTO_SET_PREVIEW_NOPHOTO'); ?></p>
			<div class="mfa-av-row"><?php echo $sample('mfa-sample--photo mfa-sample--md'); ?><div><b><?php echo $t('COM_MIFOTO_SET_SAMPLE_NAME'); ?></b><p class="mfa-sub"><?php echo $t('COM_MIFOTO_SET_SAMPLE_TEXT'); ?></p></div></div>
			<p class="mfa-sub"><?php echo $t('COM_MIFOTO_SET_PREVIEW_COMMENT'); ?></p>
		</section>
	</aside>

	<input type="hidden" name="task" value="component.save">
	<input type="hidden" name="component" value="com_mifoto">
	<input type="hidden" name="id" value="<?php echo (int) $this->extensionId; ?>">
	<input type="hidden" name="return" value="<?php echo S::e($this->return); ?>">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
<?php endif; ?>
<?php echo S::close(); ?>
