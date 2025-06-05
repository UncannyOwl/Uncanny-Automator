<?php
namespace Uncanny_Automator\Integrations\Deepseek;

use Uncanny_Automator\Integration;
use Uncanny_Automator\Integrations\Deepseek\Actions\Deepseek_Chat_Generate;
use Uncanny_Automator\Core\Lib\AI\Adapters\Integration\AI_Settings;
use Uncanny_Automator\Core\Lib\AI\Views\Settings;

/**
 * DeepSeek AI integration for Uncanny Automator.
 *
 * @since 5.6
 */
class Deepseek_Integration extends Integration {

	/**
	 * Setup integration properties.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->set_integration( 'DEEPSEEK' );
		$this->set_name( 'DeepSeek' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/deepseek-icon.svg' );
		$this->set_connected( ! empty( automator_get_option( 'automator_deepseek_api_key', '' ) ) );
		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'deepseek' ) );
	}

	/**
	 * Load integration components.
	 *
	 * @return void
	 */
	public function load() {
		new Deepseek_Chat_Generate();
		$this->define_settings();
	}

	/**
	 * Define the settings for the DeepSeek integration.
	 *
	 * @return AI_Settings
	 */
	public function define_settings() {

		$name = 'DeepSeek';
		$id   = 'deepseek';

		$presentation = new Settings(
			array(
				'heading'           => $name,
				'description'       => esc_html_x( 'Use Uncanny Automator with DeepSeek here to automate complex tasks like summarizing long posts, analyzing content, or solving code problems. DeepSeek is designed for structured reasoning, making it perfect for content planning, logic-heavy automation, or technical assistance right inside WordPress.', 'DeepSeek', 'uncanny-automator' ),
				'trigger_sentences' => array(),
				'action_sentences'  => array(
					'Use a prompt to generate a text response with a DeepSeek model',
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
					'label'       => esc_html_x( 'DeepSeek API key', 'DeepSeek', 'uncanny-automator' ),
					'placeholder' => esc_html_x( 'Paste your secret API key here', 'DeepSeek', 'uncanny-automator' ),
					'required'    => true,
					'description' => esc_html_x( 'Enter the API key from DeepSeek. This key allows Uncanny Automator to securely connect and send prompts. Your key is stored securely and never shared.', 'DeepSeek', 'uncanny-automator' ),
					'value'       => automator_get_option( 'automator_deepseek_api_key', '' ),
					'type'        => 'text',
				),
			),
			'connection_status' => 'connected',
		);

		$deepseek_settings = new AI_Settings( $properties );
		$deepseek_settings->create_settings_page( $presentation );

		return $deepseek_settings;
	}
}
