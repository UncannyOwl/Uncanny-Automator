<?php
/**
 * Automator allocation facts used by the feature policy.
 *
 * @package Uncanny_Automator
 * @since 7.5.1.1
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Feature_State\Domain;

/**
 * Reduces a validated allocation ledger to policy-relevant observations.
 */
final class Allocation_Facts {

	private int $active_allocations;
	private int $used_allocations;
	private int $expired_allocations;
	private Pending_First_Use_Allocation $pending_first_use_allocation;

	/**
	 * @param int $active_allocations  Active allocation rows.
	 * @param int $used_allocations    Fully used allocation rows.
	 * @param int $expired_allocations Expired allocation rows.
	 * @param Pending_First_Use_Allocation|null $pending_first_use_allocation Pending grant eligibility.
	 */
	private function __construct(
		int $active_allocations,
		int $used_allocations,
		int $expired_allocations,
		?Pending_First_Use_Allocation $pending_first_use_allocation
	) {
		if (
			$active_allocations < 0
			|| $used_allocations < 0
			|| $expired_allocations < 0
		) {
			throw new \InvalidArgumentException( 'The allocation facts contain impossible row counts.' );
		}

		$this->active_allocations           = $active_allocations;
		$this->used_allocations             = $used_allocations;
		$this->expired_allocations          = $expired_allocations;
		$this->pending_first_use_allocation = $pending_first_use_allocation ?? Pending_First_Use_Allocation::none();

		if (
			! $this->pending_first_use_allocation->is_none()
			&& ( $active_allocations > 0 || $used_allocations > 0 || $expired_allocations > 0 )
		) {
			throw new \InvalidArgumentException( 'A pending first-use allocation cannot accompany observed allocation rows.' );
		}
	}

	/**
	 * Create facts observed from one successful allocation response.
	 *
	 * @param int $active_allocations  Active allocation rows.
	 * @param int $used_allocations    Fully used allocation rows.
	 * @param int $expired_allocations Expired allocation rows.
	 * @param Pending_First_Use_Allocation|null $pending_first_use_allocation Pending grant eligibility.
	 *
	 * @return self
	 */
	public static function observed(
		int $active_allocations,
		int $used_allocations,
		int $expired_allocations,
		?Pending_First_Use_Allocation $pending_first_use_allocation = null
	): self {
		return new self(
			$active_allocations,
			$used_allocations,
			$expired_allocations,
			$pending_first_use_allocation
		);
	}

	/**
	 * Get the independently observed pending-grant fact.
	 *
	 * @return Pending_First_Use_Allocation
	 */
	public function pending_first_use_allocation(): Pending_First_Use_Allocation {
		return $this->pending_first_use_allocation;
	}

	/**
	 * Resolve the allocation vocabulary represented by the observations.
	 *
	 * @return Allocation_State
	 */
	public function state(): Allocation_State {
		// MCP accounting classifies every allocation at one observation time.
		// Active takes precedence because the account can also contain old history.
		if ( $this->active_allocations > 0 ) {
			return Allocation_State::valid_allocation();
		}

		if ( 0 === $this->used_allocations && 0 === $this->expired_allocations ) {
			return Allocation_State::never_had_allocation();
		}

		// Expiry has precedence over full use when the remaining collection has
		// both states. Policy_State decides which license types support that state.
		if ( $this->expired_allocations > 0 ) {
			return Allocation_State::expired_allocation();
		}

		return Allocation_State::used_100_percent_allocation();
	}
}
