<?php

namespace Uncanny_Automator\Integrations\Sportspress;

/**
 * Class Sportspress_Event_Tokens
 *
 * The event token family, in groups, so each trigger takes exactly the tokens
 * its scope doc lists:
 * - core: ID, title, date, permalink
 * - status: the WordPress post status
 * - details: format, mode, match day, minutes
 * - relations: venues, teams, leagues and seasons, each as names and as IDs
 *
 * @package Uncanny_Automator\Integrations\Sportspress
 */
class Sportspress_Event_Tokens {

	/**
	 * @var Sportspress_Helpers
	 */
	private $helpers;

	/**
	 * @param Sportspress_Helpers $helpers
	 */
	public function __construct( $helpers ) {
		$this->helpers = $helpers;
	}

	/**
	 * Token definitions for the given groups, in the given order.
	 *
	 * @param string[] $groups
	 *
	 * @return array
	 */
	public function definitions( $groups ) {

		$definitions = array();

		foreach ( (array) $groups as $group ) {
			$definitions = array_merge( $definitions, $this->group_definitions( $group ) );
		}

		return $definitions;
	}

	/**
	 * Token values for the given groups; the full keyset, empty when the event
	 * is gone.
	 *
	 * @param int      $event_id
	 * @param string[] $groups
	 *
	 * @return array
	 */
	public function hydrate( $event_id, $groups ) {

		$event  = $this->helpers->get_entity( $event_id, Sportspress_Helpers::EVENT );
		$values = $this->helpers->empty_keyset( $this->definitions( $groups ) );

		if ( null === $event ) {
			return $values;
		}

		foreach ( (array) $groups as $group ) {
			$values = array_merge( $values, $this->group_values( $group, $event ) );
		}

		return $values;
	}

	/**
	 * @param string $group
	 *
	 * @return array
	 */
	private function group_definitions( $group ) {

		switch ( $group ) {
			case 'core':
				return array(
					$this->helpers->token_def( 'SP_EVENT_ID', esc_html_x( 'Event ID', 'SportsPress', 'uncanny-automator' ), 'int' ),
					$this->helpers->token_def( 'SP_EVENT_TITLE', esc_html_x( 'Event title', 'SportsPress', 'uncanny-automator' ), 'text' ),
					$this->helpers->token_def( 'SP_EVENT_DATE', esc_html_x( 'Event date', 'SportsPress', 'uncanny-automator' ), 'text' ),
					$this->helpers->token_def( 'SP_EVENT_URL', esc_html_x( 'Event URL', 'SportsPress', 'uncanny-automator' ), 'url' ),
				);
			case 'status':
				return array(
					$this->helpers->token_def( 'SP_EVENT_STATUS', esc_html_x( 'Event post status', 'SportsPress', 'uncanny-automator' ), 'text' ),
				);
			case 'details':
				return array(
					$this->helpers->token_def( 'SP_EVENT_FORMAT', esc_html_x( 'Event format', 'SportsPress', 'uncanny-automator' ), 'text' ),
					$this->helpers->token_def( 'SP_EVENT_MODE', esc_html_x( 'Event mode', 'SportsPress', 'uncanny-automator' ), 'text' ),
					$this->helpers->token_def( 'SP_EVENT_DAY', esc_html_x( 'Match day', 'SportsPress', 'uncanny-automator' ), 'text' ),
					$this->helpers->token_def( 'SP_EVENT_MINUTES', esc_html_x( 'Match minutes', 'SportsPress', 'uncanny-automator' ), 'int' ),
				);
			case 'relations':
				return array(
					$this->helpers->token_def( 'SP_EVENT_VENUES', esc_html_x( 'Event venues', 'SportsPress', 'uncanny-automator' ), 'text' ),
					$this->helpers->token_def( 'SP_EVENT_VENUE_IDS', esc_html_x( 'Event venue IDs', 'SportsPress', 'uncanny-automator' ), 'text' ),
					$this->helpers->token_def( 'SP_EVENT_TEAMS', esc_html_x( 'Event teams', 'SportsPress', 'uncanny-automator' ), 'text' ),
					$this->helpers->token_def( 'SP_EVENT_TEAM_IDS', esc_html_x( 'Event team IDs', 'SportsPress', 'uncanny-automator' ), 'text' ),
					$this->helpers->token_def( 'SP_EVENT_LEAGUES', esc_html_x( 'Event leagues', 'SportsPress', 'uncanny-automator' ), 'text' ),
					$this->helpers->token_def( 'SP_EVENT_LEAGUE_IDS', esc_html_x( 'Event league IDs', 'SportsPress', 'uncanny-automator' ), 'text' ),
					$this->helpers->token_def( 'SP_EVENT_SEASONS', esc_html_x( 'Event seasons', 'SportsPress', 'uncanny-automator' ), 'text' ),
					$this->helpers->token_def( 'SP_EVENT_SEASON_IDS', esc_html_x( 'Event season IDs', 'SportsPress', 'uncanny-automator' ), 'text' ),
				);
		}

		return array();
	}

