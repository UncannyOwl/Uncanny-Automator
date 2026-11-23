<?php

namespace Uncanny_Automator\Integrations\Sportspress;

use Uncanny_Automator\Integration;

/**
 * Class Sportspress_Integration
 *
 * @package Uncanny_Automator\Integrations\Sportspress
 */
class Sportspress_Integration extends Integration {

	/**
	 * Set up the integration.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Sportspress_Helpers();

		$this->set_integration( 'SPORTSPRESS' );
		$this->set_name( 'SportsPress' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/sportspress-icon.svg' );

		// Identical in Pro (R19).
		$this->set_plugin_file_path( 'sportspress/sportspress.php' );
		$this->set_developer_name( 'ThemeBoy' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
		$this->set_plugin_variations( array( 'sportspress-pro/sportspress-pro.php' ) );
	}

	/**
	 * Run-time listeners; Pro's triggers ride them too.
	 *
	 * @return void
	 */
	public function load_shared_hooks() {
		Sportspress_Post_Dispatcher::boot();
		Sportspress_Meta_Dispatcher::boot();
	}

	/**
	 * Load triggers and actions.
	 *
	 * @return void
	 */
	public function load() {

		$this->load_shared_hooks();

		new Sportspress_Event_Created( $this->helpers );
		new Sportspress_Player_Created( $this->helpers );
		new Sportspress_Team_Created( $this->helpers );
		new Sportspress_Event_Updated( $this->helpers );

		new Sportspress_Create_Event( $this->helpers );
		new Sportspress_Update_Event_Results( $this->helpers );
	}

	/**
	 * Both SportsPress and SportsPress Pro define it.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'SP_PLUGIN_FILE' );
	}
}
