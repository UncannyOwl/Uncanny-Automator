<?php
namespace Uncanny_Automator;

use WP_Screen;

/**
 * This class does acts as a mediator between WordPress hooks and Zoho Campaigns functionality.
 *
 * @since 4.10
 */
class Zoho_Campaigns_Helpers {

	const NONCE_KEY = 'automator_zoho_agent';

	public function __construct( $load_hooks = true ) {

		if ( true === $load_hooks ) {

			// Use current_hook to register the settings page.
			add_action( 'current_screen', array( $this, 'register_settings' ), 10, 1 );

			// The wp_ajax handler to process authentication.
			add_action( 'wp_ajax_automator-set-zoho-agent', array( $this, 'process_zoho_agent_credentials' ), 10 );

			// The wp_ajax handler to disconnect the client.
			add_action( 'wp_ajax_automator-disconnect-zoho-client', array( $this, 'disconnect_zoho_agent_credentials' ), 10 );

			// The wp_ajax handler to serve the lists.
			add_action( 'wp_ajax_automator-fetch-lists', array( $this, 'fetch_lists' ), 10 );

			// The wp_ajax handler to serve the topics.
			add_action( 'wp_ajax_automator-fetch-topics', array( $this, 'fetch_topics' ), 10 );

			add_action( 'wp_ajax_automator-zoho-campaigns-fetch-fields', array( $this, 'fetch_fields' ), 10 );

		}

	}

	/**
	 * Registers the settings page. Callback method to 'current_screent'.
	 *
	 * Only loads the settings page if on settings page and is on admin screen.
	 *
	 * @param WP_Screen $current_screen Current WP_Screen object.
	 *
	 * @return Zoho_Campaigns_Settings instance of Zoho_Campaign_Settings.
	 */
	public function register_settings( WP_Screen $current_screen ) {

		if ( ! is_admin() || 'uo-recipe_page_uncanny-automator-config' !== $current_screen->id ) {
			return;
		}

		$this->require_dependency( 'settings/zoho-campaigns-settings' );
		$this->require_dependency( 'client/zoho-campaigns-client' );

		$zoho_campaigns = new Zoho_Campaigns_Settings( $this );
		$zoho_campaigns->set_agent( new Zoho_Campaigns_Client() );

		return $zoho_campaigns;

	}

	/**
	 * Disconnect Zoho Campaign Agent.
	 *
	 * Redirects to settings page.
	 *
	 * @return void
	 */
	public function disconnect_zoho_agent_credentials() {

		$this->verify_credentials();

		$this->require_dependency( 'client/auth/zoho-campaigns-client-auth' );

		return ( new Zoho_Campaigns_Client_Auth() )
			->disconnect_agent(
				function() {
					// Disconnect callback. Invoked after the user credentials was destroyed.
					$this->redirect_to_settings( null, null );
				}
			);
	}

	/**
	 * Processes Zoho Campaigns credentials.
	 *
	 * Redirects to settings page with success flag. Otherwise, redirects to settings page with error.
	 *
	 * @return void
	 */
	public function process_zoho_agent_credentials() {

		$this->verify_credentials();

		$this->require_dependency( 'client/auth/zoho-campaigns-client-auth' );

		return ( new Zoho_Campaigns_Client_Auth() )
			->auth_from_http_query()
			->update_agent(
				function() {
					// Success callback. Flag with success http query.
					$this->redirect_to_settings( null, 'yes' );
				},
				function( $error_message ) {
					// Error callback. Redirect with error message.
					$this->redirect_to_settings( $error_message );
				}
			);

	}

