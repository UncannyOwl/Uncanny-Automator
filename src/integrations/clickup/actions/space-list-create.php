<?php

namespace Uncanny_Automator\Integrations\ClickUp;

use Exception;

/**
 * Action: Create a list.
 *
 * @package Uncanny_Automator\Integrations\ClickUp
 *
 * @property ClickUp_App_Helpers $helpers
 * @property ClickUp_Api_Caller $api
 */
class Space_List_Create extends \Uncanny_Automator\Recipe\App_Action {

	use ClickUp_Hierarchy_Options;

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'CLICKUP' );
		$this->set_action_code( 'CLICKUP_LIST_CREATE' );
		$this->set_action_meta( 'CLICKUP_LIST_CREATE_META' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/clickup/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Create {{a list}}', 'ClickUp', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: List name
				esc_attr_x( 'Create {{a list:%1$s}}', 'ClickUp', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->get_team_option_config(),
			$this->get_space_option_config(),
			$this->get_folder_option_config(),
			$this->helpers->get_name_option_config( $this->get_action_meta() ),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional arguments.
	 * @param array $parsed      Parsed action data.
	 *
	 * @return bool
	 * @throws Exception If the action fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$body = array(
			'action'    => 'create_list',
			'folder_id' => sanitize_text_field( $parsed[ $this->helpers->get_const( 'META_FOLDER' ) ] ?? '' ),
			'name'      => sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' ),
		);

		$this->api->api_request( $body, $action_data );

		return true;
	}
}
