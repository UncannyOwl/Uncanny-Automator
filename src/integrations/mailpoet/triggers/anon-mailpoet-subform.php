<?php

namespace Uncanny_Automator;

use MailPoet\FormEntity;

class ANON_MAILPOET_SUBFORM {

	use Recipe\Triggers;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->setup_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {
		$this->set_integration( 'MAILPOET' );
		$this->set_trigger_code( 'ANON_MAILPOETSUBFORM' );
		$this->set_trigger_meta( 'MAILPOETFORMS' );
		$this->set_trigger_type( 'anonymous' );
		$this->set_is_login_required( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->trigger_code, 'integration/mailpoet/' ) );
		$this->set_sentence(
		/* Translators: Trigger sentence */
			sprintf( esc_attr__( '{{A form:%1$s}} is submitted', 'uncanny-automator' ), $this->get_trigger_meta() )
		);
		// Non-active state sentence to show
		$this->set_readable_sentence( esc_attr__( '{{A form}} is submitted', 'uncanny-automator' ) );
		// Which do_action() fires this trigger.
		$this->set_action_hook( 'mailpoet_subscription_before_subscribe' );
		$this->set_action_args_count( 3 );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->register_trigger();
	}

	/**
	 * load_options
	 *
	 * @return void
	 */
	public function load_options() {
		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					Automator()->helpers->recipe->mailpoet->options->list_mailpoet_forms( null, $this->get_trigger_meta() ),
				),
			)
		);
	}

	/**
	 * Validate the trigger.
	 *
	 * @param $args
	 *
	 * @return bool
	 */
	protected function validate_trigger( ...$args ) {
		list( $data, $segmentIds, $form ) = array_shift( $args );

		if ( ! isset( $form ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Prepare to run the trigger.
	 *
	 * @param $data
	 *
	 * @return void
	 */
	public function prepare_to_run( $data ) {
		$this->set_conditional_trigger( true );
	}

	/**
	 * Check contact status against the trigger meta
	 *
	 * @param $args
	 */
	public function validate_conditions( ...$args ) {
		list( $data, $segmentIds, $form ) = $args[0];
		$this->actual_where_values        = array(); // Fix for when not using the latest Trigger_Recipe_Filters version. Newer integration can omit this line.
		// check form ID
		return $this->find_all( $this->trigger_recipes() )
					->where( array( $this->get_trigger_meta() ) )
					->match( array( $form->getId() ) )
					->format( array( 'intval' ) )
					->get();
	}

	/**
	 * @param ...$args
	 *
	 * @return bool
	 */
	public function do_continue_anon_trigger( ...$args ) {
		return true;
	}

}
