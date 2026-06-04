<?php

namespace Uncanny_Automator;

/**
 * Class Automator_Cache_Handler
 *
 * @package Uncanny_Automator
 */
class Automator_Cache_Handler {

	/**
	 *
	 */
	const OPTION_NAME = 'uncanny_automator_advanced_automator_cache';
	/**
	 * @var Automator_Cache_Handler
	 */
	public static $instance;
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
	 * Cached result of is_cache_enabled() for this request.
	 *
	 * The cache enabled setting can only change via a settings form save,
	 * which ends the request. Within a single request the value is constant,
	 * so we resolve it once and reuse it — avoiding a repeated automator_get_option()
	 * call on every cache->get() invocation.
	 *
	 * @var bool|null  null = not yet resolved, bool = resolved value.
	 */
	private $cache_enabled = null;

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

		// Integration condition popularity cache management (always active).
		add_action( 'updated_post_meta', array( $this, 'handle_condition_meta_update' ), 10, 4 );
		add_action( 'added_post_meta', array( $this, 'handle_condition_meta_update' ), 10, 4 );
		add_action( 'deleted_post_meta', array( $this, 'handle_condition_meta_delete' ), 10, 4 );
		add_action( 'automator_recipe_status_updated', array( $this, 'handle_condition_status_update' ), 10, 4 );
		add_action( 'automator_cache_recipe_post_status_changed', array( $this, 'handle_condition_status_update_legacy' ), 10, 1 );

