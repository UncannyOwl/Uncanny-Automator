<?php

namespace Uncanny_Automator\Integrations\Beaver_Builder;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Bb_Subscribe_Form_Submitted
 *
 * Fires when a logged-in user submits a Beaver Builder Subscribe Form module.
 * The "Everyone" (anonymous) variant lives in Automator Pro.
 *
 * @package Uncanny_Automator\Integrations\Beaver_Builder
 *
 * @property Beaver_Builder_Helpers $item_helpers
 */
class Bb_Subscribe_Form_Submitted extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'BB_SUBSCRIBE_FORM_SUBMITTED', 'BEAVER_BUILDER' )
			->trigger_meta( 'BB_SUBSCRIBE_FORM' )
			->hook( 'fl_builder_subscribe_form_submission_complete', 10, 6 );
	}

	/**
	 * The Subscribe Form module ships only with Beaver Builder Pro.
	 *
	 * @return bool
	 */
	public function requirements_met() {
		return class_exists( 'FLSubscribeFormModule' );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_sentence(
			sprintf(
				/* translators: 1: Subscribe form */
				esc_html_x( 'A user submits {{a subscribe form:%1$s}}', 'Beaver Builder', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'A user submits {{a subscribe form}}', 'Beaver Builder', 'uncanny-automator' ) );
	}

	/**
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Subscribe form', 'Beaver Builder', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'subscribe_forms' ),
			),
		);
	}

	/**
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge( $tokens, $this->item_helpers->subscribe_form_tokens() );
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		if ( ! isset( $hook_args[0] ) ) {
			return false;
		}

		// Logged-in half of the pair — the anonymous "Everyone" variant in Pro
		// handles submissions by visitors who are not signed in.
		$user_id = get_current_user_id();
		if ( 0 === $user_id ) {
			return false;
		}

		// Hook fires on service success AND failure — only run for a successful
		// subscription (empty error in the service response).
		$response = is_array( $hook_args[0] ) ? $hook_args[0] : array();
		if ( ! empty( $response['error'] ) ) {
			return false;
		}

		// Match on the module node ID ($_POST['node_id']) — the stable per-form
		// identifier the picker keys options by.
		$node_id  = sanitize_text_field( (string) automator_filter_input( 'node_id', INPUT_POST ) );
		$selected = isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ? (string) $trigger['meta'][ $this->get_trigger_meta() ] : Beaver_Builder_Helpers::ANY_VALUE;

		if ( Beaver_Builder_Helpers::ANY_VALUE !== $selected && $selected !== $node_id ) {
			return false;
		}

		$this->set_user_id( $user_id );

		return true;
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		return $this->item_helpers->hydrate_subscribe_form_tokens( $hook_args );
	}
}
