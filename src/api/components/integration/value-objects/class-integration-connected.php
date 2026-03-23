<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Integration\Value_Objects;

/**
 * Integration Connected Value Object.
 *
 * Indicates if a global/site-level account for this app integration is currently connected.
 * Only applicable for "app" type integrations.
 *
 * @since 7.0.0
 */
class Integration_Connected {

	/**
	 * The connected value.
	 *
	 * @var bool
	 */
	private bool $value;

	/**
	 * Constructor.
	 *
	 * @param bool $value Connected status.
	 *
	 * @return void
	 */
	public function __construct( bool $value ) {
		$this->value = $value;
	}

	/**
	 * Get value.
	 *
	 * @return bool
	 */
	public function get_value(): bool {
		return $this->value;
	}

	/**
	 * Check if connected.
	 *
	 * @return bool
	 */
	public function is_connected(): bool {
		return $this->value;
	}

	/**
	 * Check if disconnected.
	 *
	 * @return bool
	 */
	public function is_disconnected(): bool {
		return ! $this->value;
	}
}
