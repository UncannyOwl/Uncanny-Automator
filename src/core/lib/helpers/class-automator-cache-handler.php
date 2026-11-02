<?php

namespace Uncanny_Automator;

use Uncanny_Automator\App\Presentation\Admin\Cache_Flushed_Notice;
use Uncanny_Automator\App\Transient\Application\Delete_Core_Transients;
use Uncanny_Automator\App\Transient\Domain\Core_Transient_Keys;
use Uncanny_Automator\App\Transient\Domain\License_Transient_Keys;
use Uncanny_Automator\App\Transient\Infrastructure\WP_Transient_Adapter;

/**
 * Class Automator_Cache_Handler
 *
 * @package Uncanny_Automator
 */
class Automator_Cache_Handler {

	/**
	 * Options whose value identifies the active Automator license/account.
	 *
	 * Pro also writes these options through Automator's option layer. Listening
	 * here covers normal, Setup Wizard, background, and multisite activation
	 * without making Pro depend on a new Free-plugin class.
	 *
	 * @var string[]
	 */
	private const LICENSE_IDENTITY_OPTIONS = array(
		'uap_automator_pro_license_key',
		'uap_automator_pro_license_status',
		'uap_automator_free_license_key',
		'uap_automator_free_license_status',
	);

	/**
	 * Settings option key for the Automator object-cache toggle.
	 */
	const OPTION_NAME = 'uncanny_automator_advanced_automator_cache';

	/**
	 * Network-wide cache group that holds the version of the builder's user list.
	 *
	 * Every site lists the users of the shared users table, so one change of a user
	 * outdates every site's copy. Each site caches its copy under a key that carries
	 * this version, and one change of the version clears them all.
	 *
	 * @since 8.0.0
	 */
	const USERS_LIST_VERSION_GROUP = 'automator_users';
	/**
	 * Shared singleton instance.
	 *
	 * @var Automator_Cache_Handler
	 */
	public static $instance;
	/**
	 * Default cache TTL in seconds (filterable).
	 *
	 * @var mixed|void
	 */
	public $expires;
	/**
	 * Long cache TTL in seconds (filterable, 24h default).
	 *
	 * @var mixed|void
	 */
	public $long_expires;
	/**
	 * Cache key for the recipes data blob.
	 *
	 * @var string
	 */
	public $recipes_data = 'automator_recipes_data';
	/**
	 * Cache key for the recipes list.
	 *
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
	 * Automator Free core transient cleanup use case.
	 *
	 * @var Delete_Core_Transients
	 */
	private $delete_core_transients;

	/**
	 * License evidence cleanup use case.
	 *
	 * @var Delete_Core_Transients
	 */
	private $delete_license_transients_use_case;

	/**
	 * Cache_Handler constructor.
	 */
	public function __construct() {
		// The user list's version is shared by every site of the network.
		wp_cache_add_global_groups( array( self::USERS_LIST_VERSION_GROUP ) );

		// Load the cleanup classes before a plugin update can replace their files.
		$transients = new WP_Transient_Adapter();

		$this->delete_core_transients = new Delete_Core_Transients(
			$transients,
			Core_Transient_Keys::get_all()
		);

		$this->delete_license_transients_use_case = new Delete_Core_Transients(
			$transients,
			License_Transient_Keys::get_all()
		);

		$this->register_license_transient_invalidation();

		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action(
			'automator_settings_advanced_tab_view_automator_cache',
			array(
				$this,
				'settings_output',
			)
		);

		// Entity popularity cache invalidation (always active — independent of object cache setting).
		// Refresh = mark stale + rebuild on this request's shutdown; stored counts keep serving.
		foreach ( array_keys( $this->entity_popularity_scope_map() ) as $entity_post_type ) {
			add_action( 'save_post_' . $entity_post_type, array( $this, 'refresh_entity_popularity_scope_for_post' ), 10, 2 );
		}
		add_action( 'deleted_post', array( $this, 'refresh_entity_popularity_cache_on_delete' ), 10, 2 );
		add_action( 'transition_post_status', array( $this, 'refresh_entity_popularity_cache_on_recipe_status_change' ), 10, 3 );

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
				'plugin_deactivated',
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
			2
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
			'profile_update',
			array(
				$this,
				'maybe_clear_user_cache',
			),
			99999
		);
		add_action(
			'deleted_user',
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

		add_action( 'admin_init', array( $this, 'remove_all_cache' ) );
	}

