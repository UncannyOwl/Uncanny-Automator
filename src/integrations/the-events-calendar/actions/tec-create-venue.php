<?php

namespace Uncanny_Automator\Integrations\The_Events_Calendar;

/**
 * Class EC_CREATE_VENUE
 *
 * Creates a new venue via Tribe__Events__API::createVenue().
 *
 * @property The_Events_Calendar_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 */
class EC_CREATE_VENUE extends \Uncanny_Automator\Recipe\Action {

	/**
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'EC' );
		$this->set_action_code( 'EC_CREATE_VENUE' );
		$this->set_action_meta( 'VENUE_NAME' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );

		/* translators: %1$s is the venue name field */
		$this->set_sentence( sprintf( esc_html_x( 'Create {{a venue:%1$s}}', 'The Events Calendar', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Create {{a venue}}', 'The Events Calendar', 'uncanny-automator' ) );
	}

	/**
	 * @return array<string,array<string,string>>
	 */
	public function define_tokens() {
		return array(
			'EC_CREATED_VENUE_ID'   => array(
				'name' => esc_html_x( 'Venue ID', 'The Events Calendar', 'uncanny-automator' ),
				'type' => 'int',
			),
			'EC_CREATED_VENUE_NAME' => array(
				'name' => esc_html_x( 'Venue name', 'The Events Calendar', 'uncanny-automator' ),
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
				'option_code' => $this->get_action_meta(),
				'label'       => esc_html_x( 'Venue name', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
			array(
				'option_code' => 'VENUE_ADDRESS',
				'label'       => esc_html_x( 'Address', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code' => 'VENUE_CITY',
				'label'       => esc_html_x( 'City', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code' => 'VENUE_STATE_PROVINCE',
				'label'       => esc_html_x( 'State / Province', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code' => 'VENUE_ZIP',
				'label'       => esc_html_x( 'Zip / Postal code', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code'           => 'VENUE_COUNTRY',
				'label'                 => esc_html_x( 'Country', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => false,
				'supports_custom_value' => true,
				'options'               => $this->get_country_options(),
			),
			array(
				'option_code' => 'VENUE_PHONE',
				'label'       => esc_html_x( 'Phone', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code' => 'VENUE_URL',
				'label'       => esc_html_x( 'Website', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'  => 'url',
				'required'    => false,
			),
			array(
				'option_code' => 'VENUE_DESCRIPTION',
				'label'       => esc_html_x( 'Description', 'The Events Calendar', 'uncanny-automator' ),
				'input_type'  => 'textarea',
				'required'    => false,
			),
		);
	}

	/**
	 * Build the country dropdown options from TEC's own country list. Values are
	 * the full country names — the same thing TEC's venue admin stores in
	 * `Country` / `_VenueCountry`, so createVenue() persists them unchanged.
	 *
	 * @return array<int,array{value:string,text:string}>
	 */
	private function get_country_options() {

		$options = array(
			array(
				'value' => '',
				'text'  => esc_html_x( 'Select a country', 'The Events Calendar', 'uncanny-automator' ),
			),
		);

		if ( ! class_exists( 'Tribe__View_Helpers' ) ) {
			return $options;
		}

		// constructCountries() returns [ country_code => country_name ], led by a
		// '' => 'Select a Country:' placeholder we skip in favour of our own.
		foreach ( \Tribe__View_Helpers::constructCountries() as $code => $name ) {
			if ( '' === $code ) {
				continue;
			}
			$options[] = array(
				'value' => (string) $name,
				'text'  => (string) $name,
			);
		}

		return $options;
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

		$name = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' );

		if ( '' === $name ) {
			$this->add_log_error( esc_html_x( 'Venue name is required.', 'The Events Calendar', 'uncanny-automator' ) );
			return false;
		}

		// TEC stores the region in two country-specific metas — State (_VenueState,
		// US) and Province (_VenueProvince, non-US) — and the venue screen shows
		// whichever matches the country; StateProvince (_VenueStateProvince) feeds
		// the address output. We have one "State / Province" field, so route its
		// value to the right meta based on the country (mirroring TEC's own US
		// check) and always set StateProvince.
		$state_province = sanitize_text_field( $parsed['VENUE_STATE_PROVINCE'] ?? '' );
		$country        = sanitize_text_field( $parsed['VENUE_COUNTRY'] ?? '' );

		$is_us = in_array(
			$country,
			array( 'US', 'United States', esc_html__( 'United States', 'the-events-calendar' ) ),
			true
		);

		$data = array(
			'Venue'         => $name,
			'Address'       => sanitize_text_field( $parsed['VENUE_ADDRESS'] ?? '' ),
			'City'          => sanitize_text_field( $parsed['VENUE_CITY'] ?? '' ),
			'StateProvince' => $state_province,
			'State'         => $is_us ? $state_province : '',
			'Province'      => $is_us ? '' : $state_province,
			'Zip'           => sanitize_text_field( $parsed['VENUE_ZIP'] ?? '' ),
			'Country'       => $country,
			'Phone'         => sanitize_text_field( $parsed['VENUE_PHONE'] ?? '' ),
			'URL'           => esc_url_raw( $parsed['VENUE_URL'] ?? '' ),
			'Description'   => wp_kses_post( $parsed['VENUE_DESCRIPTION'] ?? '' ),
		);

		$venue_id = \Tribe__Events__API::createVenue( $data );

		if ( is_wp_error( $venue_id ) ) {
			$this->add_log_error( $venue_id->get_error_message() );
			return false;
		}

		if ( empty( $venue_id ) ) {
			$this->add_log_error( esc_html_x( 'Failed to create venue.', 'The Events Calendar', 'uncanny-automator' ) );
			return false;
		}

		$this->hydrate_tokens(
			array(
				'EC_CREATED_VENUE_ID'   => (int) $venue_id,
				'EC_CREATED_VENUE_NAME' => get_the_title( $venue_id ),
			)
		);

		return true;
	}
}
