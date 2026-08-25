<?php

namespace Uncanny_Automator\Integrations\All_In_One_Wp_Migration;

/**
 * Class All_In_One_Wp_Migration_Integration
 *
 * @package Uncanny_Automator
 */
class All_In_One_Wp_Migration_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Set up integration code, name, icon and helpers.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new All_In_One_Wp_Migration_Helpers();
		$this->set_integration( 'ALL_IN_ONE_WP_MIGRATION' );
		$this->set_name( 'All-in-One WP Migration' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/all-in-one-wp-migration-icon.svg' );
	}

	/**
	 * Bootstrap triggers and actions.
	 *
	 * @return void
	 */
	public function load() {
		// Triggers.
		new Ai1wm_Backup_Created( $this->helpers );
		new Ai1wm_Backup_Failed( $this->helpers );
		new Ai1wm_Backup_Deleted( $this->helpers );

		// Actions.
		new Ai1wm_Delete_Backup_File( $this->helpers );
		new Ai1wm_Set_Backup_Label( $this->helpers );
	}

	/**
	 * Active when All-in-One WP Migration is installed. `AI1WM_PLUGIN_BASENAME`
	 * is defined in the plugin's main file at load, so it is the earliest
	 * reliable "is the plugin active" signal — the `Ai1wm_*` model classes are
	 * autoloaded later.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'AI1WM_PLUGIN_BASENAME' );
	}
}