	/**
	 * Redirect helper method.
	 *
	 * Exits the script after redirection.
	 *
	 * @param string $with_error The error message.
	 * @param string $with_success Can be any string.
	 *
	 * @return void.
	 */
	private function redirect_to_settings( $with_error = '', $with_success = '' ) {

		$params = array(
			'integration' => 'zoho_campaigns',
			'post_type'   => 'uo-recipe',
			'page'        => 'uncanny-automator-config',
			'tab'         => 'premium-integrations',
		);

		if ( ! empty( $with_error ) ) {
			$params['auth_error'] = $with_error;
		}

		if ( ! empty( $with_success ) ) {
			$params['success'] = $with_success;
		}

		wp_safe_redirect( add_query_arg( $params, admin_url( 'edit.php' ) ) );

		exit;

	}

	/**
	 * Fetches the list from Zoho Campaigns API.
	 *
	 * Sends JSON response back to the client.
	 *
	 * @return void.
	 */
	public function fetch_lists() {

		$this->require_dependency( 'client/actions/zoho-campaigns-actions' );
		$this->require_dependency( 'client/auth/zoho-campaigns-client-auth' );

		try {

			// Create a new instance of Zoho_Campaign_Actions. Refresh token is evaluated when the object is created.
			$actions = new Zoho_Campaigns_Actions( API_Server::get_instance(), new Zoho_Campaigns_Client_Auth() );

		} catch ( \Exception $e ) {

			// Send error the message back to the select field.
			wp_send_json(
				array(
					'success' => false,
					'error'   => $e->getMessage(),
				)
			);

		} finally {

			// Fetch the list.
			wp_send_json( $actions->wp_ajax_handler_lists_fetch() );

		}

	}

	/**
	 * Fetches the topics from Zoho Campaigns API.
	 *
	 * Sends JSON response back to the client.
	 *
	 * @return void.
	 */
	public function fetch_topics() {

		$this->require_dependency( 'client/actions/zoho-campaigns-actions' );
		$this->require_dependency( 'client/auth/zoho-campaigns-client-auth' );

		try {

			// Create a new instance of Zoho_Campaign_Actions. Refresh token is evaluated when the object is created.
			$actions = new Zoho_Campaigns_Actions( API_Server::get_instance(), new Zoho_Campaigns_Client_Auth() );

		} catch ( \Exception $e ) {

			// Send error the message back to the select field.
			wp_send_json(
				array(
					'success' => false,
					'error'   => $e->getMessage(),
				)
			);

		} finally {

			// Fetch the list.
			wp_send_json( $actions->wp_ajax_handler_topics_fetch() );

		}

	}

	/**
	 * Fetches fields from the Zoho Campaigns API.
	 *
	 * Sends JSON response back to the client.
	 *
	 * @return void
	 */
	public function fetch_fields() {

		try {

			// Create a new instance of Zoho_Campaign_Actions. Refresh token is evaluated when the object is created.
			$actions = new Zoho_Campaigns_Actions( API_Server::get_instance(), new Zoho_Campaigns_Client_Auth() );

		} catch ( \Exception $e ) {

			// Send error the message back to the select field.
			wp_send_json(
				array(
					'success' => false,
					'error'   => $e->getMessage(),
				)
			);

		} finally {

			// Fetch the list.
			wp_send_json( $actions->wp_ajax_handler_fields_fetch() );

		}

	}

	/**
	 * Includes the files from ~src/integrations/zoho-campaigns.
	 *
	 * @param string $path The path of the file. Omit the .php extension at the end of the string.
	 *
	 * @throws \E_Warning if the file is not found.
	 *
	 * @return mixed The file return value if it has. Otherwise, 1.
	 */
	public function require_dependency( $path ) {

		return require_once trailingslashit( dirname( __DIR__ ) ) . $path . '.php';

	}

	/**
	 * Helper method to validate current user nonce and role.
	 *
	 * Kills WordPress execution and displays HTML page with an error message if user has not sufficient privilege.
	 *
	 * @return void
	 */
	private function verify_credentials() {

		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( automator_filter_input( 'nonce' ), self::NONCE_KEY ) ) {

			wp_die( 'Insufficient privilege or nonce is invalid.', 403 );

		}

	}

}
