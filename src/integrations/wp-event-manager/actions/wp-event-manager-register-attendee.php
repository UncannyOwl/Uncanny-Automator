<?php

namespace Uncanny_Automator\Integrations\Wp_Event_Manager;

/**
 * Class Wp_Event_Manager_Register_Attendee
 *
 * @package Uncanny_Automator
 */
class Wp_Event_Manager_Register_Attendee extends \Uncanny_Automator\Recipe\Action {

	/**
	 * @method Wp_Event_Manager_Helpers get_item_helpers()
	 */
	const ACTION_CODE = 'WP_EVENT_MANAGER_REGISTER_ATTENDEE';
	const ACTION_META = 'WP_EVENT_MANAGER_EVENT';

	/**
	 * Setup action
	 */
	protected function setup_action() {
		$this->set_integration( 'WP_EVENT_MANAGER' );
		$this->set_action_code( self::ACTION_CODE );
		$this->set_action_meta( self::ACTION_META );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( false );
		// translators: %1$s is the attendee email, %2$s is the event name.
		$this->set_sentence( sprintf( esc_html_x( 'Register {{an attendee:%1$s}} for {{an event:%2$s}}', 'WP Event Manager', 'uncanny-automator' ), 'ATTENDEE_EMAIL:' . $this->get_action_meta(), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Register {{an attendee}} for {{an event}}', 'WP Event Manager', 'uncanny-automator' ) );
	}

	/**
	 * Define action options
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'label' => esc_html_x( 'Event', 'WP Event Manager', 'uncanny-automator' ),
				'input_type' => 'select',
				'required' => true,
				'options' => $this->get_item_helpers()->get_all_events( false ),
				'relevant_tokens' => array(),
			),
			array(
				'option_code' => 'ATTENDEE_EMAIL',
				'label' => esc_html_x( 'Attendee email', 'WP Event Manager', 'uncanny-automator' ),
				'input_type' => 'email',
				'required' => true,
				'relevant_tokens' => array(),
				'description' => esc_html_x( 'Email address of the attendee to register', 'WP Event Manager', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Define tokens
	 */
	public function define_tokens( $tokens = array(), $action_code = '' ) {
		return array(
			'REGISTRATION_ID' => array(
				'name' => esc_html_x( 'Registration ID', 'WP Event Manager', 'uncanny-automator' ),
				'type' => 'int',
			),
			'ATTENDEE_EMAIL' => array(
				'name' => esc_html_x( 'Attendee email', 'WP Event Manager', 'uncanny-automator' ),
				'type' => 'email',
			),
			'EVENT_ID' => array(
				'name' => esc_html_x( 'Event ID', 'WP Event Manager', 'uncanny-automator' ),
				'type' => 'text',
			),
			'EVENT_TITLE' => array(
				'name' => esc_html_x( 'Event title', 'WP Event Manager', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Process action
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$event_id       = $parsed[ $this->get_action_meta() ];
		$attendee_email = $parsed['ATTENDEE_EMAIL'];

		// Validate event exists
		$event = get_post( $event_id );
		if ( ! $event || 'event_listing' !== $event->post_type ) {
			// translators: %s is the event ID.
			$this->add_log_error( sprintf( esc_html_x( 'Event with ID %d does not exist or is not a valid event.', 'WP Event Manager', 'uncanny-automator' ), $event_id ) );
			return false;
		}

		// Check if WP Event Manager Registrations is active
		if ( ! class_exists( '\WPEM_Registrations' ) ) {
			$this->add_log_error( esc_html_x( 'WP Event Manager Registrations plugin is not active.', 'WP Event Manager', 'uncanny-automator' ) );
			return false;
		}

		// Validate email
		if ( ! is_email( $attendee_email ) ) {
			// translators: %s is the attendee email.
			$this->add_log_error( sprintf( esc_html_x( 'Invalid email address: %s', 'WP Event Manager', 'uncanny-automator' ), $attendee_email ) );
			return false;
		}

		// Check if user is already registered for this event
		if ( get_option( 'event_registration_prevent_multiple_registrations' ) && email_has_registered_for_event( $attendee_email, $event_id ) ) {
			// translators: %s is the attendee email.
			$this->add_log_error( sprintf( esc_html_x( 'Attendee with email %s is already registered for this event.', 'WP Event Manager', 'uncanny-automator' ), $attendee_email ) );
			return false;
		}

		// Create registration
		$registration_data = array(
			'post_type' => 'event_registration',
			// translators: %s is the event title.
			'post_title' => sprintf( esc_html_x( 'Registration for %s', 'WP Event Manager', 'uncanny-automator' ), $event->post_title ),
			'post_content' => '',
			'post_status' => 'new',
			'post_parent' => $event_id,
			'meta_input' => array(
				'_attendee_email' => $attendee_email,
				'_registration_date' => current_time( 'mysql' ),
				'_registration_status' => 'new',
			),
		);

		$registration_id = wp_insert_post( $registration_data );

		if ( is_wp_error( $registration_id ) ) {
			// translators: %s is the error message.
			$this->add_log_error( sprintf( esc_html_x( 'Failed to create registration: %s', 'WP Event Manager', 'uncanny-automator' ), $registration_id->get_error_message() ) );
			return false;
		}

		// Hydrate tokens
		$this->hydrate_tokens(
			array(
				'REGISTRATION_ID' => $registration_id,
				'ATTENDEE_EMAIL' => $attendee_email,
				'EVENT_ID' => $event_id,
				'EVENT_TITLE' => $event->post_title,
			)
		);

		// Trigger the new_event_registration hook for compatibility
		do_action( 'new_event_registration', $registration_id, $event_id );

		return true;
	}
}
