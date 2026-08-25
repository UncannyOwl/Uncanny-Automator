<?php

namespace Uncanny_Automator\Integrations\Fluent_Boards;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Fluent_Boards_Task_Label_Changed
 *
 * @package Uncanny_Automator\Integrations\Fluent_Boards
 *
 * @property Fluent_Boards_Helpers $item_helpers
 */
class Fluent_Boards_Task_Label_Changed extends Trigger {

	/**
	 * Trigger definition.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'FLUENT_BOARDS_TASK_LABEL_CHANGED', 'FLUENT_BOARDS' )
			->trigger_meta( 'FLUENT_BOARDS_LABEL' )
			->trigger_type( 'anonymous' )
			->hook( 'fluent_boards/task_label', 10, 3 );
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
				/* translators: %1$s - label, %2$s - added to/removed from, %3$s - board */
				esc_html_x( '{{A label:%1$s}} is {{added to/removed from:%2$s}} a task on {{a board:%3$s}}', 'Fluent Boards', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				'FLUENT_BOARDS_LABEL_ACTION:' . $this->get_trigger_meta(),
				Fluent_Boards_Helpers::BOARD . ':' . $this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( '{{A label}} is {{added to/removed from}} a task on {{a board}}', 'Fluent Boards', 'uncanny-automator' ) );
	}

	/**
	 * Options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => Fluent_Boards_Helpers::BOARD,
				'label'           => esc_html_x( 'Board', 'Fluent Boards', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'boards' ),
			),
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Label', 'Fluent Boards', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_parent_config( 'labels', array( Fluent_Boards_Helpers::BOARD ) ),
			),
			array(
				'option_code'     => 'FLUENT_BOARDS_LABEL_ACTION',
				'label'           => esc_html_x( 'Action', 'Fluent Boards', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => false,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'label_actions' ),
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
		return array_merge( $tokens, $this->item_helpers->label_token_definitions() );
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
		$task   = isset( $hook_args[0] ) && is_object( $hook_args[0] ) ? $hook_args[0] : null;
		$label  = isset( $hook_args[1] ) && is_object( $hook_args[1] ) ? $hook_args[1] : null;
		$action = isset( $hook_args[2] ) ? (string) $hook_args[2] : '';
		if ( null === $task || null === $label || empty( $label->id ) ) {
			return false;
		}

		// Entity event with no actor in the payload — anonymous, runs unattributed.

		$selected_board = $this->item_helpers->required_meta_value( $trigger, Fluent_Boards_Helpers::BOARD );

		// Required field: an absent value must not fall back to "any board".
		if ( null === $selected_board ) {
			return false;
		}

		if ( Fluent_Boards_Helpers::ANY !== $selected_board && absint( $selected_board ) !== absint( $task->board_id ) ) {
			return false;
		}

		$selected_label = $this->item_helpers->required_meta_value( $trigger, $this->get_trigger_meta() );

		// Required field: an absent value must not fall back to "any label".
		if ( null === $selected_label ) {
			return false;
		}

		if ( Fluent_Boards_Helpers::ANY !== $selected_label && absint( $selected_label ) !== absint( $label->id ) ) {
			return false;
		}

		$selected_action = isset( $trigger['meta']['FLUENT_BOARDS_LABEL_ACTION'] ) ? (string) $trigger['meta']['FLUENT_BOARDS_LABEL_ACTION'] : Fluent_Boards_Helpers::ANY;
		if ( Fluent_Boards_Helpers::ANY !== $selected_action && $selected_action !== $action ) {
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
		$task   = isset( $hook_args[0] ) ? $hook_args[0] : null;
		$label  = isset( $hook_args[1] ) ? $hook_args[1] : null;
		$action = isset( $hook_args[2] ) ? (string) $hook_args[2] : '';
		return $this->item_helpers->hydrate_label_tokens( $task, $label, $action );
	}
}
