<?php
declare( strict_types=1 );

namespace Uncanny_Automator\Core\Lib\AI;

use Uncanny_Automator\Core\Lib\AI\Factory\Provider_Factory;
use Uncanny_Automator\Core\Lib\AI\Provider\OpenAI_Provider;
use Uncanny_Automator\Core\Lib\AI\Provider\Claude_Provider;
use Uncanny_Automator\Core\Lib\AI\Provider\Deepseek_Provider;
use Uncanny_Automator\Core\Lib\AI\Provider\Perplexity_Provider;
use Uncanny_Automator\Core\Lib\AI\Provider\Gemini_Provider;
use Uncanny_Automator\Core\Lib\AI\Provider\Cohere_Provider;
use Uncanny_Automator\Core\Lib\AI\Provider\Grok_Provider;
use Uncanny_Automator\Core\Lib\AI\Provider\Mistral_Provider;

/**
 * AI Framework Bootstrap
 *
 * Registers all AI providers with the factory for dependency injection.
 * This file is loaded during WordPress initialization to set up the
 * complete AI framework with all supported providers.
 *
 * Optionally, you can also register your own providers in your integration load.php or integration file.
 *
 * @package Uncanny_Automator\Core\Lib\AI
 * @since 5.6
 */
class Default_Providers_Loader {

	/**
	 * Whether the providers have been registered.
	 *
	 * @var bool
	 */
	public static $providers_registered = false;

	/**
	 * Load default providers.
	 *
	 * @return void
	 */
	public function load_providers() {

		if ( self::$providers_registered ) {
			return;
		}

		Provider_Factory::register_provider( 'OPENAI', OpenAI_Provider::class );
		Provider_Factory::register_provider( 'CLAUDE', Claude_Provider::class );
		Provider_Factory::register_provider( 'PERPLEXITY', Perplexity_Provider::class );
		Provider_Factory::register_provider( 'GEMINI', Gemini_Provider::class );
		Provider_Factory::register_provider( 'DEEPSEEK', Deepseek_Provider::class );
		Provider_Factory::register_provider( 'COHERE', Cohere_Provider::class );
		Provider_Factory::register_provider( 'GROK', Grok_Provider::class );
		Provider_Factory::register_provider( 'MISTRAL', Mistral_Provider::class );

		/**
		 * Action hook fired after all AI providers are loaded and registered.
		 *
		 * Allows other plugins or themes to register additional AI providers
		 * or perform actions after the AI framework is fully initialized.
		 *
		 * @since 5.6
		 */
		do_action( 'uncanny_automator_ai_providers_loaded' );

		self::$providers_registered = true;
	}
}
