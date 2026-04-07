<?php
/**
 * Recipe Manifest
 *
 * Maintains a cached manifest of which recipe part codes are active in published recipes.
 * Used by the demand-driven integration loading system to skip unused triggers/actions.
 *
 * The manifest is rebuilt via WordPress hooks (transition_post_status, before_delete_post)
 * and stored in uap_options. Zero queries at runtime — just isset() lookups.
 *
 * @package Uncanny_Automator
 * @since   7.2
 */

namespace Uncanny_Automator;

class Recipe_Manifest {

	const OPTION_KEY = 'automator_recipe_manifest';

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Cached manifest: composite_key => integration_code.
	 *
	 * @var array|null
	 */
	private $manifest = null;

	/**
	 * O(1) lookup set for active integrations (integration_code => true).
	 *
	 * @var array|null
	 */
	private $active_integrations = null;

	/**
	 * Cached result of should_load_all().
	 *
	 * @var bool|null
	 */
	private $should_load_all = null;

	/**
	 * Merged Free + Pro item map (lazy-loaded).
	 *
	 * @var array|null
	 */
	private $item_map = null;

	/**
	 * Directory name → integration code mapping (lazy-loaded from item map).
	 *
	 * @var array|null
	 */
	private $directory_code_map = null;

	/**
	 * Re-entrancy guard for rebuild.
	 *
	 * @var bool
	 */
	private $is_rebuilding = false;

	/**
	 * Debug: which condition triggered should_load_all().
	 *
	 * @var string
	 */
	public $load_all_reason = '';

	/**
	 * Whether the manifest existed in the DB before this request.
	 * Used as a safety net for first-deploy scenarios.
	 *
	 * @var bool|null
	 */
	private $manifest_existed = null;

	/**
	 * @return self
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register WordPress hooks for event-driven manifest updates.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'transition_post_status', array( $this, 'on_status_change' ), 10, 3 );
		add_action( 'before_delete_post', array( $this, 'on_post_deleted' ), 10, 2 );
		add_action( 'activated_plugin', array( $this, 'on_plugin_change' ) );
		add_action( 'deactivated_plugin', array( $this, 'on_plugin_change' ) );

		// Trigger/action meta saved via the recipe editor REST API.
		add_action( 'automator_recipe_option_updated', array( $this, 'on_recipe_option_updated' ) );
		// Recipe status change via the recipe editor REST API.
		add_action( 'automator_recipe_status_updated', array( $this, 'on_recipe_option_updated' ) );
	}

	/**
	 * Get the manifest. On first-ever load or after reset, does a one-time full build.
	 *
	 * @return array Composite key => integration code.
	 */
	public function get() {

		if ( null !== $this->manifest ) {
			return $this->manifest;
		}

		if ( ! $this->should_use_db() ) {
			$this->manifest         = $this->full_rebuild();
			$this->manifest_existed = true;
			$this->build_integration_lookup();
			return $this->manifest;
		}

		$raw                    = automator_get_option( self::OPTION_KEY, false );
		$data                   = $this->unwrap( $raw );
		$this->manifest_existed = ( null !== $data );
		$this->manifest         = ( null !== $data ) ? $data : $this->full_rebuild();

		if ( null === $data ) {
			automator_update_option( self::OPTION_KEY, $this->wrap( $this->manifest ) );
		}

		$this->build_integration_lookup();

		return $this->manifest;
	}

	/**
	 * Whether to persist the manifest to the DB.
	 *
	 * @return bool True = use DB (default). False = rebuild every request.
	 */
	private function should_use_db() {
		return (bool) apply_filters( 'automator_manifest_use_db', true );
	}

	/**
	 * Build secondary O(1) lookup set for active integrations.
	 *
	 * @return void
	 */
	private function build_integration_lookup() {
		$this->active_integrations = array_flip( array_unique( array_values( $this->manifest ) ) );
	}

	/**
	 * Is this specific composite key used in a published recipe?
	 *
	 * @param string $composite_key INTEGRATION_CODE format (e.g. WC_WCPURCHPROD).
	 *
	 * @return bool
	 */
	public function is_code_active( $composite_key ) {
		$manifest = $this->get();
		return isset( $manifest[ $composite_key ] );
	}

