<?php

namespace Uncanny_Automator\Integrations\Sportspress;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Sportspress_Player_Created
 *
 * "A new player is created in {{a position}} on {{a team}}": the first time a
 * player is Published or Scheduled (Sportspress_Post_Dispatcher).
 *
 * @package Uncanny_Automator\Integrations\Sportspress
 * @since 8.1.0
 *
 * @property Sportspress_Helpers $item_helpers
 */
class Sportspress_Player_Created extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'SPORTSPRESS_PLAYER_CREATED', 'SPORTSPRESS' )
			->trigger_meta( 'SP_POSITION' )
			->trigger_type( 'anonymous' )
			->hook( 'automator_sportspress_player_created', 10, 1 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_is_pro( false );

		$this->set_sentence(
			sprintf(
				/* translators: 1: The selected position, 2: The selected team */
				esc_html_x( 'A new player is created in {{a position:%1$s}} on {{a team:%2$s}}', 'SportsPress', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				'SP_TEAM:' . $this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'A new player is created in {{a position}} on {{a team}}', 'SportsPress', 'uncanny-automator' )
		);
	}

	/**
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Position', 'SportsPress', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'positions' ),
			),
			array(
				'option_code'     => 'SP_TEAM',
				'label'           => esc_html_x( 'Team', 'SportsPress', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_search_config( 'teams' ),
			),
		);
	}

	/**
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge( $tokens, $this->player_tokens() );
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$player_id = $this->item_helpers->positive_id( $hook_args[0] ?? 0 );

		if ( null === $this->item_helpers->get_entity( $player_id, Sportspress_Helpers::PLAYER ) ) {
			return false;
		}

		$position = $this->item_helpers->required_meta_value( $trigger, $this->get_trigger_meta() );
		$team     = $this->item_helpers->required_meta_value( $trigger, 'SP_TEAM' );

		if ( null === $team ) {
			return false;
		}

		$on_team = Sportspress_Helpers::ANY === $team
			|| in_array( $this->item_helpers->positive_id( $team ), $this->item_helpers->player_current_team_ids( $player_id ), true );

		return $on_team && $this->item_helpers->matches_term( $player_id, Sportspress_Helpers::TAX_POSITION, $position );
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$player_id = $this->item_helpers->positive_id( $hook_args[0] ?? 0 );
		$values    = $this->item_helpers->empty_keyset( $this->player_tokens() );
		$player    = $this->item_helpers->get_entity( $player_id, Sportspress_Helpers::PLAYER );

		if ( null === $player ) {
			return $values;
		}

		$sp_player     = new \SP_Player( $player_id );
		$nationalities = $this->nationality_lists( (array) $sp_player->nationalities() );
		$positions     = $this->item_helpers->term_lists( $sp_player->positions() );
		$current_teams = $this->item_helpers->post_lists( $sp_player->current_teams() );
		$past_teams    = $this->item_helpers->post_lists( $sp_player->past_teams() );
		$leagues       = $this->item_helpers->term_lists( $sp_player->leagues() );
		$seasons       = $this->item_helpers->term_lists( $sp_player->seasons() );

		return array_merge(
			$values,
			array(
				'SP_PLAYER_ID'                => $player_id,
				'SP_PLAYER_NAME'              => (string) sp_get_player_name( $player_id ),
				'SP_PLAYER_URL'               => (string) get_permalink( $player_id ),
				'SP_PLAYER_NUMBER'            => (string) sp_get_player_number( $player_id ),
				'SP_PLAYER_NATIONALITIES'     => $nationalities['names'],
				'SP_PLAYER_NATIONALITY_CODES' => $nationalities['ids'],
				'SP_PLAYER_POSITIONS'         => $positions['names'],
				'SP_PLAYER_POSITION_IDS'      => $positions['ids'],
				'SP_PLAYER_CURRENT_TEAMS'     => $current_teams['names'],
				'SP_PLAYER_CURRENT_TEAM_IDS'  => $current_teams['ids'],
				'SP_PLAYER_PAST_TEAMS'        => $past_teams['names'],
				'SP_PLAYER_PAST_TEAM_IDS'     => $past_teams['ids'],
				'SP_PLAYER_LEAGUES'           => $leagues['names'],
				'SP_PLAYER_LEAGUE_IDS'        => $leagues['ids'],
				'SP_PLAYER_SEASONS'           => $seasons['names'],
				'SP_PLAYER_SEASON_IDS'        => $seasons['ids'],
				'SP_PLAYER_METRICS'           => $this->metrics_list( (array) $sp_player->metrics() ),
			)
		);
	}

	/**
	 * @return array
	 */
	private function player_tokens() {

		$helpers = $this->item_helpers;

		return array(
			$helpers->token_def( 'SP_PLAYER_ID', esc_html_x( 'Player ID', 'SportsPress', 'uncanny-automator' ), 'int' ),
			$helpers->token_def( 'SP_PLAYER_NAME', esc_html_x( 'Player name', 'SportsPress', 'uncanny-automator' ), 'text' ),
			$helpers->token_def( 'SP_PLAYER_URL', esc_html_x( 'Player URL', 'SportsPress', 'uncanny-automator' ), 'url' ),
			$helpers->token_def( 'SP_PLAYER_NUMBER', esc_html_x( 'Player number', 'SportsPress', 'uncanny-automator' ), 'text' ),
			$helpers->token_def( 'SP_PLAYER_NATIONALITIES', esc_html_x( 'Player nationalities', 'SportsPress', 'uncanny-automator' ), 'text' ),
			$helpers->token_def( 'SP_PLAYER_NATIONALITY_CODES', esc_html_x( 'Player nationality codes', 'SportsPress', 'uncanny-automator' ), 'text' ),
			$helpers->token_def( 'SP_PLAYER_POSITIONS', esc_html_x( 'Player positions', 'SportsPress', 'uncanny-automator' ), 'text' ),
			$helpers->token_def( 'SP_PLAYER_POSITION_IDS', esc_html_x( 'Player position IDs', 'SportsPress', 'uncanny-automator' ), 'text' ),
			$helpers->token_def( 'SP_PLAYER_CURRENT_TEAMS', esc_html_x( 'Player current teams', 'SportsPress', 'uncanny-automator' ), 'text' ),
			$helpers->token_def( 'SP_PLAYER_CURRENT_TEAM_IDS', esc_html_x( 'Player current team IDs', 'SportsPress', 'uncanny-automator' ), 'text' ),
			$helpers->token_def( 'SP_PLAYER_PAST_TEAMS', esc_html_x( 'Player past teams', 'SportsPress', 'uncanny-automator' ), 'text' ),
			$helpers->token_def( 'SP_PLAYER_PAST_TEAM_IDS', esc_html_x( 'Player past team IDs', 'SportsPress', 'uncanny-automator' ), 'text' ),
			$helpers->token_def( 'SP_PLAYER_LEAGUES', esc_html_x( 'Player leagues', 'SportsPress', 'uncanny-automator' ), 'text' ),
			$helpers->token_def( 'SP_PLAYER_LEAGUE_IDS', esc_html_x( 'Player league IDs', 'SportsPress', 'uncanny-automator' ), 'text' ),
			$helpers->token_def( 'SP_PLAYER_SEASONS', esc_html_x( 'Player seasons', 'SportsPress', 'uncanny-automator' ), 'text' ),
			$helpers->token_def( 'SP_PLAYER_SEASON_IDS', esc_html_x( 'Player season IDs', 'SportsPress', 'uncanny-automator' ), 'text' ),
			$helpers->token_def( 'SP_PLAYER_METRICS', esc_html_x( 'Player metrics', 'SportsPress', 'uncanny-automator' ), 'text' ),
		);
	}

	/**
	 * Country names joined with ", ", and the codes as the IDs.
	 *
	 * @param string[] $codes
	 *
	 * @return array{names: string, ids: string}
	 */
	private function nationality_lists( $codes ) {

		$countries = $this->item_helpers->get_countries();
		$names     = array();
		$ids       = array();

		foreach ( $codes as $code ) {
			$code = strtolower( trim( (string) $code ) );
			if ( '' === $code ) {
				continue;
			}
			$names[] = isset( $countries[ $code ] ) ? $countries[ $code ] : $code;
			$ids[]   = $code;
		}

		return array(
			'names' => implode( ', ', $names ),
			'ids'   => implode( ',', $ids ),
		);
	}

	/**
	 * "Label: value" pairs joined with ", ".
	 *
	 * @param array<string, string> $metrics
	 *
	 * @return string
	 */
	private function metrics_list( $metrics ) {

		$pairs = array();

		foreach ( $metrics as $label => $value ) {
			$pairs[] = $label . ': ' . $value;
		}

		return implode( ', ', $pairs );
	}
}
