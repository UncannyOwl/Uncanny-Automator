<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class Add_OpenAI_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Open_AI_Integration {

	use Recipe\Integrations;

	public function __construct() {

		$this->setup();

	}

	/**
	 * Sets up OpenAI integration.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->set_integration( 'OPEN_AI' );

		$this->set_name( 'OpenAI' );

		$this->set_icon( 'openai-icon.svg' );

		$this->set_icon_path( __DIR__ . '/img/' );

		$this->set_connected( false !== automator_get_option( 'automator_open_ai_secret', false ) ? true : false );

		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'open-ai' ) );

	}

	/**
	 * Determines whether the integration should be loaded or not.
	 *
	 * @return bool True. Always.
	 */
	public function plugin_active() {

		return true;

	}

}