	/**
	 * Is this integration code needed by any published recipe?
	 *
	 * @param string $integration_code e.g. WC, LD, SLACK.
	 *
	 * @return bool
	 */
	public function is_integration_needed( $integration_code ) {
		$this->get();
		return isset( $this->active_integrations[ $integration_code ] );
	}

	/**
	 * Invalidate the cached item map so the next get_item_map() call
	 * re-includes the file and re-applies filters (picking up Pro/addon maps).
	 *
	 * @return void
	 */
	public function invalidate_item_map() {
		$this->item_map           = null;
		$this->directory_code_map = null;
	}

	/**
	 * Load and merge Free + Pro item maps.
	 *
	 * @return array
	 */
	public function get_item_map() {

		if ( null !== $this->item_map ) {
			return $this->item_map;
		}

		$map_file       = UA_ABSPATH . 'vendor/composer/autoload_item_map.php';
		$this->item_map = file_exists( $map_file ) ? include $map_file : array();
		$this->item_map = apply_filters( 'automator_item_map', $this->item_map );

		return $this->item_map;
	}

	/**
	 * Build a directory name → integration code mapping from the item map.
	 *
	 * @return array Directory name => integration code (e.g. 'woocommerce' => 'WC').
	 */
	public function get_directory_code_map() {

		if ( null !== $this->directory_code_map ) {
			return $this->directory_code_map;
		}

		$this->directory_code_map = array();

		foreach ( $this->get_item_map() as $integration_code => $types ) {
			$dir = $this->extract_directory_from_types( $types );
			if ( null !== $dir ) {
				$this->directory_code_map[ $dir ] = $integration_code;
			}
		}

		return $this->directory_code_map;
	}

	/**
	 * Extract directory name from the first valid file path in a type group.
	 *
	 * @param array $types Integration type arrays (triggers, actions, etc.).
	 *
	 * @return string|null Directory name or null if not found.
	 */
	private function extract_directory_from_types( $types ) {
		foreach ( $types as $items ) {
			if ( empty( $items ) || ! is_array( $items ) ) {
				continue;
			}
			$first_item = reset( $items );
			if ( ! empty( $first_item['file'] ) && preg_match( '#[/\\\\]integrations[/\\\\]([^/\\\\]+)[/\\\\]#', $first_item['file'], $matches ) ) {
				return $matches[1];
			}
		}
		return null;
	}

	/**
	 * Should we load everything? (recipe edit page, escape hatches).
	 *
	 * Each check is a focused private method, making it trivial to add new
	 * contexts (e.g. uo-delay editor, WP-CLI commands) without growing
	 * cyclomatic complexity.
	 *
	 * @return bool
	 */
	public function should_load_all() {

		if ( null !== $this->should_load_all ) {
			return $this->should_load_all;
		}

		// Evaluate each condition to track which one triggered full load.
		$checks = array(
			'escape_hatch'         => $this->is_escape_hatch_enabled(),
			'first_deploy'         => $this->is_first_deploy(),
			'recipe_edit_page'     => $this->is_recipe_edit_page(),
			'premium_integrations' => $this->is_premium_integrations_page(),
			'options_php_submit'   => $this->is_automator_options_php_submit(),
			'automator_ajax'       => $this->is_automator_ajax(),
			'automator_rest'       => $this->is_automator_rest(),
			'mcp_rest'             => $this->is_mcp_rest(),
		);

		$this->should_load_all = false;
		foreach ( $checks as $reason => $result ) {
			if ( $result ) {
				$this->should_load_all = true;
				$this->load_all_reason = $reason;
				break;
			}
		}

		if ( ! $this->should_load_all ) {
			$this->load_all_reason = 'none (demand-driven)';
		}

		return $this->should_load_all;
	}

	/**
	 * Constant or filter escape hatch.
	 *
	 * @return bool
	 */
	private function is_escape_hatch_enabled() {
		return ( defined( 'AUTOMATOR_LOAD_ALL_INTEGRATIONS' ) && AUTOMATOR_LOAD_ALL_INTEGRATIONS )
			|| apply_filters( 'automator_should_load_all_integrations', false );
	}

