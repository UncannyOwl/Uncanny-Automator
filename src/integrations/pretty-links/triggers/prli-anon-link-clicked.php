<?php

namespace Uncanny_Automator\Integrations\Pretty_Links;

/**
 * Class PRLI_ANON_LINK_CLICKED
 *
 * @package Uncanny_Automator
 */
class PRLI_ANON_LINK_CLICKED extends \Uncanny_Automator\Recipe\Trigger {

	protected $helpers;

	/**
	 * @return mixed|void
	 */
	protected function setup_trigger() {
		$this->helpers = array_shift( $this->dependencies );
		$this->set_integration( 'PRETTY_LINKS' );
		$this->set_trigger_code( 'PRLI_ANON_LINK_CLICKED' );
		$this->set_trigger_meta( 'PRLI_LINKS' );
		$this->set_trigger_type( 'anonymous' );
		// Trigger sentence - Pretty Links
		$this->set_sentence( sprintf( esc_attr_x( '{{A pretty link:%1$s}} is clicked', 'Pretty Links', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_attr_x( '{{A pretty link}} is clicked', 'Pretty Links', 'uncanny-automator' ) );
		$this->add_action( 'prli_record_click' );
	}

	/**
	 * @return array[]
	 */
	public function options() {
		return array(
			Automator()->helpers->recipe->field->select_field_args(
				array(
					'input_type'      => 'select',
					'option_code'     => $this->get_trigger_meta(),
					'label'           => _x( 'Pretty link', 'Pretty Links', 'uncanny-automator' ),
					'required'        => true,
					'options'         => $this->helpers->get_all_pretty_links( true ),
					'options_show_id' => false,
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

		// Fail the trigger if incoming link_id from action hook is not set.
		if ( ! isset( $hook_args[0]['link_id'] ) ) {
			return false;
		}

		$selected_link_id = $trigger['meta'][ $this->get_trigger_meta() ];
		// Any pretty link
		if ( intval( '-1' ) === intval( $selected_link_id ) ) {
			return true;
		}

		if ( absint( $hook_args[0]['link_id'] ) === absint( $selected_link_id ) ) {
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
		$prli_tokens = $this->helpers->prli_common_tokens_for_link_clicked();

		return array_merge( $tokens, $prli_tokens );
	}

	/**
	 * Populate the tokens with actual values when a trigger runs.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		$parse_token_values = array();

		if ( ! empty( $hook_args ) ) {
			// Hydrate giveaways tokens.
			$parse_token_values = $this->helpers->hydrate_prli_link_clicked_tokens( $hook_args );
		}

		return $parse_token_values;
	}

}
