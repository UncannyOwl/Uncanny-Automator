<?php
namespace Uncanny_Automator\Integrations\Perplexity;

use Uncanny_Automator\Integration;
use Uncanny_Automator\Integrations\Perplexity\Actions\Perplexity_Chat_Generate;
use Uncanny_Automator\Core\Lib\AI\Adapters\Integration\AI_Settings;
use Uncanny_Automator\Core\Lib\AI\Views\Settings;

/**
 * Perplexity AI integration for Uncanny Automator.
 *
 * @since 5.7
 */
class Perplexity_Integration extends Integration {

	/**
	 * Setup integration properties.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->set_integration( 'PERPLEXITY' );
		$this->set_name( 'Perplexity' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/perplexity-icon.svg' );
		$this->set_connected( ! empty( automator_get_option( 'automator_perplexity_api_key', '' ) ) );
		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'perplexity' ) );
	}

	/**
	 * Load integration components.
	 *
	 * @return void
	 */
	public function load() {
		new Perplexity_Chat_Generate();
		$this->define_settings();
	}

	/**
	 * Define the settings for the Perplexity integration.
	 *
	 * @return AI_Settings
	 */
	public function define_settings() {

		$name = 'Perplexity';
		$id   = 'perplexity';

		$presentation = new Settings(
			array(
				'heading'           => $name,
				'description'       => esc_html_x( 'Use Uncanny Automator with Perplexity AI here to fetch fact-checked answers, stats, or summaries to enrich your content. It acts like a built-in research assistant, great for bloggers and marketers who want to enhance posts with up-to-date insights from trusted sources.', 'Perplexity', 'uncanny-automator' ),
				'trigger_sentences' => array(),
				'action_sentences'  => array(
					'Use a prompt to generate a text response with a Perplexity model',
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
					'label'       => esc_html_x( 'Perplexity API key', 'Perplexity', 'uncanny-automator' ),
					'placeholder' => esc_html_x( 'Paste your secret API key here', 'Perplexity', 'uncanny-automator' ),
					'required'    => true,
					'description' => esc_html_x( 'Enter the API key from Perplexity. This key allows Uncanny Automator to securely connect and send prompts. Your key is stored securely and never shared.', 'Perplexity', 'uncanny-automator' ),
					'value'       => automator_get_option( 'automator_perplexity_api_key', '' ),
					'type'        => 'text',
				),
			),
			'connection_status' => 'connected',
		);

		$perplexity_settings = new AI_Settings( $properties );
		$perplexity_settings->create_settings_page( $presentation );

		return $perplexity_settings;
	}
}