	/**
	 * First deploy OR schema version bump — manifest didn't exist (or was stale)
	 * before this request. A stale envelope (legacy flat shape, missing _v, or
	 * mismatched AUTOMATOR_MANIFEST_SCHEMA_VERSION) is treated as "didn't exist"
	 * by unwrap(), which forces a full_rebuild() and flips this to true. That in
	 * turn triggers should_load_all() so every integration is instantiated for
	 * the schema cache rebuild on the same request.
	 *
	 * @return bool
	 */
	private function is_first_deploy() {
		if ( null === $this->manifest_existed ) {
			$this->get();
		}
		return false === $this->manifest_existed;
	}

	/**
	 * Recipe editor page — load everything for the picker.
	 *
	 * @return bool
	 */
	private function is_recipe_edit_page() {
		return Automator()->helpers->recipe->is_edit_page();
	}

	/**
	 * Premium-integrations config page — needs full load for the app connection UI.
	 *
	 * Only this specific tab requires all integrations. Other Automator admin
	 * pages (dashboard, logs, admin-tools, settings) stay demand-driven.
	 *
	 * @return bool
	 */
	private function is_premium_integrations_page() {

		if ( ! is_admin() ) {
			return false;
		}

		// Nonce verification intentionally skipped — routing decision during bootstrap.
		$page = \automator_request_input( 'page' );
		$tab  = \automator_request_input( 'tab' );

		return 'uncanny-automator-config' === $page && 'premium-integrations' === $tab;
	}

	/**
	 * WordPress options.php processing an Automator settings form.
	 *
	 * Premium integration settings forms POST to options.php (standard WP settings API).
	 * The option_page hidden field contains the settings group ID (e.g. uncanny_automator_ontraport).
	 * Without full load, register_setting() never fires and WP rejects the submission.
	 *
	 * @return bool
	 */
	private function is_automator_options_php_submit() {

		if ( ! is_admin() ) {
			return false;
		}

		global $pagenow;

		if ( 'options.php' !== $pagenow ) {
			return false;
		}

		$option_page = automator_filter_input( 'option_page', INPUT_POST );

		return 0 === strpos( $option_page, 'uncanny_automator_' );
	}

