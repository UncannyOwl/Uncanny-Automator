<?php

namespace Uncanny_Automator;

/**
 * Class Add_Armember_Integration
 *
 * @package Uncanny_Automator
 */
class Add_Armember_Integration {

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
		$this->set_integration( 'ARMEMBER' );
		$this->set_name( 'ARMember' );
		$this->set_icon( 'armember-icon.svg' );
		$this->set_icon_path( __DIR__ . '/img/' );
		$this->set_plugin_file_path( 'armember-membership/armember-membership.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return file_exists( WP_PLUGIN_DIR . '/armember/armember.php' ) || file_exists( WP_PLUGIN_DIR . '/armember-membership/armember-membership.php' );
	}

}
