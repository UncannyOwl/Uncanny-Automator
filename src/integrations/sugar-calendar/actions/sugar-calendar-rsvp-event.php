<?php

namespace Uncanny_Automator\Integrations\Sugar_Calendar;

/**
 * Class Sugar_Calendar_Rsvp_Event
 *
 * @package Uncanny_Automator
 * @method \Uncanny_Automator\Integrations\Sugar_Calendar\Sugar_Calendar_Helpers get_item_helpers()
 */
class Sugar_Calendar_Rsvp_Event extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'SUGAR_CALENDAR' );
		$this->set_action_code( 'SUGAR_CALENDAR_RSVP_EVENT' );
		$this->set_action_meta( 'SC_EVENT' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );

		// translators: %1$s is the event.
		$this->set_sentence( sprintf( esc_html_x( 'Submit an RSVP for {{an event:%1$s}}', 'Sugar Calendar', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Submit an RSVP for {{an event}}', 'Sugar Calendar', 'uncanny-automator' ) );

		$this->set_action_tokens(
			array(
				'RSVP_ID' => array(
					'name' => esc_html_x( 'RSVP ID', 'Sugar Calendar', 'uncanny-automator' ),
					'type' => 'int',
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Check if the RSVP add-on is available.
	 *
	 * @return bool
	 */
	public function requirements_met() {
		return defined( 'SUGAR_CALENDAR_RSVP_VERSION' );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_html_x( 'Event', 'Sugar Calendar', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => $this->get_item_helpers()->get_events( false ),
				'supports_custom_value' => true,
			),
			array(
				'option_code' => 'SC_NAME',
				'label'       => esc_html_x( 'Name', 'Sugar Calendar', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
			array(
				'option_code' => 'SC_EMAIL',
				'label'       => esc_html_x( 'Email', 'Sugar Calendar', 'uncanny-automator' ),
				'input_type'  => 'email',
				'required'    => true,
			),
			array(
				'option_code' => 'SC_PHONE',
				'label'       => esc_html_x( 'Phone', 'Sugar Calendar', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code' => 'SC_GOING',
				'label'       => esc_html_x( 'Going', 'Sugar Calendar', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => false,
				'options'     => array(
					array(
						'value' => '1',
						'text'  => esc_html_x( 'Yes', 'Sugar Calendar', 'uncanny-automator' ),
					),
					array(
						'value' => '0',
						'text'  => esc_html_x( 'No', 'Sugar Calendar', 'uncanny-automator' ),
					),
				),
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action configuration.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional arguments.
	 * @param array $parsed      Parsed token values.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$event_post_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );
		$name          = sanitize_text_field( $parsed['SC_NAME'] ?? '' );
		$email         = sanitize_email( $parsed['SC_EMAIL'] ?? '' );
		$phone         = sanitize_text_field( $parsed['SC_PHONE'] ?? '' );
		$going         = absint( $parsed['SC_GOING'] ?? 1 );

		if ( empty( $event_post_id ) ) {
			$this->add_log_error( esc_html_x( 'Event is required.', 'Sugar Calendar', 'uncanny-automator' ) );
			return false;
		}

		if ( empty( $name ) ) {
			$this->add_log_error( esc_html_x( 'Name is required.', 'Sugar Calendar', 'uncanny-automator' ) );
			return false;
		}

		if ( empty( $email ) || false === is_email( $email ) ) {
			$this->add_log_error( esc_html_x( 'A valid email address is required.', 'Sugar Calendar', 'uncanny-automator' ) );
			return false;
		}

		// Resolve the SC internal event ID from the WP post ID.
		$event = sugar_calendar_get_event_by_object( $event_post_id );

		if ( empty( $event->id ) ) {
			$this->add_log_error(
				sprintf(
					// translators: %d is the post ID.
					esc_html_x( 'No Sugar Calendar event found for post ID %d.', 'Sugar Calendar', 'uncanny-automator' ),
					$event_post_id
				)
			);
			return false;
		}

		$rsvp = \Sugar_Calendar_Rsvp\Model\Rsvp::create(
			array(
				'event_id' => $event->id,
				'name'     => $name,
				'email'    => $email,
				'phone'    => $phone,
				'going'    => $going,
			)
		);

		if ( is_wp_error( $rsvp ) ) {
			$this->add_log_error( $rsvp->get_error_message() );
			return false;
		}

		$errors = $rsvp->get_errors();

		if ( ! empty( $errors ) ) {
			$first_error = reset( $errors );
			$message     = is_array( $first_error ) ? reset( $first_error ) : $first_error;
			$this->add_log_error( wp_strip_all_tags( (string) $message ) );
			return false;
		}

		$this->hydrate_tokens( array( 'RSVP_ID' => $rsvp->post_id ) );

		return true;
	}
}
