<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Recipe;

/**
 * Class AC_CONTACT_TAG_ADDED
 *
 * @package Uncanny_Automator
 */
class AC_CONTACT_TAG_ADDED {


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

		$this->set_integration( 'ACTIVE_CAMPAIGN' );
		$this->set_trigger_code( 'CONTACT_TAG_ADDED' );
		$this->set_trigger_meta( 'TAG' );
		$this->set_trigger_type( 'anonymous' );
		$this->set_is_login_required( false );

		/* Translators: Some information for translators */
		$this->set_sentence( sprintf( '{{A tag:%1$s}} is added to a contact', $this->get_trigger_meta() ) ); // Sentence to appear when trigger is added. {{a page:%1$s}} will be presented in blue box as selectable value

		/* Translators: Some information for translators */
		$this->set_readable_sentence( '{{A tag}} is added to a contact' ); // Non-active state sentence to show

		$this->add_action( 'automator_active_campaign_webhook_received' ); // which do_action() fires this trigger

		$this->set_options_callback( array( $this, 'load_options' ) );
		
		if ( get_option( 'uap_active_campaign_enable_webhook', false ) ) {
			$this->register_trigger(); // Registering this trigger
		}

	}

	/**
	 * load_options
	 *
	 * @return void
	 */
	public function load_options() {

		return Automator()->helpers->recipe->active_campaign->options->get_tag_options( $this->get_trigger_meta(), true );

	}

	/**
	 * validate_trigger
	 *
	 * @param mixed $args
	 *
	 * @return void
	 */
	public function validate_trigger( ...$args ) {

		if ( ! is_array( $args ) ) {
			return false;
		}

		$trigger_data = array_shift( $args );

		if ( ! is_array( $trigger_data ) ) {
			return false;
		}

		$ac_event = array_shift( $trigger_data );

		// If the event type or the tag is missing, bail out
		if ( ! is_array( $ac_event ) || ! isset( $ac_event['type'] ) || ! isset( $ac_event['tag'] ) ) {
			return false;
		}

		// If the event is not what we need, bail out
		if ( 'contact_tag_added' !== $ac_event['type'] ) {
			return false;
		}

		return Automator()->helpers->recipe->active_campaign->options->validate_trigger();
	}

	/**
	 *  Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $key
	 * @param $user
	 */
	public function prepare_to_run( $data ) {

		$this->set_conditional_trigger( true );

	}

	/**
	 * Check tag ID against the trigger meta
	 *
	 * @param $args
	 */
	public function trigger_conditions( $args ) {

		if ( ! is_array( $args ) ) {
			return;
		}

		$ac_event = array_shift( $args );

		if ( ! is_array( $ac_event ) || ! isset( $ac_event['tag'] ) ) {
			return;
		}

		$this->do_find_any( true ); // Support "Any tag" option

		// FInd the tag in trigger meta
		$this->do_find_this( $this->get_trigger_meta() );
		$this->do_find_in( $ac_event['tag'] );

	}
}
