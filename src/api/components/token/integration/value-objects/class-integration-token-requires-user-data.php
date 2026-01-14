<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Token\Integration\Value_Objects;

/**
 * Integration Token Requires User Value Object.
 *
 * Represents whether the integration token requires user data.
 *
 * This is for integration-level tokens (tokens provided by integrations).
 *
 * @since 7.0.0
 */
class Integration_Token_Requires_User_Data {

	/**
	 * The integration token requires user data value.
	 *
	 * @var bool
	 */
	private bool $value;

	/**
	 * Constructor.
	 *
	 * @param bool $value Integration token requires user data value state.
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
	 * Check if requires user data.
	 *
	 * @return bool
	 */
	public function requires_user(): bool {
		return $this->value;
	}

	/**
	 * Check if does not require user data.
	 *
	 * @return bool
	 */
	public function does_not_require_user(): bool {
		return ! $this->value;
	}
}
