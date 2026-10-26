<?php
/**
 * @package   MiFoto
 * @copyright Copyright (c) 2026 ChasKa
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Chaska\Component\Mifoto\Administrator\Service;

defined('_JEXEC') || die;

use Chaska\Component\Mifoto\Administrator\Security\PhotoStorage;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\User\User;

/**
 * Hands out one photo, and only after the ACL says yes. Whatever goes wrong (no such key, hidden, not allowed, file
 * missing) the answer is the same empty 404, so nobody can probe which photos exist.
 *
 * Adapted from the map component's PhotoServer (same author). The headers matter: nosniff, a CSP that forbids everything,
 * and Cross-Origin-Resource-Policy: same-origin, which makes the BROWSER refuse to show the photo on any other website.
 */
final class PhotoServer
{
	public static function send(CMSApplicationInterface $app, PhotoService $service, User $viewer, string $pkey, int $size): never
	{
		$path = null;
		$row  = $service->rowByKey($pkey);

		if ($row !== null && $service->viewerMaySee($viewer, (int) $row->user_id, (int) $row->state))
		{
			$path = $service->settings()->storage()->pathFor((int) $row->user_id, (string) $row->token, PhotoStorage::nearestSize($size));
		}

		while (ob_get_level() > 0)
		{
			@ob_end_clean();
		}

		self::deliver($app, $path, $service->settings(), $pkey . '|' . ($row->token ?? '') . '|' . $size . '|' . ($row->state ?? ''));
	}

	private static function deliver(CMSApplicationInterface $app, ?string $path, Settings $settings, string $etagSeed): never
	{
		header('X-Content-Type-Options: nosniff');
		header('Referrer-Policy: no-referrer');
		header('Cross-Origin-Resource-Policy: same-origin');

		if ($path === null)
		{
			http_response_code(404);
			header('Content-Type: text/plain; charset=utf-8');
			header('Cache-Control: no-store');
			$app->close();
			exit;
		}

		$size = (int) filesize($path);
		// Opaque: the stored file name must never travel in a header. The state is in the seed, so hiding a photo changes the tag.
		$etag = '"' . substr(hash_hmac('sha256', 'mifoto-etag|' . $etagSeed . '|' . $size, $settings->secret()), 0, 32) . '"';

		// private: the answer depends on who asks (their session), so no shared cache may keep it.
		header('Cache-Control: private, max-age=' . $settings->cacheSeconds() . ', must-revalidate');
		header('Vary: Cookie');
		header('ETag: ' . $etag);
		header('Content-Security-Policy: default-src \'none\'; sandbox');

		if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag)
		{
			http_response_code(304);
			$app->close();
			exit;
		}

		header('Content-Type: image/webp');
		header('Content-Length: ' . $size);
		header('Content-Disposition: inline; filename="foto.webp"');

		readfile($path);
		$app->close();
		exit;
	}
}
