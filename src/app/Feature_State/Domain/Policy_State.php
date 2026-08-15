<?php
/**
 * Automator feature policy states.
 *
 * @package Uncanny_Automator
 * @since 7.5.1.1
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Feature_State\Domain;

/**
 * Identifies one license, connection, and allocation state in the feature policy.
 */
final class Policy_State {

	public const LITE_ONLY_NOT_CONNECTED                         = 'lite_only_not_connected';
	public const LITE_ONLY_CONNECTED_NEVER_HAD_ALLOCATION        = 'lite_only_connected_never_had_allocation';
	public const LITE_ONLY_CONNECTED_100_PERCENT_USED_ALLOCATION = 'lite_only_connected_100_percent_used_allocation';
	public const LITE_ONLY_CONNECTED_VALID_ALLOCATION            = 'lite_only_connected_valid_allocation';
	public const PRO_NO_LICENSE_ENTERED                          = 'pro_no_license_entered';
	public const PRO_INVALID_LICENSE_ENTERED                     = 'pro_invalid_license_entered';
	public const PRO_EXPIRED_LICENSE_ENTERED                     = 'pro_expired_license_entered';
	public const PRO_LEGACY_LICENSE_VALID_HEAD_START_ALLOCATION  = 'pro_legacy_license_valid_head_start_allocation';
	public const PRO_LEGACY_LICENSE_100_PERCENT_USED_OR_EXPIRED_HEAD_START_ALLOCATION = 'pro_legacy_license_100_percent_used_or_expired_head_start_allocation';
	public const PRO_LEGACY_LICENSE_NEVER_HAD_ALLOCATION                              = 'pro_legacy_license_never_had_allocation';
	public const PRO_AI_LICENSE_VALID_ALLOCATION                                      = 'pro_ai_license_valid_allocation';
	public const PRO_AI_LICENSE_100_PERCENT_USED_OR_EXPIRED_ALLOCATIONS               = 'pro_ai_license_100_percent_used_or_expired_allocations';
	public const PRO_AI_LICENSE_NO_ALLOCATION_HISTORY                                 = 'pro_ai_license_no_allocation_history';
	public const LIFETIME_LICENSE_NO_EXTENDED_SUPPORT                                 = 'lifetime_license_no_extended_support';
	public const LIFETIME_LICENSE_ACTIVE_EXTENDED_SUPPORT_LICENSE                     = 'lifetime_license_active_extended_support_license';

	/**
	 * Policy state value.
	 *
	 * @var string
	 */
	private string $value;

	/**
	 * Create a policy state.
	 *
	 * @param string $value Policy state value.
	 */
	private function __construct( string $value ) {
		$this->value = $value;
	}

	/**
	 * Create the Lite-only, not-connected state.
	 *
	 * @return self
	 */
	public static function lite_only_not_connected(): self {
		return new self( self::LITE_ONLY_NOT_CONNECTED );
	}

	/**
	 * Resolve a connected Lite-only state.
	 *
	 * @param Allocation_State $allocation_state Current allocation state.
	 *
	 * @return self
	 */
	public static function lite_only_connected( Allocation_State $allocation_state ): self {
		if ( Allocation_State::NEVER_HAD_ALLOCATION === $allocation_state->value() ) {
			return new self( self::LITE_ONLY_CONNECTED_NEVER_HAD_ALLOCATION );
		}

		if ( Allocation_State::USED_100_PERCENT_ALLOCATION === $allocation_state->value() ) {
			return new self( self::LITE_ONLY_CONNECTED_100_PERCENT_USED_ALLOCATION );
		}

		if ( Allocation_State::VALID_ALLOCATION === $allocation_state->value() ) {
			return new self( self::LITE_ONLY_CONNECTED_VALID_ALLOCATION );
		}

		throw new \InvalidArgumentException( 'The feature policy has no connected Lite-only state for an expired allocation.' );
	}

	/**
	 * Create the Pro, no-license-entered state.
	 *
	 * @return self
	 */
	public static function pro_no_license_entered(): self {
		return new self( self::PRO_NO_LICENSE_ENTERED );
	}

	/**
	 * Create the Pro, invalid-license-entered state.
	 *
	 * @return self
	 */
	public static function pro_invalid_license_entered(): self {
		return new self( self::PRO_INVALID_LICENSE_ENTERED );
	}

	/**
	 * Create the Pro, expired-license-entered state.
	 *
	 * @return self
	 */
	public static function pro_expired_license_entered(): self {
		return new self( self::PRO_EXPIRED_LICENSE_ENTERED );
	}

