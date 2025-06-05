<?php

namespace Uncanny_Automator\Integrations\Events_Manager;

use EM_Event;
use Uncanny_Automator\Recipe\Trigger;
use EM_Location;
use EM_Person;

/**
 * Class EM_EVENT_PUBLISHED
 *
 * Handles the trigger for when an event is published in Events Manager.
 * This trigger fires when a user publishes an event, either as a new event or when updating from draft/pending status.
 *
 * @package Uncanny_Automator
 * @subpackage Events_Manager
 */
class EM_EVENT_PUBLISHED extends Trigger {

	/**
	 * Constant TRIGGER_CODE.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'EM_EVENT_PUBLISHED';

	/**
	 * Constant TRIGGER_META.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'EM_EVENT_PUBLISHED_META';

	/**
	 * Setup trigger.
	 */
	protected function setup_trigger() {
		$this->set_integration( 'EVENTSMANAGER' );
		$this->set_trigger_code( self::TRIGGER_CODE );
		$this->set_trigger_meta( self::TRIGGER_META );
		$this->set_trigger_type( 'user' );

		// Set trigger sentences
		$this->set_sentence(
			sprintf(
				/* translators: Logged-in trigger - The Events Manager */
				esc_attr_x( 'A user publishes a new event', 'Event Manager', 'uncanny-automator' )
			)
		);

		$this->set_readable_sentence(
			/* translators: Logged-in trigger - The Events Manager */
			esc_attr_x( 'A user publishes a new event', 'Event Manager', 'uncanny-automator' )
		);

		$this->add_action( 'em_event_save', 99, 2 );
	}