	/**
	 * @param string   $group
	 * @param \WP_Post $event
	 *
	 * @return array
	 */
	private function group_values( $group, $event ) {

		$event_id = (int) $event->ID;

		switch ( $group ) {
			case 'core':
				return array(
					'SP_EVENT_ID'    => $event_id,
					'SP_EVENT_TITLE' => $event->post_title,
					'SP_EVENT_DATE'  => (string) sp_get_date( $event_id ),
					'SP_EVENT_URL'   => (string) get_permalink( $event_id ),
				);
			case 'status':
				return array(
					'SP_EVENT_STATUS' => $this->helpers->post_status_label( $event->post_status ),
				);
			case 'details':
				$sp_event = new \SP_Event( $event_id );
				return array(
					'SP_EVENT_FORMAT'  => $this->format_label( (string) $sp_event->format() ),
					'SP_EVENT_MODE'    => $this->mode_label( (string) sp_get_post_mode( $event_id ) ),
					'SP_EVENT_DAY'     => (string) $sp_event->day(),
					'SP_EVENT_MINUTES' => (int) $sp_event->minutes(),
				);
			case 'relations':
				$venues  = $this->helpers->term_lists( sp_get_venues( $event_id, false ) );
				$teams   = $this->helpers->post_lists( $this->helpers->event_team_ids( $event_id ) );
				$leagues = $this->helpers->term_lists( sp_get_leagues( $event_id, false ) );
				$seasons = $this->helpers->term_lists( sp_get_seasons( $event_id, false ) );
				return array(
					'SP_EVENT_VENUES'     => $venues['names'],
					'SP_EVENT_VENUE_IDS'  => $venues['ids'],
					'SP_EVENT_TEAMS'      => $teams['names'],
					'SP_EVENT_TEAM_IDS'   => $teams['ids'],
					'SP_EVENT_LEAGUES'    => $leagues['names'],
					'SP_EVENT_LEAGUE_IDS' => $leagues['ids'],
					'SP_EVENT_SEASONS'    => $seasons['names'],
					'SP_EVENT_SEASON_IDS' => $seasons['ids'],
				);
		}

		return array();
	}

	/**
	 * @param string $format
	 *
	 * @return string
	 */
	private function format_label( $format ) {

		$formats = $this->helpers->get_event_formats();

		return isset( $formats[ $format ] ) ? (string) $formats[ $format ] : $format;
	}

	/**
	 * @param string $mode
	 *
	 * @return string
	 */
	private function mode_label( $mode ) {

		$modes = array(
			'team'   => esc_html_x( 'Team vs team', 'SportsPress', 'uncanny-automator' ),
			'player' => esc_html_x( 'Player vs player', 'SportsPress', 'uncanny-automator' ),
		);

		return isset( $modes[ $mode ] ) ? $modes[ $mode ] : $mode;
	}
}
