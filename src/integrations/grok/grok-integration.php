<?php
namespace Uncanny_Automator\Integrations\Grok;

use Uncanny_Automator\Integration;
use Uncanny_Automator\Integrations\Grok\Actions\Grok_Chat_Generate;
use Uncanny_Automator\Core\Lib\AI\Adapters\Integration\AI_Settings;
use Uncanny_Automator\Core\Lib\AI\Views\Settings;

/**
 * Grok (xAI) integration for Uncanny Automator.
 *
 * @since 5.6
 */
class Grok_Integration extends Integration {

	/**
	 * Setup integration properties.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->set_integration( 'GROK' );
		$this->set_name( 'xAI' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/grok-icon.svg' );
		$this->set_connected( ! empty( automator_get_option( 'automator_grok_api_key', false ) ) );
		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'grok' ) );
	}

	/**
	 * Load integration components.
	 *
	 * @return void
	 */
	public function load() {
		new Grok_Chat_Generate();
		$this->define_settings();
	}

	/**
	 * Define the settings for the Grok integration.
	 *
	 * @return AI_Settings
	 */
	public function define_settings() {

		$name = 'xAI';
		$id   = 'grok';

		$presentation = new Settings(
			array(
				'heading'           => $name,
				'description'       => esc_html_x( "Use Uncanny Automator with Grok here to inject real-time trends and playful insights into your content. With access to X (Twitter) data, Grok is perfect for creating timely blog posts, campaign ideas, and fun intros that resonate with what's trending right now.", 'Grok', 'uncanny-automator' ),
				'trigger_sentences' => array(),
				'action_sentences'  => array(
					'Use a prompt to generate a text response with a Grok model',
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
					'label'       => esc_html_x( 'Grok API key', 'Grok', 'uncanny-automator' ),
					'placeholder' => esc_html_x( 'Paste your secret API key here', 'Grok', 'uncanny-automator' ),
					'required'    => true,
					'description' => esc_html_x( 'Enter the API key from Grok. This key allows Uncanny Automator to securely connect and send prompts. Your key is stored securely and never shared.', 'Grok', 'uncanny-automator' ),
					'value'       => automator_get_option( 'automator_grok_api_key', '' ),
					'type'        => 'text',
				),
			),
		);

		$grok_settings = new AI_Settings( $properties );
		$grok_settings->create_settings_page( $presentation );

		return $grok_settings;
	}
}