	/**
	 * Register invalidation for persisted license identity transitions.
	 *
	 * This listener is intentional. Pro can activate through its settings form,
	 * Setup Wizard, background activation, or multisite activation. Duplicating
	 * delete_transient() calls in those controllers would leave several paths to
	 * drift. Every supported path converges on these custom option writes, so one
	 * listener deletes one canonical license-evidence group for both Free and Pro.
	 *
	 * Automator licenses live in the custom uap_options table, so WordPress's
	 * update_option_* hooks never fire. The generic Automator option events are
	 * used instead of per-option events so another listener cannot remove this
	 * callback while managing its own license-status callback. The handler then
	 * accepts only the four identity options above.
	 *
	 * Free disconnect is the one exception: automator_delete_option() emits no
	 * equivalent hook, so Admin_Menu calls delete_license_transients() explicitly
	 * after a successful disconnect.
	 *
	 * @return void
	 */
	private function register_license_transient_invalidation() {
		add_action( 'automator_option_added', array( $this, 'maybe_delete_license_transients' ), 10, 2 );
		add_action( 'automator_updated_option', array( $this, 'maybe_delete_license_transients' ), 10, 2 );
	}

	/**
	 * Delete license evidence only when an identity option changed.
	 *
	 * @param mixed $option Option name supplied by the Automator option event.
	 *
	 * @return void
	 */
	public function maybe_delete_license_transients( $option ) {
		if ( ! is_string( $option ) || ! in_array( $option, self::LICENSE_IDENTITY_OPTIONS, true ) ) {
			return;
		}

		$this->delete_license_transients();
	}

	/**
	 * Delete evidence tied to the previous license/account identity.
	 *
	 * This intentionally uses the narrow license group. A license transition
	 * must not evict unrelated recipe, integration, dashboard, or diagnostic
	 * transients.
	 *
	 * @return void
	 */
	public function delete_license_transients() {
		$this->delete_license_transients_use_case->execute();
	}

	/**
	 * Return the shared instance, constructing it on first use.
	 *
	 * @return Automator_Cache_Handler
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Clear the per-post-type transient when a non-Automator post is created.
	 *
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
	 * Clear the recipes-data caches when an Automator post type is saved.
	 *
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
		$this->clear_recipe_data_of( $post );
		if ( $post_before instanceof \WP_Post && (int) $post_before->post_parent !== (int) $post->post_parent ) {
			$this->clear_recipe_data_of( $post_before );
		}
		do_action( 'automator_cache_maybe_clear_cache_for_recipes', $post_id, $post, $update, $post_before );
	}

	/**
	 * The key every site caches the builder's user list under.
	 *
	 * It carries the network-wide version, so a cleared list is never served
	 * again on any site of the network.
	 *
	 * @since 8.0.0
	 *
	 * @return string
	 */
	public function users_list_key() {
		return 'automator_transient_users:' . wp_cache_get_last_changed( self::USERS_LIST_VERSION_GROUP );
	}

	/**
	 * Clear every site's user list when a user registers, is updated or is deleted.
	 *
	 * Dropping the version moves every site to a new key, so each site
	 * queries its list again on its next read.
	 */
	public function maybe_clear_user_cache() {
		wp_cache_delete( 'last_changed', self::USERS_LIST_VERSION_GROUP );
		do_action( 'automator_cache_maybe_clear_user_cache' );
	}

