<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mifoto\Administrator\Service;

defined('_JEXEC') || die;

use Chaska\Component\Mifoto\Administrator\Security\ImageProcessor;
use Chaska\Component\Mifoto\Administrator\Security\PhotoStorage;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;

/** The component options, read once and checked: nothing from the options reaches the file system unchecked. */
final class Settings
{
	public const DEFAULT_PATH = 'images/MiFoto';

	private string $path;
	private int $maxBytes;
	private int $quality;
	private int $cacheSeconds;
	private string $secret;
	private string $shape;
	private string $frame;
	private string $shadow;
	private bool $crop;

	public function __construct(string $path, int $maxMb, int $quality, int $cacheMinutes, string $secret, string $shape = 'circle', string $frame = 'none', string $shadow = 'none', bool $crop = true)
	{
		// Only these words ever become CSS class names: anything else falls back to the default look.
		$this->shape        = \in_array($shape, ['circle', 'square', 'soft'], true) ? $shape : 'circle';
		$this->frame        = \in_array($frame, ['none', 'thin', 'thick'], true) ? $frame : 'none';
		$this->shadow       = \in_array($shadow, ['none', 'soft', 'strong'], true) ? $shadow : 'none';
		$this->crop         = $crop;
		$this->path         = self::cleanPath($path);
		$this->maxBytes     = min(20, max(1, $maxMb)) * 1048576;
		$this->quality      = min(95, max(50, $quality));
		$this->cacheSeconds = min(1440, max(0, $cacheMinutes)) * 60;
		$this->secret       = $secret !== '' ? $secret : (\defined('JPATH_ROOT') ? JPATH_ROOT : __DIR__);
	}

	public static function load(): self
	{
		$p = ComponentHelper::getParams('com_mifoto');

		return new self(
			(string) $p->get('storage_path', self::DEFAULT_PATH),
			(int) $p->get('max_mb', 10),
			(int) $p->get('quality', 82),
			(int) $p->get('cache_minutes', 10),
			(string) Factory::getApplication()->get('secret', ''),
			(string) $p->get('shape', 'circle'),
			(string) $p->get('frame', 'none'),
			(string) $p->get('shadow', 'none'),
			(bool) $p->get('crop', 1)
		);
	}

	/** The admin's look for every photo: "mifoto-avatar mifoto-shape-circle mifoto-frame-none mifoto-shadow-none". Safe to print as a class attribute. */
	public function cssClasses(): string
	{
		return 'mifoto-avatar mifoto-shape-' . $this->shape . ' mifoto-frame-' . $this->frame . ' mifoto-shadow-' . $this->shadow;
	}

	public function cropEnabled(): bool
	{
		return $this->crop;
	}

	public function maxBytes(): int
	{
		return $this->maxBytes;
	}

	/**
	 * The folder is always inside the Joomla root and made only of letters, digits, "_", "-" and "/". Anything else
	 * (".." , absolute paths, drive letters, wrappers like php://, spaces, NUL) falls back to the default.
	 */
	public static function cleanPath(string $path): string
	{
		$path = trim(str_replace('\\', '/', $path), '/');

		if ($path === '' || !preg_match('~^[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*$~D', $path))
		{
			return self::DEFAULT_PATH;
		}

		return $path;
	}

	public function path(): string
	{
		return $this->path;
	}

	public function storage(): PhotoStorage
	{
		return new PhotoStorage(rtrim(JPATH_ROOT, '/\\') . '/' . $this->path);
	}

	public function processor(): ImageProcessor
	{
		return new ImageProcessor($this->maxBytes, 300, $this->quality, false);
	}

	public function cacheSeconds(): int
	{
		return $this->cacheSeconds;
	}

	public function secret(): string
	{
		return $this->secret;
	}
}