	/**
	 * Automator-specific AJAX (automator* or uap_* prefixed actions).
	 *
	 * Generic AJAX (WooCommerce cart, form submissions) stays demand-driven.
	 *
	 * @return bool
	 */
	private function is_automator_ajax() {
		if ( ! wp_doing_ajax() ) {
			return false;
		}

		// Nonce verification intentionally skipped — routing decision during bootstrap.
		$action = \automator_request_input( 'action' );

		// Standard Automator AJAX actions (automator_*, uap_*).
		if ( '' !== $action && ( 0 === strpos( $action, 'automator' ) || 0 === strpos( $action, 'uap_' ) ) ) {
			return true;
		}

		// Legacy integration AJAX actions that predate the automator_/uap_ naming convention.
		// These are registered by integrations like MailChimp, Active Campaign, LearnDash, etc.
		// Filterable so Pro and addons can register their own legacy prefixes.
		// @todo Remove once all integrations are migrated to automator_* naming convention.
		$legacy_prefixes = apply_filters(
			'automator_legacy_ajax_action_prefixes',
			array(
				'select_',              // Field chaining: select_mcgroupslist_from_mclist, select_form_fields_*, etc.
				'get_',                 // Data fetch: get_mailchimp_audience_fields, get_event_meeting_*, get_all_*, etc.
				'active-campaign-',     // Active Campaign settings.
				'mailchimp-',           // MailChimp settings: mailchimp-regenerate-webhook-key.
				'whatsapp-',            // WhatsApp settings: whatsapp-regenerate-webhook-key.
				'gtt_',                 // GoToTraining: gtt_disconnect.
				'gtw_',                 // GoToWebinar: gtw_disconnect.
				'helpscout_',           // Help Scout: helpscout_fetch_conversations.
				'ua_',                  // Legacy UA prefix: ua_facebook_group_list_groups.
				'uo_',                  // Legacy UO prefix: uo_mailchimp_disconnect.
				'lifter_lms_',          // LifterLMS: lifter_lms_retrieve_*.
				'ameliabooking_',       // Amelia: ameliabooking_service_category_endpoint.
				'facebook_lead',        // Facebook Lead Ads.
				'webhook_url_',         // Pro webhooks: webhook_url_get_webhook_url.
				'get_samples_',         // Pro webhooks: get_samples_get_webhook_url.
				'cancel_async_run',     // Pro async actions.
				'auto_plugin_install',  // Pro auto-installer.
				'recipe-triggers',      // Activity log.
				'recipe-actions',       // Activity log.
				'prune_logs',           // Log pruning.
				'retrieve_',            // Pro loop filters: retrieve_post_types, retrieve_taxonomies, etc.
			)
		);

		foreach ( $legacy_prefixes as $prefix ) {
			if ( 0 === strpos( $action, $prefix ) ) {
				return true;
			}
		}

		// Recipe editor AJAX — integration-specific actions (e.g. retrieve_fields_from_form_id)
		// always include both recipe_id AND item_id. Full load required so setup() hooks are
		// available. Requires both to avoid false-positives from other plugins that use
		// generic parameter names like item_id (e.g. WooCommerce order item operations).
		//
		// Source of truth: field/index.js → getAjaxRequestData() always sends both.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_REQUEST['recipe_id'] ) && ! empty( $_REQUEST['item_id'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Automator REST API requests (excluding webhook endpoints).
	 *
	 * @return bool
	 */
	private function is_automator_rest() {
		return \is_automator_rest_request() && ! $this->is_webhook_rest_request();
	}

	/**
	 * Whether the current request targets the MCP REST API endpoint.
	 *
	 * @return bool
	 */
	private function is_mcp_rest() {
		return \is_mcp_rest_request();
	}

	// ──────────────────────────────────────────────
	// Event handlers — keep manifest in sync
	// ──────────────────────────────────────────────

	/**
	 * When a recipe or recipe part changes status.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       Post object.
	 *
	 * @return void
	 */
	public function on_status_change( $new_status, $old_status, $post ) {
		$this->maybe_rebuild( $post );
	}

	/**
	 * When a recipe part is deleted.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 *
	 * @return void
	 */
	public function on_post_deleted( $post_id, $post ) {
		$this->maybe_rebuild( $post );
	}

	/**
	 * Rebuild manifest if the post is a relevant recipe part type.
	 *
	 * Guards against re-entrancy and non-WP_Post objects.
	 *
	 * @param mixed $post The post object (or non-object from malformed hooks).
	 *
	 * @return void
	 */
	private function maybe_rebuild( $post ) {

		if ( $this->is_rebuilding || ! $post instanceof \WP_Post ) {
			return;
		}

		if ( ! in_array( $post->post_type, \automator_get_recipe_post_types(), true ) ) {
			return;
		}

		$this->is_rebuilding = true;
		$this->manifest      = $this->full_rebuild();
		$this->save( $this->manifest );
		$this->is_rebuilding = false;

		// Keep trigger index in sync with manifest.
		Trigger_Index::get_instance()->rebuild();
	}

	/**
	 * On recipe option or status update via the recipe editor REST API.
	 *
	 * Rebuilds both manifest and trigger index so meta changes
	 * (e.g. changing a form selection) are reflected immediately.
	 *
	 * @return void
	 */
	public function on_recipe_option_updated() {

		if ( $this->is_rebuilding ) {
			return;
		}

		$this->is_rebuilding = true;
		$this->manifest      = $this->full_rebuild();
		$this->save( $this->manifest );
		$this->is_rebuilding = false;

		Trigger_Index::get_instance()->rebuild();
	}

	/**
	 * On plugin activation/deactivation — delete manifest, lazy rebuild on next request.
	 *
	 * @return void
	 */
	public function on_plugin_change() {
		automator_delete_option( self::OPTION_KEY );
		$this->manifest            = null;
		$this->active_integrations = null;
		$this->manifest_existed    = null;

		// Invalidate trigger index for lazy rebuild on next request.
		Trigger_Index::get_instance()->invalidate();
	}

	/**
	 * Check if the current REST request targets a webhook execution endpoint.
	 *
	 * @return bool
	 */
	private function is_webhook_rest_request() {
		return \automator_request_contains( AUTOMATOR_REST_API_END_POINT . '/webhook' );
	}

	// ──────────────────────────────────────────────
	// Internal
	// ──────────────────────────────────────────────

	/**
	 * Wrap manifest data in the versioned envelope before persisting.
	 *
	 * @param array $manifest Composite key => integration code.
	 *
	 * @return array
	 */
	private function wrap( array $manifest ) {
		return array(
			'version'    => (int) AUTOMATOR_MANIFEST_SCHEMA_VERSION,
			'built_at'   => time(),
			'data'       => $manifest,
		);
	}

	/**
	 * Unwrap the persisted envelope, returning the manifest data only if the
	 * stored schema version matches AUTOMATOR_MANIFEST_SCHEMA_VERSION. Returns
	 * null on any miss: option absent, legacy flat shape, missing version key,
	 * or stale version.
	 *
	 * Envelope shape:
	 *   [
	 *     'version'  => int,    // schema version sentinel
	 *     'built_at' => int,    // unix timestamp of last full_rebuild() — debug
	 *     'data'     => array,  // composite_key => integration_code
	 *   ]
	 *
	 * Treating stale/legacy shapes as a miss is the entire point — get() then
	 * triggers full_rebuild() and the next save writes the current envelope.
	 *
	 * @param mixed $raw Value as read from automator_get_option().
	 *
	 * @return array|null Manifest data, or null if cache is missing/stale.
	 */
	private function unwrap( $raw ) {

		if ( ! is_array( $raw ) || ! isset( $raw['version'], $raw['data'] ) ) {
			return null;
		}

		// Cast both sides to int before comparing. Strict comparison is correct in
		// principle (WP's maybe_unserialize preserves int types), but exotic object
		// cache backends, JSON-based persistence layers, or hand-edited options can
		// surface the version as a string. A type mismatch would silently bypass
		// the schema check and serve stale data — guard against it explicitly.
		if ( (int) AUTOMATOR_MANIFEST_SCHEMA_VERSION !== (int) $raw['version'] ) {
			return null;
		}

		return is_array( $raw['data'] ) ? $raw['data'] : null;
	}

	/**
	 * Save manifest to uap_options.
	 *
	 * @param array $manifest Composite key => integration code.
	 *
	 * @return void
	 */
	private function save( $manifest ) {
		$this->manifest = $manifest;
		$this->build_integration_lookup();
		automator_update_option( self::OPTION_KEY, $this->wrap( $manifest ) );
	}

	/**
	 * Force a complete reset (called on plugin version update or global cache flush).
	 *
	 * @return void
	 */
	public static function reset() {
		$instance = self::get_instance();
		Automator()->cache->remove( \Uncanny_Automator\Actionify_Triggers\Trigger_Query::CACHE_KEY );
		$instance->manifest = $instance->full_rebuild();
		$instance->save( $instance->manifest );

		// Rebuild trigger index alongside manifest.
		Trigger_Index::get_instance()->rebuild();
	}

	/**
	 * One-time full rebuild — queries DB for all active codes.
	 *
	 * Uses UNION because loop-filters are grandchildren of recipes:
	 * uo-recipe → uo-loop → uo-loop-filter (two hops, not one).
	 *
	 * @return array Composite key => integration code.
	 */
	private function full_rebuild() {

		global $wpdb;

		$direct_types = \automator_get_direct_recipe_child_types();
		$loop_types   = \automator_get_loop_child_types();

		// Build safe IN() placeholders for both type arrays.
		$direct_placeholders = implode( ', ', array_fill( 0, count( $direct_types ), '%s' ) );
		$loop_placeholders   = implode( ', ', array_fill( 0, count( $loop_types ), '%s' ) );

		// Flatten all values for $wpdb->prepare() in order of appearance.
		$prepare_values = array_merge(
			array( 'publish' ),       // part.post_status (query 1).
			$direct_types,            // part.post_type IN (...) (query 1).
			array(
				'publish',            // recipe.post_status (query 1).
				AUTOMATOR_POST_TYPE_RECIPE, // recipe.post_type (query 1).
				'publish',            // loop_child.post_status (query 2).
			),
			$loop_types,              // loop_child.post_type IN (...) (query 2).
			array(
				'publish',                  // loop_post.post_status (query 2).
				AUTOMATOR_POST_TYPE_LOOP,   // loop_post.post_type (query 2).
				'publish',            // recipe.post_status (query 2).
				AUTOMATOR_POST_TYPE_RECIPE, // recipe.post_type (query 2).
			)
		);

		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- dynamic IN() clauses built from array_fill('%s').
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT
					code_meta.meta_value AS item_code,
					int_meta.meta_value AS integration_code
				FROM {$wpdb->postmeta} code_meta
				INNER JOIN {$wpdb->postmeta} int_meta
					ON int_meta.post_id = code_meta.post_id
					AND int_meta.meta_key = 'integration'
				INNER JOIN {$wpdb->posts} part
					ON part.ID = code_meta.post_id
					AND part.post_status = %s
					AND part.post_type IN ({$direct_placeholders})
				INNER JOIN {$wpdb->posts} recipe
					ON recipe.ID = part.post_parent
					AND recipe.post_status = %s
					AND recipe.post_type = %s
				WHERE code_meta.meta_key = 'code'

				UNION

				SELECT DISTINCT
					code_meta.meta_value AS item_code,
					int_meta.meta_value AS integration_code
				FROM {$wpdb->postmeta} code_meta
				INNER JOIN {$wpdb->postmeta} int_meta
					ON int_meta.post_id = code_meta.post_id
					AND int_meta.meta_key = 'integration'
				INNER JOIN {$wpdb->posts} loop_child
					ON loop_child.ID = code_meta.post_id
					AND loop_child.post_status = %s
					AND loop_child.post_type IN ({$loop_placeholders})
				INNER JOIN {$wpdb->posts} loop_post
					ON loop_post.ID = loop_child.post_parent
					AND loop_post.post_status = %s
					AND loop_post.post_type = %s
				INNER JOIN {$wpdb->posts} recipe
					ON recipe.ID = loop_post.post_parent
					AND recipe.post_status = %s
					AND recipe.post_type = %s
				WHERE code_meta.meta_key = 'code'",
				...$prepare_values
			),
			OBJECT
		);
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		$manifest = $this->build_manifest_from_results( $results );

