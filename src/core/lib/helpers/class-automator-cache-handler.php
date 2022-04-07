<?php

namespace Uncanny_Automator;

/**
 * Class Automator_Cache_Handler
 *
 * @package Uncanny_Automator
 */
class Automator_Cache_Handler {
	/**
	 * @var mixed|void
	 */
	public $expires;
	/**
	 * @var
	 */
	public $long_expires;

	/**
	 * @var string
	 */
	public $recipes_data = 'automator_recipes_data';

	/**
	 * @var string
	 */
	public $recipes = 'automator_recipes';

	/**
	 * @var Automator_Cache_Handler
	 */
	public static $instance;

	/**
	 * @return Automator_Cache_Handler
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Cache_Handler constructor.
	 */
	public function __construct() {
		$expiry        = 30 * MINUTE_IN_SECONDS; // 5 mins.
		$this->expires = apply_filters( 'automator_cache_expiry', $expiry );

		$expiry             = 1440 * MINUTE_IN_SECONDS; // 24 hours.
		$this->long_expires = apply_filters( 'automator_cache_long_expiry', $expiry );

		add_action( 'wp_after_insert_post', array( $this, 'maybe_clear_cache_for_posts' ), 99999, 4 );
		add_action( 'wp_after_insert_post', array( $this, 'maybe_clear_cache_for_recipes' ), 99999, 4 );
		add_action( 'user_register', array( $this, 'maybe_clear_user_cache' ), 99999 );
		add_action( 'automator_recipe_completed', array( $this, 'reset_recipes_after_completion' ), 99999, 4 );
		add_action( 'activated_plugin', array( $this, 'reset_integrations_directory' ), 99999, 2 );
		add_action( 'deactivated_plugin', array( $this, 'reset_integrations_directory' ), 99999, 2 );
		add_action( 'delete_post', array( $this, 'recipe_post_deleted' ), 99999, 2 );

		add_action( 'transition_post_status', array( $this, 'recipe_post_status_changed' ), 99999, 3 );
		add_action( 'automator_recipe_action_created', array( $this, 'recipe_post_status_changed' ), 99999 );
		add_action( 'automator_recipe_trigger_created', array( $this, 'recipe_post_status_changed' ), 99999 );
		add_action( 'automator_recipe_closure_created', array( $this, 'recipe_post_status_changed' ), 99999 );

		add_action( 'admin_init', array( $this, 'remove_all_cache' ) );

		add_action( 'admin_bar_menu', array( $this, 'add_cache_clear' ), 999 );

		add_action( 'upgrader_process_complete', array( $this, 'upgrader_process_completed' ), 999, 0 );
	}

	/**
	 * @param $post_id
	 * @param $post
	 * @param $update
	 * @param $post_before
	 */
	public function maybe_clear_cache_for_posts( $post_id, $post, $update, $post_before ) {

		// If it's post update, return
		if ( $update ) {
			return;
		}

		// If it's Automator post type, return
		if ( 'uo-recipe' === $post->post_type || 'uo-trigger' === $post->post_type || 'uo-action' === $post->post_type || 'uo-closure' === $post->post_type ) {
			return;
		}

		// prepare transient key.
		$transient_key = apply_filters( 'automator_transient_name', 'automator_transient', array() );

		// suffix post type is needed.
		$transient_key .= md5( wp_json_encode( $post->post_type ) );

		$this->remove( $transient_key );

		do_action( 'automator_cache_maybe_clear_cache_for_posts', $post_id, $post, $update, $post_before );
	}

	/**
	 * @param $post_id
	 * @param $post
	 * @param $update
	 * @param $post_before
	 */
	public function maybe_clear_cache_for_recipes( $post_id, $post, $update, $post_before ) {

		// If it's Automator post type, return
		if ( 'uo-recipe' !== $post->post_type || 'uo-trigger' !== $post->post_type || 'uo-action' !== $post->post_type || 'uo-closure' !== $post->post_type ) {
			return;
		}

		// Clear recipes data cache
		$this->remove( $this->recipes_data );
		$this->remove( 'automator_actionified_triggers' );
		do_action( 'automator_cache_maybe_clear_cache_for_recipes', $post_id, $post, $update, $post_before );
	}

	/**
	 *
	 */
	public function maybe_clear_user_cache() {
		$transient_key = 'automator_transient_users';
		$this->remove( $transient_key );
		do_action( 'automator_cache_maybe_clear_user_cache' );
	}

	/**
	 * @param $recipe_id
	 * @param $user_id
	 * @param $recipe_log_id
	 * @param $args
	 */
	public function reset_recipes_after_completion( $recipe_id, $user_id, $recipe_log_id, $args ) {
		// Reset recipe ID cache
		$key = 'automator_recipe_data_of_' . $recipe_id;
		$this->remove( $key );
		// Clear recipes data cache
		$this->remove( $this->recipes_data );
		do_action( 'automator_cache_reset_recipes_after_completion', $recipe_id, $user_id, $recipe_log_id, $args );
	}

