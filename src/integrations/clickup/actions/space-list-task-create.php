<?php
namespace Uncanny_Automator;

/**
 * Class Space_List_Task_Create
 *
 * @package Uncanny_Automator
 */
class Space_List_Task_Create {

	use Recipe\Actions;

	/**
	 * Method __construct
	 *
	 * @return void
	 */
	public function __construct() {

		$this->setup_action();

		$this->set_helpers( new ClickUp_Helpers( false ) );

	}

	/**
	 * Setups the Action.
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
					$this->get_action_meta() => $this->get_helpers()->get_action_fields( $this, 'space-list-task-create-fields' ),
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

			$start_date = $this->read_from_parsed( $parsed, 'start_date' );
			$start_time = $this->read_from_parsed( $parsed, 'start_time' );

			$date_due      = $this->read_from_parsed( $parsed, 'date_due' );
			$date_due_time = $this->read_from_parsed( $parsed, 'date_due_time' );

			// Closure function to format time estimate.
			$time_estimate = function( $time ) {
				return absint( sanitize_text_field( $time ) );
			};

			$body = array(
				'action'               => 'create_task',
				'name'                 => $this->read_from_parsed( $parsed, 'name' ),
				'description'          => $this->read_from_parsed( $parsed, 'description', '', 'sanitize_textarea_field' ),
				'time_estimate'        => $this->read_from_parsed( $parsed, 'time_estimate', 0, $time_estimate ),
				'status'               => $this->read_from_parsed( $parsed, 'status' ),
				'tags'                 => $this->read_from_parsed( $parsed, 'tags' ),
				'priority'             => $this->read_from_parsed( $parsed, 'priority' ),
				'assignees'            => $this->read_from_parsed( $parsed, 'assignee' ),
				'list_id'              => $this->read_from_parsed( $parsed, $this->get_action_meta() ),
				'due_date_timestamp'   => $this->read_as_timestamp( $date_due, $date_due_time ),
				'start_date_timestamp' => $this->read_as_timestamp( $start_date, $start_time ),
				'notify_all'           => $this->read_from_parsed( $parsed, 'notify_all' ),
				'parent'               => $this->read_from_parsed( $parsed, 'parent' ),
				'links_to'             => $this->read_from_parsed( $parsed, 'links_to' ),
			);

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

	/**
	 * Normalizes the input from $parsed var.
	 *
	 * @param array $parsed The data being parsed by the Action.
	 * @param string $key The key of the Action processed from $parsed.
	 * @param string $default The default value.
	 * @param callable $cd The callable function to pass.
	 *
	 * @return mixed The value.
	 */
	private function read_from_parsed( $parsed = array(), $key = '', $default = '', callable $cb = null ) {

		$key = strtoupper( $key );

		if ( null === $cb ) {
			$cb = 'sanitize_text_field';
		}

		return isset( $parsed[ $key ] ) ? $cb( $parsed[ $key ] ) : $default;

	}

	/**
	 * Reads the date and time as timestamp which can be understood by ClickUp.
	 *
	 * @return int The 13-digit unix timestamp required by ClickUp.
	 */
	private function read_as_timestamp( $date = '', $time = '' ) {

		if ( empty( $date ) ) {
			return null;
		}

		$date_time_string = trim( $date . ' ' . $time );

		$date_time_object = new \DateTime( $date_time_string, new \DateTimeZone( Automator()->get_timezone_string() ) ); //first argument "must" be a string

		if ( false === $date_time_object ) {
			throw new \Exception( 'ClickUp integration has returned an error: Cannot parse date/time as a valid timestamp.', 400 );
		}

		return (int) $date_time_object->format( 'U' ) * 1000; // 13-digit unix timestamp as required by ClickUp.

	}

}
