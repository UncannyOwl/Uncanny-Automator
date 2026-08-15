<?php
/**
 * Automator allocation states.
 *
 * @package Uncanny_Automator
 * @since 7.5.1.1
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Feature_State\Domain;

/**
 * Identifies one allocation state from the feature policy.
 */
final class Allocation_State {

	public const VALID_ALLOCATION            = 'valid_allocation';
	public const USED_100_PERCENT_ALLOCATION = '100_percent_used_allocation';
	public const EXPIRED_ALLOCATION          = 'expired_allocation';
	public const NEVER_HAD_ALLOCATION        = 'never_had_allocation';

	/**
	 * Allocation state value.
	 *
	 * @var string
	 */
	private string $value;

	/**
	 * Create an allocation state.
	 *
	 * @param string $value Allocation state value.
	 */
	private function __construct( string $value ) {
		$this->value = $value;
	}

	/**
	 * Create a valid-allocation state.
	 *
	 * @return self
	 */
	public static function valid_allocation(): self {
		return new self( self::VALID_ALLOCATION );
	}

	/**
	 * Create a 100-percent-used-allocation state.
	 *
	 * @return self
	 */
	public static function used_100_percent_allocation(): self {
		return new self( self::USED_100_PERCENT_ALLOCATION );
	}

	/**
	 * Create an expired-allocation state.
	 *
	 * @return self
	 */
	public static function expired_allocation(): self {
		return new self( self::EXPIRED_ALLOCATION );
	}

	/**
	 * Create a never-had-allocation state.
	 *
	 * @return self
	 */
	public static function never_had_allocation(): self {
		return new self( self::NEVER_HAD_ALLOCATION );
	}

	/**
	 * Get the allocation state value.
	 *
	 * @return string
	 */
	public function value(): string {
		return $this->value;
	}
}
