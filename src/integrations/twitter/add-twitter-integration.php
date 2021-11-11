<?php
/**
 * Contains Integration class.
 *
 * @since   2.4.0
 * @version 2.4.0
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

defined( 'ABSPATH' ) || exit;

/**
 * Adds Integration to Automator.
 * @since 2.4.0
 */
class Add_Twitter_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Twitter_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'TWITTER' );
		$this->set_name( 'Twitter' );
		$this->set_icon( 'twitter-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( '' );
		$this->set_settings_url( admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-settings&tab=twitter_api' );
		$this->set_connected( $this->is_connected() );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return true;
	}

	/**
	 * is_connected
	 *
	 * @return void
	 */
	public function is_connected() {

		$connected = false;

		$tokens = get_option( '_uncannyowl_twitter_settings', array() );

		if ( ! empty( $tokens ) ) {
			$connected = true;
		}

		return $connected;

	}
}