	/**
	 * Validate the trigger.
	 *
	 * @param array $trigger     The trigger configuration.
	 * @param array $hook_args   The hook arguments from em_event_save.
	 *
	 * @return bool True if validation passes, false otherwise.
	 */
	public function validate( $trigger, $hook_args ) {
		if ( empty( $hook_args ) || ! isset( $hook_args[1] ) ) {
			return false;
		}

		$em_event = $hook_args[1];

		// Trigger only when publishing a new event (e.g. draft → publish)
		if ( 1 === (int) $em_event->previous_status || 1 !== (int) $em_event->event_status ) {
			return false;
		}

		$user_id = absint( $em_event->person_id );
		if ( empty( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		if ( empty( $user_id ) ) {
			return false;
		}

		$this->set_user_id( $user_id );

		return true;
	}

	/**
	 * Define available tokens for this trigger
	 *
	 * @param array $tokens   The existing tokens.
	 * @param array $trigger  The trigger configuration.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array(
			// Core Event Tokens
			'event_name'        => array(
				'name'      => esc_html_x( 'Event name', 'Events Manager', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'event_name',
				'tokenName' => esc_html_x( 'Event name', 'Events Manager', 'uncanny-automator' ),
			),
			'event_id'          => array(
				'name'      => esc_html_x( 'Event ID', 'Events Manager', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'event_id',
				'tokenName' => esc_html_x( 'Event ID', 'Events Manager', 'uncanny-automator' ),
			),
			'event_url'         => array(
				'name'      => esc_html_x( 'Event URL', 'Events Manager', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'event_url',
				'tokenName' => esc_html_x( 'Event URL', 'Events Manager', 'uncanny-automator' ),
			),
			'event_start_date'  => array(
				'name'      => esc_html_x( 'Event start date', 'Events Manager', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'event_start_date',
				'tokenName' => esc_html_x( 'Event start date', 'Events Manager', 'uncanny-automator' ),
			),
			'event_end_date'    => array(
				'name'      => esc_html_x( 'Event end date', 'Events Manager', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'event_end_date',
				'tokenName' => esc_html_x( 'Event end date', 'Events Manager', 'uncanny-automator' ),
			),
			'event_description' => array(
				'name'      => esc_html_x( 'Event description', 'Events Manager', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'event_description',
				'tokenName' => esc_html_x( 'Event description', 'Events Manager', 'uncanny-automator' ),
			),
			'event_excerpt'     => array(
				'name'      => esc_html_x( 'Event excerpt', 'Events Manager', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'event_excerpt',
				'tokenName' => esc_html_x( 'Event excerpt', 'Events Manager', 'uncanny-automator' ),
			),
			// Location Tokens
			'location_name'     => array(
				'name'      => esc_html_x( 'Location name', 'Events Manager', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'location_name',
				'tokenName' => esc_html_x( 'Location name', 'Events Manager', 'uncanny-automator' ),
			),
			'location_address'  => array(
				'name'      => esc_html_x( 'Location address', 'Events Manager', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'location_address',
				'tokenName' => esc_html_x( 'Location address', 'Events Manager', 'uncanny-automator' ),
			),
			'location_town'     => array(
				'name'      => esc_html_x( 'Location town/city', 'Events Manager', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'location_town',
				'tokenName' => esc_html_x( 'Location town/city', 'Events Manager', 'uncanny-automator' ),
			),
			'location_state'    => array(
				'name'      => esc_html_x( 'Location state/province', 'Events Manager', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'location_state',
				'tokenName' => esc_html_x( 'Location state/province', 'Events Manager', 'uncanny-automator' ),
			),
			'location_country'  => array(
				'name'      => esc_html_x( 'Location country', 'Events Manager', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'location_country',
				'tokenName' => esc_html_x( 'Location country', 'Events Manager', 'uncanny-automator' ),
			),
			'location_postcode' => array(
				'name'      => esc_html_x( 'Location postal code', 'Events Manager', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'location_postcode',
				'tokenName' => esc_html_x( 'Location postal code', 'Events Manager', 'uncanny-automator' ),
			),
			// Organizer Tokens
			'organizer_name'    => array(
				'name'      => esc_html_x( 'Organizer name', 'Events Manager', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'organizer_name',
				'tokenName' => esc_html_x( 'Organizer name', 'Events Manager', 'uncanny-automator' ),
			),
			'organizer_email'   => array(
				'name'      => esc_html_x( 'Organizer email', 'Events Manager', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'organizer_email',
				'tokenName' => esc_html_x( 'Organizer email', 'Events Manager', 'uncanny-automator' ),
			),
			'organizer_phone'   => array(
				'name'      => esc_html_x( 'Organizer phone', 'Events Manager', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'organizer_phone',
				'tokenName' => esc_html_x( 'Organizer phone', 'Events Manager', 'uncanny-automator' ),
			),
			// Additional Tokens
			'event_categories'  => array(
				'name'      => esc_html_x( 'Event categories', 'Events Manager', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'event_categories',
				'tokenName' => esc_html_x( 'Event categories', 'Events Manager', 'uncanny-automator' ),
			),
			'event_image_url'   => array(
				'name'      => esc_html_x( 'Event image URL', 'Events Manager', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'event_image_url',
				'tokenName' => esc_html_x( 'Event image URL', 'Events Manager', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Populate token values
	 *
	 * @param array $trigger    The trigger configuration.
	 * @param array $hook_args  Arguments from the hook.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		$em_event = $hook_args[1];

		// Initialize default values
		$token_values = array(
			// Core Event Tokens
			'event_name'        => $em_event->event_name ?? '-',
			'event_id'          => $em_event->event_id ?? '-',
			'event_url'         => get_permalink( $em_event->post_id ) ?? '-',
			'event_start_date'  => $em_event->event_start_date ?? '-',
			'event_end_date'    => $em_event->event_end_date ?? '-',
			'event_description' => $em_event->post_content ?? '-',
			'event_excerpt'     => get_the_excerpt( $em_event->post_id ) ?? '-',

			// Location Tokens
			'location_name'     => '-',
			'location_address'  => '-',
			'location_town'     => '-',
			'location_state'    => '-',
			'location_country'  => '-',
			'location_postcode' => '-',

			// Organizer Tokens
			'organizer_name'    => '-',
			'organizer_email'   => '-',
			'organizer_phone'   => '-',

			// Additional Tokens
			'event_categories'  => '',
			'event_image_url'   => '-',
		);

		// Process location data
		$location_obj = $em_event->get_location();
		if ( $location_obj instanceof \EM_Location ) {
			$token_values['location_name']     = $location_obj->location_name ?? '-';
			$token_values['location_address']  = $location_obj->location_address ?? '-';
			$token_values['location_town']     = $location_obj->location_town ?? '-';
			$token_values['location_state']    = $location_obj->location_state ?? '-';
			$token_values['location_country']  = $location_obj->location_country ?? '-';
			$token_values['location_postcode'] = $location_obj->location_postcode ?? '-';
		}

		// Process organizer data
		$organizer = $em_event->get_contact();
		if ( $organizer instanceof \EM_Person ) {
			$token_values['organizer_name']  = $organizer->get_name() ?? '-';
			$token_values['organizer_email'] = $organizer->user_email ?? '-';
			$token_values['organizer_phone'] = $organizer->phone ?? '-';
		}

		// Process categories
		$categories = $em_event->get_categories();
		if ( ! empty( $categories ) ) {
			$token_values['event_categories'] = implode( ', ', wp_list_pluck( $categories, 'name' ) );
		}

		// Process featured image
		if ( has_post_thumbnail( $em_event->post_id ) ) {
			$token_values['event_image_url'] = wp_get_attachment_url( get_post_thumbnail_id( $em_event->post_id ) );
		}

		return $token_values;
	}
}