	/**
	 * Resolve a valid Pro legacy-license state.
	 *
	 * @param Allocation_State $allocation_state Current allocation state.
	 *
	 * @return self
	 */
	public static function pro_legacy_license( Allocation_State $allocation_state ): self {
		if ( Allocation_State::NEVER_HAD_ALLOCATION === $allocation_state->value() ) {
			return new self( self::PRO_LEGACY_LICENSE_NEVER_HAD_ALLOCATION );
		}

		if ( Allocation_State::VALID_ALLOCATION === $allocation_state->value() ) {
			return new self( self::PRO_LEGACY_LICENSE_VALID_HEAD_START_ALLOCATION );
		}

		if (
			in_array(
				$allocation_state->value(),
				array(
					Allocation_State::USED_100_PERCENT_ALLOCATION,
					Allocation_State::EXPIRED_ALLOCATION,
				),
				true
			)
		) {
			return new self( self::PRO_LEGACY_LICENSE_100_PERCENT_USED_OR_EXPIRED_HEAD_START_ALLOCATION );
		}

		throw new \InvalidArgumentException( 'The feature policy has no Pro legacy state for this allocation state.' );
	}

	/**
	 * Resolve a valid Pro AI-license state.
	 *
	 * @param Allocation_State $allocation_state Current allocation state.
	 *
	 * @return self
	 */
	public static function pro_ai_license( Allocation_State $allocation_state ): self {
		if ( Allocation_State::NEVER_HAD_ALLOCATION === $allocation_state->value() ) {
			return new self( self::PRO_AI_LICENSE_NO_ALLOCATION_HISTORY );
		}

		if ( Allocation_State::VALID_ALLOCATION === $allocation_state->value() ) {
			return new self( self::PRO_AI_LICENSE_VALID_ALLOCATION );
		}

		if (
			in_array(
				$allocation_state->value(),
				array(
					Allocation_State::USED_100_PERCENT_ALLOCATION,
					Allocation_State::EXPIRED_ALLOCATION,
				),
				true
			)
		) {
			return new self( self::PRO_AI_LICENSE_100_PERCENT_USED_OR_EXPIRED_ALLOCATIONS );
		}

		throw new \InvalidArgumentException( 'The feature policy has no Pro AI state for this allocation state.' );
	}

	/**
	 * Resolve a lifetime-license state.
	 *
	 * @param bool $has_active_extended_support_license Whether the extended support license is active.
	 *
	 * @return self
	 */
	public static function lifetime_license( bool $has_active_extended_support_license ): self {
		return new self(
			$has_active_extended_support_license
				? self::LIFETIME_LICENSE_ACTIVE_EXTENDED_SUPPORT_LICENSE
				: self::LIFETIME_LICENSE_NO_EXTENDED_SUPPORT
		);
	}

	/**
	 * Get all policy states for release 7.5.1.1.
	 *
	 * @return string[]
	 */
	public static function get_all(): array {
		return array(
			self::LITE_ONLY_NOT_CONNECTED,
			self::LITE_ONLY_CONNECTED_NEVER_HAD_ALLOCATION,
			self::LITE_ONLY_CONNECTED_100_PERCENT_USED_ALLOCATION,
			self::LITE_ONLY_CONNECTED_VALID_ALLOCATION,
			self::PRO_NO_LICENSE_ENTERED,
			self::PRO_INVALID_LICENSE_ENTERED,
			self::PRO_EXPIRED_LICENSE_ENTERED,
			self::PRO_LEGACY_LICENSE_VALID_HEAD_START_ALLOCATION,
			self::PRO_LEGACY_LICENSE_100_PERCENT_USED_OR_EXPIRED_HEAD_START_ALLOCATION,
			self::PRO_LEGACY_LICENSE_NEVER_HAD_ALLOCATION,
			self::PRO_AI_LICENSE_VALID_ALLOCATION,
			self::PRO_AI_LICENSE_100_PERCENT_USED_OR_EXPIRED_ALLOCATIONS,
			self::PRO_AI_LICENSE_NO_ALLOCATION_HISTORY,
			self::LIFETIME_LICENSE_NO_EXTENDED_SUPPORT,
			self::LIFETIME_LICENSE_ACTIVE_EXTENDED_SUPPORT_LICENSE,
		);
	}

	/**
	 * Get the policy state value.
	 *
	 * @return string
	 */
	public function value(): string {
		return $this->value;
	}
}