		$this->merge_conditions_into_manifest( $manifest );

		return $manifest;
	}

	/**
	 * Convert DB results to manifest array.
	 *
	 * @param array $results Query results with item_code and integration_code.
	 *
	 * @return array Composite key => integration code.
	 */
	private function build_manifest_from_results( $results ) {
		$manifest = array();
		foreach ( (array) $results as $row ) {
			if ( empty( $row->item_code ) || empty( $row->integration_code ) ) {
				continue;
			}
			$manifest[ $row->integration_code . '_' . $row->item_code ] = $row->integration_code;
		}
		return $manifest;
	}

	/**
	 * Parse actions_conditions JSON from published recipes and merge condition
	 * integration codes into the manifest.
	 *
	 * @param array $manifest Reference to the manifest array.
	 *
	 * @return void
	 */
	private function merge_conditions_into_manifest( &$manifest ) {

		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_value AS conditions_json
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} recipe
					ON recipe.ID = pm.post_id
					AND recipe.post_status = %s
					AND recipe.post_type = %s
				WHERE pm.meta_key = 'actions_conditions'
					AND pm.meta_value != ''",
				'publish',
				AUTOMATOR_POST_TYPE_RECIPE
			),
			OBJECT
		);

		foreach ( (array) $rows as $row ) {
			$this->extract_conditions_from_json( $row->conditions_json, $manifest );
		}
	}

	/**
	 * Parse a single conditions JSON string and merge into manifest.
	 *
	 * @param string $json     The JSON string from actions_conditions postmeta.
	 * @param array  $manifest Reference to the manifest array.
	 *
	 * @return void
	 */
	private function extract_conditions_from_json( $json, &$manifest ) {

		$groups = json_decode( $json, true );

		if ( ! is_array( $groups ) ) {
			return;
		}

		foreach ( $groups as $group ) {
			if ( empty( $group['conditions'] ) || ! is_array( $group['conditions'] ) ) {
				continue;
			}
			foreach ( $group['conditions'] as $condition ) {
				if ( empty( $condition['integration'] ) || empty( $condition['condition'] ) ) {
					continue;
				}
				$manifest[ $condition['integration'] . '_' . $condition['condition'] ] = $condition['integration'];
			}
		}
	}
}
