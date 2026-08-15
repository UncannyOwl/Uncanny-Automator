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
	private ?Feature_State $memoized_state = null;

	/**
	 * @param Policy_State_Port $policy_states Current policy-state provider.
	 */
	public function __construct( Policy_State_Port $policy_states ) {
		$this->policy_states = $policy_states;
	}

	/**
	 * Get a memoized feature state, hiding every feature when resolution fails.
	 *
	 * @return Feature_State
	 */
	public function execute(): Feature_State {
		if ( null !== $this->memoized_state ) {
			return $this->memoized_state;
		}

		try {
			$this->memoized_state = Feature_State_Policy::evaluate( $this->policy_states->get_state() );
		} catch ( \Throwable $error ) {
			// Technical unavailability is separate from the 15 business rows. We keep
			// it out of Policy_State and give every consumer the same memoized
			// all-hidden snapshot, avoiding retries and mixed UI within one request.
			unset( $error );
			$this->memoized_state = Feature_State::all_hidden();
		}

		return $this->memoized_state;
	}
}
