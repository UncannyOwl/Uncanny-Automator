<?php

namespace Uncanny_Automator\Integrations\The_Events_Calendar;

/**
 * Class EC_UPDATE_EVENT
 *
 * Updates an existing event via Tribe__Events__API::updateEvent().
 * Only fields the user fills are passed through.
 *
 * @property The_Events_Calendar_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 */
class EC_UPDATE_EVENT extends \Uncanny_Automator\Recipe\Action {

	/**
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'EC' );
		$this->set_action_code( 'EC_UPDATE_EVENT' );
		$this->set_action_meta( 'EVENT_ID' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );

		/* translators: %1$s is the event field */
		$this->set_sentence( sprintf( esc_html_x( 'Update {{an event:%1$s}}', 'The Events Calendar', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Update {{an event}}', 'The Events Calendar', 'uncanny-automator' ) );
	}

	/**
	 * @return array<string,array<string,string>>
	 */
	public function define_tokens() {
		return array(
			'EC_UPDATED_EVENT_ID'    => array(
				'name' => esc_html_x( 'Event ID', 'The Events Calendar', 'uncanny-automator' ),
				'type' => 'int',
			),
			'EC_UPDATED_EVENT_URL'   => array(
				'name' => esc_html_x( 'Event URL', 'The Events Calendar', 'uncanny-automator' ),
				'type' => 'url',
			),
			'EC_UPDATED_EVENT_TITLE' => array(
				'name' => esc_html_x( 'Event title', 'The Events Calendar', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
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
				'option_code' => 'EVENT_TITLE',
				'label'       => esc_html_x( 'Event title', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code' => 'EVENT_START_DATE',
				'label'       => esc_html_x( 'Start date (Y-m-d H:i:s)', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code' => 'EVENT_END_DATE',
				'label'       => esc_html_x( 'End date (Y-m-d H:i:s)', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code' => 'EVENT_DESCRIPTION',
				'label'       => esc_html_x( 'Description', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'  => 'textarea',
				'required'    => false,
			),
			array(
				'option_code'           => 'EVENT_STATUS',
				'label'                 => esc_html_x( 'Status', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => false,
				'supports_custom_value' => true,
				'options'               => array(
					array(
						'value' => '',
						'text'  => esc_html_x( '— Leave unchanged —', 'The Events Calendar', 'uncanny-automator' ),
					),
					array(
						'value' => 'publish',
						'text'  => esc_html_x( 'Published', 'The Events Calendar', 'uncanny-automator' ),
					),
					array(
						'value' => 'draft',
						'text'  => esc_html_x( 'Draft', 'The Events Calendar', 'uncanny-automator' ),
					),
					array(
						'value' => 'pending',
						'text'  => esc_html_x( 'Pending', 'The Events Calendar', 'uncanny-automator' ),
					),
				),
			),
			array(
				'option_code'           => 'EVENT_VENUE_ID',
				'label'                 => esc_html_x( 'Venue', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => false,
				'supports_custom_value' => true,
				'options'               => array(),
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'venues_strict' ),
			),
			array(
				'option_code'           => 'EVENT_ORGANIZER_ID',
				'label'                 => esc_html_x( 'Organizer', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => false,
				'supports_custom_value' => true,
				'options'               => array(),
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'organizers_strict' ),
			),
			array(
				'option_code' => 'EVENT_TIMEZONE',
				'label'       => esc_html_x( 'Timezone', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
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

		if ( ! class_exists( 'Tribe__Events__API' ) ) {
			$this->add_log_error( esc_html_x( 'The Events Calendar API is not available.', 'The Events Calendar', 'uncanny-automator' ) );
			return false;
		}

		$event_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );

		if ( 0 === $event_id || '-1' === ( $parsed[ $this->get_action_meta() ] ?? '' ) ) {
			$this->add_log_error( esc_html_x( 'A specific event must be selected.', 'The Events Calendar', 'uncanny-automator' ) );
			return false;
		}

		if ( 'tribe_events' !== get_post_type( $event_id ) ) {
			/* translators: %d is the event ID */
			$this->add_log_error( sprintf( esc_html_x( 'Event with ID %d does not exist.', 'The Events Calendar', 'uncanny-automator' ), $event_id ) );
			return false;
		}

		$event_args = array();

		$title = sanitize_text_field( $parsed['EVENT_TITLE'] ?? '' );
		if ( '' !== $title ) {
			$event_args['post_title'] = $title;
		}

		$start_date = sanitize_text_field( $parsed['EVENT_START_DATE'] ?? '' );
		if ( '' !== $start_date ) {
			$event_args['EventStartDate'] = $start_date;
		}

		$end_date = sanitize_text_field( $parsed['EVENT_END_DATE'] ?? '' );
		if ( '' !== $end_date ) {
			$event_args['EventEndDate'] = $end_date;
		}

		$description = $parsed['EVENT_DESCRIPTION'] ?? '';
		if ( '' !== $description ) {
			$event_args['post_content'] = wp_kses_post( $description );
		}

		$status = sanitize_text_field( $parsed['EVENT_STATUS'] ?? '' );
		if ( '' !== $status ) {
			$event_args['post_status'] = $status;
		}

		$venue_id = absint( $parsed['EVENT_VENUE_ID'] ?? 0 );
		if ( 0 !== $venue_id ) {
			$event_args['Venue'] = array( 'VenueID' => $venue_id );
		}

		$organizer_id = absint( $parsed['EVENT_ORGANIZER_ID'] ?? 0 );
		if ( 0 !== $organizer_id ) {
			$event_args['Organizer'] = array( 'OrganizerID' => $organizer_id );
		}

		$timezone = sanitize_text_field( $parsed['EVENT_TIMEZONE'] ?? '' );
		// Reject typos like "UTC+5" rather than passing them through to TEC.
		if ( '' !== $timezone && in_array( $timezone, timezone_identifiers_list(), true ) ) {
			$event_args['EventTimezone'] = $timezone;
		}

		if ( empty( $event_args ) ) {
			$this->add_log_error( esc_html_x( 'No fields provided to update.', 'The Events Calendar', 'uncanny-automator' ) );
			return false;
		}

		$result = \Tribe__Events__API::updateEvent( $event_id, $event_args );

		if ( is_wp_error( $result ) ) {
			$this->add_log_error( $result->get_error_message() );
			return false;
		}

		if ( empty( $result ) ) {
			$this->add_log_error( esc_html_x( 'Failed to update event.', 'The Events Calendar', 'uncanny-automator' ) );
			return false;
		}

		$this->hydrate_tokens(
			array(
				'EC_UPDATED_EVENT_ID'    => (int) $event_id,
				'EC_UPDATED_EVENT_URL'   => get_permalink( $event_id ),
				'EC_UPDATED_EVENT_TITLE' => get_the_title( $event_id ),
			)
		);

		return true;
	}
}
