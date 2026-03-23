<?php

namespace Uncanny_Automator\Integrations\Wp_Event_Manager;

/**
 * Class Wp_Event_Manager_Attendee_Registered
 *
 * @package Uncanny_Automator
 */
class Wp_Event_Manager_Attendee_Registered extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * @method Wp_Event_Manager_Helpers get_item_helpers()
	 */
	const TRIGGER_CODE = 'WP_EVENT_MANAGER_ATTENDEE_REGISTERED';
	const TRIGGER_META = 'WP_EVENT_MANAGER_EVENT';
	/**
	 * Requirements met.
	 *
	 * @return mixed
	 */
	public function requirements_met() {
		return class_exists( '\WPEM_Registrations' );
	}

	/**
	 * @return mixed|void
	 */
	protected function setup_trigger() {
		$this->set_integration( 'WP_EVENT_MANAGER' );
		$this->set_trigger_code( self::TRIGGER_CODE );
		$this->set_trigger_meta( self::TRIGGER_META );
		$this->set_is_pro( false );
		$this->set_is_login_required( false );
		$this->set_trigger_type( 'anonymous' );
		$this->set_uses_api( false );
		// translators: %1$s is the event name.
		$this->set_sentence( sprintf( esc_html_x( 'An attendee is registered for {{an event:%1$s}}', 'WP Event Manager', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'An attendee is registered for {{an event}}', 'WP Event Manager', 'uncanny-automator' ) );
		$this->add_action( 'new_event_registration', 10, 2 );
	}

	/**
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code' => self::TRIGGER_META,
				'label'       => esc_html_x( 'Event', 'WP Event Manager', 'uncanny-automator' ),
				'token_name'  => esc_html_x( 'Selected event', 'WP Event Manager', 'uncanny-automator' ),
				'input_type'  => 'select',
				'options'     => $this->get_item_helpers()->get_all_events( true ),
				'required'    => true,
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * Define Tokens.
	 *
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		$selected_event_id = isset( $trigger['meta'][ self::TRIGGER_META ] ) ? $trigger['meta'][ self::TRIGGER_META ] : null;
		$event_tokens      = $this->get_item_helpers()->get_common_event_tokens( $selected_event_id );

		return $event_tokens;
	}

	/**
	 * Validate trigger.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		if ( ! isset( $trigger['meta'][ self::TRIGGER_META ] ) ) {
			return false;
		}

		list ( $registration_id, $event_id ) = $hook_args;

		if ( ! is_array( $event_id ) ) {
			$event = get_post( $event_id );
			if ( isset( $event ) && 'event_listing' !== $event->post_type ) {
				return false;
			}
		}

		$selected_event_id = $trigger['meta'][ self::TRIGGER_META ];

		// Check if "Any event" is selected or if the event ID matches
		if ( '-1' === $selected_event_id || (int) $event_id === (int) $selected_event_id ) {
			return true;
		}

		return false;
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
		list ( $registration_id, $event_id ) = $hook_args;
		$event_tokens                        = $this->get_item_helpers()->parse_common_event_token_values( $event_id, $registration_id );

		return $event_tokens;
	}
}
