<?php

namespace Uncanny_Automator;

/**
 * Class Add_Affwp_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Affwp_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Affwp_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'AFFWP' );
		$this->set_name( 'AffiliateWP' );
		$this->set_icon( 'affiliatewp-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'affiliate-wp/affiliate-wp.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'Affiliate_WP' );
	}
}
