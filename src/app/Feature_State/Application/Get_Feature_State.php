<?php
/**
 * Get the current Automator feature state.
 *
 * @package Uncanny_Automator
 * @since 7.5.1.1
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Feature_State\Application;

use Uncanny_Automator\App\Feature_State\Domain\Feature_State;
use Uncanny_Automator\App\Feature_State\Domain\Feature_State_Policy;
use Uncanny_Automator\App\Feature_State\Ports\Last_Known_Feature_State_Store;
use Uncanny_Automator\App\Feature_State\Ports\Policy_State_Port;

/**
 * Application query for the request-wide feature visibility decision.
 *
 * Consumers ask this use case instead of reading licenses themselves. One
 * request therefore gets one coherent snapshot across settings, menus, Agent
 * presentation, and Setup Wizard, regardless of which consumer asks first.
 */
final class Get_Feature_State {

	private Policy_State_Port $policy_states;
	private ?Last_Known_Feature_State_Store $last_known_good_states;
	private ?Feature_State $memoized_state = null;

	/**
	 * The optional store preserves the existing one-argument construction contract.
	 * Callers without a store still fail closed and never manufacture persistence.
	 *
	 * @param Policy_State_Port                   $policy_states          Current policy-state provider.
	 * @param Last_Known_Feature_State_Store|null $last_known_good_states Successful-state store.
	 */
	public function __construct(
		Policy_State_Port $policy_states,
		?Last_Known_Feature_State_Store $last_known_good_states = null
	) {
		$this->policy_states          = $policy_states;
		$this->last_known_good_states = $last_known_good_states;
	}

	/**
	 * Get a memoized feature state, preserving the last successful state on failure.
	 *
	 * @return Feature_State
	 */
	public function execute(): Feature_State {
		if ( null !== $this->memoized_state ) {
			return $this->memoized_state;
		}

		$last_known_good_state = $this->load_last_known_good_state();

		// Establish the request fallback before attempting fresh resolution. This
		// all-hidden default is never persisted unless policy evaluation itself
		// successfully produces an all-hidden business state.
		$this->memoized_state = $last_known_good_state ?? Feature_State::all_hidden();

		try {
			$resolved_state = Feature_State_Policy::evaluate( $this->policy_states->get_state() );
		} catch ( \Throwable $error ) {
			unset( $error );
			return $this->memoized_state;
		}

		$this->memoized_state = $resolved_state;
		$this->save_last_known_good_state( $resolved_state );

		return $this->memoized_state;
	}

	/**
	 * Load the fallback before policy resolution without allowing storage errors
	 * to become feature-state decisions.
	 *
	 * @return Feature_State|null
	 */
	private function load_last_known_good_state(): ?Feature_State {
		if ( null === $this->last_known_good_states ) {
			return null;
		}

		try {
			return $this->last_known_good_states->load();
		} catch ( \Throwable $error ) {
			unset( $error );
			return null;
		}
	}

	/**
	 * Persist only a state produced by successful policy evaluation.
	 *
	 * @param Feature_State $state Successfully evaluated state.
	 *
	 * @return void
	 */
	private function save_last_known_good_state( Feature_State $state ): void {
		if ( null === $this->last_known_good_states ) {
			return;
		}

		try {
			$this->last_known_good_states->save( $state );
		} catch ( \Throwable $error ) {
			unset( $error );
		}
	}
}
