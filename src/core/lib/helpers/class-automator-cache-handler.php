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
	 *
	 */
	const OPTION_NAME = 'uncanny_automator_advanced_automator_cache';

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

		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action(
			'automator_settings_advanced_tab_view_automator_cache',
			array(
				$this,
				'settings_output',
			)
		);

		if ( false === $this->is_cache_enabled() ) {
			return;
		}
		$expiry        = 30 * MINUTE_IN_SECONDS; // 5 mins.
		$this->expires = apply_filters( 'automator_cache_expiry', $expiry );

		$expiry             = 1440 * MINUTE_IN_SECONDS; // 24 hours.
		$this->long_expires = apply_filters( 'automator_cache_long_expiry', $expiry );

		add_action(
			'wp_after_insert_post',
			array(
				$this,
				'maybe_clear_cache_for_posts',
			),
			99999,
			4
		);
		add_action(
			'wp_after_insert_post',
			array(
				$this,
				'maybe_clear_cache_for_recipes',
			),
			99999,
			4
		);
		add_action(
			'user_register',
			array(
				$this,
				'maybe_clear_user_cache',
			),
			99999
		);
		add_action(
			'automator_recipe_completed',
			array(
				$this,
				'reset_recipes_after_completion',
			),
			99999,
			4
		);
		add_action(
			'activated_plugin',
			array(
				$this,
				'reset_integrations_directory',
			),
			99999,
			2
		);
		add_action(
			'deactivated_plugin',
			array(
				$this,
				'reset_integrations_directory',
			),
			99999,
			2
		);
		add_action(
			'delete_post',
			array(
				$this,
				'recipe_post_deleted',
			),
			99999,
			2
		);

		add_action(
			'transition_post_status',
			array(
				$this,
				'recipe_post_status_changed',
			),
			99999,
			3
		);
		add_action(
			'automator_recipe_action_created',
			array(
				$this,
				'recipe_post_status_changed',
			),
			99999
		);
		add_action(
			'automator_recipe_trigger_created',
			array(
				$this,
				'recipe_post_status_changed',
			),
			99999
		);
		add_action(
			'automator_recipe_closure_created',
			array(
				$this,
				'recipe_post_status_changed',
			),
			99999
		);

		add_action( 'admin_init', array( $this, 'remove_all_cache' ) );

		add_action( 'admin_bar_menu', array( $this, 'add_cache_clear' ), 999 );

		// Enqueue assets for the admin bar
		add_action(
			'wp_enqueue_scripts',
			array(
				$this,
				'enqueue_admin_bar_assets',
			)
		);
		add_action(
			'admin_enqueue_scripts',
			array(
				$this,
				'enqueue_admin_bar_assets',
			)
		);

		add_action(
			'upgrader_process_complete',
			array(
				$this,
				'upgrader_process_completed',
			),
			999,
			0
		);
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
		if ( false === (bool) $this->is_cache_enabled( $key ) ) {
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
		if ( false === (bool) $this->is_cache_enabled( $key ) ) {
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

				<div class="uap notice">
					<uo-alert type="success">
						Automator cache flushed!
					</uo-alert>
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
		$icon_url  = 'data:image/svg+xml;base64,' . base64_encode( '<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><path d="m42.63 28.37c-0.27 2.32-0.27 4.67 0 6.99 0.4 3.44 6.7 3.44 7.27 0 0.37-2.31 0.37-4.65 0-6.96-0.56-3.44-6.9-3.44-7.27-0.03z" fill="#a7aaad"/><path d="m14.04 28.41c-0.37 2.31-0.37 4.65 0 6.96 0.57 3.44 6.88 3.44 7.28 0 0.27-2.32 0.27-4.67 0-6.99-0.37-3.42-6.67-3.42-7.28 0.03z" fill="#a7aaad"/><path d="m37.07 39.61c-3.13 0.82-6.41 0.82-9.54 0 0.29 5.9 9.25 5.9 9.54 0z" fill="#a7aaad"/><path d="m63.5 19.04c-0.34-3.07-2.25-4.75-5.15-5.4-8.67-1.82-17.51-2.69-26.38-2.58-8.84-0.08-17.66 0.78-26.32 2.58-2.96 0.65-4.86 2.33-5.2 5.4-0.39 4-0.53 8.01-0.4 12.02 0.09 4.27 0.61 8.52 1.56 12.69 0.65 2.73 3.6 4.49 6.05 5.52 11.5 4.91 37.1 4.91 48.65 0 2.45-1.04 5.36-2.79 6.05-5.52 0.96-4.16 1.5-8.42 1.6-12.69 0.13-4.01-0.02-8.03-0.46-12.02zm-5.42 23.64c-0.25 0.99-2.75 2.24-3.52 2.53-10.4 4.48-34.86 4.48-45.25 0-0.78-0.32-3.24-1.51-3.52-2.53-1.46-6.05-1.84-15.43-1.01-23.2 0.12-1.19 0.82-1.36 1.73-1.56 5.08-1.18 10.25-1.89 15.46-2.16 0.18-0.01 0.36 0.06 0.5 0.18 0.13 0.12 0.21 0.29 0.24 0.47 0.37 2.86 0.99 5.67 1.84 8.43 0.62 1.23 4.58 1.76 7.42 1.76 2.87 0 6.84-0.53 7.36-1.76 0.85-2.75 1.48-5.57 1.85-8.43 0.02-0.18 0.11-0.35 0.25-0.47 0.13-0.11 0.31-0.18 0.49-0.18 5.2 0.24 10.35 0.96 15.42 2.16 0.94 0.2 1.64 0.37 1.76 1.56 0.82 7.74 0.45 17.11-1.02 23.2z" fill="#a7aaad"/></svg>' );

		$args = array(
			'id'    => $parent_id,
			'title' => '<div id="uncanny-automator-ab-icon" class="ab-item ab-item-uncanny-automator-logo svg" style="background-image: url(\'' . $icon_url . '\') !important;"></div> ' . esc_html__( 'Automator', 'uncanny-automator' ),
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
				'title'  => esc_html__( 'Logs', 'uncanny-automator' ),
				'group'  => false,
				'href'   => admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-admin-logs' ),
			)
		);
		$wp_admin_bar->add_node(
			array(
				'id'     => 'automator-settings',
				'parent' => $parent_id,
				'title'  => esc_html__( 'Settings', 'uncanny-automator' ),
				'group'  => false,
				'href'   => admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-config' ),
			)
		);

		if ( true === (bool) $this->is_cache_enabled() ) {
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
	 * Enqueue a CSS file to add the Automator icon to the sidebar and admin bar
	 */
	public function enqueue_admin_bar_assets() {
		// If not logged in, bail.
		// Mainly to avoid adding menu for BuddyBoss platform
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Check if the admin bar is showing, otherwise we don't need the file
		if ( ! is_admin_bar_showing() ) {
			return;
		}

		// If the current user can't write posts, it can't use Automator, so let's not output an admin menu
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		// Load the file
		wp_enqueue_style(
			'uap-admin-admin-menu',
			Utilities::automator_get_asset( 'legacy/css/admin/admin-menu.css' ),
			array(),
			Utilities::automator_get_version()
		);
	}

	/**
	 */
	public function upgrader_process_completed() {
		$this->reset_integrations_directory( null, null );
		do_action( 'automator_cache_upgrader_process_completed' );
	}

	/**
	 * @return mixed|void
	 */
	public function is_cache_enabled( $key = '' ) {
		$value = automator_get_option( self::OPTION_NAME, '' );

		if ( '' === (string) $value ) {
			// Use filter to check if user has disabled object cache previously.
			// Once the value is saved, no need to run the filter, since it's redundant and inverse
			// Since the filter is to 'Disable' the cache,
			// We need to inverse the value, true is false in this case
			$value = ! apply_filters( 'automator_disable_object_cache', false, $key );
		}

		return '0' === $value || false === $value ? false : true;
	}

	/**
	 * @return void
	 */
	public function register_setting() {

		$args = array(
			'type'              => 'boolean',
			'sanitize_callback' => array( $this, 'test_cache' ),
		);

		register_setting( 'uncanny_automator_advanced_automator_cache', self::OPTION_NAME, $args );
	}

	/**
	 * @param $settings_group
	 *
	 * @return void
	 */
	public function settings_output( $settings_group ) {
		$cache_enabled = $this->is_cache_enabled();
		?>

		<div class="uap-field uap-spacing-top--small">

			<uo-switch
					id="<?php echo esc_attr( self::OPTION_NAME ); ?>"
				<?php echo true === $cache_enabled ? 'checked' : ''; ?>
					status-label="<?php esc_attr_e( 'Enabled', 'uncanny-automator' ); ?>,<?php esc_attr_e( 'Disabled', 'uncanny-automator' ); ?>"
					class="uap-spacing-top"
			></uo-switch>

			<div class="uap-field-description">
				<?php esc_html_e( 'Automator uses object caching to accelerate some processes. Disable when troubleshooting recipes.', 'uncanny-automator' ); ?>
			</div>

		</div>
		<?php
	}

	/**
	 * @param $value
	 *
	 * @return string
	 */
	public function test_cache( $value ) {

		if ( empty( $value ) ) {
			return '0';
		}

		return '1';
	}

}
