<?php

namespace Uncanny_Automator\Integrations\Sportspress;

use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;
use Uncanny_Automator\Recipe\Action;

/**
 * Class Sportspress_Create_Event
 *
 * "Create {{an event}}": a team-vs-team event. SportsPress has no create API,
 * so this writes the post, its meta and its terms. Like SportsPress's admin
 * screen, it gives the event the home team's venue when Venue is empty.
 *
 * @package Uncanny_Automator\Integrations\Sportspress
 * @since 8.1.0
 *
 * @property Sportspress_Helpers $item_helpers
 */
class Sportspress_Create_Event extends Action {

	/**
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'SPORTSPRESS' );
		$this->set_action_code( 'SPORTSPRESS_CREATE_EVENT' );
		$this->set_action_meta( 'SP_EVENT_TITLE' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s: The event */
				esc_html_x( 'Create {{an event:%1$s}}', 'SportsPress', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'Create {{an event}}', 'SportsPress', 'uncanny-automator' ) );
	}

	/**
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_html_x( 'Title', 'SportsPress', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
			array(
				'option_code'           => 'SP_EVENT_DATE',
				'label'                 => esc_html_x( 'Date', 'SportsPress', 'uncanny-automator' ),
				'input_type'            => 'date',
				'required'              => true,
				'supports_custom_value' => true,
				'supports_tokens'       => true,
			),
			array(
				'option_code'           => 'SP_EVENT_TIME',
				'label'                 => esc_html_x( 'Time', 'SportsPress', 'uncanny-automator' ),
				'input_type'            => 'time',
				'required'              => false,
				'supports_custom_value' => true,
				'supports_tokens'       => true,
				'description'           => esc_html_x( "In the site's time zone. Leave empty for midnight.", 'SportsPress', 'uncanny-automator' ),
			),
			array(
				'option_code'              => 'SP_TEAM',
				'label'                    => esc_html_x( 'Teams', 'SportsPress', 'uncanny-automator' ),
				'input_type'               => 'select',
				'required'                 => false,
				'supports_multiple_values' => true,
				'supports_custom_value'    => true,
				'options'                  => array(),
				'relevant_tokens'          => array(),
				'description'              => esc_html_x( 'The first team is the home team.', 'SportsPress', 'uncanny-automator' ),
				'remote_data'              => $this->item_helpers->remote_data_search_config( 'teams_strict' ),
			),
			array(
				'option_code'              => 'SP_LEAGUE',
				'label'                    => esc_html_x( 'Leagues', 'SportsPress', 'uncanny-automator' ),
				'input_type'               => 'select',
				'required'                 => false,
				'supports_multiple_values' => true,
				'options'                  => array(),
				'relevant_tokens'          => array(),
				'remote_data'              => $this->item_helpers->remote_data_load_config( 'leagues_strict' ),
			),
			array(
				'option_code'              => 'SP_SEASON',
				'label'                    => esc_html_x( 'Seasons', 'SportsPress', 'uncanny-automator' ),
				'input_type'               => 'select',
				'required'                 => false,
				'supports_multiple_values' => true,
				'options'                  => array(),
				'relevant_tokens'          => array(),
				'remote_data'              => $this->item_helpers->remote_data_load_config( 'seasons_strict' ),
			),
			array(
				'option_code'     => 'SP_VENUE',
				'label'           => esc_html_x( 'Venue', 'SportsPress', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => false,
				'options'         => array(),
				'relevant_tokens' => array(),
				'description'     => esc_html_x( "With no venue, the event gets the home team's venue.", 'SportsPress', 'uncanny-automator' ),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'venues_strict' ),
			),
			array(
				'option_code'           => 'SP_FORMAT',
				'label'                 => esc_html_x( 'Format', 'SportsPress', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'supports_custom_value' => false,
				'options'               => $this->item_helpers->format_options(),
				'default_value'         => 'league',
			),
			array(
				'option_code'     => 'SP_DAY',
				'label'           => esc_html_x( 'Match day', 'SportsPress', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => false,
				'supports_tokens' => true,
			),
			array(
				'option_code'     => 'SP_MINUTES',
				'label'           => esc_html_x( 'Minutes', 'SportsPress', 'uncanny-automator' ),
				'input_type'      => 'int',
				'required'        => false,
				'supports_tokens' => true,
				'description'     => esc_html_x( "Match duration. Leave empty to use SportsPress's default.", 'SportsPress', 'uncanny-automator' ),
			),
			array(
				'option_code'           => 'SP_POST_STATUS',
				'label'                 => esc_html_x( 'Status', 'SportsPress', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'supports_custom_value' => false,
				'options'               => $this->item_helpers->post_status_options( array( 'publish', 'future', 'draft' ) ),
				'default_value'         => 'publish',
				'description'           => esc_html_x( 'WordPress schedules a published event with a future date, and publishes a scheduled event with a past date.', 'SportsPress', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'SP_EVENT_ID'    => array(
				'name' => esc_html_x( 'Event ID', 'SportsPress', 'uncanny-automator' ),
				'type' => 'int',
			),
			'SP_EVENT_URL'   => array(
				'name' => esc_html_x( 'Event URL', 'SportsPress', 'uncanny-automator' ),
				'type' => 'url',
			),
			'SP_EVENT_TITLE' => array(
				'name' => esc_html_x( 'Event title', 'SportsPress', 'uncanny-automator' ),
				'type' => 'text',
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
	 * @throws Action_Error_Exception When the event cannot be created.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$helpers = $this->item_helpers;

		if ( ! $helpers->plugin_loaded() ) {
			throw Action_Error_Exception::execution_failed( esc_html_x( 'SportsPress is not active.', 'SportsPress', 'uncanny-automator' ) );
		}

		$title = trim( (string) ( $parsed[ $this->get_action_meta() ] ?? '' ) );

		if ( '' === $title ) {
			throw Action_Error_Exception::validation_failed( esc_html_x( 'An event title is required.', 'SportsPress', 'uncanny-automator' ) );
		}

		$date = $this->event_date( (string) ( $parsed['SP_EVENT_DATE'] ?? '' ), (string) ( $parsed['SP_EVENT_TIME'] ?? '' ) );

		$status = (string) ( $parsed['SP_POST_STATUS'] ?? '' );

		if ( ! in_array( $status, array( 'publish', 'future', 'draft' ), true ) ) {
			/* translators: %s: The status */
			throw Action_Error_Exception::validation_failed( sprintf( esc_html_x( '"%s" is not a status this action can create an event with.', 'SportsPress', 'uncanny-automator' ), esc_html( $status ) ) );
		}

		$format = (string) ( $parsed['SP_FORMAT'] ?? '' );

		if ( ! array_key_exists( $format, $helpers->get_event_formats() ) ) {
			/* translators: %s: The format */
			throw Action_Error_Exception::validation_failed( sprintf( esc_html_x( '"%s" is not an event format SportsPress offers.', 'SportsPress', 'uncanny-automator' ), esc_html( $format ) ) );
		}

		// Every value is checked before anything is written (R15).
		$selections = array(
			'teams'   => $helpers->selected_posts( $parsed, 'SP_TEAM', Sportspress_Helpers::TEAM, esc_html_x( 'Teams', 'SportsPress', 'uncanny-automator' ) ),
			'leagues' => $helpers->selected_terms( $parsed, 'SP_LEAGUE', Sportspress_Helpers::TAX_LEAGUE, esc_html_x( 'Leagues', 'SportsPress', 'uncanny-automator' ) ),
			'seasons' => $helpers->selected_terms( $parsed, 'SP_SEASON', Sportspress_Helpers::TAX_SEASON, esc_html_x( 'Seasons', 'SportsPress', 'uncanny-automator' ) ),
			'venue'   => $helpers->selected_terms( $parsed, 'SP_VENUE', Sportspress_Helpers::TAX_VENUE, esc_html_x( 'Venue', 'SportsPress', 'uncanny-automator' ) ),
		);

		foreach ( $selections as $selection ) {
			if ( '' !== $selection['error'] ) {
				throw Action_Error_Exception::validation_failed( esc_html( $selection['error'] ) );
			}
		}

		if ( count( $selections['venue']['ids'] ) > 1 ) {
			throw Action_Error_Exception::validation_failed( esc_html_x( 'Venue: an event takes one venue.', 'SportsPress', 'uncanny-automator' ) );
		}

		// Unset, SportsPress reads its sportspress_event_minutes option.
		$minutes = trim( (string) ( $parsed['SP_MINUTES'] ?? '' ) );

		if ( '' !== $minutes && ! ctype_digit( $minutes ) ) {
			/* translators: %s: The minutes value */
			throw Action_Error_Exception::validation_failed( sprintf( esc_html_x( '"%s" is not a number of minutes.', 'SportsPress', 'uncanny-automator' ), esc_html( $minutes ) ) );
		}

		$team_ids = $selections['teams']['ids'];

		$event_id = $helpers->insert_post(
			array(
				'post_type'     => Sportspress_Helpers::EVENT,
				'post_title'    => $title,
				'post_status'   => $status,
				'post_date'     => $date,
				// A draft's GMT date is otherwise left floating, and publishing it
				// later would replace the date with the publish time.
				'post_date_gmt' => get_gmt_from_date( $date ),
			)
		);

		if ( is_wp_error( $event_id ) ) {
			throw Action_Error_Exception::execution_failed( esc_html( $event_id->get_error_message() ) );
		}

		$event_id = (int) $event_id;

		update_post_meta( $event_id, 'sp_format', $format );
		// Team vs team, whatever the site's default mode is.
		update_post_meta( $event_id, 'sp_mode', 'team' );

		$day = sanitize_text_field( (string) ( $parsed['SP_DAY'] ?? '' ) );
		if ( '' !== $day ) {
			update_post_meta( $event_id, 'sp_day', $day );
		}

		if ( '' !== $minutes ) {
			update_post_meta( $event_id, 'sp_minutes', (int) $minutes );
		}

		$helpers->add_multi_meta( $event_id, 'sp_team', $team_ids );
		$helpers->set_terms( $event_id, Sportspress_Helpers::TAX_LEAGUE, $selections['leagues']['ids'] );
		$helpers->set_terms( $event_id, Sportspress_Helpers::TAX_SEASON, $selections['seasons']['ids'] );
		$helpers->set_terms( $event_id, Sportspress_Helpers::TAX_VENUE, $this->venue_ids( $selections['venue']['ids'], $team_ids ) );

		if ( null === $helpers->get_entity( $event_id, Sportspress_Helpers::EVENT ) ) {
			/* translators: %d: Event ID */
			throw Action_Error_Exception::execution_failed( sprintf( esc_html_x( 'Event %d was not saved as a SportsPress event.', 'SportsPress', 'uncanny-automator' ), esc_html( $event_id ) ) );
		}

		$this->hydrate_tokens(
			array(
				'SP_EVENT_ID'    => $event_id,
				'SP_EVENT_URL'   => (string) get_permalink( $event_id ),
				'SP_EVENT_TITLE' => (string) get_post_field( 'post_title', $event_id ),
			)
		);

		return true;
	}

	/**
	 * The event's local date and time.
	 *
	 * @param string $date
	 * @param string $time
	 *
	 * @return string Y-m-d H:i:s in the site's time zone.
	 * @throws Action_Error_Exception When the date is empty or unusable.
	 */
	private function event_date( $date, $time ) {

		$date = trim( $date );
		$time = trim( $time );

		if ( '' === $date ) {
			throw Action_Error_Exception::validation_failed( esc_html_x( 'An event date is required.', 'SportsPress', 'uncanny-automator' ) );
		}

		$moment = date_create( $date . ' ' . ( '' === $time ? '00:00' : $time ), wp_timezone() );

		if ( false === $moment ) {
			/* translators: 1: The date, 2: The time */
			throw Action_Error_Exception::validation_failed( sprintf( esc_html_x( '"%1$s %2$s" is not a date and time SportsPress can use.', 'SportsPress', 'uncanny-automator' ), esc_html( $date ), esc_html( $time ) ) );
		}

		return $moment->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * The chosen venue, or the home team's venue as SportsPress's admin save
	 * sets it.
	 *
	 * @param int[] $selected Checked venue IDs, at most one.
	 * @param int[] $team_ids
	 *
	 * @return int[]
	 */
	private function venue_ids( $selected, $team_ids ) {

		if ( ! empty( $selected ) ) {
			return $selected;
		}

		$venue_id = 0;

		if ( ! empty( $team_ids ) && function_exists( 'sp_get_the_term_id' ) ) {
			$venue_id = (int) sp_get_the_term_id( reset( $team_ids ), Sportspress_Helpers::TAX_VENUE );
		}

		return $venue_id > 0 ? array( $venue_id ) : array();
	}
}
