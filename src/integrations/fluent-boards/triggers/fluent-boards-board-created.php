<?php

namespace Uncanny_Automator\Integrations\Fluent_Boards;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Fluent_Boards_Board_Created
 *
 * @package Uncanny_Automator\Integrations\Fluent_Boards
 *
 * @property Fluent_Boards_Helpers $item_helpers
 */
class Fluent_Boards_Board_Created extends Trigger {

	/**
	 * Trigger definition.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'FLUENT_BOARDS_BOARD_CREATED', 'FLUENT_BOARDS' )
			->trigger_meta( 'FLUENT_BOARDS_BOARD_CREATED' )
			->trigger_type( 'anonymous' )
			->hook( 'fluent_boards/board_created', 10, 1 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_is_pro( false );
		$this->set_is_login_required( false );
		$this->set_sentence( esc_html_x( 'A board is created', 'Fluent Boards', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A board is created', 'Fluent Boards', 'uncanny-automator' ) );
	}

	/**
	 * Options.
	 *
	 * @return array
	 */
	public function options() {
		return array();
	}

	/**
	 * Define tokens.
	 *
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge( $tokens, $this->item_helpers->board_token_definitions() );
	}

	/**
	 * Validate.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		$board = isset( $hook_args[0] ) && is_object( $hook_args[0] ) ? $hook_args[0] : null;
		if ( null === $board || empty( $board->id ) ) {
			return false;
		}

		$created_by = isset( $board->created_by ) ? absint( $board->created_by ) : 0;
		if ( 0 !== $created_by ) {
			$this->set_user_id( $created_by );
		}

		return true;
	}

	/**
	 * Hydrate tokens.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		$board = isset( $hook_args[0] ) ? $hook_args[0] : null;
		return $this->item_helpers->hydrate_board_tokens( $board );
	}
}
