<?php

namespace Uncanny_Automator\Integrations\Fluent_Boards;

use Uncanny_Automator\Recipe\Action;

/**
 * Class Fluent_Boards_Create_Board
 *
 * @package Uncanny_Automator\Integrations\Fluent_Boards
 *
 * @property Fluent_Boards_Helpers $item_helpers
 */
class Fluent_Boards_Create_Board extends Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'FLUENT_BOARDS' );
		$this->set_action_code( 'FLUENT_BOARDS_CREATE_BOARD' );
		$this->set_action_meta( 'FLUENT_BOARDS_BOARD_TITLE' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s - board */
				esc_html_x( 'Create {{a board:%1$s}}', 'Fluent Boards', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Create {{a board}}', 'Fluent Boards', 'uncanny-automator' ) );
	}

	/**
	 * Options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_html_x( 'Title', 'Fluent Boards', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
			array(
				'option_code'   => 'FLUENT_BOARDS_TYPE',
				'label'         => esc_html_x( 'Type', 'Fluent Boards', 'uncanny-automator' ),
				'input_type'    => 'text',
				'required'      => false,
				'default_value' => 'to-do',
				'description'   => esc_html_x( 'Typically to-do or roadmap.', 'Fluent Boards', 'uncanny-automator' ),
			),
			array(
				'option_code' => 'FLUENT_BOARDS_DESCRIPTION',
				'label'       => esc_html_x( 'Description', 'Fluent Boards', 'uncanny-automator' ),
				'input_type'  => 'textarea',
				'required'    => false,
			),
			array(
				'option_code'   => 'FLUENT_BOARDS_CURRENCY',
				'label'         => esc_html_x( 'Currency', 'Fluent Boards', 'uncanny-automator' ),
				'input_type'    => 'text',
				'required'      => false,
				'default_value' => 'USD',
			),
		);
	}

	/**
	 * Define tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'FB_BOARD_ID'    => array(
				'name' => esc_html_x( 'Board ID', 'Fluent Boards', 'uncanny-automator' ),
				'type' => 'int',
			),
			'FB_BOARD_TITLE' => array(
				'name' => esc_html_x( 'Board title', 'Fluent Boards', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Process action.
	 *
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		if ( ! function_exists( 'FluentBoardsApi' ) ) {
			$this->add_log_error( esc_html_x( 'Fluent Boards is not active.', 'Fluent Boards', 'uncanny-automator' ) );
			return false;
		}

		$title = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' );
		if ( '' === $title ) {
			$this->add_log_error( esc_html_x( 'A board title is required.', 'Fluent Boards', 'uncanny-automator' ) );
			return false;
		}

		$data = array( 'title' => $title );

		$type = sanitize_text_field( $parsed['FLUENT_BOARDS_TYPE'] ?? '' );
		if ( '' !== $type ) {
			$data['type'] = $type;
		}
		$description = $parsed['FLUENT_BOARDS_DESCRIPTION'] ?? '';
		if ( '' !== $description ) {
			$data['description'] = wp_kses_post( $description );
		}
		$currency = sanitize_text_field( $parsed['FLUENT_BOARDS_CURRENCY'] ?? '' );
		if ( '' !== $currency ) {
			$data['currency'] = $currency;
		}

		$board = FluentBoardsApi( 'boards' )->create( $data );

		if ( ! is_object( $board ) || empty( $board->id ) ) {
			$this->add_log_error( esc_html_x( 'The board could not be created.', 'Fluent Boards', 'uncanny-automator' ) );
			return false;
		}

		$this->hydrate_tokens(
			array(
				'FB_BOARD_ID'    => $board->id,
				'FB_BOARD_TITLE' => $board->title,
			)
		);

		return true;
	}
}
