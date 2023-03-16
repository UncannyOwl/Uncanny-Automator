<?php
namespace Uncanny_Automator;

/**
 * Class Space_List_Task_Delete
 *
 * @package Uncanny_Automator
 */
class Space_List_Task_Delete {

	use Recipe\Actions;

	/**
	 * Method __construct
	 *
	 * @return void
	 */
	public function __construct() {

		$this->setup_action();

		$this->set_helpers( new ClickUp_Helpers() );

	}

	/**
	 * Setups the Action.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->set_integration( 'CLICKUP' );

		$this->set_action_code( 'CLICKUP_SPACE_LIST_TASK_DELETE' );

		$this->set_action_meta( 'CLICKUP_SPACE_LIST_TASK_DELETE_META' );

		$this->set_is_pro( false );

		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/clickup/' ) );

		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: Action sentence */
				esc_attr__( 'Delete {{a task:%1$s}}', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_attr__( 'Delete {{a task}}', 'uncanny-automator' ) );

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->set_background_processing( true );

		$this->register_action();

	}

	/**
	 * Loads options.
	 *
	 * @return void.
	 */
	public function load_options() {

		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->get_action_meta() => array(
						array(
							'option_code'           => $this->get_action_meta(),
							'label'                 => esc_attr__( 'Task ID', 'uncanny-automator' ),
							'input_type'            => 'text',
							'supports_custom_value' => false,
							'required'              => true,
						),
					),
				),
			)
		);

	}

	/**
	 * Processes the action.
	 *
	 * @return void.
	 */
	public function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		try {

			$body = array(
				'action'  => 'task_delete',
				'task_id' => isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : 0,
			);

			if ( empty( absint( str_replace( '#', '', $body['task_id'] ) ) ) ) {
				throw new \Exception( 'Client error: Task ID is empty. Check token value if using token for the Task ID field.', 422 );
			}

			$response = $this->get_helpers()->api_request(
				$this->get_helpers()->get_client(),
				$body,
				$action_data
			);

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

		}

	}

}
