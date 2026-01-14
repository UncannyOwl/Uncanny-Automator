<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Integration\Value_Objects;

use InvalidArgumentException;

/**
 * Integration Required Tier Value Object.
 *
 * Represents the required license tier to access this integration.
 * Valid values: "lite", "pro-basic", "pro-plus", "pro-elite"
 *
 * @since 7.0.0
 */
class Integration_Required_Tier {

	/**
	 * The required tier value.
	 *
	 * @var string
	 */
	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Required tier value.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid tier.
	 */
	public function __construct( string $value ) {
		$this->validate( $value );
		$this->value = $value;
	}

	/**
	 * Get value.
	 *
	 * @return string
	 */
	public function get_value(): string {
		return $this->value;
	}

	/**
	 * Check if tier is lite.
	 *
	 * @return bool
	 */
	public function is_lite(): bool {
		return 'lite' === $this->value;
	}

	/**
	 * Check if tier is pro (any level).
	 *
	 * @return bool
	 */
	public function is_pro(): bool {
		return in_array( $this->value, array( 'pro-basic', 'pro-plus', 'pro-elite' ), true );
	}

	/**
	 * Check if tier is pro-basic.
	 *
	 * @return bool
	 */
	public function is_pro_basic(): bool {
		return 'pro-basic' === $this->value;
	}

	/**
	 * Check if tier is pro-plus.
	 *
	 * @return bool
	 */
	public function is_pro_plus(): bool {
		return 'pro-plus' === $this->value;
	}

	/**
	 * Check if tier is pro-elite.
	 *
	 * @return bool
	 */
	public function is_pro_elite(): bool {
		return 'pro-elite' === $this->value;
	}

	/**
	 * Validate required tier.
	 *
	 * @param string $value Value to validate.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid.
	 */
	private function validate( string $value ): void {
		$valid_tiers = array( 'lite', 'pro-basic', 'pro-plus', 'pro-elite' );

		if ( ! in_array( $value, $valid_tiers, true ) ) {
			throw new InvalidArgumentException(
				'Integration required tier must be one of: ' . implode( ', ', $valid_tiers ) . ', got: ' . $value
			);
		}
	}
}
