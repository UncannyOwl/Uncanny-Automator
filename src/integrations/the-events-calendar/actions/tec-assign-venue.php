<?php

namespace Uncanny_Automator\Integrations\The_Events_Calendar;

/**
 * Class EC_ASSIGN_VENUE
 *
 * Assigns a venue to an event by writing _EventVenueID meta.
 *
 * @property The_Events_Calendar_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 */
class EC_ASSIGN_VENUE extends \Uncanny_Automator\Recipe\Action {

	/**
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'EC' );
		$this->set_action_code( 'EC_ASSIGN_VENUE' );
		$this->set_action_meta( 'EVENT_ID' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );

		/* translators: %1$s is the event field, %2$s is the venue field */
		$this->set_sentence( sprintf( esc_html_x( 'Add {{a venue:%2$s}} to {{an event:%1$s}}', 'The Events Calendar', 'uncanny-automator' ), $this->get_action_meta(), 'VENUE_ID:' . $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Add {{a venue}} to {{an event}}', 'The Events Calendar', 'uncanny-automator' ) );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function options() {

		return array(
			array(
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_html_x( 'Event', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'supports_custom_value' => true,
				'options'               => array(),
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'events_strict' ),
			),
			array(
				'option_code'           => 'VENUE_ID',
				'label'                 => esc_html_x( 'Venue', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'supports_custom_value' => true,
				'options'               => array(),
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'venues_strict' ),
			),
		);
	}

	/**
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$event_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );
		$venue_id = absint( $parsed['VENUE_ID'] ?? 0 );

		if ( 0 === $event_id || 0 === $venue_id ) {
			$this->add_log_error( esc_html_x( 'Both a specific event and a specific venue must be selected.', 'The Events Calendar', 'uncanny-automator' ) );
			return false;
		}

		if ( 'tribe_events' !== get_post_type( $event_id ) ) {
			/* translators: %d is the event ID */
			$this->add_log_error( sprintf( esc_html_x( 'Event with ID %d does not exist.', 'The Events Calendar', 'uncanny-automator' ), $event_id ) );
			return false;
		}

		if ( 'tribe_venue' !== get_post_type( $venue_id ) ) {
			/* translators: %d is the venue ID */
			$this->add_log_error( sprintf( esc_html_x( 'Venue with ID %d does not exist.', 'The Events Calendar', 'uncanny-automator' ), $venue_id ) );
			return false;
		}

		// Use the TEC ORM rather than writing _EventVenueID directly.
		// Direct meta writes skip the Linked_Posts service, do not fire
		// `tribe_events_link_post` (which `EC_EVENT_LINKED` listens to),
		// and don't sync the CT1 mirror tables on modern TEC builds.
		if ( ! function_exists( 'tribe_events' ) ) {
			$this->add_log_error( esc_html_x( 'TEC ORM (tribe_events) is not available.', 'The Events Calendar', 'uncanny-automator' ) );
			return false;
		}

		try {
			tribe_events()
				->where( 'id', $event_id )
				->set( 'venue', $venue_id )
				->save();
		} catch ( \Throwable $e ) {
			$this->add_log_error( sprintf( esc_html_x( 'Failed to assign venue: %s', 'The Events Calendar', 'uncanny-automator' ), $e->getMessage() ) );
			return false;
		}

		return true;
	}
}
