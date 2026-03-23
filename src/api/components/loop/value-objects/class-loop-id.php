<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Loop\Value_Objects;

use Uncanny_Automator\Api\Components\Interfaces\Parent_Id;

/**
 * Loop ID Value Object.
 *
 * Immutable value object that validates and encapsulates loop identifier.
 * Implements Parent_Id interface to allow it to be used as a parent reference for actions.
 *
 * @since 7.0.0
 */
class Loop_Id implements Parent_Id {

	/**
	 * The loop ID value.
	 *
	 * @var int|null
	 */
	private $value;

	/**
	 * Constructor.
	 *
	 * @param int|null $value Loop ID or null for new loops.
	 * @throws \InvalidArgumentException If ID is invalid.
	 */
	public function __construct( $value ) {
		if ( null !== $value ) {
			$value = (int) $value;
			if ( $value <= 0 ) {
				throw new \InvalidArgumentException( 'Loop ID must be a positive integer or null' );
			}
		}
		$this->value = $value;
	}

	/**
	 * Get value.
	 *
	 * @return int|null Loop ID or null.
	 */
	public function get_value(): ?int {
		return $this->value;
	}

	/**
	 * Check if the loop ID is null (unsaved loop).
	 *
	 * @return bool True if null, false otherwise.
	 */
	public function is_null(): bool {
		return null === $this->value;
	}
}
