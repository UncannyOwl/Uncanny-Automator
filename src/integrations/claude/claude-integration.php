<?php
namespace Uncanny_Automator\Integrations\Claude;

use Uncanny_Automator\App_Integrations\App_Integration;
use Uncanny_Automator\Integrations\Claude\Actions\Claude_Chat_Generate;
use Uncanny_Automator\Core\Lib\AI\Views\Settings;
use Uncanny_Automator\Core\Lib\AI\Adapters\Integration\AI_Settings;

/**
 * Claude AI integration for Uncanny Automator.
 *
 * @since 5.6
 */
class Claude_Integration extends App_Integration {

	/**
	 * API key for Claude integration.
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
			'integration'  => 'CLAUDE',
			'name'         => 'Anthropic',
			'api_endpoint' => 'v2/claude', // Even though not used, required by framework
			'settings_id'  => 'claude',
		);
	}

	/**
	 * Setup integration properties.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->api_key = automator_get_option( 'automator_claude_api_key', '' );

		$config = self::get_config();

		$this->set_integration( $config['integration'] );
		$this->set_name( $config['name'] );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/claude-icon.svg' );

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
		// These are not actually used but required by the framework
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
		// Pass dependencies (even though minimal) to maintain consistency
		new Claude_Chat_Generate( $this->dependencies );
		$this->define_settings();
	}

	/**
	 * Define the settings for the Claude integration.
	 *
	 * @return AI_Settings
	 */
	public function define_settings() {

		$name = 'Anthropic';
		$id   = 'claude';

		$presentation = new Settings(
			array(
				'heading'           => $name,
				'description'       => esc_html_x( 'Use Uncanny Automator with Claude here to summarize lengthy posts, process large volumes of text, and create clear, structured output. Known for safety and reliability, Claude helps bloggers, marketers, and devs automate time-consuming tasks like drafting replies or extracting key insights.', 'Claude', 'uncanny-automator' ),
				'trigger_sentences' => array(),
				'action_sentences'  => array(
					'Use a prompt to generate a text response with a Claude model',
				),
				'body'              => 'body',
			)
		);

		$properties = array(
			'id'      => $id,
			'icon'    => strtoupper( $id ),
			'name'    => $name,
			'options' => array(
				array(
					'id'          => 'automator_' . $id . '_api_key',
					'label'       => esc_html_x( 'Anthropic Claude API key', 'Claude', 'uncanny-automator' ),
					'placeholder' => esc_html_x( 'Paste your secret API key here', 'Claude', 'uncanny-automator' ),
					'required'    => true,
					'description' => esc_html_x( 'Enter the API key from Anthropic Claude. This key allows Uncanny Automator to securely connect and send prompts. Your key is stored securely and never shared.', 'Claude', 'uncanny-automator' ),
					'value'       => $this->api_key,
					'type'        => 'text',
				),
			),
		);

		$claude_settings = new AI_Settings( $properties );
		$claude_settings->create_settings_page( $presentation );

		return $claude_settings;
	}
}
