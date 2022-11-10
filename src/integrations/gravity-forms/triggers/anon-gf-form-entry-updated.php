<?php
namespace Uncanny_Automator;

class ANON_GF_FORM_ENTRY_UPDATED {

	use Recipe\Triggers;

	const TRIGGER_CODE = 'ANON_GF_FORM_ENTRY_UPDATED';

	const TRIGGER_META = 'ANON_GF_FORM_ENTRY_UPDATED_META';

	public function __construct() {

		$this->setup_trigger();

	}

	/**
	 * Continue trigger process even for logged-in user.
	 *
	 * @return boolean True, always.
	 */
	public function do_continue_anon_trigger( ...$args ) {

		return true;

	}

	public function setup_trigger() {

		$this->set_integration( 'GF' );

		$this->set_trigger_code( self::TRIGGER_CODE );

		$this->set_trigger_meta( self::TRIGGER_META );

		$this->set_is_login_required( false );

		$this->set_trigger_type( 'anonymous' );

		// The action hook to attach this trigger into.
		$this->add_action( 'gform_after_update_entry' );

		// The number of arguments that the action hook accepts.
		$this->set_action_args_count( 3 );

		$this->set_sentence(
			sprintf(
				/* Translators: Trigger sentence */
				esc_html__( 'An entry for {{a form:%1$s}} is updated', 'uncanny-automator-pro' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			/* Translators: Trigger sentence */
			esc_html__( 'An entry for {{a form}} is updated', 'uncanny-automator-pro' )
		);

		// Set the options field group.
		$this->set_options_group(
			array(
				$this->get_trigger_meta() => $this->get_fields(),
			)
		);

		// Set new tokens.
		if ( class_exists( '\Uncanny_Automator\GF_COMMON_TOKENS' ) ) {

			$this->set_tokens( GF_COMMON_TOKENS::get_common_tokens() );

		}

		// Register the trigger.
		$this->register_trigger();

	}

		/**
		 * Retrieves the fields to be used as option fields in the dropdown.
		 *
		 * @return array The dropdown option fields.
		 */
	private function get_fields() {

		$helper = new Gravity_Forms_Helpers();

		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_attr__( 'Form', 'uncanny-automator-pro' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => $helper->get_forms_as_option_fields(),
				'relevant_tokens' => array(),
			),
		);

	}

	public function prepare_to_run( $data ) {

		$this->set_conditional_trigger( true );

	}

		/**
		 * Validates the trigger before processing.
		 *
		 * @return boolean False or true.
		 */
	public function validate_trigger( ...$args ) {

		list( $form, $entry_id ) = end( $args );

		return ! empty( $form ) && ! empty( $entry_id );

	}

	/**
	 * Validate conditions.
	 *
	 * @return array The matching recipes and triggers.
	 */
	protected function validate_conditions( ...$args ) {

		list( $form, $entry_id, $previous_values ) = end( $args );

		$matching_recipes_triggers = $this->find_all( $this->trigger_recipes() )
			->where( array( $this->get_trigger_meta() ) )
			->match( array( $form['id'] ) )
			->format( array( 'intval' ) )
			->get();

		return $matching_recipes_triggers;

	}

	/**
	 * Method parse_additional_tokens.
	 *
	 * @param $parsed
	 * @param $args
	 * @param $trigger
	 *
	 * @return array
	 */
	public function parse_additional_tokens( $parsed, $args, $trigger ) {

		if ( class_exists( '\Uncanny_Automator\GF_COMMON_TOKENS' ) ) {

			return GF_COMMON_TOKENS::get_hydrated_common_tokens( $parsed, $args, $trigger )
			+ GF_COMMON_TOKENS::get_hydrated_form_tokens( $parsed, $args, $trigger );

		}

		return $parsed;

	}

}
