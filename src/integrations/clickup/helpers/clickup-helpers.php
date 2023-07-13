<?php
namespace Uncanny_Automator;

/**
 * Class ClickUp_Helpers
 *
 * @package Uncanny_Automator
 */
class ClickUp_Helpers {

	/**
	 * API endpoint.
	 *
	 * @var string The ClickUp's endpoint from our API.
	 */
	const API_ENDPOINT = 'v2/clickup';

	/**
	 * API timeout. This property can always be overwritten by a server config.
	 *
	 * ClickUp is slow at times. The 45 seconds timeout is set to solve most timeout issues.
	 *
	 * @var int The allowed number of seconds before the request is halted.
	 */
	const API_TIMEOUT = 45;

	/**
	 * Nonce key.
	 *
	 * @var string The nonce key.
	 */
	const NONCE_KEY = 'automator_clickup_auth_nonce';

	/**
	 * Options table key.
	 *
	 * @var string The client option name from the DB.
	 */
	const CLIENT = 'automator_clickup_client';

	/**
	 * Transient expiration time..
	 *
	 * @var integer The transients expires time in seconds.
	 */
	const TRANSIENT_EXPIRES_TIME = 900; // 15 Minutes.

	/**
	 * Transient key for Workspaces.
	 *
	 * @var string e.g. `automator_clickup_workspaces`.
	 */
	const TRANSIENT_WORKSPACES = 'automator_clickup_workspaces';

	/**
	 * The settings tab ID.
	 *
	 * @var string The settings tab ID.
	 */
	protected $setting_tab = '';

	public function __construct( $load_hooks = true ) {

		if ( $load_hooks && is_admin() ) {

			// OAuth response.
			add_action( 'wp_ajax_automator_clickup_capture_tokens', array( $this, 'capture_oauth_response' ) );
			// Spaces endpoint.
			add_action( 'wp_ajax_automator_clickup_fetch_spaces', array( $this, 'fetch_spaces_handler' ) );
			// Folders endpoint.
			add_action( 'wp_ajax_automator_clickup_fetch_folders', array( $this, 'fetch_folders_handler' ) );
			// List endpoint.
			add_action( 'wp_ajax_automator_clickup_fetch_lists', array( $this, 'fetch_lists_handler' ) );
			// Members (Assignees) endpoint. Deprecated.
			add_action( 'wp_ajax_automator_clickup_fetch_assignees', array( $this, 'fetch_assignees_handler' ) );
			// Members (Assignees) endpoint (list only).
			add_action( 'wp_ajax_automator_clickup_fetch_assignees_list', array( $this, 'fetch_assignees_handler_list' ) );
			// Fetch statuses.
			add_action( 'wp_ajax_automator_clickup_fetch_statuses', array( $this, 'fetch_statuses_handler' ) );
			// Lists tasks.
			add_action( 'wp_ajax_automator_clickup_fetch_tasks', array( $this, 'fetch_tasks_handler' ) );
			// Disconnect.
			add_action( 'wp_ajax_automator_clickup_disconnect', array( $this, 'disconnect' ) );

		}

		$this->setting_tab = 'clickup';

		if ( is_admin() ) {

			// Load the settings page.
			require_once __DIR__ . '/../settings/settings-clickup.php';

			new ClickUp_Settings( $this );

		}

	}

	/**
	 * Retrieves the client from options table.
	 *
	 * @return array The options table.
	 */
	public function get_client() {

		return automator_get_option( self::CLIENT, false );

	}

	/**
	 * Capture OAuth response from ClickUP API.
	 *
	 * Saves the result in the option table. Redirects on success or failure.
	 *
	 * @return void.
	 */
	public function capture_oauth_response() {

		$nonce = automator_filter_input( 'nonce' );

		$this->verify_access( $nonce );

		$response = (array) Automator_Helpers_Recipe::automator_api_decode_message(
			automator_filter_input( 'automator_api_message' ),
			$nonce
		);

		$response_query_vars = array(
			'response' => 'ok',
		);

		if ( ! empty( $response['access_token'] ) ) {

			try {

				$resource_owner = $this->api_request( $response, array( 'action' => 'get_authorized_user' ), null );

				update_option( 'automator_clickup_client', array_merge( $response, $resource_owner['data']['user'] ), true );

			} catch ( \Exception $e ) {

				$response_query_vars['response'] = rawurlencode( $e->getMessage() );

			}

			return $this->redirect(
				$this->get_settings_url(),
				array(
					'response' => rawurlencode( wp_json_encode( $response_query_vars ) ),
				)
			);

		}

		$response_query_vars['response'] = $response;

		return $this->redirect(
			$this->get_settings_url(),
			array(
				'response' => rawurlencode( wp_json_encode( $response_query_vars ) ),
			)
		);

	}