	/**
	 * @param $plugin
	 * @param $network_wide
	 */
	public function reset_integrations_directory( $plugin, $network_wide ) {
		$this->remove( 'automator_integration_directories_loaded' );
		$this->remove( 'automator_get_all_integrations' );
		$this->remove( 'automator_actionified_triggers' );
		do_action( 'automator_cache_reset_integrations_directory', $plugin, $network_wide );
	}

	/**
	 * @param $post_id
	 */
	public function clear_automator_recipe_part_cache( $post_id ) {
		$key = $this->recipes_data;
		$this->remove( $key );
		// Reset recipe ID cache
		$key = 'automator_recipe_data_of_' . $post_id;
		$this->remove( $key );
		$this->remove( 'get_recipe_type' );
		do_action( 'automator_cache_clear_automator_recipe_part_cache', $post_id );
	}

	/**
	 * @param mixed ...$args
	 */
	public function recipe_post_status_changed( ...$args ) {
		if ( 1 === count( $args ) ) {
			return;
		}
		$new_status = $args[0];
		$old_status = $args[1];
		$post       = $args[2];
		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		// prepare transient key.
		$transient_key = apply_filters( 'automator_transient_name', 'automator_transient', array() );

		// suffix post type is needed.
		$transient_key .= md5( wp_json_encode( $post->post_type ) );
		$this->remove( $transient_key );

		// Clear recipes data cache
		$this->remove( $this->recipes_data );
		$this->remove( 'automator_actionified_triggers' );
		$this->remove( 'automator_integration_directories_loaded' );
		$this->remove( 'automator_get_all_integrations' );
		$this->remove( 'get_recipe_type' );

		do_action( 'automator_cache_recipe_post_status_changed', $args );
	}

	/**
	 * @param mixed ...$args
	 */
	public function recipe_post_deleted( ...$args ) {

		if ( empty( $args ) ) {
			return;
		}
		$post_id = isset( $args[0] ) ? absint( $args[0] ) : 0;
		if ( 0 === $post_id ) {
			return;
		}
		$post = isset( $args[1] ) ? $args[1] : get_post( $post_id );

		if ( ! $post instanceof \WP_Post ) {
			return;
		}
		// prepare transient key.
		$transient_key = apply_filters( 'automator_transient_name', 'automator_transient', array() );

		// suffix post type is needed.
		$transient_key .= md5( wp_json_encode( $post->post_type ) );
		$this->remove( $transient_key );

		// Clear recipes data cache
		$this->remove( $this->recipes_data );
		$this->remove( 'automator_actionified_triggers' );
		do_action( 'automator_cache_recipe_post_deleted', $args );
	}

	/**
	 * @param string $key
	 * @param mixed $data
	 * @param string $group
	 * @param null|mixed $expires
	 */
	public function set( $key, $data, $group = 'automator', $expires = null ) {
		// Allow users to disable cache
		if ( true === apply_filters( 'automator_disable_object_cache', false, $key ) ) {
			return;
		}

		if ( null === $expires ) {
			$expires = $this->expires;
		}
		wp_cache_set( $key, $data, $group, $expires );
	}

	/**
	 * @param string $key
	 * @param string $group
	 *
	 * @return bool|mixed
	 */
	public function get( $key, $group = 'automator' ) {
		// Allow users to disable cache
		if ( true === apply_filters( 'automator_disable_object_cache', false, $key ) ) {
			return array();
		}

		return wp_cache_get( $key, $group );
	}

	/**
	 * @param string $key
	 * @param string $group
	 */
	public function remove( $key, $group = 'automator' ) {
		wp_cache_delete( $key, $group );
	}

	/**
	 *
	 */
	public function remove_all() {
		wp_cache_flush();
		$this->remove( 'automator_integration_directories_loaded' );
		$this->remove( 'automator_get_all_integrations' );
		$this->remove( 'automator_actionified_triggers' );
		$this->remove( $this->recipes_data );
		$this->remove( 'get_recipe_type' );
		do_action( 'automator_cache_remove_all' );
	}

	/**
	 *
	 */
	public function remove_all_cache() {
		if ( ! automator_filter_has_var( 'automator_flush_all' ) ) {
			return;
		}
		if ( ! wp_verify_nonce( automator_filter_input( '_wpnonce' ), AUTOMATOR_BASE_FILE ) ) {
			return;
		}
		$this->remove_all();
		add_action(
			'admin_notices',
			function () {
				?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Automator cache flushed!', 'uncanny-automator' ); ?></p>
				</div>
				<?php
			}
		);
	}

