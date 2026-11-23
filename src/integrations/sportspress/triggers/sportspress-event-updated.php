<?php

namespace Uncanny_Automator\Integrations\Sportspress;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Sportspress_Event_Updated
 *
 * "An event is updated in {{a league}} and {{a season}}": an event saved while
 * Published or Scheduled before and after the save (Sportspress_Post_Dispatcher).
 * The tokens are read after SportsPress saved the event's meta.
 *
 * @package Uncanny_Automator\Integrations\Sportspress
 * @since 8.1.0
 *
 * @property Sportspress_Helpers $item_helpers
 */
class Sportspress_Event_Updated extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'SPORTSPRESS_EVENT_UPDATED', 'SPORTSPRESS' )
			->trigger_meta( 'SP_LEAGUE' )
			->trigger_type( 'anonymous' )
			->hook( 'automator_sportspress_event_updated', 10, 1 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_is_pro( false );

		$this->set_sentence(
			sprintf(
				/* translators: 1: The selected league, 2: The selected season */
				esc_html_x( 'An event is updated in {{a league:%1$s}} and {{a season:%2$s}}', 'SportsPress', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				'SP_SEASON:' . $this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'An event is updated in {{a league}} and {{a season}}', 'SportsPress', 'uncanny-automator' )
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
		return array_merge( $tokens, $this->item_helpers->event_token_definitions( array( 'core', 'status', 'details', 'relations' ) ) );
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$event_id = $this->item_helpers->positive_id( $hook_args[0] ?? 0 );

		if ( null === $this->item_helpers->get_entity( $event_id, Sportspress_Helpers::EVENT ) ) {
			return false;
		}

		$league = $this->item_helpers->required_meta_value( $trigger, $this->get_trigger_meta() );
		$season = $this->item_helpers->required_meta_value( $trigger, 'SP_SEASON' );

		return $this->item_helpers->matches_term( $event_id, Sportspress_Helpers::TAX_LEAGUE, $league )
			&& $this->item_helpers->matches_term( $event_id, Sportspress_Helpers::TAX_SEASON, $season );
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		return $this->item_helpers->hydrate_event_tokens(
			$this->item_helpers->positive_id( $hook_args[0] ?? 0 ),
			array( 'core', 'status', 'details', 'relations' )
		);
	}
}
