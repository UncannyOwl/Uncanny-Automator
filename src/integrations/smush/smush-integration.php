<?php

namespace Uncanny_Automator\Integrations\Smush;

/**
 * Class Smush_Integration
 *
 * @package Uncanny_Automator
 */
class Smush_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Smush_Helpers();
		$this->set_integration( 'SMUSH' );
		$this->set_name( 'Smush' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/smush-icon.svg' );

		$this->set_plugin_file_path( 'wp-smushit/wp-smush.php' );
		$this->set_developer_name( 'WPMU DEV' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * Register hooks that must exist even when only a subset of items is loaded.
	 *
	 * The skip dispatcher has to be listening whenever Smush runs, not only when
	 * the recipe editor loads everything — in targeted mode load() is skipped for
	 * items that aren't in the manifest, but load_shared_hooks() always runs.
	 *
	 * @return void
	 */
	protected function load_shared_hooks() {
		Smush_Skipped_Dispatcher::boot();
	}

	/**
	 * Load triggers and actions.
	 *
	 * @return void
	 */
	public function load() {

		$this->load_shared_hooks();

		// Triggers.
		new Smush_Image_Optimized( $this->helpers );
		new Smush_Image_Optimization_Skipped( $this->helpers );

		// Actions.
		new Smush_Optimize_Image( $this->helpers );
		new Smush_Restore_Image( $this->helpers );
	}

	/**
	 * Check if Smush is active.
	 *
	 * Smush Free and Smush Pro are separate plugins that both define
	 * WP_SMUSH_VERSION, so this covers either.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'WP_SMUSH_VERSION' );
	}
}
