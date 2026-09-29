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

	public const SOURCE_RESOLVED            = 'resolved';
	public const SOURCE_LAST_KNOWN_GOOD     = 'last_known_good';
	public const SOURCE_ALL_HIDDEN_FALLBACK = 'all_hidden_fallback';
	public const ERROR_RESOLUTION_FAILED    = 'Feature visibility could not be determined from the available facts.';

	private Policy_State_Port $policy_states;
	private ?Last_Known_Feature_State_Store $last_known_good_states;
	private ?Feature_State $memoized_state = null;
	private string $resolution_source      = self::SOURCE_ALL_HIDDEN_FALLBACK;
	private ?string $policy_state          = null;
	private ?string $resolution_error      = null;

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
		$this->memoized_state    = $last_known_good_state ?? Feature_State::all_hidden();
		$this->resolution_source = null !== $last_known_good_state ? self::SOURCE_LAST_KNOWN_GOOD : self::SOURCE_ALL_HIDDEN_FALLBACK;

		try {
			$policy_state       = $this->policy_states->get_state();
			$this->policy_state = $policy_state->value();
			$resolved_state     = Feature_State_Policy::evaluate( $policy_state );
		} catch ( \Throwable $error ) {
			// Ports and third-party hooks may put credentials in exception messages.
			// Expose a fixed diagnostic reason rather than retaining untrusted text.
			unset( $error );
			$this->resolution_error = self::ERROR_RESOLUTION_FAILED;
			return $this->memoized_state;
		}

		$this->resolution_source = self::SOURCE_RESOLVED;
		$this->memoized_state    = $resolved_state;
		$this->save_last_known_good_state( $resolved_state );

		return $this->memoized_state;
	}

	/**
	 * Describe the same memoized decision used by product surfaces.
	 *
	 * Reading diagnostics never retries resolution or replaces a request fallback.
	 * Failure reasons are fixed text; raw exception messages are never exposed.
	 *
	 * @return array<string,mixed>
	 */
	public function diagnostics(): array {
		$state = $this->execute();

		return array(
			'source'       => $this->resolution_source,
			'policy_state' => $this->policy_state,
			'error'        => $this->resolution_error,
			'visibility'   => $state->to_array(),
		);
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
