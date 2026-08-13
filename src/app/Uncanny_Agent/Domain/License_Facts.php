<?php
/**
 * Uncanny Agent license facts.
 *
 * @package Uncanny_Automator
 * @since 7.6
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Uncanny_Agent\Domain;

/**
 * Immutable local license facts used by Uncanny Agent rules.
 */
final class License_Facts {

	public const FREE = 'free';
	public const PRO  = 'pro';

	/**
	 * Local Automator license type.
	 *
	 * @var string
	 */
	private string $type;

	/**
	 * Whether the local license is connected.
	 *
	 * @var bool
	 */
	private bool $is_connected;

	/**
	 * Create the facts.
	 *
	 * @param string $type         Local Automator license type.
	 * @param bool   $is_connected Whether the local license is connected.
	 */
	public function __construct( string $type, bool $is_connected ) {
		$this->type         = $type;
		$this->is_connected = $is_connected;
	}

	/**
	 * Get the local license type.
	 *
	 * @return string
	 */
	public function type(): string {
		return $this->type;
	}

	/**
	 * Determine whether the local license is connected.
	 *
	 * @return bool
	 */
	public function is_connected(): bool {
		return $this->is_connected;
	}
}
