<?php

namespace Uncanny_Automator\Integrations\Favorites;

use Uncanny_Automator\Integration;

/**
 * Class Favorites_Integration
 *
 * @package Uncanny_Automator
 */
class Favorites_Integration extends Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Favorites_Helpers();

		$this->set_integration( 'FAVORITES' );
		$this->set_name( 'Favorites' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/favorites-icon.svg' );

		$this->set_plugin_file_path( 'favorites/favorites.php' );
		$this->set_developer_name( 'Hook & Filter' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * Run-time listeners. Called first from load(), and alone in targeted mode.
	 *
	 * @return void
	 */
	public function load_shared_hooks() {
		Favorites_Clear_Dispatcher::boot();
	}

	/**
	 * Load triggers.
	 *
	 * @return void
	 */
	public function load() {
		$this->load_shared_hooks();

		new Favorites_Post_Favorited( $this->helpers );
		new Favorites_Post_Unfavorited( $this->helpers );
		new Favorites_Cleared( $this->helpers );
	}

	/**
	 * The plugin defines its file constant above the guard that loads its API, so
	 * a global Bootstrap class from another plugin leaves the constant set with
	 * nothing loaded. Check the API the triggers actually call.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return function_exists( 'get_user_favorites' );
	}
}
