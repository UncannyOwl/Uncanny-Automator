<?php

namespace Uncanny_Automator\Integrations\Pretty_Links;

/**
 * Class PRLI_ANON_CREATE_A_LINK
 *
 * @package Uncanny_Automator
 */
class PRLI_ANON_CREATE_A_LINK extends \Uncanny_Automator\Recipe\Trigger {

	protected $helpers;

	/**
	 * @return mixed|void
	 */
	protected function setup_trigger() {
		$this->helpers = array_shift( $this->dependencies );
		$this->set_integration( 'PRETTY_LINKS' );
		$this->set_trigger_code( 'PRLI_ANON_CREATE_LINK' );
		$this->set_trigger_meta( 'PRLI_REDIRECTION' );
		$this->set_trigger_type( 'anonymous' );
		// Trigger sentence - Pretty Links
		$this->set_sentence( sprintf( esc_attr_x( 'A pretty link of {{a specific redirect type:%1$s}} is created', 'Pretty Links', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'A pretty link of {{a specific redirect type}} is created', 'Pretty Links', 'uncanny-automator' ) );
		$this->add_action( 'prli-create-link', 10, 2 );
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
					'label'           => _x( 'Redirection type', 'Pretty Links', 'uncanny-automator' ),
					'required'        => true,
					'options'         => $this->helpers->get_all_redirection_types( true ),
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

		list( $id, $args ) = $hook_args;

		// Fail the trigger if incoming redirect type from action hook is not set.
		if ( ! isset( $args['redirect_type'] ) ) {
			return false;
		}

		$selected_redirection_type = $trigger['meta'][ $this->get_trigger_meta() ];
		// Any redirection type
		if ( intval( '-1' ) === intval( $selected_redirection_type ) ) {
			return true;
		}

		if ( absint( $args['redirect_type'] ) === absint( $selected_redirection_type ) ) {
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
		$prli_tokens = $this->helpers->prli_common_tokens_for_link_created();

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
			$parse_token_values = $this->helpers->hydrate_prli_common_tokens( $hook_args );
		}

		return $parse_token_values;
	}

}
