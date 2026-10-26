<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

defined('_JEXEC') || die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var  \Chaska\Component\Mifoto\Administrator\View\Settings\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('com_mifoto');
$wa->useStyle('com_mifoto.avatar')->useStyle('com_mifoto.settings')->useScript('com_mifoto.settings');

$p   = $this->params;
$e   = static fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$t   = static fn (string $key): string => htmlspecialchars(Text::_($key), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$cur = static fn (string $key, string $default) => (string) $p->get($key, $default);

$shape  = $cur('shape', 'circle');
$frame  = $cur('frame', 'none');
$shadow = $cur('shadow', 'none');
$crop   = $cur('crop', '1') === '0' ? '0' : '1';

/** One segmented control: a group of radio buttons that look like a row of buttons. */
$segmented = static function (string $name, string $legend, string $current, array $options) use ($e, $t): string {
	$h = '<fieldset class="mifoto-seg"><legend>' . $t($legend) . '</legend><div class="mifoto-seg__row">';

	foreach ($options as $value => $labelKey)
	{
		$h .= '<label><input type="radio" name="jform[' . $e($name) . ']" value="' . $e($value) . '"' . ($current === (string) $value ? ' checked' : '') . '>'
			. '<span>' . $t($labelKey) . '</span></label>';
	}

	return $h . '</div></fieldset>';
};

/** A labelled field with its help text underneath. */
$field = static function (string $id, string $labelKey, string $descKey, string $input) use ($t): string {
	return '<div class="mifoto-field"><label for="' . $id . '">' . $t($labelKey) . '</label>' . $input
		. ($descKey !== '' ? '<p class="mifoto-help">' . $t($descKey) . '</p>' : '') . '</div>';
};

$sample = static fn (string $extra, string $text = ''): string => '<span class="mifoto-avatar mifoto-sample mifoto-shape-' . $e($shape) . ' mifoto-frame-' . $e($frame) . ' mifoto-shadow-' . $e($shadow) . ' ' . $extra . '" aria-hidden="true">' . $text . '</span>';
?>
<?php if (!$this->canEdit) : ?>
	<div class="alert alert-warning"><?php echo $t('COM_MIFOTO_SET_NO_RIGHTS'); ?></div>
<?php else : ?>
<form action="<?php echo $e(Route::_('index.php?option=com_config', false)); ?>" method="post" id="mifoto-settings" class="mifoto-set">
	<div class="mifoto-set__main">

		<section class="mifoto-card">
			<h2><?php echo $t('COM_MIFOTO_SET_LOOK'); ?></h2>
			<p class="mifoto-help"><?php echo $t('COM_MIFOTO_SET_LOOK_HELP'); ?></p>
			<?php
			echo $segmented('shape', 'COM_MIFOTO_CONFIG_SHAPE', $shape, ['circle' => 'COM_MIFOTO_SHAPE_CIRCLE', 'square' => 'COM_MIFOTO_SHAPE_SQUARE', 'soft' => 'COM_MIFOTO_SHAPE_SOFT']);
			echo $segmented('frame', 'COM_MIFOTO_CONFIG_FRAME', $frame, ['none' => 'COM_MIFOTO_FRAME_NONE', 'thin' => 'COM_MIFOTO_FRAME_THIN', 'thick' => 'COM_MIFOTO_FRAME_THICK']);
			echo $segmented('shadow', 'COM_MIFOTO_CONFIG_SHADOW', $shadow, ['none' => 'COM_MIFOTO_SHADOW_NONE', 'soft' => 'COM_MIFOTO_SHADOW_SOFT', 'strong' => 'COM_MIFOTO_SHADOW_STRONG']);
			?>
		</section>

		<section class="mifoto-card">
			<h2><?php echo $t('COM_MIFOTO_SET_UPLOAD'); ?></h2>
			<p class="mifoto-help"><?php echo $t('COM_MIFOTO_SET_UPLOAD_HELP'); ?></p>
			<div class="mifoto-grid">
				<?php
				echo $field('mifoto-max-mb', 'COM_MIFOTO_CONFIG_MAX_MB', 'COM_MIFOTO_CONFIG_MAX_MB_DESC', '<input type="number" class="form-control" id="mifoto-max-mb" name="jform[max_mb]" min="1" max="20" step="1" value="' . $e($cur('max_mb', '10')) . '">');
				echo $field('mifoto-quality', 'COM_MIFOTO_CONFIG_QUALITY', '', '<input type="number" class="form-control" id="mifoto-quality" name="jform[quality]" min="50" max="95" step="1" value="' . $e($cur('quality', '82')) . '">');
				echo $field('mifoto-uploads', 'COM_MIFOTO_CONFIG_MAX_UPLOADS', 'COM_MIFOTO_CONFIG_MAX_UPLOADS_DESC', '<input type="number" class="form-control" id="mifoto-uploads" name="jform[max_uploads_hour]" min="0" max="1000" step="1" value="' . $e($cur('max_uploads_hour', '10')) . '">');
				?>
			</div>
			<input type="hidden" name="jform[long_side]" value="300">
			<?php echo $segmented('crop', 'COM_MIFOTO_CONFIG_CROP', $crop, ['1' => 'COM_MIFOTO_SET_YES', '0' => 'COM_MIFOTO_SET_NO']); ?>
			<p class="mifoto-help"><?php echo $t('COM_MIFOTO_CONFIG_CROP_DESC'); ?></p>
		</section>

		<section class="mifoto-card">
			<h2><?php echo $t('COM_MIFOTO_SET_STORAGE'); ?></h2>
			<div class="mifoto-grid">
				<?php
				echo $field('mifoto-path', 'COM_MIFOTO_CONFIG_STORAGE_PATH', 'COM_MIFOTO_CONFIG_STORAGE_PATH_DESC', '<input type="text" class="form-control" id="mifoto-path" name="jform[storage_path]" value="' . $e($cur('storage_path', 'images/MiFoto')) . '" autocomplete="off" spellcheck="false">');
				echo $field('mifoto-cache', 'COM_MIFOTO_CONFIG_CACHE', 'COM_MIFOTO_CONFIG_CACHE_DESC', '<input type="number" class="form-control" id="mifoto-cache" name="jform[cache_minutes]" min="0" max="1440" step="1" value="' . $e($cur('cache_minutes', '10')) . '">');
				?>
			</div>
		</section>

		<section class="mifoto-card">
			<h2><?php echo $t('COM_MIFOTO_SET_PERMS'); ?></h2>
			<p class="mifoto-help"><?php echo $t('COM_MIFOTO_SET_PERMS_HELP'); ?></p>
			<ul class="mifoto-perms">
				<li><?php echo $t('COM_MIFOTO_ACTION_UPLOAD'); ?></li>
				<li><?php echo $t('COM_MIFOTO_ACTION_VIEW'); ?></li>
				<li><?php echo $t('COM_MIFOTO_ACTION_MODERATE'); ?></li>
			</ul>
			<a class="btn btn-outline-primary" href="<?php echo $e(Route::_('index.php?option=com_config&view=component&component=com_mifoto', false)); ?>"><?php echo $t('COM_MIFOTO_SET_PERMS_BUTTON'); ?></a>
		</section>

		<div class="mifoto-savebar">
			<button type="submit" class="btn btn-primary btn-lg"><?php echo $t('COM_MIFOTO_SET_SAVE'); ?></button>
			<span class="mifoto-unsaved" id="mifoto-unsaved" hidden><?php echo $t('COM_MIFOTO_SET_UNSAVED'); ?></span>
		</div>
	</div>

	<aside class="mifoto-set__side" aria-label="<?php echo $t('COM_MIFOTO_SET_PREVIEW'); ?>">
		<div class="mifoto-card mifoto-preview">
			<h2><?php echo $t('COM_MIFOTO_SET_PREVIEW'); ?></h2>

			<p class="mifoto-preview__label"><?php echo $t('COM_MIFOTO_SET_PREVIEW_PHOTO'); ?></p>
			<div class="mifoto-preview__row">
				<?php echo $sample('mifoto-sample--photo mifoto-sample--xl'); ?>
				<?php echo $sample('mifoto-sample--photo mifoto-sample--md'); ?>
				<?php echo $sample('mifoto-sample--photo mifoto-sample--sm'); ?>
			</div>

			<p class="mifoto-preview__label"><?php echo $t('COM_MIFOTO_SET_PREVIEW_NOPHOTO'); ?></p>
			<div class="mifoto-preview__row">
				<?php echo $sample('mifoto-sample--md mifoto-sample--c1', 'ML'); ?>
				<?php echo $sample('mifoto-sample--md mifoto-sample--c2', 'JR'); ?>
				<?php echo $sample('mifoto-sample--md mifoto-sample--c3', 'AS'); ?>
			</div>

			<p class="mifoto-preview__label"><?php echo $t('COM_MIFOTO_SET_PREVIEW_COMMENT'); ?></p>
			<div class="mifoto-preview__comment">
				<?php echo $sample('mifoto-sample--photo mifoto-sample--md'); ?>
				<div>
					<strong><?php echo $t('COM_MIFOTO_SET_SAMPLE_NAME'); ?></strong>
					<p><?php echo $t('COM_MIFOTO_SET_SAMPLE_TEXT'); ?></p>
				</div>
			</div>
		</div>
	</aside>

	<input type="hidden" name="task" value="component.save">
	<input type="hidden" name="component" value="com_mifoto">
	<input type="hidden" name="id" value="<?php echo (int) $this->extensionId; ?>">
	<input type="hidden" name="return" value="<?php echo $e($this->return); ?>">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
<?php endif; ?>