	/**
	 * Clear the completed recipe's data caches after a run finishes.
	 *
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
	 * Clear the integration-directory caches on plugin (de)activation.
	 *
	 * @param $plugin
	 * @param $network_wide
	 */
	public function reset_integrations_directory( $plugin, $network_wide ) {
		$this->remove( 'automator_integration_directories_loaded' );
		$this->remove( 'automator_get_all_integrations' );
		$this->remove( 'automator_actionified_triggers' );
		automator_clear_integration_discovery_cache();
		do_action( 'automator_cache_reset_integrations_directory', $plugin, $network_wide );
	}

	/**
	 * Reset integration caches after plugin deactivation.
	 *
	 * @param string $plugin       The deactivated plugin file.
	 * @param bool   $network_wide Whether WordPress deactivated the plugin for the network.
	 *
	 * @return void
	 */
	public function plugin_deactivated( $plugin, $network_wide ) {
		$this->reset_integrations_directory( $plugin, $network_wide );

		if ( $this->is_automator_free_plugin( $plugin ) ) {
			$this->delete_core_automator_transients();
		}
	}

	/**
	 * Clear the caches tied to one recipe and the shared recipes-data blob.
	 *
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
	 * Clear recipe caches when a recipe part changes status.
	 *
	 * Only Automator's recipe post types count: a status change on any other post (a published
	 * blog post, the revision WordPress saves on every update, a trashed page) leaves the recipe
	 * data and the 24-hour integration caches in place.
	 *
	 * @param mixed ...$args
	 */
	public function recipe_post_status_changed( ...$args ) {
		$new_status = $args[0];
		$old_status = $args[1];
		$post       = $args[2];
		if ( ! $post instanceof \WP_Post || ! $this->is_recipe_part( $post ) ) {
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
		$this->clear_recipe_data_of( $post );

		do_action( 'automator_cache_recipe_post_status_changed', $args );
	}

	/**
	 * Clear recipe caches when a recipe part is deleted.
	 *
	 * Deleting any other post leaves the recipe data in place.
	 *
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

		if ( ! $post instanceof \WP_Post || ! $this->is_recipe_part( $post ) ) {
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
		$this->clear_recipe_data_of( $post );
		do_action( 'automator_cache_recipe_post_deleted', $args );
	}

	/**
	 * Clear the per-recipe data cache of the recipe a post belongs to.
	 *
	 * The post is the recipe itself, or a trigger, action, loop, loop filter, block or block modifier
	 * somewhere under it. Any other post leaves the cache alone.
	 *
	 * @since 8.0.0
	 *
	 * @param \WP_Post $post The saved, changed or deleted post.
	 *
	 * @return void
	 */
	private function clear_recipe_data_of( \WP_Post $post ) {

		if ( ! in_array( $post->post_type, \automator_get_recipe_post_types(), true ) ) {
			return;
		}

		$recipe_id = $this->owning_recipe_id( $post );

		if ( 0 < $recipe_id ) {
			$this->remove( 'automator_recipe_data_of_' . $recipe_id );
		}
	}

	/**
	 * The recipe a recipe post belongs to: itself, or the nearest recipe above it.
	 *
	 * @since 8.0.0
	 *
	 * @param \WP_Post $post A recipe, or a part of one.
	 *
	 * @return int The recipe ID, or 0 when no recipe holds the post.
	 */
	private function owning_recipe_id( \WP_Post $post ) {

		if ( AUTOMATOR_POST_TYPE_RECIPE === $post->post_type ) {
			return (int) $post->ID;
		}

		foreach ( get_post_ancestors( $post ) as $ancestor_id ) {
			if ( AUTOMATOR_POST_TYPE_RECIPE === get_post_type( $ancestor_id ) ) {
				return (int) $ancestor_id;
			}
		}

		return 0;
	}

	/**
	 * Whether the post is one of Automator's recipe post types, as the save-time clear decides.
	 *
	 * @since 8.0.0
	 *
	 * @param \WP_Post $post The post that changed.
	 *
	 * @return bool
	 */
	private function is_recipe_part( \WP_Post $post ) {
		return in_array( $post->post_type, \automator_get_recipe_post_types(), true );
	}

	/**
	 * Write a value to the object cache with the default or supplied TTL.
	 *
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
	 * Read a value from the object cache; empty array when caching is disabled.
	 *
	 * @param string $key
	 * @param string $group
	 * @param bool   $force Read even when the cache setting is disabled.
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
	 * Delete a value from the object cache and its matching transient.
	 *
	 * @param string $key
	 * @param string $group
	 */
	public function remove( $key, $group = 'automator' ) {
		wp_cache_delete( $key, $group );
		// Also clear the matching transient if one exists (e.g., trigger query cache).
		delete_transient( $key );
	}

	/**
	 * Flush every Automator cache surface and rebuild the recipe manifest.
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
		automator_clear_integration_discovery_cache();
		automator_clear_integration_feed_cache();

		do_action( 'automator_cache_remove_all' );
	}

	/**
	 * Handle the nonce-guarded admin-bar "Flush cache" request.
	 */
	public function remove_all_cache() {
		if ( ! automator_filter_has_var( 'automator_flush_all' ) ) {
			return;
		}
		if ( ! wp_verify_nonce( automator_filter_input( '_wpnonce' ), AUTOMATOR_BASE_FILE ) ) {
			return;
		}
		$this->remove_all();

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- The hook name is a class constant that starts with the automator_ prefix.
		do_action( Cache_Flushed_Notice::FLUSHED_ACTION );
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

		if ( ! current_user_can( automator_get_capability() ) ) { // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- The capability comes from automator_get_capability().
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
	 * Reset integration-directory caches after a plugin/theme/core update.
	 */
	public function upgrader_process_completed( $upgrader = null, $options = array() ) {
		$this->reset_integrations_directory( null, null );

		foreach ( $this->resolve_updated_plugins( $upgrader, $options ) as $plugin ) {
			if ( $this->is_automator_free_plugin( $plugin ) ) {
				$this->delete_core_automator_transients();
				break;
			}
		}

		do_action( 'automator_cache_upgrader_process_completed' );
	}

	/**
	 * Get plugin files from a WordPress upgrader event.
	 *
	 * WordPress provides different data for bulk updates, single updates, and
	 * uploaded ZIP replacements. Normalize those forms into one plugin list.
	 *
	 * @param mixed $upgrader The WordPress upgrader instance.
	 * @param array $options  The upgrader hook data.
	 *
	 * @return string[]
	 */
	private function resolve_updated_plugins( $upgrader, $options ) {
		if ( 'plugin' !== ( $options['type'] ?? '' ) ) {
			return array();
		}

		if ( ! in_array( $options['action'] ?? '', array( 'install', 'update' ), true ) ) {
			return array();
		}

		if ( ! empty( $options['plugins'] ) && is_array( $options['plugins'] ) ) {
			return array_values( $options['plugins'] );
		}

		if ( ! empty( $options['plugin'] ) && is_string( $options['plugin'] ) ) {
			return array( $options['plugin'] );
		}

		if ( is_object( $upgrader ) && method_exists( $upgrader, 'plugin_info' ) ) {
			$plugin = $upgrader->plugin_info();

			if ( is_string( $plugin ) && '' !== $plugin ) {
				return array( $plugin );
			}
		}

		return array();
	}

	/**
	 * Determine whether a plugin is Automator Free.
	 *
	 * @param mixed $plugin The plugin file.
	 *
	 * @return bool
	 */
	private function is_automator_free_plugin( $plugin ) {
		if ( ! is_string( $plugin ) ) {
			return false;
		}

		return plugin_basename( AUTOMATOR_BASE_FILE ) === str_replace( '\\', '/', $plugin );
	}

	/**
	 * Delete Automator Free core transients.
	 *
	 * @return void
	 */
	private function delete_core_automator_transients() {
		// TODO: Add deferred network-wide cleanup for multisite lifecycle events.
		$this->delete_core_transients->execute();
		// Core_Transient_Keys drops the English feed copy; the language copies go with it.
		automator_clear_integration_feed_cache();
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
	 * Register the object-cache toggle with the WordPress settings API.
	 *
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
	 * Render the object-cache toggle on the advanced settings tab.
	 *
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
	 * Sanitize the cache-toggle setting and flush everything when it flips off.
	 *
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
	 * Entity post type => the popularity (kind, scope) bucket its saves affect.
	 *
	 * Drives both the save_post_{type} hook registration and the per-type
	 * targeted clears, so the two surfaces cannot drift apart.
	 *
	 * A null scope means every scope in that kind. `uo-block-modifier` carries
	 * both modifier discriminators — a loop filter is a modifier whose parent
	 * block is a loop — and the discriminator lives in post meta owned by
	 * src/app, which this layer must not read. Refreshing the whole kind keeps
	 * the seam clean; the cost is one extra aggregate on the shutdown rebuild,
	 * against counts going stale if the wrong scope is picked.
	 *
	 * The retired `uo-loop-filter` post type is deliberately absent: the loop
	 * fold-in renamed every row to `uo-block-modifier`, so its save hook can
	 * never fire again. The `loop_filter` SCOPE is still live and still
	 * counted — only the post type died.
	 *
	 * @return array<string,array{0:string,1:string|null}>
	 */
	private function entity_popularity_scope_map() {
		return array(
			AUTOMATOR_POST_TYPE_TRIGGER        => array( 'item', 'trigger' ),
			AUTOMATOR_POST_TYPE_ACTION         => array( 'item', 'action' ),
			AUTOMATOR_POST_TYPE_BLOCK_MODIFIER => array( 'block_modifier', null ),
		);
	}

	/**
	 * Refresh only the popularity bucket(s) the saved entity belongs to —
	 * an item save cannot change any modifier count and vice versa, and
	 * recipe-builder sessions fire these hooks constantly (reorder saves
	 * every child).
	 *
	 * @param int           $post_id Saved post id (unused; the hook supplies it).
	 * @param \WP_Post|null $post    Saved post.
	 * @return void
	 */
	public function refresh_entity_popularity_scope_for_post( $post_id, $post = null ) {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$map = $this->entity_popularity_scope_map();
		if ( ! isset( $map[ $post->post_type ] ) ) {
			return;
		}

		automator_refresh_entity_popularity_cache( $map[ $post->post_type ][0], $map[ $post->post_type ][1] );
	}

	/**
	 * Refresh popularity caches on deletion: the matching bucket for an entity
	 * post, everything for a recipe (the census counts by recipe, so a recipe
	 * delete changes every count under it).
	 *
	 * Reads the post type from the WP_Post object `deleted_post` passes. The
	 * row is already gone by this point, so there is nothing to look up if it
	 * is absent — `deleted_post` has passed the object since WP 5.5 and the
	 * plugin requires 5.8, so it never is.
	 *
	 * @param int           $post_id Deleted post id.
	 * @param \WP_Post|null $post    Deleted post object.
	 * @return void
	 */
	public function refresh_entity_popularity_cache_on_delete( $post_id, $post = null ) {
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$post_type = $post->post_type;

		if ( AUTOMATOR_POST_TYPE_RECIPE === $post_type ) {
			automator_refresh_entity_popularity_cache();
			return;
		}

		$map = $this->entity_popularity_scope_map();
		if ( ! is_string( $post_type ) || ! isset( $map[ $post_type ] ) ) {
			return;
		}

		automator_refresh_entity_popularity_cache( $map[ $post_type ][0], $map[ $post_type ][1] );
	}

	/**
	 * Refresh the stored usage census on any recipe status change. The census
	 * counts draft recipes as well as published ones and leaves trashed ones
	 * out, so every transition can move a count, but a status flip saves only
	 * the recipe post, so the save_post_{entity} hooks never fire.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       Post being transitioned.
	 * @return void
	 */
	public function refresh_entity_popularity_cache_on_recipe_status_change( $new_status, $old_status, $post ) {
		if ( ! $post instanceof \WP_Post || AUTOMATOR_POST_TYPE_RECIPE !== $post->post_type ) {
			return;
		}
		if ( $new_status === $old_status ) {
			return;
		}

		automator_refresh_entity_popularity_cache();
	}
}
