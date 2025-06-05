<?php
namespace Uncanny_Automator\Integrations\Cohere;

use Uncanny_Automator\Integration;
use Uncanny_Automator\Integrations\Cohere\Actions\Cohere_Chat_Generate;
use Uncanny_Automator\Core\Lib\AI\Adapters\Integration\AI_Settings;
use Uncanny_Automator\Core\Lib\AI\Views\Settings;

/**
 * Cohere AI integration for Uncanny Automator.
 *
 * @since 5.6
 */
class Cohere_Integration extends Integration {

	/**
	 * Setup integration properties.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->set_integration( 'COHERE' );
		$this->set_name( 'Cohere' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/cohere-icon.svg' );
		$this->set_connected( ! empty( automator_get_option( 'automator_cohere_api_key', '' ) ) );
		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'cohere' ) );
	}

	/**
	 * Load integration components.
	 *
	 * @return void
	 */
	public function load() {

		new Cohere_Chat_Generate();

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
			'connection_status' => 'connected',
		);

		$cohere_settings = new AI_Settings( $properties );
		$cohere_settings->create_settings_page( $presentation );

		return $cohere_settings;
	}
}
