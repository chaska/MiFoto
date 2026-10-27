<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mifoto\Administrator\Security;

defined('_JEXEC') || die;

/**
 * Where profile photos live: <root>/<user id>/<token>-<size>.webp, a folder the web server must never serve directly.
 * Photos are only handed out by the component, after checking who is asking. The token is 96 random bits generated
 * here and never comes from a user; every upload makes a new token, so an old URL stops working at once.
 *
 * Adapted from the map component's PrivateStorage (same author).
 */
final class PhotoStorage
{
	/** Sizes (long side, px) kept for every photo; 300 is the processed original. */
	public const SIZES = [300, 128, 64, 32];

	/** <user id>/<24 hex>-<size>.webp */
	public const NAME_PATTERN = '/^([0-9]{1,10})\/([a-f0-9]{24})-(300|128|64|32)\.webp$/D';

	private string $root;

	public function __construct(string $root)
	{
		$this->root = rtrim($root, '/\\');
	}

	public function root(): string
	{
		return $this->root;
	}

	/** Creates the folders and the files that tell Apache, LiteSpeed and IIS to refuse any direct request. */
	public function prepare(): void
	{
		foreach ([$this->root, $this->root . '/tmp'] as $dir)
		{
			if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir))
			{
				throw new \RuntimeException('storage');
			}
		}

		$guards = [
			'.htaccess'  => "# Photos are served only by the component.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder deny,allow\n\tDeny from all\n</IfModule>\nOptions -Indexes -ExecCGI\n<IfModule mod_php.c>\n\tphp_flag engine off\n</IfModule>\nRemoveHandler .php .phtml .php5 .php7 .php8\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
			'index.html' => '<!DOCTYPE html><title></title>',
		];

		foreach ($guards as $name => $body)
		{
			$file = $this->root . '/' . $name;

			if (!is_file($file))
			{
				@file_put_contents($file, $body, LOCK_EX);
			}
		}
	}

	/** A fresh, unused temporary path for the processor to write to. */
	public function newTemporaryPath(): string
	{
		return $this->root . '/tmp/' . bin2hex(random_bytes(16)) . '.part';
	}

	/**
	 * Moves a verified temporary WebP to <user id>/<token>-300.webp, makes the smaller sizes, and removes the user's previous
	 * photo. Returns the new token.
	 */
	public function commit(string $temporary, int $userId, bool $dropOld = true): string
	{
		if ($userId < 1)
		{
			throw new \RuntimeException('storage');
		}

		$real    = realpath($temporary);
		$tmpRoot = realpath($this->root . '/tmp');

		if ($real === false || $tmpRoot === false || !str_starts_with($real, $tmpRoot . DIRECTORY_SEPARATOR))
		{
			throw new \RuntimeException('storage');
		}

		$dir = $this->root . '/' . $userId;

		if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir))
		{
			throw new \RuntimeException('storage');
		}

		$token = bin2hex(random_bytes(12));
		$big   = $dir . '/' . $token . '-300.webp';

		if (file_exists($big) || !@rename($real, $big))
		{
			throw new \RuntimeException('storage');
		}

		@chmod($big, 0640);

		try
		{
			$this->makeSmaller($big, $dir, $token);
		}
		catch (\Throwable $e)
		{
			$this->deleteToken($userId, $token);

			throw new \RuntimeException('storage');
		}

		// Only now, with the new photo complete, the old one goes (unless the caller drops it after saving the database row).
		if ($dropOld)
		{
			$this->deleteUser($userId, $token);
		}

		return $token;
	}

	/** Absolute path of a stored photo, or null when the arguments are not ours or the file is not there. */
	public function pathFor(int $userId, string $token, int $size): ?string
	{
		$name = $userId . '/' . $token . '-' . $size . '.webp';

		if ($userId < 1 || preg_match(self::NAME_PATTERN, $name) !== 1)
		{
			return null;
		}

		$path = realpath($this->root . '/' . $name);
		$base = realpath($this->root);

		if ($path === false || $base === false || !str_starts_with($path, $base . DIRECTORY_SEPARATOR) || !is_file($path) || is_link($path))
		{
			return null;
		}

		return $path;
	}

	/** The smallest stored size that is at least $wanted px (or the biggest one when $wanted is larger than all). */
	public static function nearestSize(int $wanted): int
	{
		$sizes = self::SIZES;
		sort($sizes);

		foreach ($sizes as $size)
		{
			if ($size >= $wanted)
			{
				return $size;
			}
		}

		return end($sizes);
	}

	/** Deletes every file of one photo (all sizes). */
	public function deleteToken(int $userId, string $token): void
	{
		foreach (self::SIZES as $size)
		{
			$path = $this->pathFor($userId, $token, $size);

			if ($path !== null)
			{
				@unlink($path);
			}
		}
	}

	/** Deletes the user's photos, keeping the one with $keepToken (empty: delete all) and removing the folder when it ends up empty. */
	public function deleteUser(int $userId, string $keepToken = ''): void
	{
		if ($userId < 1)
		{
			return;
		}

		$dir  = $this->root . '/' . $userId;
		$real = realpath($dir);
		$base = realpath($this->root);

		if ($real === false || $base === false || !str_starts_with($real, $base . DIRECTORY_SEPARATOR) || is_link($dir))
		{
			return;
		}

		foreach (glob($real . '/*.webp') ?: [] as $file)
		{
			if (is_link($file) || preg_match('/^([a-f0-9]{24})-(?:300|128|64|32)\.webp$/D', basename($file), $m) !== 1)
			{
				continue;
			}

			if ($keepToken === '' || $m[1] !== $keepToken)
			{
				@unlink($file);
			}
		}

		@rmdir($real); // refuses a folder that still has files
	}

	public function discard(string $temporary): void
	{
		if (is_file($temporary) && str_starts_with(realpath($temporary) ?: '', (realpath($this->root . '/tmp') ?: "\0") . DIRECTORY_SEPARATOR))
		{
			@unlink($temporary);
		}
	}

	/** Removes abandoned temporary files (a request that died half way). */
	public function sweep(int $olderThanSeconds = 3600): void
	{
		foreach (glob($this->root . '/tmp/*.part') ?: [] as $file)
		{
			if (is_file($file) && !is_link($file) && (time() - (int) filemtime($file)) > $olderThanSeconds)
			{
				@unlink($file);
			}
		}
	}

	private function makeSmaller(string $big, string $dir, string $token): void
	{
		$src = @imagecreatefromwebp($big);

		if ($src === false)
		{
			throw new \RuntimeException('storage');
		}

		$w = imagesx($src);
		$h = imagesy($src);

		foreach (self::SIZES as $side)
		{
			if ($side === 300)
			{
				continue;
			}

			// Long side = $side, same proportion; never bigger than the processed original.
			$k  = min(1.0, $side / max($w, $h));
			$nw = max(1, (int) round($w * $k));
			$nh = max(1, (int) round($h * $k));

			$small = imagecreatetruecolor($nw, $nh);
			imagealphablending($small, false);
			imagesavealpha($small, true);
			imagefill($small, 0, 0, (int) imagecolorallocatealpha($small, 255, 255, 255, 127));
			imagecopyresampled($small, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

			$dest = $dir . '/' . $token . '-' . $side . '.webp';

			if (!@imagewebp($small, $dest, 80))
			{
				throw new \RuntimeException('storage');
			}

			@chmod($dest, 0640);
		}
	}
}
