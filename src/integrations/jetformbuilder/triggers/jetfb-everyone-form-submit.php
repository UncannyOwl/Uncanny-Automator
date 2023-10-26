<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Recipe;

/**
 *
 */
class JETFB_EVERYONE_FORM_SUBMIT {

	use Recipe\Triggers;

	/**
	 * Trigger code.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'JETFB_EVERYONE_FORM_SUBMIT';

	/**
	 * Trigger meta.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'JETFB_EVERYONE_FORM_SUBMIT_META';

	/**
	 * @var Jetfb_Tokens
	 */
	public $jetfb_tokens;

	/**
	 *
	 */
	public function __construct() {

		$this->set_helper( new Jetfb_Helpers() );

		$this->jetfb_tokens = new Jetfb_Tokens();

		$this->setup_trigger();

	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {

		$this->set_integration( 'JET_FORM_BUILDER' );

		$this->set_trigger_code( self::TRIGGER_CODE );

		$this->set_trigger_meta( self::TRIGGER_META );

		$this->set_trigger_type( 'anonymous' );

		$this->set_is_login_required( false );

		/* Translators: Trigger sentence */
		$this->set_sentence(
			sprintf(
			/* Translators: Trigger sentence */
				esc_html__( '{{A form:%1$s}} is submitted', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html__( '{{A form}} is submitted', 'uncanny-automator' )
		);

		$this->add_action( 'jet-form-builder/form-handler/after-send' );

		$this->set_tokens( $this->jetfb_tokens->common_tokens() );

		$this->set_action_args_count( 2 );

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->register_trigger();

	}

	/**
	 * @return mixed
	 */
	public function load_options() {

		return $this->get_helper()->get_option_fields( $this );

	}

	/**
	 * @param ...$args
	 *
	 * @return mixed
	 */
	public function validate_trigger( ...$args ) {

		list( $form_handler, $is_success ) = $args[0];

		return $is_success;

	}

	/**
	 * @param $data
	 *
	 * @return void
	 */
	public function prepare_to_run( $data ) {

		$this->set_conditional_trigger( true );

	}

	/**
	 * @param ...$args
	 *
	 * @return array
	 */
	public function validate_conditions( ...$args ) {

		list( $form_handler, $is_success ) = $args[0];

		if ( empty( $form_handler->action_handler->form_id ) ) {
			return array();
		}

		$matching_recipes_triggers = $this->find_all( $this->trigger_recipes() )
										  ->where( array( $this->get_trigger_meta() ) )
										  ->match( array( absint( $form_handler->action_handler->form_id ) ) )
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

		return $this->jetfb_tokens->hydrate_tokens( $parsed, $args, $trigger );

	}

	/**
	 * Return true for everyone recipe.
	 */
	public function do_continue_anon_trigger( ...$args ) {

		return true;

	}

}
