<?php

namespace Uncanny_Automator\Integrations\Wp_Event_Manager;

/**
 * Class Wp_Event_Manager_Helpers
 *
 * @package Uncanny_Automator
 */
class Wp_Event_Manager_Helpers {

	/**
	 * Get all events for options.
	 *
	 * @param bool $is_any Whether to include "Any event" option.
	 * @return array
	 */
	public function get_all_events( $is_any = true ) {
		$all_events = array();

		if ( true === $is_any ) {
			$all_events[] = array(
				'text'  => esc_html_x( 'Any event', 'WP Event Manager', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		$args = array(
			'post_type'      => 'event_listing',
			'posts_per_page' => 9999, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$events = get_posts( $args );

		foreach ( $events as $event ) {
			$all_events[] = array(
				'text'  => $event->post_title,
				'value' => (string) $event->ID,
			);
		}

		return $all_events;
	}

	/**
	 * Get event details by ID.
	 *
	 * @param int $event_id Event ID.
	 * @return array
	 */
	public function get_event_details( $event_id ) {
		$event = get_post( $event_id );

		if ( ! $event ) {
			return array();
		}

		$format           = sprintf( '%s %s', get_option( 'date_format' ), get_option( 'time_format' ) );
		$event_start_date = date_i18n( $format, strtotime( get_post_meta( $event_id, '_event_start_date', true ) ) );
		$event_end_date   = date_i18n( $format, strtotime( get_post_meta( $event_id, '_event_end_date', true ) ) );
		$is_online_event  = get_post_meta( $event_id, '_event_online', true );
		$event_location   = 'yes' === $is_online_event ? 'Online Event' : get_event_location( $event );
		$venues           = get_post_meta( $event_id, '_event_venue_ids', true );
		$event_banner     = get_post_meta( $event_id, '_event_banner', true );
		$event_video      = get_post_meta( $event_id, '_event_video_url', true );
		$organizer_array  = get_post_meta( $event_id, '_event_organizer_ids', true );

		$event_organizer = array();
		$event_venue     = '';
		if ( isset( $organizer_array ) && ! empty( $organizer_array ) ) {
			foreach ( $organizer_array as $org_id ) {
				$organizer         = get_post( $org_id );
				$event_organizer[] = $organizer->post_title;
			}
		}

		if ( isset( $venues ) ) {
			$venue       = get_post( $venues );
			$event_venue = $venue->post_title;
		}

			// Get event types
		$event_types        = wp_get_post_terms( $event_id, 'event_listing_type', array( 'fields' => 'names' ) );
		$event_types_string = is_array( $event_types ) ? implode( ', ', $event_types ) : '';

		// Get event categories
		$event_categories        = wp_get_post_terms( $event_id, 'event_listing_category', array( 'fields' => 'names' ) );
		$event_categories_string = is_array( $event_categories ) ? implode( ', ', $event_categories ) : '';

		return array(
			'event_id'              => $event_id,
			'event_title'           => $event->post_title,
			'event_content'         => $event->post_content,
			'event_start_datetime'  => $event_start_date,
			'event_end_datetime'    => $event_end_date,
			'event_location'        => $event_location,
			'event_banner_url'      => $event_banner,
			'event_video_url'       => $event_video,
			'event_organizer'       => join( ', ', $event_organizer ),
			'event_venues'          => $event_venue,
			'event_types'           => $event_types_string,
			'event_categories'      => $event_categories_string,
			'event_url'             => get_permalink( $event_id ),
		);
	}

	/**
	 * Get common tokens for events.
	 *
	 * @param int $event_id Event ID (optional).
	 * @return array
	 */
	public function get_common_event_tokens( $event_id = null ) {
		$event_tokens = array(
			array(
				'tokenId'   => 'EVENT_ID',
				'tokenName' => esc_html_x( 'Event ID', 'WP Event Manager', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'EVENT_TITLE',
				'tokenName' => esc_html_x( 'Event title', 'WP Event Manager', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'EVENT_START_DATETIME',
				'tokenName' => esc_html_x( 'Event start date & time', 'WP Event Manager', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'EVENT_END_DATETIME',
				'tokenName' => esc_html_x( 'Event end date & time', 'WP Event Manager', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'EVENT_BANNER_URL',
				'tokenName' => esc_html_x( 'Event banner URL', 'WP Event Manager', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'EVENT_VIDEO_URL',
				'tokenName' => esc_html_x( 'Event video URL', 'WP Event Manager', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'EVENT_ORGANIZER',
				'tokenName' => esc_html_x( 'Event organizer', 'WP Event Manager', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'EVENT_VENUE',
				'tokenName' => esc_html_x( 'Event venue', 'WP Event Manager', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'EVENT_LOCATION',
				'tokenName' => esc_html_x( 'Event location', 'WP Event Manager', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'EVENT_TYPES',
				'tokenName' => esc_html_x( 'Event types', 'WP Event Manager', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'EVENT_CATEGORIES',
				'tokenName' => esc_html_x( 'Event categories', 'WP Event Manager', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'EVENT_URL',
				'tokenName' => esc_html_x( 'Event URL', 'WP Event Manager', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
		);

		// Only add form field tokens if event ID is provided and WP Event Manager Registrations is active
		if ( $event_id && function_exists( 'get_event_registration_form_fields' ) ) {
			$event_registration_form_fields = $this->get_event_registration_form_fields( $event_id );
			foreach ( $event_registration_form_fields as $field_key => $field_data ) {
				$event_tokens[] = array(
					'tokenId'   => 'EVENT_FORM_FIELD|' . str_replace( ' ', '_', $field_key ),
					'tokenName' => esc_html_x( $field_data['label'], 'WP Event Manager', 'uncanny-automator' ),
					'tokenType' => $this->get_token_type_from_field_type( $field_data['type'] ),
				);
			}
		}

		return $event_tokens;
	}

	/**
	 * Parse common token values for events.
	 *
	 * @param int $event_id Event ID.
	 * @param int $registration_id Registration ID (optional).
	 * @return array
	 */
	public function parse_common_event_token_values( $event_id, $registration_id = null ) {
		$event_details = $this->get_event_details( $event_id );

		// Generate array of empty default values.
		$defaults = wp_list_pluck( $this->get_common_event_tokens(), 'tokenId' );
		$tokens   = array_fill_keys( $defaults, '' );

		$tokens['EVENT_ID']             = $event_details['event_id'];
		$tokens['EVENT_TITLE']          = $event_details['event_title'];
		$tokens['EVENT_START_DATETIME'] = $event_details['event_start_datetime'];
		$tokens['EVENT_END_DATETIME']   = $event_details['event_end_datetime'];
		$tokens['EVENT_BANNER_URL']     = $event_details['event_banner_url'];
		$tokens['EVENT_VIDEO_URL']      = $event_details['event_video_url'];
		$tokens['EVENT_ORGANIZER']      = $event_details['event_organizer'];
		$tokens['EVENT_VENUE']          = $event_details['event_venues'];
		$tokens['EVENT_LOCATION']       = $event_details['event_location'];
		$tokens['EVENT_TYPES']          = $event_details['event_types'];
		$tokens['EVENT_CATEGORIES']     = $event_details['event_categories'];
		$tokens['EVENT_URL']            = $event_details['event_url'];

		// Parse event form field tokens if registration ID is provided
		if ( $registration_id ) {
			$registration_meta = get_post_custom( $registration_id );
			$form_field_tokens = $this->parse_all_event_form_field_tokens( $registration_id, $event_id, $registration_meta );
			$tokens            = array_merge( $tokens, $form_field_tokens );
		}

		return $tokens;
	}


	/**
	 * Get event registration form fields.
	 *
	 * @param int $event_id Event ID (optional).
	 * @return array
	 */
	public function get_event_registration_form_fields( $event_id = null ) {
		// Check if WP Event Manager Registrations plugin is active
		if ( ! function_exists( 'get_event_registration_form_fields' ) ) {
			return array();
		}

		// Get all registration form fields
		$all_fields = get_event_registration_form_fields();

		// If event ID is provided, try to get event-specific fields
		if ( $event_id && function_exists( 'get_event_organizer_attendee_fields' ) ) {
			$event_fields = get_event_organizer_attendee_fields( $event_id );
			return ! empty( $event_fields ) ? $event_fields : $all_fields;
		}

		return $all_fields;
	}

	/**
	 * Get registration form field by key.
	 *
	 * @param string $field_key Field key.
	 * @param int $event_id Event ID (optional).
	 * @return array|false
	 */
	public function get_registration_form_field( $field_key, $event_id = null ) {
		$fields = $this->get_event_registration_form_fields( $event_id );

		return isset( $fields[ $field_key ] ) ? $fields[ $field_key ] : false;
	}

	/**
	 * Get registration form field value from registration.
	 *
	 * @param int $registration_id Registration ID.
	 * @param string $field_key Field key.
	 * @return mixed
	 */
	public function get_registration_field_value( $registration_id, $field_key ) {
		$value = get_post_meta( $registration_id, '_' . $field_key, true );

		// If value is empty, try without underscore prefix
		if ( empty( $value ) ) {
			$value = get_post_meta( $registration_id, $field_key, true );
		}

		return $value;
	}

	/**
	 * Get all registration field values for a registration.
	 *
	 * @param int $registration_id Registration ID.
	 * @return array
	 */
	public function get_all_registration_field_values( $registration_id ) {
		$fields = $this->get_event_registration_form_fields();
		$values = array();

		foreach ( $fields as $field_key => $field_data ) {
			$values[ $field_key ] = $this->get_registration_field_value( $registration_id, $field_key );
		}

		return $values;
	}

	/**
	 * Get token type from field type.
	 *
	 * @param string $field_type Field type.
	 * @return string
	 */
	private function get_token_type_from_field_type( $field_type ) {
		switch ( $field_type ) {
			case 'email':
				return 'email';
			case 'url':
				return 'url';
			case 'number':
				return 'int';
			case 'date':
			case 'datetime-local':
				return 'text';
			default:
				return 'text';
		}
	}

	/**
	 * Parse all event form field tokens for a registration.
	 *
	 * @param int $registration_id Registration ID.
	 * @param int $event_id Event ID.
	 * @return array
	 */
	public function parse_all_event_form_field_tokens( $registration_id, $event_id, $registration_meta ) {
		$fields = $this->get_event_registration_form_fields( $event_id );
		$tokens = array();

		foreach ( $fields as $field_key => $field_data ) {
			$token_id            = 'EVENT_FORM_FIELD|' . str_replace( ' ', '_', $field_key );
			$token_value         = isset( $registration_meta[ '_' . $field_key ][0] ) ? maybe_unserialize( $registration_meta[ '_' . $field_key ][0] ) : '';
			$token_value         = is_serialized( $token_value ) ? maybe_unserialize( $token_value ) : $token_value;
			$tokens[ $token_id ] = is_bool( $token_value ) ? ( $token_value ? 'Yes' : 'No' ) : $token_value;
		}

		return $tokens;
	}
	/**
	 * Get a single post ID by meta key/value.
	 *
	 * @param string $meta_key
	 * @param mixed  $meta_value
	 * @param string $post_type Optional post type filter.
	 * @return int|null
	 */
	function get_registration_id_by_attendee_email( $attendee_email, $event_id ) {
		$args = array(
			'post_type'      => 'event_registration',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'parent'         => $event_id,
			'no_found_rows'  => true,
			'meta_query'     => array(
				array(
					'key'   => '_attendee_email',
					'value' => $attendee_email,
				),
			),
		);

		$posts = get_posts( $args );

		return ! empty( $posts ) ? (int) $posts[0] : null;
	}
}
