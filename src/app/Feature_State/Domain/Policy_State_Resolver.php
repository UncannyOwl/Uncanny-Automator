<?php
/**
 * Automator policy state resolver.
 *
 * @package Uncanny_Automator
 * @since 7.5.1.1
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Feature_State\Domain;

/**
 * Classifies authoritative facts into the Release 7.5.1.1 Axis vocabulary.
 *
 * This is the only layer allowed to decide which Policy_State the observed
 * license and allocation facts represent. Infrastructure supplies facts; the
 * feature policy later projects the resulting row into visibility decisions.
 */
final class Policy_State_Resolver {

	/**
	 * Storefront identities pinned for release 7.5.1.1.
	 *
	 * Price ID 3 is deliberately absent: the release catalog does not identify
	 * it as either Legacy or AI + Automation, so it remains unresolved.
	 */
	private const AUTOMATOR_PRO_DOWNLOAD_ID = 506;
	private const LEGACY_PRICE_IDS          = array( 1, 2, 4 );
	private const AI_PRICE_IDS              = array( 5, 6, 7, 8, 9, 10 );

	/**
	 * Resolve one policy state.
	 *
	 * @param License_Facts         $license    License observations.
	 * @param Allocation_Facts|null $allocation Allocation observations, when required.
	 *
	 * @return Policy_State
	 */
	public static function resolve( License_Facts $license, ?Allocation_Facts $allocation ): Policy_State {
		if ( License_Facts::LITE_ONLY === $license->installation() ) {
			return self::resolve_lite_only( $license, $allocation );
		}

		if ( License_Facts::PRO !== $license->installation() ) {
			throw new \UnexpectedValueException( 'The feature policy does not recognize the installation kind.' );
		}

		// Order is part of the policy. We check for no key first so a stale status
		// cannot change that row; exact "expired" is its own row; every other
		// non-valid status is the invalid-license row. None needs remote evidence.
		if ( ! $license->license_entered() ) {
			return Policy_State::pro_no_license_entered();
		}

		if ( 'expired' === $license->license_status() ) {
			return Policy_State::pro_expired_license_entered();
		}

		if ( 'valid' !== $license->license_status() ) {
			return Policy_State::pro_invalid_license_entered();
		}

		if ( true === $license->is_lifetime() ) {
			// Lifetime has two explicit Axis rows. The confirmed extended-support fact
			// selects between them, keeping plan-name guesses out of the Domain.
			if ( null === $license->has_active_extended_support_license() ) {
				throw new \UnexpectedValueException( 'The lifetime policy requires an extended-support license fact.' );
			}

			return Policy_State::lifetime_license( $license->has_active_extended_support_license() );
		}

		// From here onward the license is valid and non-lifetime, so catalog and
		// allocation evidence are mandatory. Unknown evidence is not a new Axis row.
		if ( self::AUTOMATOR_PRO_DOWNLOAD_ID !== $license->download_id() ) {
			throw new \UnexpectedValueException( 'The valid Pro license has an unknown product identifier.' );
		}

		if ( false !== $license->is_lifetime() || null === $allocation ) {
			throw new \UnexpectedValueException( 'The valid Pro license facts are incomplete.' );
		}

		$allocation_state = $allocation->state();
		$pending_grant    = $allocation->pending_first_use_allocation();
		$price_id         = $license->price_id();

		if ( in_array( $price_id, self::LEGACY_PRICE_IDS, true ) ) {
			if ( Pending_First_Use_Allocation::LEGACY_HEAD_START === $pending_grant->value() ) {
				// MCP owns the lazy Head Start lifecycle. The ledger remains truthfully
				// empty until first use, while this eligibility fact selects the existing
				// product-access row that makes first use reachable.
				return Policy_State::pro_legacy_license( Allocation_State::valid_allocation() );
			}

			if ( ! $pending_grant->is_none() ) {
				throw new \UnexpectedValueException( 'The pending first-use allocation does not match the Legacy license.' );
			}

			return Policy_State::pro_legacy_license( $allocation_state );
		}

		if ( in_array( $price_id, self::AI_PRICE_IDS, true ) ) {
			if ( ! $pending_grant->is_none() ) {
				throw new \UnexpectedValueException( 'An AI plan cannot have a pending first-use allocation.' );
			}

			// A successful empty observation is an explicit AI policy row. Product
			// surfaces stay available; chat reports any allocation failure to the user.
			return Policy_State::pro_ai_license( $allocation_state );
		}

		throw new \UnexpectedValueException( 'The valid Pro license has an unknown price identifier.' );
	}

	/**
	 * Resolve a Lite-only state.
	 *
	 * @param License_Facts         $license    License observations.
	 * @param Allocation_Facts|null $allocation Allocation observations, when connected.
	 *
	 * @return Policy_State
	 */
	private static function resolve_lite_only( License_Facts $license, ?Allocation_Facts $allocation ): Policy_State {
		// Disconnected Lite is locally conclusive, so no ledger is needed.
		if ( ! $license->connected() ) {
			return Policy_State::lite_only_not_connected();
		}

		if ( null === $allocation ) {
			throw new \UnexpectedValueException( 'A connected Lite account requires allocation facts.' );
		}

		$pending_grant = $allocation->pending_first_use_allocation();

		if ( Pending_First_Use_Allocation::PHASE_1_LITE === $pending_grant->value() ) {
			// Phase 1 deliberately grants credits on first product use. This fact
			// selects the existing valid-access row without changing the truthful
			// empty-ledger observation held by Allocation_Facts.
			return Policy_State::lite_only_connected( Allocation_State::valid_allocation() );
		}

		if ( ! $pending_grant->is_none() ) {
			throw new \UnexpectedValueException( 'The pending first-use allocation does not match the Lite account.' );
		}

		return Policy_State::lite_only_connected( $allocation->state() );
	}
}
