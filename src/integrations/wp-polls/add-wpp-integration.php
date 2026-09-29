<?php

namespace Uncanny_Automator;

/**
 * Class Add_Wpp_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Wpp_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Wpp_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'WPP' );
		$this->set_name( 'WP-Polls' );
		$this->set_icon( 'wp-polls-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'wp-polls/wp-polls.php' );

		$this->set_developer_name( "Lester 'GaMerZ' Chan" );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'WP_POLLS_VERSION' );
	}
}
