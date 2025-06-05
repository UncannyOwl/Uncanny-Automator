<?php
namespace Uncanny_Automator\Integrations\Gemini;

use Uncanny_Automator\Integration;
use Uncanny_Automator\Integrations\Gemini\Actions\Gemini_Chat_Generate;
use Uncanny_Automator\Core\Lib\AI\Adapters\Integration\AI_Settings;
use Uncanny_Automator\Core\Lib\AI\Views\Settings;

/**
 * Google Gemini AI integration for Uncanny Automator.
 *
 * @since 5.6
 */
class Gemini_Integration extends Integration {

	/**
	 * Setup integration properties.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->set_integration( 'GEMINI' );
		$this->set_name( 'Google Gemini' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/gemini-icon.svg' );
		$this->set_connected( ! empty( automator_get_option( 'automator_gemini_api_key', '' ) ) );
		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'gemini' ) );
	}

	/**
	 * Load integration components.
	 *
	 * @return void
	 */
	public function load() {
		new Gemini_Chat_Generate();
		$this->define_settings();
	}

	/**
	 * Define the settings for the Gemini integration.
	 *
	 * @return AI_Settings
	 */
	public function define_settings() {

		$name = 'Google Gemini';
		$id   = 'gemini';

		$presentation = new Settings(
			array(
				'heading'           => $name,
				'description'       => esc_html_x( "Use Uncanny Automator with Google Gemini here to create SEO-optimized content, analyze images, or simplify data using Google's top AI. Whether drafting blog posts or generating alt text, Gemini boosts productivity with smart, data-driven suggestions tailored for WordPress users.", 'Gemini', 'uncanny-automator' ),
				'trigger_sentences' => array(),
				'action_sentences'  => array(
					'Use a prompt to generate a text response with a Gemini model',
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
					'label'       => esc_html_x( 'Google Gemini API key', 'Gemini', 'uncanny-automator' ),
					'placeholder' => esc_html_x( 'Paste your secret API key here', 'Gemini', 'uncanny-automator' ),
					'required'    => true,
					'description' => esc_html_x( 'Enter the API key from Google Gemini. This key allows Uncanny Automator to securely connect and send prompts. Your key is stored securely and never shared.', 'Gemini', 'uncanny-automator' ),
					'value'       => automator_get_option( 'automator_gemini_api_key', '' ),
					'type'        => 'text',
				),
			),
			'connection_status' => 'connected',
		);

		$gemini_settings = new AI_Settings( $properties );
		$gemini_settings->create_settings_page( $presentation );

		return $gemini_settings;
	}
}
