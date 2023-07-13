<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class Trello_Functions
 *
 * @package Uncanny_Automator
 */
class Trello_Functions {

	/**
	 * The verification nonce.
	 *
	 * @var NONCE nonce.
	 */
	const NONCE = 'automator_trello';

	/**
	 * Token option.
	 *
	 * @var NONCE nonce.
	 */
	const TOKEN = 'automator_trello_token';

	/**
	 * @var string
	 */
	public $setting_tab = 'trello-api';

	/**
	 * @var Trello_Api
	 */
	public $api;

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {
		require_once __DIR__ . '/../functions/trello-api.php';
		$this->api = new Trello_Api( $this->get_client() );
	}

	/**
	 * load_scripts
	 *
	 * @param  string $hook
	 * @return void
	 */
	public function load_scripts( $hook ) {

		if ( 'post.php' !== $hook ) {
			return;
		}

		if ( 'uo-recipe' !== get_current_screen()->post_type ) {
			return;
		}

		$script_uri = plugin_dir_url( __FILE__ ) . '../scripts/trello.js';

		wp_enqueue_script( 'automator-trello', $script_uri, array( 'jquery' ), InitializePlugin::PLUGIN_VERSION, true );
	}

	/**
	 * get_settings_page_url
	 *
	 * @return string
	 */
	public function get_settings_page_url() {
		return add_query_arg(
			array(
				'post_type'   => 'uo-recipe',
				'page'        => 'uncanny-automator-config',
				'tab'         => 'premium-integrations',
				'integration' => $this->setting_tab,
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * get_auth_url
	 *
	 * @return string
	 */
	public function get_auth_url() {

		// Define the parameters of the URL
		$parameters = array(
			// Authentication nonce
			'nonce'        => wp_create_nonce( self::NONCE ),

			// Action
			'action'       => 'authorization_request',

			// Redirect URL
			'redirect_url' => rawurlencode( $this->get_settings_page_url() ),
		);

		// Return the URL
		return add_query_arg(
			$parameters,
			$this->api->get_url()
		);
	}

	/**
	 * get_disconnect_url
	 *
	 * @return string
	 */
	public function get_disconnect_url() {

		$parameters = array(
			'disconnect' => '1',
		);

		// Return the URL
		return add_query_arg(
			$parameters,
			$this->get_settings_page_url()
		);
	}

	/**
	 * get_client
	 *
	 * @return array
	 */
	public function get_client() {
		return automator_get_option( self::TOKEN, false );
	}

	/**
	 * integration_status
	 *
	 * @return string
	 */
	public function integration_status() {
		return $this->get_client() ? 'success' : '';
	}

	/**
	 * capture_oauth_token
	 *
	 * @return void
	 */
	public function capture_oauth_token() {

		if ( ! $this->is_current_settings_tab() ) {
			return;
		}

		$error = automator_filter_input( 'error' );

		if ( ! empty( $error ) ) {
			$this->redirect_with_message( $error );
		}

		$token = automator_filter_input( 'token' );

		if ( empty( $token ) ) {
			return;
		}

		$nonce = automator_filter_input( 'nonce' );

		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			$this->redirect_with_message( __( 'Unable to verify security nonce', 'uncanny-automator' ) );
		}

		update_option( self::TOKEN, $token );

		$this->redirect_with_message( '1' );
	}

	/**
	 * redirect_with_message
	 *
	 * @param  string $message
	 * @return void
	 */
	public function redirect_with_message( $message ) {

		wp_safe_redirect(
			add_query_arg(
				array(
					'connect' => $message,
				),
				$this->get_settings_page_url()
			)
		);

		die;
	}

	/**
	 * is_current_settings_tab
	 *
	 * @return bool
	 */
	public function is_current_settings_tab() {

		if ( 'uo-recipe' !== automator_filter_input( 'post_type' ) ) {
			return false;
		}

		if ( 'uncanny-automator-config' !== automator_filter_input( 'page' ) ) {
			return false;
		}

		if ( 'premium-integrations' !== automator_filter_input( 'tab' ) ) {
			return false;
		}

		if ( automator_filter_input( 'integration' ) !== $this->setting_tab ) {
			return;
		}

		return true;
	}

	/**
	 * disconnect
	 *
	 * @return void
	 */
	public function disconnect() {

		if ( ! $this->is_current_settings_tab() ) {
			return;
		}

		if ( empty( automator_filter_input( 'disconnect' ) ) ) {
			return;
		}

		delete_option( self::TOKEN );

		wp_safe_redirect( $this->get_settings_page_url() );

		die;
	}

	/**
	 * get_user
	 *
	 * @return void
	 */
	public function get_user() {

		$transient_name = 'automator_trello_user';

		$transient = get_transient( $transient_name );

		if ( $transient ) {
			return $transient;
		}

		try {

			$user = $this->api->get_user();

			set_transient( $transient_name, $user, 60 * 60 * 24 );

			return $user;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * user_boards_options
	 *
	 * @return array
	 */
	public function user_boards_options() {

		$options = array();

		try {

			$boards = $this->api->get_boards();

			if ( empty( $boards ) ) {
				throw new \Exception( __( 'No boards were found', 'uncanny-automator' ) );
			}

			foreach ( $boards as $board ) {
				$options[] = array(
					'value' => $board['id'],
					'text'  => $board['name'],
				);
			}
		} catch ( \Exception $e ) {
			$options[] = array(
				'value' => '',
				'text'  => $e->getMessage(),
			);
		}

		return $options;
	}

	/**
	 * ajax_get_board_lists_options
	 *
	 * @return void
	 */
	public function ajax_get_board_lists_options() {

		Automator()->utilities->ajax_auth_check();

		$options = array();

		try {

			$values = automator_filter_input_array( 'values', INPUT_POST );

			if ( empty( $values['BOARD'] ) ) {
				throw new \Exception( __( 'Please select a board', 'uncanny-automator' ) );
			}

			$board_id = $values['BOARD'];

			if ( empty( $board_id ) ) {
				throw new \Exception( __( 'Please select a board', 'uncanny-automator' ) );
			}

			$lists = $this->api->get_board_lists( $board_id );

			if ( empty( $lists ) ) {
				throw new \Exception( __( 'No lists were found', 'uncanny-automator' ) );
			}

			foreach ( $lists as $list ) {

				$options[] = array(
					'value' => $list['id'],
					'text'  => $list['name'],
				);
			}
		} catch ( \Exception $e ) {
			$options[] = array(
				'value' => '',
				'text'  => $e->getMessage(),
			);
		}

		$response = array(
			'success' => true,
			'options' => $options,
		);

		wp_send_json( $response );

		die();
	}

	/**
	 * ajax_get_board_members_options
	 *
	 * @return void
	 */
	public function ajax_get_board_members_options() {

		Automator()->utilities->ajax_auth_check();

		$options = array();

		try {

			$values = automator_filter_input_array( 'values', INPUT_POST );

			if ( empty( $values['BOARD'] ) ) {
				throw new \Exception( __( 'Please select a board', 'uncanny-automator' ) );
			}

			$board_id = $values['BOARD'];

			$members = $this->api->get_board_members( $board_id );

			if ( empty( $members ) ) {
				throw new \Exception( __( 'No members were found in the given board', 'uncanny-automator' ) );
			}

			foreach ( $members as $member ) {
				$options[] = array(
					'value' => $member['id'],
					'text'  => $member['fullName'] . ' (' . $member['username'] . ')',
				);
			}
		} catch ( \Exception $e ) {
			$options[] = array(
				'value' => '',
				'text'  => $e->getMessage(),
			);
		}

		$response = array(
			'success' => true,
			'options' => $options,
		);

		wp_send_json( $response );

		die();
	}

	/**
	 * ajax_get_board_labels_options
	 *
	 * @return void
	 */
	public function ajax_get_board_labels_options() {

		Automator()->utilities->ajax_auth_check();

		try {

			$values = automator_filter_input_array( 'values', INPUT_POST );

			if ( empty( $values['BOARD'] ) ) {
				throw new \Exception( __( 'Please select a board', 'uncanny-automator' ) );
			}

			$board_id = $values['BOARD'];

			$labels = $this->api->get_board_labels( $board_id );

			if ( empty( $labels ) ) {
				throw new \Exception( __( 'No labels were found', 'uncanny-automator' ) );
			}

			foreach ( $labels as $label ) {

				$name = $label['color'];

				if ( ! empty( $label['name'] ) ) {
					$name .= ' (' . $label['name'] . ')';
				}

				$options[] = array(
					'value' => $label['id'],
					'text'  => $name,
				);
			}
		} catch ( \Exception $e ) {
			$options[] = array(
				'value' => '',
				'text'  => $e->getMessage(),
			);
		}

		$response = array(
			'success' => true,
			'options' => $options,
		);

		wp_send_json( $response );

		die();
	}

	/**
	 * ajax_get_custom_fields
	 *
	 * @return void
	 */
	public function ajax_get_custom_fields() {

		// Nonce and post object validation
		Automator()->utilities->ajax_auth_check();

		$board_id = automator_filter_input( 'board_id', INPUT_POST );

		try {

			$fields = $this->api->get_custom_fields( $board_id );

			wp_send_json_success( $fields );

		} catch ( \Exception $e ) {
			$error = new \WP_Error( $e->getCode(), $e->getMessage() );
			wp_send_json_error( $error );
		}

		die();
	}

	/**
	 * ajax_get_list_cards_options
	 *
	 * @return void
	 */
	public function ajax_get_list_cards_options() {

		Automator()->utilities->ajax_auth_check();

		try {

			$values = automator_filter_input_array( 'values', INPUT_POST );

			if ( empty( $values['LIST'] ) ) {
				throw new \Exception( __( 'Please select a list', 'uncanny-automator' ) );
			}

			$list_id = $values['LIST'];

			$cards = $this->api->get_list_cards( $list_id );

			if ( empty( $cards ) ) {
				throw new \Exception( __( 'No cards were found in the given list', 'uncanny-automator' ) );
			}

			foreach ( $cards as $card ) {

				$options[] = array(
					'value' => $card['id'],
					'text'  => $card['name'],
				);
			}
		} catch ( \Exception $e ) {
			$options[] = array(
				'value' => '',
				'text'  => $e->getMessage(),
			);
		}

		$response = array(
			'success' => true,
			'options' => $options,
		);

		wp_send_json( $response );

		die();
	}


	/**
	 * ajax_get_card_checklists
	 *
	 * @return void
	 */
	public function ajax_get_card_checklists() {

		Automator()->utilities->ajax_auth_check();

		try {

			$values = automator_filter_input_array( 'values', INPUT_POST );

			if ( empty( $values['CARD'] ) ) {
				throw new \Exception( __( 'Please select a card', 'uncanny-automator' ) );
			}

			$card_id = $values['CARD'];

			$checklists = $this->api->get_card_checklists( $card_id );

			if ( empty( $checklists ) ) {
				throw new \Exception( __( 'No checklists were found on the given card', 'uncanny-automator' ) );
			}

			foreach ( $checklists as $checklist ) {

				$options[] = array(
					'value' => $checklist['id'],
					'text'  => $checklist['name'],
				);
			}
		} catch ( \Exception $e ) {
			$options[] = array(
				'value' => '',
				'text'  => $e->getMessage(),
			);
		}

		$response = array(
			'success' => true,
			'options' => $options,
		);

		wp_send_json( $response );

		die();
	}
}
