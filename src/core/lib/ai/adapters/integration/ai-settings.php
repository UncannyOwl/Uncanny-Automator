<?php
namespace Uncanny_Automator\Core\Lib\AI\Adapters\Integration;

use Uncanny_Automator\Core\Lib\AI\Views\Settings;
use Uncanny_Automator\Core\Lib\AI\Core\Interfaces\Settings_Interface;
use Uncanny_Automator\Settings\Premium_Integrations;

/**
 * AI Settings Integration Class
 *
 * This class serves as the WordPress integration layer for AI provider settings.
 * It bridges our clean AI architecture with WordPress/Automator's existing infrastructure.
 *
 * ARCHITECTURAL NOTES:
 * - Uses Premium_Integrations trait (required for Automator UI integration)
 * - Handles WordPress-specific concerns (hooks, nonces, redirects, permissions)
 * - Mixes UI rendering with HTTP request handling (WordPress convention)
 * - Contains WordPress coupling that cannot be avoided due to trait dependency
 *
 * DESIGN TRADE-OFFS:
 * - Pragmatic over pure: Works within existing WordPress/Automator constraints
 * - Business functionality over architectural purity
 * - Contained coupling: WordPress mess isolated to this integration layer
 * - Core AI logic remains clean and testable in separate provider classes
 *
 * RESPONSIBILITIES:
 * - WordPress settings page registration and rendering
 * - HTTP request handling for save/disconnect actions
 * - WordPress security (nonces, permissions, sanitization)
 * - Integration with Automator's UI component system
 * - Presentation data injection via Settings DTO
 *
 * FUTURE CONSIDERATIONS:
 * - If Premium_Integrations trait evolves, this can be refactored
 * - Core AI business logic remains decoupled and portable
 * - Settings persistence could be abstracted behind repository or CQRS interface
 *
 * @package Uncanny_Automator\Core\Lib\AI\Adapters\Integration
 * @since   5.6
 */
final class AI_Settings implements Settings_Interface {

	use Premium_Integrations;

	/**
	 * Settings page ID.
	 *
	 * @var string
	 */
	private $settings_id;

	/**
	 * Settings page icon.
	 *
	 * @var string
	 */
	private $settings_icon;

	/**
	 * Settings page name.
	 *
	 * @var string
	 */
	private $settings_name;

	/**
	 * Settings page options.
	 *
	 * @var array
	 */
	private $settings_options = array();

	/**
	 * Settings view document.
	 *
	 * @var array
	 */
	private $settings_view_document = array();

	/**
	 * Connection status.
	 *
	 * @var string
	 */
	private $connection_status;

	/**
	 * Settings presentation data.
	 *
	 * @var Settings
	 */
	private $presentation;

	/**
	 * Settings instance.
	 *
	 * @var array
	 */
	private static $settings_instance = array();

	/**
	 * Hooks registered flag.
	 *
	 * @var bool
	 */
	private static $hooks_registered = false;

	/**
	 * Admin post action for saving settings.
	 */
	const ADMIN_POST_ACTION = 'automator_ai_settings_save';

	/**
	 * Admin post action for disconnecting app.
	 */
	const ADMIN_POST_ACTION_APP_DISCONNECT = 'uncanny_automator_disconnect_ai_provider';

	/**
	 * Creates the settings page and registers the necessary hooks.
	 *
	 * @param mixed[] $args Settings configuration arguments
	 *
	 * @return void
	 */
	public function __construct( $args = array() ) {
		if ( ! empty( $args ) ) {
			$this->create( $args );
		}
	}

	/**
	 * Register wp hooks.
	 *
	 * @return void
	 */
	public static function register_wp_hooks() {

		// Prevent duplicate registration.
		if ( self::$hooks_registered ) {
			return;
		}

		$instance = new self();

		add_action( 'admin_post_' . self::ADMIN_POST_ACTION, array( $instance, 'save_settings' ) );
		add_action( 'admin_post_' . self::ADMIN_POST_ACTION_APP_DISCONNECT, array( $instance, 'disconnect' ) );

		self::$hooks_registered = true;
	}

