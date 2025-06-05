<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations;

use Uncanny_Automator\Integrations\OpenAI\Actions\OpenAI_Chat_Generate;
use Uncanny_Automator\Integrations\OpenAI\Actions\OpenAI_Image_Generate;
use Uncanny_Automator\Integration;
use Uncanny_Automator\OPEN_AI_CORRECT_SPELLING_GRAMMAR;
use Uncanny_Automator\OPEN_AI_EXCERPT_GENERATE;
use Uncanny_Automator\Open_AI_Helpers;
use Uncanny_Automator\OPEN_AI_IMAGE_GENERATE_DALL_E;
use Uncanny_Automator\OPEN_AI_INSTAGRAM_EXCERPT_GENERATE;
use Uncanny_Automator\OPEN_AI_LINKS_SUGGEST;
use Uncanny_Automator\OPEN_AI_META_DESCRIPTION_GENERATE;
use Uncanny_Automator\OPEN_AI_SENTIMENT_ANALYZE;
use Uncanny_Automator\OPEN_AI_SEO_TITLE_GENERATE;
use Uncanny_Automator\OPEN_AI_TEXT_GENERATE;
use Uncanny_Automator\OPEN_AI_TEXT_TRANSLATE;
use Uncanny_Automator\OPEN_AI_TWITTER_EXCERPT_GENERATE;

/**
 * Class Open_AI_Integration
 *
 * This integration loads both v2 and v3 actions.
 *
 * @todo - Convert all v2 actions to v3 actions.
 *
 * @package Uncanny_Automator
 */
class OpenAI_Integration extends Integration {

	/**
	 * Sets up OpenAI integration.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Open_AI_Helpers();

		$this->set_integration( 'OPEN_AI' );
		$this->set_name( 'OpenAI' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/openai-icon.svg' );
		$this->set_connected( $this->is_openai_connected() );
		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'open-ai' ) );
		$this->register_hooks();
	}

	/**
	 * Determines if OpenAI is connected or not.
	 *
	 * @return bool True if connected, false otherwise.
	 */
	private function is_openai_connected() {
		return '' !== automator_get_option( 'automator_open_ai_secret', '' ) ? true : false;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	private function register_hooks() {
		add_action( 'wp_ajax_automator_openai_fetch_image_generation_models', array( $this->helpers, 'handle_fetch_image_generation_models' ) );
	}

	/**
	 * Loads the integration.
	 *
	 * @return void
	 */
	public function load() {

		// Register v3 actions.
		new OpenAI_Image_Generate();
		new OpenAI_Chat_Generate();

		// Register v2 actions.
		new OPEN_AI_CORRECT_SPELLING_GRAMMAR();
		new OPEN_AI_EXCERPT_GENERATE();
		new OPEN_AI_INSTAGRAM_EXCERPT_GENERATE();
		new OPEN_AI_LINKS_SUGGEST();
		new OPEN_AI_META_DESCRIPTION_GENERATE();
		new OPEN_AI_SENTIMENT_ANALYZE();
		new OPEN_AI_SEO_TITLE_GENERATE();
		new OPEN_AI_TEXT_GENERATE();
		new OPEN_AI_TEXT_TRANSLATE();
		new OPEN_AI_IMAGE_GENERATE_DALL_E();
		new OPEN_AI_TWITTER_EXCERPT_GENERATE();
	}
}
