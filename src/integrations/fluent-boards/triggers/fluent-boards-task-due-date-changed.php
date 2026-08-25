<?php

namespace Uncanny_Automator\Integrations\Fluent_Boards;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Fluent_Boards_Task_Due_Date_Changed
 *
 * @package Uncanny_Automator\Integrations\Fluent_Boards
 *
 * @property Fluent_Boards_Helpers $item_helpers
 */
class Fluent_Boards_Task_Due_Date_Changed extends Trigger {

	/**
	 * Trigger definition.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'FLUENT_BOARDS_TASK_DUE_DATE_CHANGED', 'FLUENT_BOARDS' )
			->trigger_meta( Fluent_Boards_Helpers::BOARD )
			->trigger_type( 'anonymous' )
			->hook( 'fluent_boards/task_due_date_changed', 10, 2 );
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
				esc_html_x( "A task's due date is set or changed on {{a board:%1\$s}}", 'Fluent Boards', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( "A task's due date is set or changed on {{a board}}", 'Fluent Boards', 'uncanny-automator' ) );
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
		return array_merge(
			$tokens,
			$this->item_helpers->task_token_definitions(),
			array(
				array(
					'tokenId'   => 'FB_TASK_OLD_DUE_DATE',
					'tokenName' => esc_html_x( 'Old due date', 'Fluent Boards', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
			)
		);
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
		$task = isset( $hook_args[0] ) && is_object( $hook_args[0] ) ? $hook_args[0] : null;
		if ( null === $task || empty( $task->id ) ) {
			return false;
		}

		// Entity event with no actor in the payload — anonymous, runs unattributed.

		$selected_board = $this->item_helpers->required_meta_value( $trigger, $this->get_trigger_meta() );

		// Required field: an absent value must not fall back to "any board".
		if ( null === $selected_board ) {
			return false;
		}

		if ( Fluent_Boards_Helpers::ANY !== $selected_board && absint( $selected_board ) !== absint( $task->board_id ) ) {
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
		$task      = isset( $hook_args[0] ) ? $hook_args[0] : null;
		$old_value = isset( $hook_args[1] ) ? (string) $hook_args[1] : '';
		return array_merge(
			$this->item_helpers->hydrate_task_tokens( $task ),
			array( 'FB_TASK_OLD_DUE_DATE' => $old_value )
		);
	}
}