	/**
	 * Save settings.
	 *
	 * @return void
	 *
	 * @throws \Exception If method called outside proper action context
	 */
	public function save_settings() {

		// Only allow this method to be called via the `admin_post_automator_ai_settings_save` action.
		if ( 'admin_post_automator_ai_settings_save' !== current_action() ) {
			throw new \Exception( 'Method save_settings() can only be called via the `admin_post_automator_ai_settings_save` action.' );
		}

		// Check if the user has the necessary permissions.
		if ( ! current_user_can( automator_get_admin_capability() ) ) {
			wp_die( esc_html_x( 'You do not have permission to save settings.', 'AI', 'uncanny-automator' ) );
		}

		// Get the provider from the POST data.
		$provider = automator_filter_input( 'provider', INPUT_POST );

		// Verify the nonce and user intent before saving the settings.
		check_admin_referer( sprintf( 'uncanny_automator_%s-options', $provider ) );

		// Get the stringified options from the POST data.
		$stringified_options = automator_filter_input( 'stringified_options', INPUT_POST );
		$options             = (array) json_decode( $stringified_options, true );

		// Loop through the options and save them.
		foreach ( $options as $option ) {

			// Validate the required field.
			if ( isset( $option['is_required'] ) && true === $option['is_required'] && is_scalar( $option['value'] ) && '' === strval( $option['value'] ) ) {

				automator_flash_message(
					$provider,
					/* translators: %s - Field label */
					sprintf( esc_html_x( '%s is required.', 'AI', 'uncanny-automator' ), esc_html( $option['label'] ) ),
					'error'
				);

				wp_safe_redirect( wp_get_referer() );

				exit;
			}

			// Validate the option value using the validate_callback if it exists.
			if ( isset( $option['validate_callback'] ) && is_callable( $option['validate_callback'] ) ) {
				$validate_callback = $option['validate_callback'];
				$result            = call_user_func( $validate_callback, $option['value'] );
				if ( is_wp_error( $result ) ) {
					automator_flash_message(
						$provider,
						$result->get_error_message(),
						'error'
					);
					wp_safe_redirect( wp_get_referer() );
					exit;
				}
			}

			// Get the value from the POST data.
			$value = automator_filter_input( $option['id'], INPUT_POST );

			// Sanitize the value using the sanitize_callback if it exists.
			if ( isset( $option['sanitize_callback'] ) && is_callable( $option['sanitize_callback'] ) ) {
				$sanitize_callback = $option['sanitize_callback'];
				$value             = call_user_func( $sanitize_callback, $value );
			}

			// Update the option in the database.
			automator_update_option( 'automator_' . $provider . '_api_key', $value );

		}

		// Flash a success message.
		automator_flash_message(
			$provider,
			esc_html_x( 'Settings saved successfully.', 'AI', 'uncanny-automator' ),
			'success'
		);

		// Redirect back to the settings page.
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Disconnect AI provider.
	 *
	 * @return void
	 *
	 * @throws \Exception If method called outside proper action context
	 */
	public function disconnect() {

		// Only allow this method to be called via the `admin_post_uncanny_automator_disconnect_ai_provider` action.
		if ( 'admin_post_uncanny_automator_disconnect_ai_provider' !== current_action() ) {
			throw new \Exception( 'Method disconnect() can only be called via the `admin_post_uncanny_automator_disconnect_ai_provider` action.' );
		}

		// Check if the user has the necessary permissions.
		if ( ! current_user_can( automator_get_admin_capability() ) ) {
			wp_die( esc_html_x( 'You do not have permission to disconnect the AI provider.', 'AI', 'uncanny-automator' ) );
		}

		$provider = automator_filter_input( 'provider' );

		if ( ! is_string( $provider ) ) {
			wp_die( esc_html_x( 'Invalid provider.', 'AI', 'uncanny-automator' ) );
		}

		// Verify the nonce and user intent before disconnecting the provider.
		check_admin_referer( 'uncanny_automator_disconnect_ai_provider_' . $provider );

		// Delete the API key.
		automator_delete_option( 'automator_' . esc_sql( sanitize_title( $provider ) ) . '_api_key' );

		// Flash a success message.
		automator_flash_message(
			$provider,
			esc_html_x( 'Disconnected successfully.', 'AI', 'uncanny-automator' ),
			'success'
		);

		// Redirect back to the settings page.
		wp_safe_redirect( wp_get_referer() );
		exit;
	}

	/**
	 * Creates the settings page.
	 *
	 * @param Settings $presentation The presentation of the settings page.
	 *
	 * @return void
	 */
	public function create_settings_page( Settings $presentation ) {

		// First, call the trait to setup the settings page.
		$this->setup_settings();

		// Then lets setup the basic things like the title, short description, etc.
		$this->presentation = $presentation;
	}

	/**
	 * Create settings configuration.
	 *
	 * @param array<string, mixed> $args The settings arguments.
	 *
	 * @return self
	 */
	public function create( $args ) {

		$id = $args['id'] ?? '';

		// Do not set any properties if the id is empty. This is a valid use case. Constructor accepts an empty array.
		if ( empty( $id ) ) {
			return $this;
		}

		// If the settings page already exists, return the instance.
		if ( $this->settings_exists( $id ) ) {
			return $this;
		}

		$option_key = 'automator_' . $id . '_api_key';
		$success    = ! empty( automator_get_option( $option_key, '' ) ) ? 'success' : ''; // The 'success' is a valid uo-tab component status.

		$this->settings_id       = $id;
		$this->settings_icon     = $args['icon'] ?? '';
		$this->settings_name     = $args['name'] ?? '(empty value)';
		$this->settings_options  = $args['options'] ?? array();
		$this->connection_status = $success;

		$this->add_settings( $id );

		return $this;
	}

	/**
	 * Set properties for the settings page.
	 *
	 * @return void
	 */
	public function set_properties(): void {

		$this->set_id( $this->settings_id );
		$this->set_icon( $this->settings_icon );
		$this->set_name( $this->settings_name );
		$this->set_status( $this->connection_status );

		// Allow adding more options to the settings page.
		foreach ( $this->settings_options as $option ) {
			if ( is_array( $option ) && isset( $option['id'] ) ) {
				$this->register_option( $option['id'] );
			}
		}
	}

	/**
	 * Creates the output of the settings page.
	 *
	 * @return void
	 */
	public function output(): void {
		// No need to escape output here since it's already escaped in the template, and we are loading custom components here.
		echo $this->get_settings_page_output( 'settings-html', $this->presentation ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Get settings page output.
	 *
	 * @param string   $file The template file name.
	 * @param Settings $presentation The presentation data.
	 *
	 * @return string The output of the settings page.
	 *
	 * @throws \InvalidArgumentException If file is not a string
	 */
	private function get_settings_page_output( $file, Settings $presentation ) {

		if ( ! is_string( $file ) ) {
			throw new \InvalidArgumentException( 'File must be a string.' );
		}

		$file = trailingslashit( __DIR__ ) . '../../views/' . sanitize_file_name( $file ) . '.php';

		$vars = array(
			'presentation' => $presentation,
			'settings'     => array(
				'id'                => $this->settings_id,
				'icon'              => $this->settings_icon,
				'name'              => $this->settings_name,
				'options'           => $this->settings_options,
				'connection_status' => $this->connection_status,
			),
		);

		return $this->get_template_file_content( $file, $vars );
	}

	/**
	 * Get template file content.
	 *
	 * @param string               $file The template file path.
	 * @param array<string, mixed> $vars The template variables.
	 *
	 * @return string The content of the template file.
	 *
	 * @throws \InvalidArgumentException If template file does not exist
	 */
	private function get_template_file_content( $file, $vars = array() ) {

		$vars['meta'] = apply_filters( 'automator_ai_settings_meta', $vars, $file );

		if ( ! file_exists( $file ) ) {
			throw new \InvalidArgumentException( sprintf( 'Template file %s does not exist.', esc_html( $file ) ) );
		}

		ob_start();
		include $file;
		return ob_get_clean();
	}

	/**
	 * Check if the settings page exists.
	 *
	 * @param string $id The settings page ID.
	 *
	 * @return bool True if the settings id already exists, false otherwise.
	 */
	private function settings_exists( $id ) {
		return self::$settings_instance[ $id ] ?? false;
	}

	/**
	 * Add settings to the instance.
	 *
	 * @param string $id The settings page ID.
	 *
	 * @return void
	 */
	private function add_settings( $id ) {
		self::$settings_instance[ $id ] = $id;
	}

	/**
	 * Get settings from the instance.
	 *
	 * @param string $id The settings page ID.
	 *
	 * @return string|null The settings page ID, or null if it does not exist.
	 */
	private function get_settings( $id ) {
		if ( ! $this->settings_exists( $id ) ) {
			return null;
		}

		return self::$settings_instance[ $id ];
	}
}
