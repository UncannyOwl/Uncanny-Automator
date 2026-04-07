<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator\Integrations\OpenAI;

use Uncanny_Automator\Integrations\OpenAI\Actions\OpenAI_Chat_Generate;
use Uncanny_Automator\Integrations\OpenAI\Actions\OpenAI_Image_Generate;

/**
 * Class OpenAI_Integration
 *
 * @package Uncanny_Automator
 */
class OpenAI_Integration extends \Uncanny_Automator\App_Integrations\App_Integration {

	/**
	 * Get the integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'OPEN_AI',
			'name'         => 'OpenAI',
			'api_endpoint' => 'v2/open-ai',
			'settings_id'  => 'open-ai',
		);
	}

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new OpenAI_App_Helpers( self::get_config() );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/openai-icon.svg' );
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Load integration components.
	 *
	 * @return void
	 */
	public function load() {
		// Settings page.
		new OpenAI_Settings(
			$this->dependencies,
			$this->get_settings_config()
		);

		// V3 actions.
		new OpenAI_Chat_Generate( $this->dependencies );
		new OpenAI_Image_Generate( $this->dependencies );

		// V2 actions (migrated to App_Action).
		new OPEN_AI_CORRECT_SPELLING_GRAMMAR( $this->dependencies );
		new OPEN_AI_EXCERPT_GENERATE( $this->dependencies );
		new OPEN_AI_INSTAGRAM_EXCERPT_GENERATE( $this->dependencies );
		new OPEN_AI_LINKS_SUGGEST( $this->dependencies );
		new OPEN_AI_META_DESCRIPTION_GENERATE( $this->dependencies );
		new OPEN_AI_SENTIMENT_ANALYZE( $this->dependencies );
		new OPEN_AI_SEO_TITLE_GENERATE( $this->dependencies );
		new OPEN_AI_TEXT_GENERATE( $this->dependencies );
		new OPEN_AI_TEXT_TRANSLATE( $this->dependencies );
		new OPEN_AI_IMAGE_GENERATE_DALL_E( $this->dependencies );
		new OPEN_AI_TWITTER_EXCERPT_GENERATE( $this->dependencies );
	}

	/**
	 * Check if app is connected.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		$credentials = $this->helpers->get_credentials();
		return ! empty( $credentials );
	}

	/**
	 * Register AJAX hooks for the recipe builder.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp_ajax_automator_openai_get_gpt_models', array( $this->helpers, 'get_gpt_models_ajax' ) );
		add_action( 'wp_ajax_automator_openai_fetch_image_generation_models', array( $this->helpers, 'get_image_generation_models_ajax' ) );
	}
}
