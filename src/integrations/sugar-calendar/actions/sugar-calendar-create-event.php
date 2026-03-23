<?php

namespace Uncanny_Automator\Integrations\Sugar_Calendar;

/**
 * Class Sugar_Calendar_Create_Event
 *
 * @package Uncanny_Automator
 * @method \Uncanny_Automator\Integrations\Sugar_Calendar\Sugar_Calendar_Helpers get_item_helpers()
 */
class Sugar_Calendar_Create_Event extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'SUGAR_CALENDAR' );
		$this->set_action_code( 'SUGAR_CALENDAR_CREATE_EVENT' );
		$this->set_action_meta( 'SC_EVENT_TITLE' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );

		// translators: %1$s is the event title.
		$this->set_sentence( sprintf( esc_html_x( 'Create an event {{with a title:%1$s}}', 'Sugar Calendar', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Create an event {{with a title}}', 'Sugar Calendar', 'uncanny-automator' ) );

		$this->set_action_tokens(
			array(
				'EVENT_ID'  => array(
					'name' => esc_html_x( 'Event ID', 'Sugar Calendar', 'uncanny-automator' ),
					'type' => 'int',
				),
				'EVENT_URL' => array(
					'name' => esc_html_x( 'Event URL', 'Sugar Calendar', 'uncanny-automator' ),
					'type' => 'url',
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_html_x( 'Title', 'Sugar Calendar', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
			array(
				'option_code' => 'SC_DESCRIPTION',
				'label'       => esc_html_x( 'Description', 'Sugar Calendar', 'uncanny-automator' ),
				'input_type'  => 'textarea',
				'required'    => false,
			),
			array(
				'option_code' => 'SC_START_DATE',
				'label'       => esc_html_x( 'Start date', 'Sugar Calendar', 'uncanny-automator' ),
				'input_type'  => 'date',
				'required'    => true,
			),
			array(
				'option_code' => 'SC_START_TIME',
				'label'       => esc_html_x( 'Start time', 'Sugar Calendar', 'uncanny-automator' ),
				'input_type'  => 'time',
				'required'    => false,
			),
			array(
				'option_code' => 'SC_END_DATE',
				'label'       => esc_html_x( 'End date', 'Sugar Calendar', 'uncanny-automator' ),
				'input_type'  => 'date',
				'required'    => false,
			),
			array(
				'option_code' => 'SC_END_TIME',
				'label'       => esc_html_x( 'End time', 'Sugar Calendar', 'uncanny-automator' ),
				'input_type'  => 'time',
				'required'    => false,
			),
			array(
				'option_code' => 'SC_ALL_DAY',
				'label'       => esc_html_x( 'All day', 'Sugar Calendar', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => false,
				'options'     => array(
					array(
						'value' => '0',
						'text'  => esc_html_x( 'No', 'Sugar Calendar', 'uncanny-automator' ),
					),
					array(
						'value' => '1',
						'text'  => esc_html_x( 'Yes', 'Sugar Calendar', 'uncanny-automator' ),
					),
				),
			),
			array(
				'option_code' => 'SC_CALENDAR',
				'label'       => esc_html_x( 'Calendar', 'Sugar Calendar', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => false,
				'options'     => $this->get_item_helpers()->get_calendars( false ),
			),
			array(
				'option_code' => 'SC_LOCATION',
				'label'       => esc_html_x( 'Location', 'Sugar Calendar', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code' => 'SC_STATUS',
				'label'       => esc_html_x( 'Status', 'Sugar Calendar', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => false,
				'options'     => array(
					array(
						'value' => 'publish',
						'text'  => esc_html_x( 'Published', 'Sugar Calendar', 'uncanny-automator' ),
					),
					array(
						'value' => 'draft',
						'text'  => esc_html_x( 'Draft', 'Sugar Calendar', 'uncanny-automator' ),
					),
				),
			),
			array(
				'option_code' => 'SC_REGISTRATION',
				'label'       => esc_html_x( 'Registration', 'Sugar Calendar', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => false,
				'description' => esc_html_x( 'RSVP and ticketing are mutually exclusive. Requires the respective Sugar Calendar add-on.', 'Sugar Calendar', 'uncanny-automator' ),
				'options'     => array(
					array(
						'value' => '',
						'text'  => esc_html_x( 'None', 'Sugar Calendar', 'uncanny-automator' ),
					),
					array(
						'value' => 'rsvp',
						'text'  => esc_html_x( 'RSVP', 'Sugar Calendar', 'uncanny-automator' ),
					),
					array(
						'value' => 'ticketing',
						'text'  => esc_html_x( 'Ticketing', 'Sugar Calendar', 'uncanny-automator' ),
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

		$title        = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' );
		$description  = wp_kses_post( $parsed['SC_DESCRIPTION'] ?? '' );
		$start_date   = sanitize_text_field( $parsed['SC_START_DATE'] ?? '' );
		$start_time   = sanitize_text_field( $parsed['SC_START_TIME'] ?? '' );
		$end_date     = sanitize_text_field( $parsed['SC_END_DATE'] ?? '' );
		$end_time     = sanitize_text_field( $parsed['SC_END_TIME'] ?? '' );
		$all_day      = absint( $parsed['SC_ALL_DAY'] ?? 0 );
		$calendar_id  = absint( $parsed['SC_CALENDAR'] ?? 0 );
		$location     = sanitize_text_field( $parsed['SC_LOCATION'] ?? '' );
		$status       = sanitize_text_field( $parsed['SC_STATUS'] ?? 'publish' );
		$registration = sanitize_text_field( $parsed['SC_REGISTRATION'] ?? '' );

		if ( empty( $title ) ) {
			$this->add_log_error( esc_html_x( 'Event title is required.', 'Sugar Calendar', 'uncanny-automator' ) );
			return false;
		}

		if ( empty( $start_date ) ) {
			$this->add_log_error( esc_html_x( 'Event start date is required.', 'Sugar Calendar', 'uncanny-automator' ) );
			return false;
		}

		$start_time_part = '' !== $start_time ? $start_time : '00:00';
		$start_datetime  = gmdate( 'Y-m-d H:i:s', strtotime( $start_date . ' ' . $start_time_part ) );

		$end_datetime = '';
		if ( '' !== $end_date ) {
			$end_time_part = '' !== $end_time ? $end_time : '00:00';
			$end_datetime  = gmdate( 'Y-m-d H:i:s', strtotime( $end_date . ' ' . $end_time_part ) );
		}

		// Create the WP post first.
		$post_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_content' => $description,
				'post_status'  => $status,
				'post_type'    => 'sc_event',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			$this->add_log_error( $post_id->get_error_message() );
			return false;
		}

		// Build the event data.
		$event_data = array(
			'object_id'      => $post_id,
			'object_type'    => 'post',
			'object_subtype' => 'sc_event',
			'title'          => $title,
			'content'        => $description,
			'start'          => $start_datetime,
			'end'            => '' !== $end_datetime ? $end_datetime : $start_datetime,
			'all_day'        => $all_day,
			'status'         => $status,
		);

		// Include location as event meta via the data array.
		if ( '' !== $location ) {
			$event_data['location'] = $location;
		}

		// Enable RSVP or ticketing (mutually exclusive) via the data array so
		// SC's add_item() saves them automatically as registered meta keys.
		if ( 'rsvp' === $registration ) {
			$event_data['rsvp_enable'] = '1';
		} elseif ( 'ticketing' === $registration ) {
			$event_data['tickets'] = '1';
		}

		$event_id = sugar_calendar_add_event( $event_data );

		if ( empty( $event_id ) ) {
			wp_delete_post( $post_id, true );
			$this->add_log_error( esc_html_x( 'Failed to create Sugar Calendar event.', 'Sugar Calendar', 'uncanny-automator' ) );
			return false;
		}

		// Assign calendar taxonomy.
		if ( ! empty( $calendar_id ) ) {
			wp_set_post_terms( $post_id, array( $calendar_id ), 'sc_event_category' );
		}

		$this->hydrate_tokens(
			array(
				'EVENT_ID'  => $post_id,
				'EVENT_URL' => get_permalink( $post_id ),
			)
		);

		return true;
	}
}
