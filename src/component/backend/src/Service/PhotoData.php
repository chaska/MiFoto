<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mifoto\Administrator\Service;

defined('_JEXEC') || die;

/**
 * What other extensions get back when they ask for a photo. Plain values, safe to put in an attribute once escaped.
 * The Joomla user id is not in here on purpose: the URL carries an opaque key.
 */
final class PhotoData
{
	public function __construct(
		/** Site-relative URL of the image (same domain, no quotes, no scheme tricks). */
		public readonly string $url,
		/** The stored size actually served: 300, 128, 64 or 32 (long side, px). */
		public readonly int $size,
		/** Real width and height of that file. */
		public readonly int $width,
		public readonly int $height,
		/** Changes with every upload: use it to bust caches. */
		public readonly string $version
	)
	{
	}

	/** @return  array{url: string, size: int, width: int, height: int, version: string} */
	public function toArray(): array
	{
		return ['url' => $this->url, 'size' => $this->size, 'width' => $this->width, 'height' => $this->height, 'version' => $this->version];
	}
}
