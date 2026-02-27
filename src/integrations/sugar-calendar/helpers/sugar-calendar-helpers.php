<?php

namespace Uncanny_Automator\Integrations\Sugar_Calendar;

/**
 * Class Sugar_Calendar_Helpers
 *
 * @package Uncanny_Automator
 */
class Sugar_Calendar_Helpers {

	/**
	 * Sugar_Calendar_Helpers constructor.
	 */
	public function __construct() {
	}

	/**
	 * Get calendars as dropdown options.
	 *
	 * @param bool $include_any Whether to include "Any calendar" option.
	 *
	 * @return array
	 */
	public function get_calendars( $include_any = false ) {

		$options = array();

		if ( $include_any ) {
			$options[] = array(
				'value' => -1,
				'text'  => esc_html_x( 'Any calendar', 'Sugar Calendar', 'uncanny-automator' ),
			);
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'sc_event_category',
				'hide_empty' => false,
			)
		);

		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				$options[] = array(
					'value' => $term->term_id,
					'text'  => $term->name,
				);
			}
		}

		return $options;
	}

	/**
	 * Get events as dropdown options.
	 *
	 * @param bool $include_any Whether to include "Any event" option.
	 *
	 * @return array
	 */
	public function get_events( $include_any = false ) {

		$options = array();

		if ( $include_any ) {
			$options[] = array(
				'value' => -1,
				'text'  => esc_html_x( 'Any event', 'Sugar Calendar', 'uncanny-automator' ),
			);
		}

		$events = get_posts(
			array(
				'post_type'      => 'sc_event',
				'post_status'    => 'publish',
				'posts_per_page' => 999,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		if ( ! empty( $events ) ) {
			foreach ( $events as $event ) {
				$options[] = array(
					'value' => $event->ID,
					'text'  => $event->post_title,
				);
			}
		}

		return $options;
	}

	/**
	 * AJAX handler to fetch events for action dropdowns.
	 *
	 * @return void
	 */
	public function ajax_fetch_events() {

		Automator()->utilities->ajax_auth_check();

		$options = $this->get_events( false );

		echo wp_json_encode( $options );

		die();
	}

	/**
	 * AJAX handler to fetch events for trigger dropdowns (includes "Any").
	 *
	 * @return void
	 */
	public function ajax_fetch_events_for_triggers() {

		Automator()->utilities->ajax_auth_check();

		$options = $this->get_events( true );

		echo wp_json_encode( $options );

		die();
	}

	/**
	 * Get event token definitions for triggers.
	 *
	 * @return array
	 */
	public function get_event_tokens_config() {

		return array(
			array(
				'tokenId'   => 'EVENT_ID',
				'tokenName' => esc_html_x( 'Event ID', 'Sugar Calendar', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'EVENT_TITLE',
				'tokenName' => esc_html_x( 'Event title', 'Sugar Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'EVENT_START_DATE',
				'tokenName' => esc_html_x( 'Event start date', 'Sugar Calendar', 'uncanny-automator' ),
				'tokenType' => 'datetime',
			),
			array(
				'tokenId'   => 'EVENT_END_DATE',
				'tokenName' => esc_html_x( 'Event end date', 'Sugar Calendar', 'uncanny-automator' ),
				'tokenType' => 'datetime',
			),
			array(
				'tokenId'   => 'ALL_DAY',
				'tokenName' => esc_html_x( 'All day', 'Sugar Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LOCATION',
				'tokenName' => esc_html_x( 'Location', 'Sugar Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DESCRIPTION',
				'tokenName' => esc_html_x( 'Description', 'Sugar Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CALENDAR_NAME',
				'tokenName' => esc_html_x( 'Calendar name', 'Sugar Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'EVENT_URL',
				'tokenName' => esc_html_x( 'Event URL', 'Sugar Calendar', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
		);
	}

	/**
	 * Get ticket purchase token definitions for triggers.
	 *
	 * @return array
	 */
	public function get_ticket_tokens_config() {

		return array(
			array(
				'tokenId'   => 'ORDER_ID',
				'tokenName' => esc_html_x( 'Order ID', 'Sugar Calendar', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'EVENT_ID',
				'tokenName' => esc_html_x( 'Event ID', 'Sugar Calendar', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'EVENT_TITLE',
				'tokenName' => esc_html_x( 'Event title', 'Sugar Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'BUYER_FIRST_NAME',
				'tokenName' => esc_html_x( 'Buyer first name', 'Sugar Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'BUYER_LAST_NAME',
				'tokenName' => esc_html_x( 'Buyer last name', 'Sugar Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'BUYER_EMAIL',
				'tokenName' => esc_html_x( 'Buyer email', 'Sugar Calendar', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'ORDER_TOTAL',
				'tokenName' => esc_html_x( 'Order total', 'Sugar Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ORDER_CURRENCY',
				'tokenName' => esc_html_x( 'Order currency', 'Sugar Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'TRANSACTION_ID',
				'tokenName' => esc_html_x( 'Transaction ID', 'Sugar Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ATTENDEE_COUNT',
				'tokenName' => esc_html_x( 'Attendee count', 'Sugar Calendar', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'ATTENDEE_NAMES',
				'tokenName' => esc_html_x( 'Attendee names', 'Sugar Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ATTENDEE_EMAILS',
				'tokenName' => esc_html_x( 'Attendee emails', 'Sugar Calendar', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Build token values for event triggers.
	 *
	 * @param int $event_id Sugar Calendar event ID (from sc_events table).
	 *
	 * @return array
	 */
	public function hydrate_event_tokens( $event_id ) {

		$defaults = wp_list_pluck( $this->get_event_tokens_config(), 'tokenId' );
		$tokens   = array_fill_keys( $defaults, '' );

		$event = sugar_calendar_get_event( $event_id );

		if ( empty( $event->id ) ) {
			return $tokens;
		}

		$tokens['EVENT_ID']         = $event_id;
		$tokens['EVENT_TITLE']      = $event->title;
		$tokens['EVENT_START_DATE'] = $event->start;
		$tokens['EVENT_END_DATE']   = $event->end;
		$tokens['ALL_DAY']          = ! empty( $event->all_day ) ? esc_html_x( 'Yes', 'Sugar Calendar', 'uncanny-automator' ) : esc_html_x( 'No', 'Sugar Calendar', 'uncanny-automator' );
		$tokens['DESCRIPTION']      = get_post_field( 'post_content', $event->object_id );

		$location = get_event_meta( $event_id, 'location', true );

		if ( ! empty( $location ) ) {
			$tokens['LOCATION'] = $location;
		}

		$calendar_terms = wp_get_post_terms( $event->object_id, 'sc_event_category', array( 'fields' => 'names' ) );

		if ( ! is_wp_error( $calendar_terms ) && ! empty( $calendar_terms ) ) {
			$tokens['CALENDAR_NAME'] = implode( ', ', $calendar_terms );
		}

		$tokens['EVENT_URL'] = get_permalink( $event->object_id );

		return $tokens;
	}
}
