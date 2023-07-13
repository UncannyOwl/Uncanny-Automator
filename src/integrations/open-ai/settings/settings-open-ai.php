<?php
namespace Uncanny_Automator;

/**
 * Open_AI_Settings Settings
 *
 * @package 4.10
 */
class Open_AI_Settings {

	use Settings\Premium_Integrations;

	/**
	 * Instance of Open_AI_Helpers
	 *
	 * @var Open_AI_Helpers
	 */
	protected $helper = null;

	/**
	 * Determines if there is a user connected.
	 *
	 * @var bool $is_connected Pass true to tick the connected checkbox. Pass false to not connect the user.
	 */
	protected $is_connected = false;

	/**
	 * The option key from _options table.
	 *
	 * @var string Option key.
	 */
	const OPTION_KEY = 'automator_open_ai_secret';

	/**
	 * The settings error for validating connection alerts such as invalid access token.
	 *
	 * @var string The connection alerts.
	 */
	const SETTINGS_ERROR = 'automator_open_ai_connection_alerts';

	/**
	 * Cache group preventing settings validation to run multiple times.
	 *
	 * @var string The cache group.
	 */
	const CACHE_GROUP_VALIDATION = 'open_ai_validate_secret_key';

	/**
	 * Setups helper object and settings.
	 *
	 * @param Open_AI_Helpers $helper instance of the helper class.
	 *
	 * @return void.
	 */
	public function __construct( Open_AI_Helpers $helper ) {

		$this->helper = $helper;

		// Setup settings.
		$this->setup_settings();

		add_filter( 'sanitize_option_' . self::OPTION_KEY, array( $this, 'validate_secret_key' ), 10, 3 );

	}

	/**
	 * Validates provided secret key from the settings field.
	 *
	 * @param string $sanitized_input
	 * @param string $option_name
	 * @param string $original_input
	 *
	 * @see <https://developer.wordpress.org/reference/hooks/sanitize_option_option/>
	 *
	 * @return string|false The sanitized input. Returns false if update is failing.
	 */
	public function validate_secret_key( $sanitized_input, $option_name, $original_input ) {

		// Early bail on empty input.
		if ( empty( $sanitized_input ) ) {
			return false;
		}

		$cache_key = $option_name . '_validated';

		// Prevents duplicate process.
		if ( wp_cache_get( $cache_key, self::CACHE_GROUP_VALIDATION ) ) {
			return $sanitized_input;
		}

		try {

			$this->helper->api_request(
				array(
					'action'       => 'get_models',
					'access_token' => $sanitized_input,
				),
				null
			);

			$heading = __( 'Your account has been connected successfully!', 'uncanny-automator' );

			automator_add_settings_error( self::SETTINGS_ERROR, $heading, '', 'success' );

			wp_cache_set( $cache_key, true, self::CACHE_GROUP_VALIDATION );

			return $sanitized_input;

		} catch ( \Exception $e ) {

			wp_cache_set( $cache_key, true, self::CACHE_GROUP_VALIDATION );

			automator_add_settings_error( self::SETTINGS_ERROR, __( 'Authentication error', 'uncanny-automator' ), $e->getMessage(), 'error' );

			return false;

		}

	}


	/**
	 * Setups the properties of the settings page.
	 *
	 * @return void.
	 */
	protected function set_properties() {

		$this->set_id( 'open-ai' );

		$this->set_icon( 'OPEN_AI' );

		$this->set_name( 'OpenAI' );

		$this->register_option( self::OPTION_KEY );

		$this->set_status( $this->helper->is_connected() ? 'success' : '' );

	}

	/**
	 * Creates the output of the settings page.
	 *
	 * @return void.
	 */
	public function output() {

		$disconnect_url = add_query_arg(
			array(
				'action' => 'automator_openai_disconnect',
				'nonce'  => wp_create_nonce( 'automator_openai_disconnect' ),
			),
			admin_url( 'admin-ajax.php' )
		);

		$secret_key = get_option( self::OPTION_KEY, '' );

		$vars = array(
			'alerts'                  => (array) get_settings_errors( self::SETTINGS_ERROR ),
			'setup_url'               => automator_utm_parameters( 'https://automatorplugin.com/knowledge-base/open-ai/', 'settings', 'open-ai-kb_article' ),
			'secret_key'              => $secret_key,
			'is_connected'            => $this->helper->is_connected(),
			'disconnect_url'          => $disconnect_url,
			'recheck_gpt4_access_url' => admin_url( 'admin-ajax.php?action=automator_openai_recheck_gpt4_access&nonce=' . wp_create_nonce( 'automator_openai_gpt4_check_access_clear' ) ),
			'redacted_token'          => substr( $secret_key, 0, 3 ) . '&hellip;' . substr( $secret_key, strlen( $secret_key ) - 4, strlen( $secret_key ) ),
			'can_access_gpt4'         => $this->helper->has_gpt4_access(),
		);

		include_once 'view-open-ai.php';

	}

}

