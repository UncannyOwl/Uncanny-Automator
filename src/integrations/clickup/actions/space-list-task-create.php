<?php
namespace Uncanny_Automator;

use Uncanny_Automator\Integrations\ClickUp\Utilities\Time_Utility;
use Uncanny_Automator\Recipe\Action_Tokens;

/**
 * Class Space_List_Task_Create
 *
 * Handles the creation of tasks within a specified space list in ClickUp.
 *
 * @package Uncanny_Automator
 */
class Space_List_Task_Create {

	use Action_Tokens;
	use Recipe\Actions;

	/**
	 * Constructor.
	 *
	 * Initializes the action setup and helper functions for task creation.
	 *
	 * @return void
	 */
	public function __construct() {

		$this->setup_action();
		$this->set_helpers( new ClickUp_Helpers( false ) );

	}

	/**
	 * Sets up the ClickUp task creation action.
	 *
	 * Configures metadata, integration, sentences, tokens, and processing for the action.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->set_integration( 'CLICKUP' );
		$this->set_action_code( 'CLICKUP_SPACE_LIST_TASK_CREATE' );
		$this->set_action_meta( 'CLICKUP_SPACE_LIST_TASK_CREATE_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/clickup/' ) );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: Action sentence */
				esc_html__( 'Create a {{task:%1$s}}', 'uncanny-automator' ),
				'NAME:' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_html__( 'Create a {{task}}', 'uncanny-automator' ) );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->set_background_processing( true );

		$this->set_action_tokens(
			array(
				'TASK_ID' => array(
					'name' => esc_attr_x( 'Task ID', 'ClickUp', 'uncanny-automator' ),
					'type' => 'int',
				),
			),
			$this->get_action_code()
		);

		$this->register_action();

	}

	/**
	 * Loads options for the action.
	 *
	 * Retrieves the options group for the ClickUp task creation action.
	 *
	 * @return void
	 */
	public function load_options() {

		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->get_action_meta() => $this->get_helpers()->get_action_fields( $this, 'space-list-task-create-fields' ),
				),
			)
		);

	}

	/**
	 * Processes the task creation action.
	 *
	 * @param int    $user_id The ID of the user performing the action.
	 * @param array  $action_data Data associated with the action.
	 * @param int    $recipe_id The recipe ID the action belongs to.
	 * @param array  $args Additional arguments.
	 * @param array  $parsed Parsed data for the action.
	 * @return void
	 */
	public function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		try {

			$start_date = $this->read_from_parsed( $parsed, 'start_date' );
			$start_time = $this->read_from_parsed( $parsed, 'start_time' );
			$due_date   = $this->read_from_parsed( $parsed, 'date_due' );
			$due_time   = $this->read_from_parsed( $parsed, 'date_due_time' );

			// Closure function to format time estimate.
			$time_estimate = function( $time ) {
				return absint( sanitize_text_field( $time ) );
			};

			// Patch ready. Can be removed in the future once built.
			if ( ! class_exists( '\Uncanny_Automator\Integrations\ClickUp\Utilities\Time_Utility' ) ) {
				require_once trailingslashit( UA_ABSPATH ) . 'src/integrations/clickup/utilities/time-utility.php';
			}

			// Initiate the time utility class base on the customer's wp settings.
			$time_utility = new Time_Utility(
				Automator()->get_date_format(),
				Automator()->get_time_format(),
				Automator()->get_timezone_string()
			);

			$start_datetime = $time_utility->to_timestamp( $start_date, $start_time );
			$due_datetime   = $time_utility->to_timestamp( $due_date, $due_time );

			$body = array(
				'action'               => 'create_task',
				'start_date_timestamp' => $start_datetime,
				'due_date_timestamp'   => $due_datetime,
				'name'                 => $this->read_from_parsed( $parsed, 'name' ),
				'description'          => $this->read_from_parsed( $parsed, 'description', '', 'sanitize_textarea_field' ),
				'time_estimate'        => $this->read_from_parsed( $parsed, 'time_estimate', 0, $time_estimate ),
				'status'               => $this->read_from_parsed( $parsed, 'status' ),
				'tags'                 => $this->read_from_parsed( $parsed, 'tags' ),
				'priority'             => $this->read_from_parsed( $parsed, 'priority' ),
				'assignees'            => $this->read_from_parsed( $parsed, 'assignee' ),
				'list_id'              => $this->read_from_parsed( $parsed, $this->get_action_meta() ),
				'notify_all'           => $this->read_from_parsed( $parsed, 'notify_all' ),
				'parent'               => $this->read_from_parsed( $parsed, 'parent' ),
				'links_to'             => $this->read_from_parsed( $parsed, 'links_to' ),
			);

			$client   = $this->get_helpers()->get_client();
			$response = $this->get_helpers()->api_request( $client, $body, $action_data );

			$this->hydrate_tokens(
				array(
					'TASK_ID' => $response['data']['id'] ?? null,
				)
			);

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

		}

	}

	/**
	 * Reads and sanitizes input from parsed data.
	 *
	 * @param array    $parsed Parsed data array.
	 * @param string   $key The key to fetch.
	 * @param mixed    $default Default value if key not found.
	 * @param callable $cb A callable for sanitization.
	 * @return mixed The sanitized value.
	 */
	private function read_from_parsed( $parsed = array(), $key = '', $default = '', callable $cb = null ) {

		$key = strtoupper( $key );

		if ( null === $cb ) {
			$cb = 'sanitize_text_field';
		}

		return isset( $parsed[ $key ] ) ? $cb( $parsed[ $key ] ) : $default;

	}

}
