<?php

namespace Uncanny_Automator\Integrations\Fluent_Boards;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Fluent_Boards_Task_Completed_Reopened
 *
 * @package Uncanny_Automator\Integrations\Fluent_Boards
 *
 * @property Fluent_Boards_Helpers $item_helpers
 */
class Fluent_Boards_Task_Completed_Reopened extends Trigger {

	/**
	 * Trigger definition.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'FLUENT_BOARDS_TASK_COMPLETED_REOPENED', 'FLUENT_BOARDS' )
			->trigger_meta( Fluent_Boards_Helpers::BOARD )
			->trigger_type( 'anonymous' )
			// Not a native Fluent Boards hook — Fluent_Boards_Integration
			// bridges `task_completed_activity` (status circle) and
			// `task_stage_updated` (drag into a closed-default stage) into it.
			->hook( 'automator_fluent_boards_task_status_changed', 10, 2 );
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
				/* translators: %1$s - board, %2$s - completed/reopened */
				esc_html_x( 'A task on {{a board:%1$s}} is {{completed/reopened:%2$s}}', 'Fluent Boards', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				'FLUENT_BOARDS_STATUS_DIRECTION:' . $this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'A task on {{a board}} is {{completed/reopened}}', 'Fluent Boards', 'uncanny-automator' ) );
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
			array(
				'option_code'     => 'FLUENT_BOARDS_STATUS_DIRECTION',
				'label'           => esc_html_x( 'Completed or reopened', 'Fluent Boards', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'status_directions' ),
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
		return array_merge( $tokens, $this->item_helpers->task_token_definitions() );
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
		$task  = isset( $hook_args[0] ) && is_object( $hook_args[0] ) ? $hook_args[0] : null;
		$value = isset( $hook_args[1] ) ? (string) $hook_args[1] : '';
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

		$selected_direction = isset( $trigger['meta']['FLUENT_BOARDS_STATUS_DIRECTION'] ) ? (string) $trigger['meta']['FLUENT_BOARDS_STATUS_DIRECTION'] : '';
		if ( $selected_direction !== $value ) {
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
		$task = isset( $hook_args[0] ) ? $hook_args[0] : null;
		return $this->item_helpers->hydrate_task_tokens( $task );
	}
}
