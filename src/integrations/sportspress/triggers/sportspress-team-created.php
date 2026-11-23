<?php

namespace Uncanny_Automator\Integrations\Sportspress;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Sportspress_Team_Created
 *
 * "A new team is created in {{a league}} and {{a season}}": the first time a
 * team is Published or Scheduled (Sportspress_Post_Dispatcher).
 *
 * @package Uncanny_Automator\Integrations\Sportspress
 * @since 8.1.0
 *
 * @property Sportspress_Helpers $item_helpers
 */
class Sportspress_Team_Created extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'SPORTSPRESS_TEAM_CREATED', 'SPORTSPRESS' )
			->trigger_meta( 'SP_LEAGUE' )
			->trigger_type( 'anonymous' )
			->hook( 'automator_sportspress_team_created', 10, 1 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_is_pro( false );

		$this->set_sentence(
			sprintf(
				/* translators: 1: The selected league, 2: The selected season */
				esc_html_x( 'A new team is created in {{a league:%1$s}} and {{a season:%2$s}}', 'SportsPress', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				'SP_SEASON:' . $this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'A new team is created in {{a league}} and {{a season}}', 'SportsPress', 'uncanny-automator' )
		);
	}

	/**
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'League', 'SportsPress', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'leagues' ),
			),
			array(
				'option_code'     => 'SP_SEASON',
				'label'           => esc_html_x( 'Season', 'SportsPress', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'seasons' ),
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
		return array_merge( $tokens, $this->team_tokens() );
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$team_id = $this->item_helpers->positive_id( $hook_args[0] ?? 0 );

		if ( null === $this->item_helpers->get_entity( $team_id, Sportspress_Helpers::TEAM ) ) {
			return false;
		}

		$league = $this->item_helpers->required_meta_value( $trigger, $this->get_trigger_meta() );
		$season = $this->item_helpers->required_meta_value( $trigger, 'SP_SEASON' );

		return $this->item_helpers->matches_term( $team_id, Sportspress_Helpers::TAX_LEAGUE, $league )
			&& $this->item_helpers->matches_term( $team_id, Sportspress_Helpers::TAX_SEASON, $season );
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$team_id = $this->item_helpers->positive_id( $hook_args[0] ?? 0 );
		$values  = $this->item_helpers->empty_keyset( $this->team_tokens() );
		$team    = $this->item_helpers->get_entity( $team_id, Sportspress_Helpers::TEAM );

		if ( null === $team ) {
			return $values;
		}

		$leagues = $this->item_helpers->term_lists( sp_get_leagues( $team_id, false ) );
		$seasons = $this->item_helpers->term_lists( sp_get_seasons( $team_id, false ) );
		$venues  = $this->item_helpers->term_lists( sp_get_venues( $team_id, false ) );

		return array_merge(
			$values,
			array(
				'SP_TEAM_ID'           => $team_id,
				'SP_TEAM_NAME'         => $team->post_title,
				'SP_TEAM_URL'          => (string) get_permalink( $team_id ),
				'SP_TEAM_SHORT_NAME'   => (string) sp_team_short_name( $team_id ),
				'SP_TEAM_ABBREVIATION' => (string) sp_get_abbreviation( $team_id ),
				'SP_TEAM_SITE_URL'     => (string) get_post_meta( $team_id, 'sp_url', true ),
				'SP_TEAM_LEAGUES'      => $leagues['names'],
				'SP_TEAM_LEAGUE_IDS'   => $leagues['ids'],
				'SP_TEAM_SEASONS'      => $seasons['names'],
				'SP_TEAM_SEASON_IDS'   => $seasons['ids'],
				'SP_TEAM_VENUES'       => $venues['names'],
				'SP_TEAM_VENUE_IDS'    => $venues['ids'],
			)
		);
	}

	/**
	 * @return array
	 */
	private function team_tokens() {

		$helpers = $this->item_helpers;

		return array(
			$helpers->token_def( 'SP_TEAM_ID', esc_html_x( 'Team ID', 'SportsPress', 'uncanny-automator' ), 'int' ),
			$helpers->token_def( 'SP_TEAM_NAME', esc_html_x( 'Team name', 'SportsPress', 'uncanny-automator' ), 'text' ),
			$helpers->token_def( 'SP_TEAM_URL', esc_html_x( 'Team URL', 'SportsPress', 'uncanny-automator' ), 'url' ),
			$helpers->token_def( 'SP_TEAM_SHORT_NAME', esc_html_x( 'Team short name', 'SportsPress', 'uncanny-automator' ), 'text' ),
			$helpers->token_def( 'SP_TEAM_ABBREVIATION', esc_html_x( 'Team abbreviation', 'SportsPress', 'uncanny-automator' ), 'text' ),
			$helpers->token_def( 'SP_TEAM_SITE_URL', esc_html_x( 'Team website', 'SportsPress', 'uncanny-automator' ), 'url' ),
			$helpers->token_def( 'SP_TEAM_LEAGUES', esc_html_x( 'Team leagues', 'SportsPress', 'uncanny-automator' ), 'text' ),
			$helpers->token_def( 'SP_TEAM_LEAGUE_IDS', esc_html_x( 'Team league IDs', 'SportsPress', 'uncanny-automator' ), 'text' ),
			$helpers->token_def( 'SP_TEAM_SEASONS', esc_html_x( 'Team seasons', 'SportsPress', 'uncanny-automator' ), 'text' ),
			$helpers->token_def( 'SP_TEAM_SEASON_IDS', esc_html_x( 'Team season IDs', 'SportsPress', 'uncanny-automator' ), 'text' ),
			$helpers->token_def( 'SP_TEAM_VENUES', esc_html_x( 'Team venues', 'SportsPress', 'uncanny-automator' ), 'text' ),
			$helpers->token_def( 'SP_TEAM_VENUE_IDS', esc_html_x( 'Team venue IDs', 'SportsPress', 'uncanny-automator' ), 'text' ),
		);
	}
}
