<?php

namespace Uncanny_Automator;

/**
 * Class Add_Wm_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Wm_Integration {

	use Recipe\Integrations;

	/**
	 * Add_Wm_Integration constructor.
	 */
	public function __construct() {
		$this->setup();
	}

	/**
	 *
	 */
	protected function setup() {
		$this->set_integration( 'WISHLISTMEMBER' );
		$this->set_name( 'Wishlist Member' );
		$this->set_icon( 'wishlist-member-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'wishlist-member/wpm.php' );

		$this->set_developer_name( 'Wishlist Products' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'commercial' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'WishListMember' );
	}
}
