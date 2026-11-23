<?php

namespace Uncanny_Automator\Integrations\Sportspress;

use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;
use Uncanny_Automator\App\Recipe_Runner\Services\Error_Code;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Action_Error;
use Uncanny_Automator\Recipe\Action;

/**
 * Class Sportspress_Update_Event_Results
 *
 * "Update the results of {{an event}}" through sp_update_main_results(), the
 * call SportsPress's inline results editor makes. It writes each participant's
 * primary result and recomputes the outcomes.
 *
 * Every participant goes into the call, as the inline editor posts them: one
 * team's score alone would take the method's all-equal branch and give every
 * team the draw outcome. A participant the recipe leaves empty keeps its
 * stored result.
 *
 * The method returns nothing on success, so the result is read back.
 *
 * @package Uncanny_Automator\Integrations\Sportspress
 * @since 8.1.0
 *
 * @property Sportspress_Helpers $item_helpers
 */
class Sportspress_Update_Event_Results extends Action {

	/**
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'SPORTSPRESS' );
		$this->set_action_code( 'SPORTSPRESS_UPDATE_EVENT_RESULTS' );
		$this->set_action_meta( 'SP_EVENT' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s: The event */
				esc_html_x( 'Update the results of {{an event:%1$s}}', 'SportsPress', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'Update the results of {{an event}}', 'SportsPress', 'uncanny-automator' ) );
	}

	/**
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_html_x( 'Event', 'SportsPress', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => array(),
				'relevant_tokens'       => array(),
				'supports_custom_value' => true,
				'description'           => esc_html_x( 'Select an event or insert an event ID token.', 'SportsPress', 'uncanny-automator' ),
				'remote_data'           => $this->item_helpers->remote_data_search_config( 'events_strict' ),
			),
			array(
				'option_code'       => 'SP_RESULTS',
				'input_type'        => 'repeater',
				'label'             => esc_html_x( 'Results', 'SportsPress', 'uncanny-automator' ),
				'description'       => esc_html_x( "One row per team. A team with an empty result keeps its current result. For an event given as a token, enter each team's ID or a token.", 'SportsPress', 'uncanny-automator' ),
				'required'          => true,
				'relevant_tokens'   => array(),
				'add_row_button'    => esc_html_x( 'Add a team', 'SportsPress', 'uncanny-automator' ),
				'remove_row_button' => esc_html_x( 'Remove a team', 'SportsPress', 'uncanny-automator' ),
				'fields'            => $this->item_helpers->result_row_fields( array() ),
				'remote_data'       => $this->item_helpers->remote_data_with_mapping_column(
					$this->item_helpers->remote_data_parent_config( 'event_teams_strict', array( $this->get_action_meta() ) ),
					'SP_RESULT_TEAM'
				),
			),
		);
	}

	/**
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'SP_EVENT_ID'      => array(
				'name' => esc_html_x( 'Event ID', 'SportsPress', 'uncanny-automator' ),
				'type' => 'int',
			),
			'SP_EVENT_TITLE'   => array(
				'name' => esc_html_x( 'Event title', 'SportsPress', 'uncanny-automator' ),
				'type' => 'text',
			),
			'SP_EVENT_WINNER'  => array(
				'name' => esc_html_x( 'Winning team', 'SportsPress', 'uncanny-automator' ),
				'type' => 'text',
			),
			'SP_EVENT_RESULTS' => array(
				'name' => esc_html_x( 'Results', 'SportsPress', 'uncanny-automator' ),
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
	 * @return bool|null
	 * @throws Action_Error_Exception When the results cannot be updated.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$helpers = $this->item_helpers;

		if ( ! function_exists( 'sp_update_main_results' ) ) {
			throw Action_Error_Exception::execution_failed( esc_html_x( 'SportsPress is not active.', 'SportsPress', 'uncanny-automator' ) );
		}

		$event_id = $helpers->positive_id( $parsed[ $this->get_action_meta() ] ?? 0 );

		if ( null === $helpers->get_entity( $event_id, Sportspress_Helpers::EVENT ) ) {
			/* translators: %d: Event ID */
			throw Action_Error_Exception::not_found( sprintf( esc_html_x( 'Event %d was not found.', 'SportsPress', 'uncanny-automator' ), esc_html( $event_id ) ) );
		}

		$main_result = sp_get_main_result_option();

		if ( null === $main_result ) {
			throw Action_Error_Exception::execution_failed( esc_html_x( 'SportsPress has no results configured, so there is no main result to write. Add one under SportsPress > Configure > Results.', 'SportsPress', 'uncanny-automator' ) );
		}

		$participants = $helpers->event_team_ids( $event_id );

		if ( empty( $participants ) ) {
			/* translators: %d: Event ID */
			throw Action_Error_Exception::validation_failed( sprintf( esc_html_x( 'Event %d has no teams.', 'SportsPress', 'uncanny-automator' ), esc_html( $event_id ) ) );
		}

		$unmatched = array();
		$given     = $this->given_results( $parsed['SP_RESULTS'] ?? '', $participants, $unmatched );

		if ( empty( $given ) ) {
			$this->log_unmatched( $unmatched );

			throw Action_Error_Exception::validation_failed( esc_html_x( "No result was given for any of the event's teams.", 'SportsPress', 'uncanny-automator' ) );
		}

		$stored = sp_get_results( $event_id );
		$map    = array();

		foreach ( $participants as $team_id ) {
			$map[ $team_id ] = array_key_exists( $team_id, $given ) ? $given[ $team_id ] : (string) ( $stored[ $team_id ][ $main_result ] ?? '' );
		}

		sp_update_main_results( $event_id, $map );

		$after = sp_get_results( $event_id );

		foreach ( $map as $team_id => $value ) {
			if ( (string) ( $after[ $team_id ][ $main_result ] ?? '' ) !== (string) $value ) {
				/* translators: 1: Team ID, 2: Event ID */
				throw Action_Error_Exception::execution_failed( sprintf( esc_html_x( "Team %1\$d's result on event %2\$d was not saved.", 'SportsPress', 'uncanny-automator' ), esc_html( $team_id ), esc_html( $event_id ) ) );
			}
		}

		$winner = $helpers->positive_id( sp_get_winner( $event_id ) );

		$this->hydrate_tokens(
			array(
				'SP_EVENT_ID'      => $event_id,
				'SP_EVENT_TITLE'   => (string) get_post_field( 'post_title', $event_id ),
				'SP_EVENT_WINNER'  => $winner > 0 ? (string) get_post_field( 'post_title', $winner ) : '',
				'SP_EVENT_RESULTS' => $this->results_list( $participants, $after, $main_result ),
			)
		);

		// The results were written; rows naming another team are a notice.
		if ( ! empty( $unmatched ) ) {
			$this->log_unmatched( $unmatched );
			$this->set_complete_with_notice( true );
			return null;
		}

		return true;
	}

	/**
	 * The recipe's non-empty results for the event's participants, keyed by
	 * participant ID.
	 *
	 * @param mixed    $rows
	 * @param int[]    $participants
	 * @param string[] $unmatched Rows naming another team.
	 *
	 * @return array<int, string>
	 */
	private function given_results( $rows, $participants, &$unmatched ) {

		if ( ! is_array( $rows ) ) {
			$rows = json_decode( (string) $rows, true );
		}

		$given = array();

		foreach ( is_array( $rows ) ? $rows : array() as $row ) {

			if ( ! is_array( $row ) ) {
				continue;
			}

			$team  = trim( (string) ( $row['SP_RESULT_TEAM'] ?? '' ) );
			$value = sanitize_text_field( (string) ( $row['SP_RESULT_SCORE'] ?? '' ) );

			if ( 'automator_custom_value' === $team ) {
				$team = trim( (string) ( $row['SP_RESULT_TEAM_custom'] ?? '' ) );
			}

			if ( '' === $team && '' === $value ) {
				continue;
			}

			$team_id = $this->item_helpers->positive_id( $team );

			if ( ! in_array( $team_id, $participants, true ) ) {
				$unmatched[] = $team;
				continue;
			}

			if ( '' !== $value ) {
				$given[ $team_id ] = $value;
			}
		}

		return $given;
	}

	/**
	 * "Team name: value" pairs joined with ", ", in the event's order.
	 *
	 * @param int[]  $participants
	 * @param array  $results
	 * @param string $main_result
	 *
	 * @return string
	 */
	private function results_list( $participants, $results, $main_result ) {

		$pairs = array();

		foreach ( $participants as $team_id ) {
			$pairs[] = get_post_field( 'post_title', $team_id ) . ': ' . ( $results[ $team_id ][ $main_result ] ?? '' );
		}

		return implode( ', ', $pairs );
	}

	/**
	 * @param string[] $unmatched
	 *
	 * @return void
	 */
	private function log_unmatched( $unmatched ) {

		if ( empty( $unmatched ) ) {
			return;
		}

		$this->add_error(
			new Action_Error(
				Error_Code::VALIDATION_FAILED,
				/* translators: %s: Comma-separated team IDs or values */
				sprintf( esc_html_x( "These rows don't name a team on the event and were skipped: %s", 'SportsPress', 'uncanny-automator' ), esc_html( implode( ', ', $unmatched ) ) )
			)
		);
	}
}