	/**
	 * @param $wp_admin_bar
	 */
	public function add_cache_clear( $wp_admin_bar ) {
		// If not logged in, bail.
		// Mainly to avoid adding menu for BuddyBoss platform
		if ( ! is_user_logged_in() ) {
			return;
		}

		// If user not admin, bail
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Let users hide this menu item
		if ( true === apply_filters( 'automator_hide_wp_admin_header_menu', false ) ) {
			return;
		}

		$parent_id = 'automator-bar';
		$icon_url  = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48c3ZnIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgdmlld0JveD0iMCAwIDI2LjQ1IDI5LjUxIj48cGF0aCBkPSJNMjIuODkgNS41N2MtLjE4LS4xOC0uMzYtLjM1LS41NS0uNTFsMi41NS0yLjU1YTEuNDcgMS40NyAwIDAwLjQzLTEuMDcgMS40OSAxLjQ5IDAgMDAtMi41NS0xbC0yLjk1IDNhMTIuMDkgMTIuMDkgMCAwMC01LjUyLTEuNDNoLTIuMTlhMTIgMTIgMCAwMC01LjUyIDEuMzNMMy42NC40NGExLjUgMS41IDAgMDAtMi4xLjMxIDEuNDkgMS40OSAwIDAwMCAxLjc2bDIuNTMgMi41NUExMi4wNyAxMi4wNyAwIDAwMCAxNC4xN2E0LjM0IDQuMzQgMCAwMDQuMyA0LjM0aDE3LjgxYTQuMzQgNC4zNCAwIDAwNC4zNC00LjM0IDEyLjE1IDEyLjE1IDAgMDAtMy41Ni04LjZ6TTcuMiA3LjUxYTEuNSAxLjUgMCAxMTEuNSAxLjUgMS41IDEuNSAwIDAxLTEuNS0xLjV6bTkuNTQgNS43MWE1IDUgMCAwMS03LjA3IDAgMSAxIDAgMDExLjQxLTEuNDEgMyAzIDAgMDA0LjI0IDAgMSAxIDAgMDExLjQxIDAgMSAxIDAgMDEuMDEgMS40MXptMS00LjIxYTEuNSAxLjUgMCAxMTEuNS0xLjUgMS41IDEuNSAwIDAxLTEuNSAxLjV6TTIyLjcgMjAuNTFoLTE5YTMuNzEgMy43MSAwIDAwLTMuNyAzLjd2MS42YTMuNzEgMy43MSAwIDAwMy43IDMuN2gxOWEzLjcxIDMuNzEgMCAwMDMuNy0zLjd2LTEuNmEzLjcxIDMuNzEgMCAwMC0zLjctMy43em0tNi41IDdoLTMuNXYtMmExIDEgMCAwMC0yIDB2MmgtLjVhMSAxIDAgMDEtMS0xIDQgNCAwIDAxOCAwIDEgMSAwIDAxLTEgMXoiIGZpbGw9IiM4Mjg3OEMiLz48Y2lyY2xlIGN4PSIxNC43IiBjeT0iMjUuNTEiIHI9IjEiIGZpbGw9IiM4Mjg3OEMiLz48L3N2Zz4=';
		$args      = array(
			'id'    => $parent_id,
			'title' => '<div class="ab-item automator-menu-icon svg" style="background-image: url(\'' . $icon_url . '\');"></div><span class="ab-label">' . esc_html__( 'Automator', 'uncanny-automator' ) . '</span>',
			'href'  => admin_url( 'admin.php?page=uncanny-automator-dashboard' ),
			'meta'  => array(
				'class' => 'automator',
				'title' => esc_html__( 'Automator', 'uncanny-automator' ),
			),
		);
		$wp_admin_bar->add_node( $args );

		$wp_admin_bar->add_node(
			array(
				'id'     => 'automator-all-recipes',
				'parent' => $parent_id,
				'title'  => esc_html__( 'All recipes', 'uncanny-automator' ),
				'group'  => false,
				'href'   => admin_url( 'edit.php?post_type=uo-recipe' ),
			)
		);
		$wp_admin_bar->add_node(
			array(
				'id'     => 'automator-add-recipe',
				'parent' => $parent_id,
				'title'  => esc_html__( 'Add new recipe', 'uncanny-automator' ),
				'group'  => false,
				'href'   => admin_url( 'post-new.php?post_type=uo-recipe' ),
			)
		);
		$wp_admin_bar->add_node(
			array(
				'id'     => 'automator-recipe-logs',
				'parent' => $parent_id,
				'title'  => esc_html__( 'Recipe log', 'uncanny-automator' ),
				'group'  => false,
				'href'   => admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-recipe-log' ),
			)
		);

		if ( false === apply_filters( 'automator_disable_object_cache', false, '' ) ) {
			$wp_admin_bar->add_node(
				array(
					'id'     => 'automator-clear-cache',
					'parent' => $parent_id,
					'title'  => esc_html__( 'Flush cache', 'uncanny-automator' ),
					'group'  => false,
					'href'   => admin_url( 'admin.php?page=uncanny-automator-dashboard&automator_flush_all=true&_wpnonce=' ) . wp_create_nonce( AUTOMATOR_BASE_FILE ),
				)
			);
		}
	}

	/**
	 */
	public function upgrader_process_completed() {
		$this->reset_integrations_directory( null, null );
		do_action( 'automator_cache_upgrader_process_completed' );
	}
}
