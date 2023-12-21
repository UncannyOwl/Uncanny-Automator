<?php

namespace Uncanny_Automator\Integrations\Thrive_Ultimatum;

/**
 * Class THRIVE_ULTIMATUM_EVERGREEN_CAMPAIGN_TRIGGERED
 *
 * @package Uncanny_Automator
 */
class THRIVE_ULTIMATUM_EVERGREEN_CAMPAIGN_TRIGGERED extends \Uncanny_Automator\Recipe\Trigger {

	protected $helpers;

	/**
	 * @return mixed|void
	 */
	protected function setup_trigger() {
		$this->helpers = array_shift( $this->dependencies );
		$this->set_integration( 'THRIVE_ULTIMATUM' );
		$this->set_trigger_code( 'THRIVE_ULT_CAMPAIGN_TRIGGERED' );
		$this->set_trigger_meta( 'EVERGREEN_CAMPAIGN' );
		$this->set_trigger_type( 'anonymous' );
		// Trigger sentence - Thrive Ultimatum
		$this->set_sentence( esc_attr_x( 'An evergreen campaign is triggered', 'Thrive Ultimatum', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_attr_x( 'An evergreen campaign is triggered', 'Thrive Ultimatum', 'uncanny-automator' ) );
		$this->add_action( 'thrive_ultimatum_evergreen_campaign_start', 20, 2 );
	}

	/**
	 * @param $trigger
	 * @param $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		if ( ! isset( $hook_args[0] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Define Tokens.
	 *
	 * @param array $tokens
	 * @param array $trigger - options selected in the current recipe/trigger
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		$campaign_tokens = $this->helpers->get_all_campaign_tokens();

		return array_merge( $campaign_tokens, $tokens );
	}

	/**
	 * Hydrate Tokens.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		$campaign = $hook_args[0];

		return $this->helpers->parse_all_token_values( $campaign );
	}

}
