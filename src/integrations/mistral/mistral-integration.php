<?php
namespace Uncanny_Automator\Integrations\Mistral;

use Uncanny_Automator\Integration;
use Uncanny_Automator\Integrations\Mistral\Actions\Mistral_Chat_Generate;
use Uncanny_Automator\Core\Lib\AI\Adapters\Integration\AI_Settings;
use Uncanny_Automator\Core\Lib\AI\Views\Settings;

/**
 * Mistral AI integration for Uncanny Automator.
 *
 * @since 5.6
 */
class Mistral_Integration extends Integration {

	/**
	 * Setup integration properties.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->set_integration( 'MISTRAL' );
		$this->set_name( 'Mistral AI' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/mistral-icon.svg' );
		$this->set_connected( ! empty( automator_get_option( 'automator_mistral_api_key', '' ) ) );
		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'mistral' ) );
	}

	/**
	 * Load integration components.
	 *
	 * @return void
	 */
	public function load() {
		new Mistral_Chat_Generate();
		$this->define_settings();
	}

	/**
	 * Define the settings for the Mistral integration.
	 *
	 * @return AI_Settings
	 */
	public function define_settings() {

		$name = 'Mistral AI';
		$id   = 'mistral';

		$presentation = new Settings(
			array(
				'heading'           => $name,
				'description'       => esc_html_x( 'Use Uncanny Automator with Mistral AI here to generate content or code instantly using a fast, open-source AI. Mistral AI is ideal for developers, bloggers, or marketers who want lightweight, on-site AI that delivers speed and performance without the overhead.', 'Mistral', 'uncanny-automator' ),
				'trigger_sentences' => array(),
				'action_sentences'  => array(
					'Use a prompt to generate a text response with a Mistral model',
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
					'label'       => esc_html_x( 'Mistral AI API key', 'Mistral', 'uncanny-automator' ),
					'placeholder' => esc_html_x( 'Paste your secret API key here', 'Mistral', 'uncanny-automator' ),
					'required'    => true,
					'description' => esc_html_x( 'Enter the API key from Mistral AI. This key allows Uncanny Automator to securely connect and send prompts. Your key is stored securely and never shared.', 'Mistral', 'uncanny-automator' ),
					'value'       => automator_get_option( 'automator_mistral_api_key', '' ),
					'type'        => 'text',
				),
			),
			'connection_status' => 'connected',
		);

		$mistral_settings = new AI_Settings( $properties );
		$mistral_settings->create_settings_page( $presentation );

		return $mistral_settings;
	}
}
