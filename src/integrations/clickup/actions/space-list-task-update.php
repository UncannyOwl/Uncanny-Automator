<?php
namespace Uncanny_Automator;

/**
 * Class Space_List_Task_Update
 *
 * @package Uncanny_Automator
 */
class Space_List_Task_Update {

	use Recipe\Actions;

	/**
	 * Method __construct
	 *
	 * @return void
	 */
	public function __construct() {

		$this->set_helpers( new ClickUp_Helpers() );

		$this->setup_action();

	}

	/**
	 * Setups the Action.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->set_integration( 'CLICKUP' );

		$this->set_action_code( 'CLICKUP_SPACE_LIST_TASK_TAG_UPDATE' );

		$this->set_action_meta( 'CLICKUP_SPACE_LIST_TASK_TAG_UPDATE_META' );

		$this->set_is_pro( false );

		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/clickup/' ) );

		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: Action sentence */
				esc_attr__( 'Update {{a task:%1$s}}', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_attr__( 'Update {{a task}}', 'uncanny-automator' ) );

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->set_wpautop( false );

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
					$this->get_action_meta() => $this->get_helpers()->get_action_fields( $this, 'space-list-task-update-fields' ),
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

			$name        = $this->read_from_repeater( $parsed, 'name' );
			$description = $this->read_from_repeater( $parsed, 'description', '', 'sanitize_textarea_field' );
			$start_date  = $this->read_from_repeater( $parsed, 'start_date', true );
			$start_time  = $this->read_from_repeater( $parsed, 'start_time', true );
			$due_date    = $this->read_from_repeater( $parsed, 'due_date', true );
			$due_time    = $this->read_from_repeater( $parsed, 'due_time', true );

			$body = array(
				'action'               => 'task_update',
				'status'               => $this->read_from_parsed( $parsed, 'status' ),
				'priority'             => $this->read_from_parsed( $parsed, 'priority' ),
				'assignees_add'        => $this->read_from_parsed( $parsed, 'assignees_add' ),
				'assignees_remove'     => $this->read_from_parsed( $parsed, 'assignees_remove' ),
				'task_id'              => $this->read_from_parsed( $parsed, $this->get_action_meta() ),
				'name'                 => $name,
				'description'          => $description,
				'date_start_timestamp' => $this->read_as_timestamp( $start_date, $start_time ),
				'date_due_timestamp'   => $this->read_as_timestamp( $due_date, $due_time ),
			);

			// Enable start time if value exists.
			if ( ! empty( $start_time ) ) {
				$body['enable_start_time'] = 'yes';
			}

			// Enable due time if value exists.
			if ( ! empty( $due_time ) ) {
				$body['enable_due_time'] = 'yes';
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

	/**
	 * Read from repeater field.
	 *
	 * @param array $parsed The data being parsed by the Action.
	 * @param string $key The key of the Action processed from $parsed.
	 * @param string $default The default value.
	 * @param callable $cd The callable function to pass.
	 *
	 * @return null|string Null when disabled. Otherwise, the value of the field.
	 */
	private function read_from_repeater( $parsed = array(), $key = '', $default = '', callable $cb = null ) {

		$key = strtoupper( $key );

		if ( null === $cb ) {
			$cb = 'sanitize_text_field';
		}

		// Convert br to nl.
		$field_value_br2nl = str_replace( array( '<br>', '<br/>', '<br />' ), PHP_EOL, $parsed[ $key . '_REPEATER' ] );

		// E.g. addcslashes( sanitize_text_field( $parsed['NAME_REPEATER] ) ).
		$restore_slashes = addcslashes( $cb( $field_value_br2nl ), PHP_EOL );
		$repeater_fields = (array) json_decode( $restore_slashes, true );
		$repeater_fields = end( $repeater_fields );

		$retval = null;

		if ( isset( $repeater_fields[ $key . '_UPDATE' ] ) && true === $repeater_fields[ $key . '_UPDATE' ] ) {
			$retval = isset( $repeater_fields[ $key ] ) ? $repeater_fields[ $key ] : '';
		}

		return apply_filters( 'automator_clickup_update_action_read_from_repeater', $retval, $parsed, $key, $default, $cb, $this );

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

		$date_time_object = new \DateTime(
			$date_time_string,
			new \DateTimeZone( Automator()->get_timezone_string() )
		);

		if ( false === $date_time_object ) {
			throw new \Exception( 'ClickUp integration has returned an error: Cannot parse date/time as a valid timestamp.', 400 );
		}

		return (int) $date_time_object->format( 'U' ) * 1000; // 13-digit unix timestamp as required by ClickUp.

	}

}