		// Integration directory reset must always run on plugin activation/deactivation.
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
			'upgrader_process_complete',
			array(
				$this,
				'upgrader_process_completed',
			),
			999,
			0
		);

		// Append the cache-flush sub-item to the Automator admin-bar menu owned by Automator_WP_Admin_Bar.
		// Priority 1010 ensures the parent node (registered at 999) already exists.
		add_action( 'admin_bar_menu', array( $this, 'add_flush_cache_admin_bar_node' ), 1010 );

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
	}

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
		if ( in_array( $post->post_type, \automator_get_recipe_post_types(), true ) ) {
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

		$automator_types = \automator_get_recipe_post_types();

		// If it's not an Automator post type, return.
		if ( ! in_array( $post->post_type, $automator_types, true ) ) {
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
		//      // Allow users to disable cache
		//      if ( false === (bool) $this->is_cache_enabled( $key ) ) {
		//          return;
		//      }

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
	public function get( $key, $group = 'automator', $force = false ) {
		// Allow users to disable cache
		if ( false === $force && false === (bool) $this->is_cache_enabled( $key ) ) {
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
		// Also clear the matching transient if one exists (e.g., trigger query cache).
		delete_transient( $key );
	}

	/**
	 *
	 */
	public function remove_all() {

		$this->remove( 'automator_integration_directories_loaded' );
		$this->remove( 'automator_get_all_integrations' );
		$this->remove( 'automator_actionified_triggers' );
		$this->remove( $this->recipes_data );
		$this->remove( 'get_recipe_type' );

		// Rebuild the recipe manifest (active codes cache for demand-driven loading).
		Recipe_Manifest::reset();

		automator_cache_delete_group( 'automator' );

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
			'automator_show_internal_admin_notice',
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
	 * Append the "Flush cache" sub-item to the Automator admin-bar menu when the object cache is enabled.
	 *
	 * The parent node ("automator-bar") is registered by Automator_WP_Admin_Bar at priority 999.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 *
	 * @return void
	 */
	public function add_flush_cache_admin_bar_node( $wp_admin_bar ) {

		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( ! current_user_can( automator_get_capability() ) ) {
			return;
		}

		if ( true === apply_filters( 'automator_hide_wp_admin_header_menu', false ) ) {
			return;
		}

		if ( false === (bool) $this->is_cache_enabled() ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'     => 'automator-clear-cache',
				'parent' => Automator_WP_Admin_Bar::PARENT_ID,
				'title'  => esc_html__( 'Flush cache', 'uncanny-automator' ),
				'group'  => false,
				'href'   => admin_url( 'admin.php?page=uncanny-automator-dashboard&automator_flush_all=true&_wpnonce=' ) . wp_create_nonce( AUTOMATOR_BASE_FILE ),
			)
		);
	}

	/**
	 */
	public function upgrader_process_completed() {
		$this->reset_integrations_directory( null, null );
		do_action( 'automator_cache_upgrader_process_completed' );
	}

	/**
	 * Whether the Automator object cache is enabled.
	 *
	 * Result is cached in $this->cache_enabled for the lifetime of the request.
	 * The setting can only change via a form save (new request), so resolving
	 * once per request is safe and avoids repeated automator_get_option() calls
	 * on every cache->get() invocation.
	 *
	 * @param string $key Optional — passed to the automator_disable_object_cache filter.
	 *
	 * @return bool
	 */
	public function is_cache_enabled( $key = '' ) {
		if ( null !== $this->cache_enabled ) {
			return $this->cache_enabled;
		}

		$value = automator_get_option( self::OPTION_NAME, '' );

		if ( '' === (string) $value ) {
			// Use filter to check if user has disabled object cache previously.
			// Once the value is saved, no need to run the filter, since it's redundant and inverse.
			// Since the filter is to 'Disable' the cache,
			// we need to inverse the value — true means cache is active.
			$value = ! apply_filters( 'automator_disable_object_cache', false, $key );
		}

		$this->cache_enabled = '0' === $value || false === $value ? false : true;

		return $this->cache_enabled;
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
			automator_update_option( self::OPTION_NAME, '0' );
			return '0';
		}

		automator_update_option( self::OPTION_NAME, '1' );
		return '1';
	}

	/**
	 * Handle condition popularity cache clearing on meta update.
	 *
	 * @param int $meta_id Meta ID
	 * @param int $post_id Post ID
	 * @param string $meta_key Meta key
	 * @param mixed $meta_value Meta value
	 *
	 * @return void
	 */
	public function handle_condition_meta_update( $meta_id, $post_id, $meta_key, $meta_value ) {
		$this->get_condition_popularity_tracker()
			->handle_meta_update( $meta_id, $post_id, $meta_key, $meta_value );
	}

	/**
	 * Handle condition popularity cache clearing on meta deletion.
	 *
	 * @param array $meta_ids Meta IDs
	 * @param int $post_id Post ID
	 * @param string $meta_key Meta key
	 * @param mixed $meta_value Meta value
	 *
	 * @return void
	 */
	public function handle_condition_meta_delete( $meta_ids, $post_id, $meta_key, $meta_value ) {
		$this->get_condition_popularity_tracker()
			->handle_meta_delete( $meta_ids, $post_id, $meta_key, $meta_value );
	}

	/**
	 * Handle condition popularity cache clearing on recipe status change.
	 *
	 * @param int $post_id Post ID
	 * @param int $recipe_id Recipe ID
	 * @param string $post_status Post status
	 * @param array $return_data Return data
	 *
	 * @return void
	 */
	public function handle_condition_status_update( $post_id, $recipe_id, $post_status, $return_data ) {
		$this->get_condition_popularity_tracker()
			->handle_status_change( $post_id, $recipe_id, $post_status );
	}

	/**
	 * Handle condition popularity cache clearing on recipe status change (legacy hook).
	 *
	 * @param array $args Arguments array: [new_status, old_status, post]
	 *
	 * @return void
	 */
	public function handle_condition_status_update_legacy( $args ) {

		if ( empty( $args ) || count( $args ) < 3 ) {
			return;
		}

		$new_status = $args[0];
		$post       = $args[2];

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		if ( AUTOMATOR_POST_TYPE_RECIPE !== $post->post_type ) {
			return;
		}

		$this->get_condition_popularity_tracker()
			->handle_status_change( $post->ID, $post->ID, $new_status );
	}

	/**
	 * Get the condition popularity tracker instance.
	 *
	 * @return \Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Popularity\Filter_Condition_Popularity_Tracker
	 */
	private function get_condition_popularity_tracker() {
		return \Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Popularity\Filter_Condition_Popularity_Tracker::get_instance();
	}
}