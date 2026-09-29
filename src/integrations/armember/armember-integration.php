<?php

namespace Uncanny_Automator\Integrations\Armember;

use Uncanny_Automator\Integration_Manifest;

/**
 * Class Armember_Integration
 *
 * @package Uncanny_Automator
 */
class Armember_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Armember_Helpers();
		$this->set_integration( 'ARMEMBER' );
		$this->set_name( 'ARMember' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/armember-icon.svg' );

		$this->set_plugin_file_path( 'armember-membership/armember-membership.php' );
		$this->set_developer_name( 'Repute Infosystems' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * Load triggers and actions.
	 *
	 * @return void
	 */
	public function load() {
		new ARMEMBER_MEMBERSHIP_ADDED( $this->helpers );
		new ARMEMBER_MEMBERSHIP_CANCELLED( $this->helpers );

		new ARMEMBER_MEMBERSHIP_PLAN_CANCELLED( $this->helpers );
	}

	/**
	 * Whether ARMember is active, in either edition.
	 *
	 * Each edition defines its own directory constant on load; the Pro edition
	 * does not define the Lite one.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'MEMBERSHIPLITE_DIR_NAME' ) || defined( 'MEMBERSHIP_DIR_NAME' );
	}
}
