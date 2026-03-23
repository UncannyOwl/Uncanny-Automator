<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\User_Selector\Value_Objects;

/**
 * User Selector ID Value Object.
 *
 * Immutable value object that validates and encapsulates user selector identifier.
 * Ensures ID integrity and manages null states for new user selector instances.
 *
 * @since 7.0.0
 */
class User_Selector_Id {

	/**
	 * The user selector ID value.
	 *
	 * @var int|null
	 */
	private $value;

	/**
	 * Constructor.
	 *
	 * @param int|null $value User selector ID or null for new instances.
	 * @throws \InvalidArgumentException If ID is invalid.
	 */
	public function __construct( $value ) {
		if ( null !== $value ) {
			$value = (int) $value;
			if ( $value <= 0 ) {
				throw new \InvalidArgumentException( 'User selector ID must be a positive integer or null' );
			}
		}
		$this->value = $value;
	}

	/**
	 * Get value.
	 *
	 * @return int|null
	 */
	public function get_value(): ?int {
		return $this->value;
	}
}
