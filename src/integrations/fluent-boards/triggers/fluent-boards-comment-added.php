<?php

namespace Uncanny_Automator\Integrations\Fluent_Boards;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Fluent_Boards_Comment_Added
 *
 * @package Uncanny_Automator\Integrations\Fluent_Boards
 *
 * @property Fluent_Boards_Helpers $item_helpers
 */
class Fluent_Boards_Comment_Added extends Trigger {

	/**
	 * Trigger definition.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'FLUENT_BOARDS_COMMENT_ADDED', 'FLUENT_BOARDS' )
			->trigger_meta( Fluent_Boards_Helpers::BOARD )
			->trigger_type( 'anonymous' )
			->hook( 'fluent_boards/comment_created', 10, 1 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_is_pro( false );
		$this->set_is_login_required( false );
		$this->set_sentence(
			sprintf(
				/* translators: %1$s - board */
				esc_html_x( 'A comment is added to a task on {{a board:%1$s}}', 'Fluent Boards', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'A comment is added to a task on {{a board}}', 'Fluent Boards', 'uncanny-automator' ) );
	}

	/**
	 * Options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Board', 'Fluent Boards', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'boards' ),
			),
		);
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
		return array_merge( $tokens, $this->item_helpers->comment_token_definitions() );
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
		$comment = isset( $hook_args[0] ) && is_object( $hook_args[0] ) ? $hook_args[0] : null;
		if ( null === $comment || empty( $comment->id ) ) {
			return false;
		}

		$created_by = isset( $comment->created_by ) ? absint( $comment->created_by ) : 0;
		if ( 0 !== $created_by ) {
			$this->set_user_id( $created_by );
		}

		$selected_board = $this->item_helpers->required_meta_value( $trigger, $this->get_trigger_meta() );

		// Required field: an absent value must not fall back to "any board".
		if ( null === $selected_board ) {
			return false;
		}

		if ( Fluent_Boards_Helpers::ANY !== $selected_board && absint( $selected_board ) !== absint( $comment->board_id ) ) {
			return false;
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
		$comment = isset( $hook_args[0] ) ? $hook_args[0] : null;
		return $this->item_helpers->hydrate_comment_tokens( $comment );
	}
}
