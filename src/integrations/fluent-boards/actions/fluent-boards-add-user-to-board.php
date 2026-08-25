<?php

namespace Uncanny_Automator\Integrations\Fluent_Boards;

use FluentBoards\App\Services\BoardService;
use Uncanny_Automator\Recipe\Action;

/**
 * Class Fluent_Boards_Add_User_To_Board
 *
 * @package Uncanny_Automator\Integrations\Fluent_Boards
 *
 * @property Fluent_Boards_Helpers $item_helpers
 */
class Fluent_Boards_Add_User_To_Board extends Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'FLUENT_BOARDS' );
		$this->set_action_code( 'FLUENT_BOARDS_ADD_USER_TO_BOARD' );
		$this->set_action_meta( 'FLUENT_BOARDS_USER' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s - user, %2$s - board */
				esc_html_x( 'Add {{a user:%1$s}} to {{a board:%2$s}}', 'Fluent Boards', 'uncanny-automator' ),
				$this->get_action_meta(),
				Fluent_Boards_Helpers::BOARD . ':' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Add {{a user}} to {{a board}}', 'Fluent Boards', 'uncanny-automator' ) );
	}

	/**
	 * Options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_action_meta(),
				'label'           => esc_html_x( 'User', 'Fluent Boards', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_search_config( 'users_strict' ),
			),
			array(
				'option_code'     => Fluent_Boards_Helpers::BOARD,
				'label'           => esc_html_x( 'Board', 'Fluent Boards', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'boards_strict' ),
			),
			array(
				'option_code'     => 'FLUENT_BOARDS_ROLE',
				'label'           => esc_html_x( 'Role', 'Fluent Boards', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => false,
				'default_value'   => 'member',
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'board_roles_strict' ),
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
			'FB_BOARD_TITLE'       => array(
				'name' => esc_html_x( 'Board title', 'Fluent Boards', 'uncanny-automator' ),
				'type' => 'text',
			),
			'FB_USER_DISPLAY_NAME' => array(
				'name' => esc_html_x( 'User display name', 'Fluent Boards', 'uncanny-automator' ),
				'type' => 'text',
			),
			'FB_USER_EMAIL'        => array(
				'name' => esc_html_x( 'User email', 'Fluent Boards', 'uncanny-automator' ),
				'type' => 'email',
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

		if ( ! class_exists( '\FluentBoards\App\Services\BoardService' ) ) {
			$this->add_log_error( esc_html_x( 'Fluent Boards is not active.', 'Fluent Boards', 'uncanny-automator' ) );
			return false;
		}

		$member_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );
		$board_id  = absint( $parsed[ Fluent_Boards_Helpers::BOARD ] ?? 0 );
		$role      = sanitize_text_field( $parsed['FLUENT_BOARDS_ROLE'] ?? 'member' );

		if ( 0 === $board_id || 0 === $member_id ) {
			$this->add_log_error( esc_html_x( 'A valid board and user are required.', 'Fluent Boards', 'uncanny-automator' ) );
			return false;
		}

		if ( ! get_userdata( $member_id ) ) {
			$this->add_log_error(
				sprintf(
					/* translators: %d - the user ID */
					esc_html_x( 'A valid user is required. Got user ID %d.', 'Fluent Boards', 'uncanny-automator' ),
					$member_id
				)
			);
			return false;
		}

		$is_viewer_only = ( 'viewer' === $role ) ? 'yes' : null;

		$result = ( new BoardService() )->addMembersInBoard( $board_id, $member_id, $is_viewer_only );

		if ( false === $result ) {
			$this->add_log_error( esc_html_x( 'The user could not be added (they may already be a member or the board is invalid).', 'Fluent Boards', 'uncanny-automator' ) );
			return false;
		}

		$member = get_userdata( $member_id );

		$this->hydrate_tokens(
			array(
				'FB_BOARD_TITLE'       => $this->item_helpers->board_title( $board_id ),
				'FB_USER_DISPLAY_NAME' => $member ? $member->display_name : '',
				'FB_USER_EMAIL'        => $member ? $member->user_email : '',
			)
		);

		return true;
	}
}