	/**
	 * Retrieves the OAuth URL.
	 *
	 * @return string The OAuth URL.
	 */
	public function get_oauth_url() {

		$nonce = wp_create_nonce( self::NONCE_KEY );

		return add_query_arg(
			array(
				'action'   => 'authorization_request',
				'user_url' => rawurlencode( admin_url( 'admin-ajax.php?action=automator_clickup_capture_tokens&nonce=' . $nonce ) ),
				'nonce'    => $nonce,
			),
			AUTOMATOR_API_URL . self::API_ENDPOINT
		);

	}

	/**
	 * Checks whether the current request has valid nonce key and if current logged-in user has sufficient permissions.
	 *
	 * @throws wp_die
	 *
	 * @return void
	 */
	public function verify_access( $nonce = '' ) {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Error 403: Insufficient permissions.' );
		}

		if ( ! wp_verify_nonce( $nonce, self::NONCE_KEY ) ) {
			wp_die( 'Error 401: Invalid nonce key.' );
		}

	}

	/**
	 * Retrieves the ClickUp's settings url.
	 *
	 * @return string The ClickUp's settings URL.
	 */
	public function get_settings_url() {

		return add_query_arg(
			array(
				'post_type'   => 'uo-recipe',
				'page'        => 'uncanny-automator-config',
				'tab'         => 'premium-integrations',
				'integration' => 'clickup',
			),
			admin_url( 'edit.php' )
		);

	}

	/**
	 * Redirects the user with query parameter.
	 *
	 * @param string $redirect_url The redirect url.
	 * @param array $args The query parameters.
	 *
	 * @return void
	 */
	public function redirect( $redirect_url = '', $args = array() ) {

		wp_safe_redirect( add_query_arg( $args, $redirect_url ) );

		exit;

	}

	/**
	 * Sends requests to our API server.
	 *
	 * @param array $body The payload to send.
	 * @param mixed $action_data Pass null for independent HTTP request. Otherwise pass the Action's action_data when using inside the action.
	 *
	 * @throws \Exception
	 *
	 * @return array The response.
	 */
	public function api_request( $client, $body, $action_data = null ) {

		$body['access_token'] = $client['access_token'];

		$params = array(
			'endpoint' => self::API_ENDPOINT,
			'body'     => $body,
			'action'   => $action_data,
			'timeout'  => self::API_TIMEOUT,
		);

		$response = Api_Server::api_call( $params );

		if ( 200 !== $response['statusCode'] ) {
			$this->throw_clickup_error_exceptions( $response );
		}

		if ( ! empty( $response['error'] ) ) {
			throw new \Exception(
				sprintf( 'Uncanny Automator API has responded with error %s: %s', $response['error']['type'], $response['error']['description'] ),
				$response['statusCode']
			);
		}

		return $response;

	}

	/**
	 * Throws ClickUp related error messages.
	 *
	 * @param array The response forwarded by API.
	 *
	 * @throws \Exception
	 *
	 * @return void
	 */
	private function throw_clickup_error_exceptions( $response = array() ) {

		$error_code    = isset( $response['data']['ECODE'] ) ? $response['data']['ECODE'] : 'UNKOWN_ERR_CODE';
		$error_message = isset( $response['data']['err'] ) ? $response['data']['err'] : 'No error code specified.';

		throw new \Exception(
			sprintf( 'ClickUp API has responded with a status code: %s and with an error %s: %s', $response['statusCode'], $error_code, $error_message ),
			$response['statusCode']
		);

	}

	/**
	 * Determine if the user is connected or not.
	 *
	 * Only checks if the client is empty or not. Does not validate tokens.
	 *
	 * @return bool True if client is not empty. Returns false, otherwise.
	 */
	public function is_connected() {

		return ! empty( get_option( self::CLIENT, null ) );

	}

	/**
	 * Disconnect the ClickUp user.
	 *
	 * @param bool $do_redirect Pass bool true to redirect. Otherwise, pass false.
	 *
	 * @return void.
	 */
	public function disconnect( $do_redirect = true ) {

		$nonce = automator_filter_input( 'nonce' );

		$this->verify_access( $nonce );

		delete_option( self::CLIENT );

		delete_transient( self::TRANSIENT_WORKSPACES );

		if ( false === $do_redirect ) {
			return true;
		}

		$this->redirect(
			$this->get_settings_url(),
			array(
				'status' => 'success',
				'action' => 'disconnected',
				'code'   => 200,
			)
		);

	}

	/**
	 * Retrieve the disconnect url.
	 *
	 * @return string The disconnect url.
	 */
	public function get_disconnect_url() {

		return add_query_arg(
			array(
				'nonce'  => wp_create_nonce( self::NONCE_KEY ),
				'action' => 'automator_clickup_disconnect',
			),
			admin_url( 'admin-ajax.php' )
		);

	}

	/**
	 * Retrieves all of the Team Workspaces.
	 *
	 * @return array The Team Workspaces.
	 */
	public function get_team_workspaces() {

		$team_workspace_cached = get_transient( self::TRANSIENT_WORKSPACES );

		if ( false !== $team_workspace_cached ) {
			return $team_workspace_cached;
		}

		$teams = array();

		try {

			$response = $this->api_request( $this->get_client(), array( 'action' => 'get_authorized_teams' ), null );

			foreach ( $response['data']['teams'] as $team ) {
				$teams[ $team['id'] ] = $team['name'];
			}
		} catch ( \Exception $e ) {

			return array(
				'Error: ' . $e->getCode() => $e->getMessage(),
			);

		}

		set_transient( self::TRANSIENT_WORKSPACES, $teams, self::TRANSIENT_EXPIRES_TIME );

		return $teams;

	}

	/**
	 * Retrieves all of the Spaces.
	 *
	 * @param int $team_id The ID of the Team.
	 *
	 * @return array The list of Spaces.
	 */
	public function get_spaces( $team_id = 0 ) {

		$spaces = array();

		try {

			$response = $this->api_request(
				$this->get_client(),
				array(
					'action'  => 'get_spaces',
					'team_id' => $team_id,
				),
				null
			);

			foreach ( $response['data']['spaces'] as $space ) {
				$spaces[ $space['id'] ] = $space['name'];
			}
		} catch ( \Exception $e ) {

			return array(
				'Error: ' . $e->getCode() => $e->getMessage(),
			);

		}

		return $spaces;

	}

	/**
	 * Retrieves all of the Folders.
	 *
	 * @param int $space_id The ID of the Space.
	 *
	 * @return array Folderless option + The list of Folders.
	 */
	public function get_folders( $space_id = 0 ) {

		$folders = array();

		try {

			$response = $this->api_request(
				$this->get_client(),
				array(
					'action'   => 'get_folders',
					'space_id' => $space_id,
				),
				null
			);

			foreach ( $response['data']['folders'] as $folder ) {
				$folders[ $folder['id'] ] = $folder;
			}
		} catch ( \Exception $e ) {

			return array(
				'Error: ' . $e->getCode() => $e->getMessage(),
			);

		}

		return $folders;

	}

	/**
	 * Retrieves all of the Lists.
	 *
	 * @param int $folder_id The ID of the Folder.
	 *
	 * @return array The list of Lists.
	 */
	public function get_lists( $folder_id = 0 ) {

		$lists = array();

		try {

			$response = $this->api_request(
				$this->get_client(),
				array(
					'action'    => 'get_lists',
					'folder_id' => $folder_id,
				),
				null
			);

			foreach ( $response['data']['lists'] as $list ) {
				$lists[ $list['id'] ] = $list['name'];
			}
		} catch ( \Exception $e ) {

			return array(
				'Error: ' . $e->getCode() => $e->getMessage(),
			);

		}

		return $lists;

	}

	/**
	 * Retrieve statuses.
	 *
	 * @return array The statuses.
	 */
	public function get_space_statuses( $space_id ) {

		$statuses = array();

		try {

			$response = $this->api_request(
				$this->get_client(),
				array(
					'action'   => 'get_space_members',
					'space_id' => $space_id,
				),
				null
			);

			if ( ! empty( $response['data']['statuses'] ) ) {

				return $response['data']['statuses'];

			}

			return array();

		} catch ( \Exception $e ) {

			return array(
				array(
					'text' => 'Error: ' . $e->getMessage(),
					'id'   => 'Error: ' . $e->getCode(),
				),
			);

		}

		return $statuses;

	}

	/**
	 * Retrieves all of the List members.
	 *
	 * @return array The List' members.
	 */
	public function get_list_members_dropdown( $list_id ) {

		try {

			$response = $this->api_request(
				$this->get_client(),
				array(
					'action'  => 'get_list_members',
					'list_id' => $list_id,
				),
				null
			);

			return $response;

		} catch ( \Exception $e ) {

			return array(
				array(
					'text'  => 'Error: ' . $e->getMessage(),
					'value' => 'Error: ' . $e->getCode(),
				),
			);

		}

	}

	/**
	 * Handles Spaces result from an Ajax request.
	 *
	 * @return void
	 */
	public function fetch_spaces_handler() {

		Automator()->utilities->ajax_auth_check();

		$team_id = $this->get_payload_values( 'TEAM', null );

		$options = array();

		foreach ( $this->get_spaces( $team_id ) as $id => $space ) {
			$options[] = array(
				'text'  => $space,
				'value' => $id,
			);
		}

		$this->respond_with_json( $options );

	}

	/**
	 * Handles the Folders result from an Ajax request.
	 *
	 * @return void
	 */
	public function fetch_folders_handler() {

		Automator()->utilities->ajax_auth_check();

		$options = array();

		$space_id = $this->get_payload_values( 'SPACE', null );

		// Add default folderless list.
		$options[] = array(
			'text'  => esc_html__( 'Folderless lists', 'uncanny-automator' ),
			'value' => $space_id . '|SPACE_ID', // Set flag to Space ID instead of Folder ID.
		);

		foreach ( $this->get_folders( $space_id ) as $id => $folder ) {

			if ( empty( $folder['name'] ) ) {
				continue;
			}

			$options[] = array(
				'text'  => $folder['name'],
				'value' => $id,
			);

		}

		$this->respond_with_json( $options );

	}

	/**
	 * Handles the Lists result from an Ajax request.
	 *
	 * Callback from wp_ajax.
	 *
	 * @return void
	 */
	public function fetch_lists_handler() {

		Automator()->utilities->ajax_auth_check();

		$folder_id = $this->get_payload_values( 'FOLDER', null );

		$options = array();

		foreach ( $this->get_lists( $folder_id ) as $id => $list ) {

			$options[] = array(
				'text'  => $list,
				'value' => $id,
			);

		}

		$this->respond_with_json( $options );

	}

	/**
	 * Handles the Lists result from an Ajax request.
	 *
	 * Callback to wp_ajax.
	 *
	 * @return void
	 */
	public function fetch_assignees_handler() {

		Automator()->utilities->ajax_auth_check();

		$members = array();

		$list_option_code = 'CLICKUP_SPACE_LIST_TASK_CREATE_META';

		// Supports `LIST` option code.
		if ( isset( $_POST['values']['LIST'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
			$list_option_code = 'LIST';
			// Allows the dropdown to have 'Everyone' option.
			$members[] = array(
				'value' => '-1',
				'text'  => esc_html__( 'Everyone', 'uncanny-automator' ),
			);
		}

		$list_value = isset( $_POST['values'][ $list_option_code ] ) //phpcs:ignore WordPress.Security.NonceVerification.Missing
			? sanitize_text_field( wp_unslash( $_POST['values'][ $list_option_code ] ) ) //phpcs:ignore WordPress.Security.NonceVerification.Missing
			: 0;

		// Supports custom value.
		if ( 'automator_custom_value' === $list_value ) {
			$list_option_code .= '_custom';
		}

		$list_id = isset( $_POST['values'][ $list_option_code ] )  //phpcs:ignore WordPress.Security.NonceVerification.Missing
			? sanitize_text_field( wp_unslash( $_POST['values'][ $list_option_code ] ) )  //phpcs:ignore WordPress.Security.NonceVerification.Missing
			: 0;

		$list_members = $this->get_list_members_dropdown( $list_id );

		if ( ! empty( $list_members['data']['members'] ) ) {

			foreach ( $list_members['data']['members'] as $member ) {

				if ( ! empty( $member['id'] ) ) {

					$members[] = array(
						'value' => $member['id'],
						'text'  => sprintf( '%s (%s)', $member['username'], $member['email'] ),
					);

				}
			}
		}

		wp_send_json( $members );

	}

	/**
	 * Fetches assignees from List.
	 *
	 * Callback to wp_ajax.
	 *
	 * @return void.
	 */
	public function fetch_assignees_handler_list() {

		Automator()->utilities->ajax_auth_check();

		$options = array();

		$list_id = $this->get_payload_values( 'LIST', null );

		// Overwrite the list_id with action meta of the task create action.
		if ( isset( $_POST['values']['CLICKUP_SPACE_LIST_TASK_CREATE_META'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$list_id = $this->get_payload_values( 'CLICKUP_SPACE_LIST_TASK_CREATE_META', null );
		}

		$list_members = $this->get_list_members_dropdown( $list_id );

		if ( ! empty( $list_members['data']['members'] ) ) {

			foreach ( $list_members['data']['members'] as $member ) {

				if ( ! empty( $member['id'] ) ) {

					$options[] = array(
						'value' => $member['id'],
						'text'  => sprintf( '%s (%s)', $member['username'], $member['email'] ),
					);

				}
			}
		}

		$this->respond_with_json( $options );

	}

	/**
	 * Fetches spaces statuses.
	 *
	 * @return $statuses.
	 */
	public function fetch_statuses_handler() {

		Automator()->utilities->ajax_auth_check();

		$space_id = $this->get_payload_values( 'SPACE', null );

		$statuses = array(
			array(
				'text'  => __( 'Leave unchanged in ClickUp', 'uncanny-automator' ),
				'value' => '__NO_UPDATE__',
			),
			array(
				'text'  => __( 'Remove status', 'uncanny-automator' ),
				'value' => '__REMOVE__',
			),
		);

		foreach ( $this->get_space_statuses( $space_id ) as $status ) {

			$status = strtoupper( $status['status'] );

			$statuses[] = array(
				'text'  => $status,
				'value' => $status,
			);

		}

		$this->respond_with_json( $statuses );

	}

	/**
	 * Fetches all of the tasks.
	 *
	 * @return void
	 */
	public function fetch_tasks_handler() {

		Automator()->utilities->ajax_auth_check();

		$list_id = $this->get_payload_values( 'LIST', null );

		$tasks = array();

		foreach ( $this->get_list_tasks( $list_id ) as $task ) {

			$tasks[] = array(
				'value' => $task['id'],
				'text'  => $task['name'],
			);

		}

		$this->respond_with_json( $tasks );

	}

	/**
	 * Retrieves all the tasks in a specific list.
	 *
	 * @return array The list of tasks.
	 */
	protected function get_list_tasks( $list_id = 0 ) {

		$tasks = array();

		try {

			$response = $this->api_request(
				$this->get_client(),
				array(
					'action'  => 'task_list',
					'list_id' => $list_id,
				),
				null
			);

			if ( ! empty( $response['data']['tasks'] ) ) {

				return $response['data']['tasks'];

			}

			return array();

		} catch ( \Exception $e ) {

			$tasks = array(
				array(
					'name' => 'Error: ' . $e->getMessage(),
					'id'   => 'Error: ' . $e->getCode(),
				),
			);

		}

		return $tasks;

	}

	/**
	 * Includes the fields file and return its value.
	 *
	 * @param $file_name The file name.
	 * @param $action The action class.
	 *
	 * @return array The fields.
	 */
	public function get_action_fields( $action = null, $file_name = '' ) {

		return require UA_ABSPATH . '/src/integrations/clickup/fields/' . sanitize_file_name( $file_name ) . '.php';

	}

	/**
	 * Retrieves the specific key from `VALUES`.
	 *
	 * @param string $key The key of the payload from `VALUES`.
	 * @param mixed $default The default value in case the specified payload value is empty.
	 *
	 * @return mixed The supplied default value.
	 */
	protected function get_payload_values( $key = '', $default = null ) {

		Automator()->utilities->ajax_auth_check( $_POST );  //phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! isset( $_POST['values'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $default;
		}

		if ( ! isset( $_POST['values'][ $key ] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $default;
		}

		return ! empty( $_POST['values'][ $key ] ) ? sanitize_text_field( wp_unslash( $_POST['values'][ $key ] ) ) : $default; //phpcs:ignore WordPress.Security.NonceVerification.Missing

	}

	/**
	 * Respond with JSON data.
	 *
	 * @param array $options The options to send to the dropdown.
	 *
	 * @return void
	 */
	protected function respond_with_json( $options = array() ) {

		wp_send_json(
			array(
				'success' => true, // Just send success for now since we don't have error handler.
				'options' => $options,
			)
		);

	}

}
