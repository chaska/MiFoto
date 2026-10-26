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
 * Raised when an image is refused. The code is a short, stable identifier that maps to a language key
 * (COM_MIFOTO_ERR_<CODE>); the message never echoes what the user sent.
 */
final class SanitizeException extends \RuntimeException
{
	private string $reason;

	public function __construct(string $reason)
	{
		parent::__construct($reason);
		$this->reason = $reason;
	}

	public function getReason(): string
	{
		return $this->reason;
	}
}
