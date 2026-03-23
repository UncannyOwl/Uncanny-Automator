<?php

namespace Uncanny_Automator\Integrations\Sugar_Calendar;

/**
 * Class Sugar_Calendar_Event_Created
 *
 * @package Uncanny_Automator
 * @method \Uncanny_Automator\Integrations\Sugar_Calendar\Sugar_Calendar_Helpers get_item_helpers()
 */
class Sugar_Calendar_Event_Created extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_integration( 'SUGAR_CALENDAR' );
		$this->set_trigger_code( 'SUGAR_CALENDAR_EVENT_CREATED' );
		$this->set_trigger_meta( 'SC_CALENDAR' );
		$this->set_is_pro( false );
		$this->set_is_login_required( false );
		$this->set_trigger_type( 'anonymous' );

		// translators: %1$s is the calendar.
		$this->set_sentence( sprintf( esc_html_x( 'An event is created in {{a calendar:%1$s}}', 'Sugar Calendar', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'An event is created in {{a calendar}}', 'Sugar Calendar', 'uncanny-automator' ) );

		$this->add_action( 'sugar_calendar_added_event', 10, 2 );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Calendar', 'Sugar Calendar', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => $this->get_item_helpers()->get_calendars( true ),
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * Validate the trigger.
	 *
	 * @param array $trigger   Trigger data.
	 * @param array $hook_args Hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		list( $event_id ) = $hook_args;

		$event = sugar_calendar_get_event( $event_id );

		if ( empty( $event->id ) ) {
			return false;
		}

		$selected_calendar = $trigger['meta'][ $this->get_trigger_meta() ];

		// "Any calendar" — always match.
		if ( intval( '-1' ) === intval( $selected_calendar ) ) {
			return true;
		}

		$calendar_terms = wp_get_post_terms( $event->object_id, 'sc_event_category', array( 'fields' => 'ids' ) );

		if ( is_wp_error( $calendar_terms ) ) {
			return false;
		}

		return in_array( (int) $selected_calendar, $calendar_terms, true );
	}

	/**
	 * Define tokens.
	 *
	 * @param array $trigger Trigger data.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge( $tokens, $this->get_item_helpers()->get_event_tokens_config() );
	}

	/**
	 * Hydrate tokens.
	 *
	 * @param array $trigger   Trigger data.
	 * @param array $hook_args Hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		return $this->get_item_helpers()->hydrate_event_tokens( $hook_args[0] );
	}
}
