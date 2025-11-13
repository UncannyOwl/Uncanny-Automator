<?php
namespace Uncanny_Automator\Integrations\Cohere;

use Uncanny_Automator\App_Integrations\App_Integration;
use Uncanny_Automator\Integrations\Cohere\Actions\Cohere_Chat_Generate;
use Uncanny_Automator\Core\Lib\AI\Adapters\Integration\AI_Settings;
use Uncanny_Automator\Core\Lib\AI\Views\Settings;

/**
 * Cohere AI integration for Uncanny Automator.
 *
 * @since 5.6
 */
class Cohere_Integration extends App_Integration {

	/**
	 * API key for Cohere integration.
	 *
	 * @var string
	 */
	protected $api_key;

	/**
	 * Get configuration for the integration.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'COHERE',
			'name'         => 'Cohere',
			'api_endpoint' => 'v2/cohere',
			'settings_id'  => 'cohere',
		);
	}

	/**
	 * Setup integration properties.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->api_key = automator_get_option( 'automator_cohere_api_key', '' );

		$config = self::get_config();

		$this->set_integration( $config['integration'] );
		$this->set_name( $config['name'] );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/cohere-icon.svg' );

		// Setup app integration (required by framework)
		$this->setup_app_integration( $config );
	}

	/**
	 * Check if the app is connected.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		return ! empty( $this->api_key );
	}

	/**
	 * Initialize the app integration with minimal dependencies.
	 * Overridden to avoid requiring helpers and API classes for AI integrations.
	 *
	 * @return void
	 */
	protected function initialize_app_integration() {
		// Check and set the connected status using the is_app_connected method.
		$this->set_connected( $this->is_app_connected() );

		// Set the settings URL.
		$this->set_settings_url(
			automator_get_premium_integrations_settings_url(
				$this->get_settings_id()
			)
		);

		// Create minimal dependencies as stdClass objects for AI integrations
		$this->dependencies           = new \stdClass();
		$this->dependencies->helpers  = new \stdClass();
		$this->dependencies->api      = new \stdClass();
		$this->dependencies->webhooks = null;

		// Register integration-specific hooks if needed
		$this->register_hooks();
	}

	/**
	 * Load integration components.
	 *
	 * @return void
	 */
	public function load() {
		new Cohere_Chat_Generate( $this->dependencies );
		$this->define_settings();
	}

	/**
	 * Define the settings for the Cohere integration.
	 *
	 * @return AI_Settings
	 */
	public function define_settings() {

		$name = 'Cohere';
		$id   = 'cohere';

		$presentation = new Settings(
			array(
				'heading'           => $name,
				'description'       => esc_html_x( 'Use Uncanny Automator with Cohere here to generate well-written content, multilingual summaries, or auto-categorized posts. With its strong contextual understanding and language analysis, Cohere helps bloggers and marketers produce polished, SEO-friendly content faster.', 'Cohere', 'uncanny-automator' ),
				'trigger_sentences' => array(),
				'action_sentences'  => array(
					'Use a prompt to generate a text response with a Cohere model',
				),
				'body'              => 'body',
			)
		);

		$properties = array(
			'id'                => $id,
			'icon'              => strtoupper( $id ),
			'name'              => $name,
			'options'           => array(
				array(
					'id'          => 'automator_' . $id . '_api_key',
					'label'       => esc_html_x( 'Cohere API key', 'Cohere', 'uncanny-automator' ),
					'placeholder' => esc_html_x( 'Paste your secret API key here', 'Cohere', 'uncanny-automator' ),
					'required'    => true,
					'description' => esc_html_x( 'Enter the API key from Cohere. This key allows Uncanny Automator to securely connect and send prompts. Your key is stored securely and never shared.', 'Cohere', 'uncanny-automator' ),
					'value'       => automator_get_option( 'automator_cohere_api_key', '' ),
					'type'        => 'text',
				),
			),
		);

		$cohere_settings = new AI_Settings( $properties );
		$cohere_settings->create_settings_page( $presentation );

		return $cohere_settings;
	}
}
