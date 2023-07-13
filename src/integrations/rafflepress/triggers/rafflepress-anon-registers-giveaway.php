<?php

namespace Uncanny_Automator\Integrations\RafflePress;

/**
 * Class RAFFLEPRESS_ANON_REGISTERS_GIVEAWAY
 *
 * @package Uncanny_Automator
 */
class RAFFLEPRESS_ANON_REGISTERS_GIVEAWAY extends \Uncanny_Automator\Recipe\Trigger {

	protected $helpers;

	/**
	 * @return mixed|void
	 */
	protected function setup_trigger() {
		$this->helpers = array_shift( $this->dependencies );
		$this->set_integration( 'RAFFLE_PRESS' );
		$this->set_trigger_code( 'ANON_REGISTERED_FOR_GIVEAWAY' );
		$this->set_trigger_meta( 'RP_GIVEAWAYS' );
		$this->set_trigger_type( 'anonymous' );
		// Trigger sentence - RafflePress
		$this->set_sentence( sprintf( esc_attr_x( 'Someone registers for {{a giveaway:%1$s}}', 'RafflePress', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'Someone registers for {{a giveaway}}', 'RafflePress', 'uncanny-automator' ) );
		$this->add_action( 'rafflepress_post_entry_add', 90, 4 );
	}

	/**
	 * @return array[]
	 */
	public function options() {
		return array(
			Automator()->helpers->recipe->field->select(
				array(
					'option_code'     => $this->get_trigger_meta(),
					'label'           => esc_attr_x( 'Giveaway', 'RafflePress', 'uncanny-automator' ),
					// Load the options from the helpers file
					'options'         => $this->helpers->get_all_rafflepress_giveaway(),
					'relevant_tokens' => array(),
				)
			),
		);
	}

	/**
	 * @param $trigger
	 * @param $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return false;
		}

		if ( ! isset( $hook_args[0]['giveaway_id'] ) ) {
			return false;
		}

		$selected_giveaway_id = intval( $trigger['meta'][ $this->get_trigger_meta() ] );
		// Any giveaway
		if ( intval( '-1' ) === $selected_giveaway_id ) {
			return true;
		}

		if ( intval( $hook_args[0]['giveaway_id'] ) === $selected_giveaway_id ) {
			return true;
		}

		return false;
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
		$giveaway_tokens   = $this->helpers->rafflepress_common_tokens_for_giveaway();
		$contestant_tokens = $this->helpers->rafflepress_common_tokens_for_contestant();

		return array_merge( $tokens, $giveaway_tokens, $contestant_tokens );
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
		$giveaway_tokens   = array();
		$contestant_tokens = array();

		if ( ! empty( $hook_args[0]['giveaway_id'] ) ) {
			// Hydrate giveaways tokens.
			$giveaway_tokens = $this->helpers->hydrate_giveaway_tokens( $hook_args[0]['giveaway_id'] );
		}

		if ( ! empty( $hook_args[0]['contestant_id'] ) ) {
			// Hydrate contestant tokens.
			$contestant_tokens = $this->helpers->hydrate_contestant_tokens( $hook_args[0]['contestant_id'] );
		}

		return array_merge( $giveaway_tokens, $contestant_tokens );
	}

}
