<?php

namespace Uncanny_Automator\Integrations\Saveto_Wishlist;

use Uncanny_Automator\Integrations\Saveto_Wishlist\Dispatchers\Removal_Dispatcher;

/**
 * Class Saveto_Wishlist_Integration
 *
 * @package Uncanny_Automator
 */
class Saveto_Wishlist_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Flag set while SaveTo Wishlist's auto-create-default-wishlist filter is
	 * running. T2 (USER_CREATES_WISHLIST) reads this so it does not fire for
	 * the implicit "My Wishlist" the plugin creates on first wishlist access.
	 *
	 * @var bool
	 */
	private static $is_auto_creating_default = false;

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Saveto_Wishlist_Helpers();
		$this->set_integration( 'SAVETO_WISHLIST' );
		$this->set_name( 'SaveTo Wishlist' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/saveto-wishlist-icon.svg' );
	}

	/**
	 * Always-on upstream listeners — registered in targeted mode too.
	 *
	 * In targeted (front-end) load mode the framework never calls load(): only
	 * load_shared_hooks() runs, and triggers are lazy-constructed on demand.
	 * The auto-create guard must therefore live here, or USER_CREATES_WISHLIST
	 * fires for the implicit "My Wishlist" the plugin auto-creates.
	 *
	 * @return void
	 */
	protected function load_shared_hooks() {

		// Bracket the auto-create-default-wishlist filter so the implicit
		// "My Wishlist" create does not fire USER_CREATES_WISHLIST.
		add_filter( 'stwlite_response_wishlist_data', array( $this, 'mark_auto_create_start' ), 9, 2 );
		add_filter( 'stwlite_response_wishlist_data', array( $this, 'mark_auto_create_end' ), 11, 2 );

		// Normalize SaveTo's multiple removal paths (legacy AJAX, front-end REST,
		// admin bulk save) into one internal action for USER_REMOVES_PRODUCT.
		Removal_Dispatcher::boot();
	}

	/**
	 * Bootstrap triggers and actions.
	 *
	 * @return void
	 */
	public function load() {

		// Register always-on upstream listeners in full-load mode too —
		// targeted mode calls load_shared_hooks() directly.
		$this->load_shared_hooks();

		// Triggers.
		new SAVETO_WISHLIST_USER_ADDS_PRODUCT( $this->helpers );
		new SAVETO_WISHLIST_USER_CREATES_WISHLIST( $this->helpers );
		new SAVETO_WISHLIST_USER_REMOVES_PRODUCT( $this->helpers );
		new SAVETO_WISHLIST_USER_MOVES_TO_CART( $this->helpers );
		new SAVETO_WISHLIST_GUEST_SYNCED( $this->helpers );

		// Actions.
		new SAVETO_WISHLIST_ADD_PRODUCT( $this->helpers );
		new SAVETO_WISHLIST_REMOVE_PRODUCT( $this->helpers );
		new SAVETO_WISHLIST_CREATE_WISHLIST( $this->helpers );
	}

	/**
	 * Filter callback fired before SaveTo Wishlist's own `auto_create_wishlist`
	 * callback (priority 10). Records that any save_collection() invoked from
	 * within this filter chain is the implicit default-wishlist create, not a
	 * user-driven one.
	 *
	 * @param array|object $wishlist_data
	 * @param int          $user_id
	 *
	 * @return array|object
	 */
	public function mark_auto_create_start( $wishlist_data, $user_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		self::$is_auto_creating_default = true;
		return $wishlist_data;
	}

	/**
	 * Filter callback fired after SaveTo Wishlist's `auto_create_wishlist`
	 * callback at priority 11.
	 *
	 * @param array|object $wishlist_data
	 * @param int          $user_id
	 *
	 * @return array|object
	 */
	public function mark_auto_create_end( $wishlist_data, $user_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		self::$is_auto_creating_default = false;
		return $wishlist_data;
	}

	/**
	 * Whether the auto-create-default-wishlist filter is currently running.
	 * T2 uses this to skip its trigger in that path.
	 *
	 * @return bool
	 */
	public static function is_auto_creating_default() {
		return self::$is_auto_creating_default;
	}

	/**
	 * Reset the auto-create flag — for tests only. The flag is normally
	 * cleared by the priority-11 filter callback after every request that
	 * touches the response-wishlist-data chain.
	 *
	 * @return void
	 */
	public static function reset_auto_create_flag() {
		self::$is_auto_creating_default = false;
	}

	/**
	 * Plugin-active check.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'STWLITE_VERSION' ) && class_exists( 'WooCommerce' );
	}
}
