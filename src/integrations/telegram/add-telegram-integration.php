<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class Add_Telegram_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Telegram_Integration {

	use Recipe\Integrations;

	private $functions;

	public function __construct() {
		$this->setup();
	}

	/**
	 * Sets up OpenAI integration.
	 *
	 * @return void
	 */
	protected function setup() {

		require_once __DIR__ . '/settings/settings-telegram.php';
		require_once __DIR__ . '/functions/telegram-webhook.php';
		require_once __DIR__ . '/functions/telegram-api.php';
		require_once __DIR__ . '/functions/telegram-functions.php';

		$this->functions = new Telegram_Functions();

		$this->set_integration( 'TELEGRAM' );

		$this->set_name( 'Telegram' );

		$this->set_icon( 'telegram-icon.svg' );

		$this->set_icon_path( __DIR__ . '/img/' );

		$this->set_connected( $this->functions->integration_connected() );

		$this->set_settings_url( automator_get_premium_integrations_settings_url( Telegram_Functions::SETTINGS_TAB ) );
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

