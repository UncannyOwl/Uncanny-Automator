<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Database\Stores;

use InvalidArgumentException;
use RuntimeException;
use Uncanny_Automator\Api\Components\Recipe\Recipe;
use Uncanny_Automator\Api\Components\Recipe\Recipe_Config;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_User_Type;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Status;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Action_Conditions;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Id;
use Uncanny_Automator\Api\Components\Condition\Value_Objects\Condition_Group;
use Uncanny_Automator\Api\Components\Condition\Value_Objects\Condition_Group_Id;
use Uncanny_Automator\Api\Components\Condition\Value_Objects\Condition_Group_Mode;
use Uncanny_Automator\Api\Components\Condition\Value_Objects\Individual_Condition;
use Uncanny_Automator\Api\Components\Condition\Value_Objects\Condition_Fields;
use Uncanny_Automator\Api\Components\Condition\Dtos\Condition_Backup_Info;
use Uncanny_Automator\Api\Database\Interfaces\Recipe_Store;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Trigger_Logic;
use WP_Post;
use wpdb;

class WP_Recipe_Store implements Recipe_Store {

	const POST_TYPE                   = 'uo-recipe';
	const _META_RECIPE_TYPE           = 'uap_recipe_type';
	const _META_RECIPE_NOTES          = 'uap_recipe_notes';
	const _META_RECIPE_THROTTLE       = 'field_recipe_throttle';
	const _META_RECIPE_TIMES_PER_USER = 'field_recipe_times_per_user';
	const _META_RECIPE_TOTAL_TIMES    = 'field_recipe_total_times';
	const _META_ACTION_CONDITIONS     = 'actions_conditions';
	const _META_RECIPE_TRIGGER_LOGIC  = 'automator_trigger_logic';

	private static $recipe_version_key = 'uap_recipe_version';

	/**
	 * Initialize recipe store.
	 *
	 * @since 7.0.0
	 */
	public function __construct() {
		// WordPress functions automatically use global $wpdb
	}

	/**
	 * Persist recipe to WordPress database, handling both creation and updates.
	 *
	 * Transforms domain objects to WordPress post/meta format while preserving
	 * type-specific execution limits and legacy compatibility requirements.
	 *
	 * @param Recipe $recipe Domain recipe object to persist.
	 * @return Recipe Refreshed recipe object with updated database state.
	 * @throws RuntimeException When database operations fail.
	 * @since 7.0.0
	 */
	public function save( Recipe $recipe ): Recipe {

		$recipe_id  = $recipe->get_recipe_id()->get_value();
		$meta_input = $this->prepare_meta_input_from_recipe( $recipe );

		if ( null === $recipe_id ) {
			$recipe_id = $this->create_recipe_post( $recipe, $meta_input );
		} else {
			$this->update_recipe_post( $recipe_id, $recipe );
			$this->persist_recipe_meta( $recipe_id, $meta_input );
		}

		return $this->get( $recipe_id );
	}

	/**
	 * Build meta input array from recipe domain object.
	 *
	 * Prepares all post meta fields for persistence including base fields
	 * and type-specific execution limits (user vs anonymous).
	 *
	 * @param Recipe $recipe Domain recipe object to extract meta from.
	 * @return array Meta input array for wp_insert_post or update_post_meta.
	 * @since 7.0.0
	 */
	private function prepare_meta_input_from_recipe( Recipe $recipe ): array {

		$recipe_type          = $recipe->get_recipe_type()->get_value();
		$recipe_trigger_logic = $recipe->get_recipe_trigger_logic()->get_value();
		$recipe_id            = $recipe->get_recipe_id()->get_value();
		$throttle             = $this->format_throttle( $recipe->get_recipe_throttle()->to_array(), $recipe_type );

		$meta_input = array(
			self::$recipe_version_key        => AUTOMATOR_PLUGIN_VERSION,
			self::_META_RECIPE_TYPE          => $recipe_type,
			self::_META_RECIPE_NOTES         => $recipe->get_recipe_notes()->get_value(),
			self::_META_RECIPE_THROTTLE      => $throttle,
			self::_META_RECIPE_TRIGGER_LOGIC => $recipe_trigger_logic,
			self::_META_ACTION_CONDITIONS    => $this->convert_action_conditions_to_legacy(
				$recipe->get_recipe_action_conditions(),
				$recipe_id
			),
		);

		/** Add type-specific execution limit meta fields */
		if ( 'user' === $recipe_type ) {
			$meta_input = $this->add_user_recipe_meta( $meta_input, $recipe );
		}

		if ( 'anonymous' === $recipe_type ) {
			$meta_input = $this->add_anonymous_recipe_meta( $meta_input, $recipe );
		}

		return $meta_input;
	}

	/**
	 * Add user recipe execution limit meta fields.
	 *
	 * User recipes support both per-user and total execution limits.
	 *
	 * @param array  $meta_input Existing meta input array.
	 * @param Recipe $recipe Domain recipe object.
	 * @return array Updated meta input array with user-specific fields.
	 * @since 7.0.0
	 */
	private function add_user_recipe_meta( array $meta_input, Recipe $recipe ): array {

		$times_per_user = $this->convert_times_per_user_to_legacy(
			$recipe->get_recipe_times_per_user()->get_value()
		);
		$total_times    = $this->convert_total_times_to_legacy(
			$recipe->get_recipe_total_times()->get_value()
		);

		$meta_input[ self::_META_RECIPE_TIMES_PER_USER ] = $times_per_user;
		$meta_input[ self::_META_RECIPE_TOTAL_TIMES ]    = $total_times;

		return $meta_input;
	}

	/**
	 * Add anonymous recipe execution limit meta fields.
	 *
	 * Anonymous recipes only track total executions (no per-user limits).
	 *
	 * @param array  $meta_input Existing meta input array.
	 * @param Recipe $recipe Domain recipe object.
	 * @return array Updated meta input array with anonymous-specific fields.
	 * @since 7.0.0
	 */
	private function add_anonymous_recipe_meta( array $meta_input, Recipe $recipe ): array {

		$total_times = $this->convert_total_times_to_legacy(
			$recipe->get_recipe_total_times()->get_value()
		);

		$meta_input[ self::_META_RECIPE_TOTAL_TIMES ] = $total_times;

		return $meta_input;
	}

	/**
	 * Create new recipe post in WordPress database.
	 *
	 * Handles recipe creation for new domain objects.
	 *
	 * @param Recipe $recipe Domain recipe object to create.
	 * @param array  $meta_input Meta fields to persist.
	 * @return int Created post ID.
	 * @throws RuntimeException When post creation fails.
	 * @since 7.0.0
	 */
	private function create_recipe_post( Recipe $recipe, array $meta_input ): int {

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_title'  => $recipe->get_recipe_title()->get_value(),
				'post_status' => $recipe->get_recipe_status()->get_value(),
				'meta_input'  => $meta_input,
			)
		);

		if ( is_wp_error( $post_id ) ) {
			// translators: %s is the error message.
			throw new RuntimeException( sprintf( esc_html_x( 'Failed to save recipe: %s', 'Recipe store save error with message', 'uncanny-automator' ), esc_html( $post_id->get_error_message() ) ) );
		}

		return $post_id;
	}

	/**
	 * Update existing recipe post with current domain state.
	 *
	 * Updates post title and status for existing recipes.
	 *
	 * @param int    $recipe_id WordPress post ID.
	 * @param Recipe $recipe Domain recipe object.
	 * @throws RuntimeException When post update fails.
	 * @since 7.0.0
	 */
	private function update_recipe_post( int $recipe_id, Recipe $recipe ): void {

		$result = wp_update_post(
			array(
				'ID'          => $recipe_id,
				'post_title'  => $recipe->get_recipe_title()->get_value(),
				'post_status' => $recipe->get_recipe_status()->get_value(),
			)
		);

		if ( is_wp_error( $result ) ) {
			// translators: %s is the error message.
			throw new RuntimeException( sprintf( esc_html_x( 'Failed to update recipe: %s', 'Recipe store update error with message', 'uncanny-automator' ), esc_html( $result->get_error_message() ) ) );
		}
	}

	/**
	 * Persist all recipe meta fields to WordPress database.
	 *
	 * WordPress post meta is used for recipe configuration to maintain
	 * compatibility with existing query patterns.
	 *
	 * @param int   $recipe_id WordPress post ID.
	 * @param array $meta_input Meta fields to persist.
	 * @since 7.0.0
	 */
	private function persist_recipe_meta( int $recipe_id, array $meta_input ): void {

		foreach ( $meta_input as $meta_key => $meta_value ) {
			update_post_meta( $recipe_id, $meta_key, $meta_value );
		}
	}

	/**
	 * Retrieve recipe by ID and hydrate into domain object.
	 *
	 * @param int $id WordPress post ID of recipe to retrieve.
	 * @return Recipe|null Domain recipe object or null if not found/invalid.
	 * @since 7.0.0
	 */
	public function get( int $id ): ?Recipe {

		$post = $this->get_wp_post( $id );

		if ( ! $post ) {
			return null;
		}

		return $this->hydrate_recipe_from_post( $post );
	}

	/**
	 * Get WP_Post object for recipe.
	 *
	 * @param int $id Recipe ID.
	 * @return WP_Post|null WP_Post object or null if not found.
	 */
	public function get_wp_post( int $id ): ?WP_Post {
		$post = get_post( $id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return $post;
	}

	/**
	 * Permanently remove recipe from WordPress database.
	 *
	 * @param Recipe $recipe Domain recipe object to delete.
	 * @throws InvalidArgumentException When recipe has no ID.
	 * @throws RuntimeException When deletion fails.
	 * @since 7.0.0
	 */
	public function delete( Recipe $recipe ): void {
		$recipe_id = $recipe->get_recipe_id()->get_value();

		if ( null === $recipe_id ) {
			throw new InvalidArgumentException( esc_html_x( 'Cannot delete recipe without ID', 'Recipe store delete error', 'uncanny-automator' ) );
		}

		$result = wp_delete_post( $recipe_id, true );

		if ( false === $result || null === $result ) {
			throw new RuntimeException( esc_html_x( 'Failed to delete recipe', 'Recipe store deletion failed', 'uncanny-automator' ) );
		}
	}

	/**
	 * Retrieve all recipes with optional filtering and sorting.
	 *
	 * Supports filtering by status, type, integration, title search, and meta queries.
	 * Type and integration filters are applied post-hydration due to meta storage requirements.
	 *
	 * @param array $filters {
	 *     Optional filtering parameters.
	 *     @type string $status Recipe status ('draft', 'publish', 'trash').
	 *     @type string $type Recipe type ('user', 'anonymous').
	 *     @type string $integration Integration code to filter by.
	 *     @type string $title Text search in recipe titles.
	 *     @type int    $limit Maximum results to return.
	 *     @type string $orderby WordPress orderby parameter.
	 *     @type string $order Sort order ('ASC', 'DESC').
	 *     @type string $meta_key Custom meta key to filter by.
	 *     @type mixed  $meta_value Custom meta value to match.
	 *     @type string $meta_compare Meta comparison operator ('=', '!=', etc.).
	 * }
	 * @return array Array of Recipe domain objects.
	 * @since 7.0.0
	 */
	/**
	 * All.
	 *
	 * @param array $filters The filter.
	 * @return array
	 */
	public function all( array $filters = array() ): array {

		$query_args = $this->build_query_args_from_filters( $filters );
		$posts      = get_posts( $query_args );
		$recipes    = array_map( array( $this, 'hydrate_recipe_from_post' ), $posts );

		$recipes = $this->apply_post_hydration_filters( $recipes, $filters );

		/** Reindex array to maintain consistent numeric keys after filtering */
		return array_values( $recipes );
	}

	/**
	 * Build WP_Query arguments from filter array.
	 *
	 * Constructs query parameters for recipe retrieval including status,
	 * pagination, ordering, search, and custom meta queries.
	 *
	 * @param array $filters Filter parameters from all() method.
	 * @return array WP_Query compatible arguments array.
	 * @since 7.0.0
	 */
	private function build_query_args_from_filters( array $filters ): array {

		$query_args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => array( Recipe_Status::DRAFT, Recipe_Status::PUBLISH ),
			'posts_per_page' => -1,
		);

		/** Validate status filter using domain object to ensure data integrity */
		if ( isset( $filters['status'] ) && ! empty( $filters['status'] ) ) {
			$query_args['post_status'] = ( new Recipe_Status( $filters['status'] ) )->get_value();
		}

		if ( isset( $filters['limit'] ) && is_numeric( $filters['limit'] ) && $filters['limit'] > 0 ) {
			$query_args['posts_per_page'] = intval( $filters['limit'] );
		}

		if ( isset( $filters['orderby'] ) ) {
			$query_args['orderby'] = $filters['orderby'];
		}

		if ( isset( $filters['order'] ) ) {
			$query_args['order'] = $filters['order'];
		}

		if ( isset( $filters['title'] ) && ! empty( $filters['title'] ) ) {
			$query_args['s'] = $filters['title'];
		}

		/** Custom meta filtering for advanced query scenarios */
		if ( ! empty( $filters['meta_key'] ) && isset( $filters['meta_value'] ) ) {
			$query_args['meta_query'] = array(
				array(
					'key'     => $filters['meta_key'],
					'value'   => $filters['meta_value'],
					'compare' => $filters['meta_compare'] ?? '=',
				),
			);
		}

		return $query_args;
	}

	/**
	 * Apply filters that require hydrated recipe objects.
	 *
	 * Type and integration filters need domain objects since they rely
	 * on meta data and child post relationships not available in WP_Query.
	 *
	 * @param array $recipes Array of Recipe domain objects.
	 * @param array $filters Filter parameters from all() method.
	 * @return array Filtered array of Recipe objects.
	 * @since 7.0.0
	 */
	private function apply_post_hydration_filters( array $recipes, array $filters ): array {

		if ( isset( $filters['type'] ) && ! empty( $filters['type'] ) ) {
			$recipes = $this->filter_recipes_by_type( $recipes, $filters['type'] );
		}

		if ( isset( $filters['integration'] ) && ! empty( $filters['integration'] ) ) {
			$recipes = $this->filter_recipes_by_integration( $recipes, $filters['integration'] );
		}

		return $recipes;
	}

	/**
	 * Filter recipes by type value.
	 *
	 * Type filtering requires post-hydration since recipe type is stored in meta.
	 * Domain validation ensures type integrity at the boundary.
	 *
	 * @param array  $recipes Array of Recipe domain objects.
	 * @param string $type_value Recipe type to filter by ('user' or 'anonymous').
	 * @return array Filtered array of Recipe objects matching type.
	 * @since 7.0.0
	 */
	private function filter_recipes_by_type( array $recipes, string $type_value ): array {

		$type_filter = new Recipe_User_Type( $type_value );

		return array_filter(
			$recipes,
			function ( $recipe ) use ( $type_filter ) {
				if ( $recipe instanceof Recipe ) {
					return $recipe->get_recipe_type()->get_value() === $type_filter->get_value();
				}
				return false;
			}
		);
	}

	/**
	 * Filter recipes by integration code.
	 *
	 * Integration filtering requires examining child triggers/actions,
	 * necessitating post-hydration filtering approach.
	 *
	 * @param array  $recipes Array of Recipe domain objects.
	 * @param string $integration Integration code to filter by.
	 * @return array Filtered array of Recipe objects containing integration.
	 * @since 7.0.0
	 */
	private function filter_recipes_by_integration( array $recipes, string $integration ): array {

		return array_filter(
			$recipes,
			function ( $recipe ) use ( $integration ) {
				if ( ! $recipe instanceof Recipe ) {
					return false;
				}
				return $this->recipe_has_integration( $recipe, $integration );
			}
		);
	}

	/**
	 * Query recipes by trigger/action field values with universal matching support.
	 *
	 * Searches postmeta for exact matches, universal (-1) values, and serialized arrays.
	 * Essential for finding recipes that should execute based on dynamic field values
	 * from external systems (e.g., WooCommerce product purchases, form submissions).
	 *
	 * @param mixed $field_value Field value to match against trigger/action configurations.
	 * @return array Recipe IDs that contain matching triggers or actions.
	 * @since 7.0.0
	 */
	public function get_recipe_ids_from_field_value( $field_value ): array {
		global $wpdb;

		$field_value_str = (string) $field_value;

		/**
		 * Query pattern handles three matching scenarios:
		 * 1. Exact value matches (meta_value = '330')
		 * 2. Universal triggers (meta_value = '-1')
		 * 3. Multi-select serialized arrays containing the value
		 *
		 * The LIKE pattern matches JSON-serialized arrays where the value
		 * appears as a string element within the array structure.
		 */
		$query = $wpdb->prepare(
			"SELECT DISTINCT p.post_parent
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE (
				pm.meta_value = %s
				OR pm.meta_value = '-1'
				OR pm.meta_value LIKE %s
			)
			AND p.post_parent > 0
			AND p.post_type IN ('uo-trigger', 'uo-action')",
			$field_value_str,
			'%"' . $field_value_str . '"%'
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- False positive: $query is already prepared on line 455
		$recipe_ids = $wpdb->get_col( $query );

		return array_map( 'intval', $recipe_ids );
	}

	/**
	 * Transform WordPress post and meta into Recipe domain object.
	 *
	 * Handles legacy format conversion, type-specific field hydration,
	 * and graceful degradation for invalid data structures.
	 *
	 * @param WP_Post $post WordPress post object representing recipe.
	 * @return Recipe|null Domain recipe object or null if transformation fails.
	 * @since 7.0.0
	 */
	private function hydrate_recipe_from_post( WP_Post $post ): ?Recipe {
		/** Early validation prevents downstream errors from malformed post objects */
		if ( ! isset( $post->ID, $post->post_title, $post->post_status ) ) {
			return null;
		}

		$meta        = $this->extract_meta_fields( $post->ID );
		$recipe_type = $meta['recipe_type'];

		$config = $this->build_base_config( $post, $meta );
		$config = $this->add_type_specific_config( $config, $post->ID, $recipe_type );
		$config = $this->add_action_conditions_to_config( $config, $post->ID );

		return new Recipe( $config );
	}

	/**
	 * Extract meta fields from WordPress post.
	 *
	 * Retrieves all recipe meta fields with sensible defaults for missing values.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return array Meta fields array with keys for recipe_type, trigger_logic, notes, throttle_legacy.
	 * @since 7.0.0
	 */
	private function extract_meta_fields( int $post_id ): array {

		$recipe_type     = get_post_meta( $post_id, self::_META_RECIPE_TYPE, true );
		$trigger_logic   = get_post_meta( $post_id, self::_META_RECIPE_TRIGGER_LOGIC, true );
		$notes           = get_post_meta( $post_id, self::_META_RECIPE_NOTES, true );
		$throttle_legacy = get_post_meta( $post_id, self::_META_RECIPE_THROTTLE, true );

		return array(
			'recipe_type'     => ! empty( $recipe_type ) ? $recipe_type : 'user',
			'trigger_logic'   => ! empty( $trigger_logic ) ? $trigger_logic : Recipe_Trigger_Logic::LOGIC_ALL,
			'notes'           => ! empty( $notes ) ? $notes : '',
			'throttle_legacy' => ! empty( $throttle_legacy ) ? $throttle_legacy : '',
		);
	}

	/**
	 * Build base Recipe_Config from post and meta data.
	 *
	 * Constructs configuration object with common fields shared by all recipe types.
	 *
	 * @param WP_Post $post WordPress post object.
	 * @param array   $meta Meta fields array from extract_meta_fields.
	 * @return Recipe_Config Base configuration object.
	 * @since 7.0.0
	 */
	private function build_base_config( WP_Post $post, array $meta ): Recipe_Config {

		$throttle = $this->convert_legacy_to_throttle( $meta['throttle_legacy'], $meta['recipe_type'] );

		return ( new Recipe_Config() )
			->id( $post->ID )
			->title( $post->post_title )
			->status( $post->post_status )
			->user_type( $meta['recipe_type'] )
			->notes( $meta['notes'] )
			->trigger_logic( $meta['trigger_logic'] )
			->throttle( $throttle );
	}

	/**
	 * Add type-specific configuration fields.
	 *
	 * User and anonymous recipes have different execution limit requirements.
	 *
	 * @param Recipe_Config $config Base configuration object.
	 * @param int           $post_id WordPress post ID.
	 * @param string        $recipe_type Recipe type ('user' or 'anonymous').
	 * @return Recipe_Config Updated configuration with type-specific fields.
	 * @since 7.0.0
	 */
	private function add_type_specific_config( Recipe_Config $config, int $post_id, string $recipe_type ): Recipe_Config {

		if ( 'user' === $recipe_type ) {
			return $this->add_user_execution_limits( $config, $post_id );
		}

		if ( 'anonymous' === $recipe_type ) {
			return $this->add_anonymous_execution_limits( $config, $post_id );
		}

		return $config;
	}

	/**
	 * Add user recipe execution limit configuration.
	 *
	 * User recipes support both per-user and global execution limits.
	 *
	 * @param Recipe_Config $config Base configuration object.
	 * @param int           $post_id WordPress post ID.
	 * @return Recipe_Config Updated configuration with user execution limits.
	 * @since 7.0.0
	 */
	private function add_user_execution_limits( Recipe_Config $config, int $post_id ): Recipe_Config {

		$times_per_user_legacy = get_post_meta( $post_id, self::_META_RECIPE_TIMES_PER_USER, true );
		$times_per_user_legacy = ! empty( $times_per_user_legacy ) ? $times_per_user_legacy : '';
		$times_per_user        = $this->convert_legacy_to_times_per_user( $times_per_user_legacy );

		$total_times_legacy = get_post_meta( $post_id, self::_META_RECIPE_TOTAL_TIMES, true );
		$total_times_legacy = ! empty( $total_times_legacy ) ? $total_times_legacy : '';
		$total_times        = $this->convert_legacy_to_total_times( $total_times_legacy );

		return $config
			->times_per_user( $times_per_user )
			->total_times( $total_times );
	}

	/**
	 * Add anonymous recipe execution limit configuration.
	 *
	 * Anonymous recipes only track total executions (no per-user limits).
	 *
	 * @param Recipe_Config $config Base configuration object.
	 * @param int           $post_id WordPress post ID.
	 * @return Recipe_Config Updated configuration with total execution limit.
	 * @since 7.0.0
	 */
	private function add_anonymous_execution_limits( Recipe_Config $config, int $post_id ): Recipe_Config {

		$total_times_legacy = get_post_meta( $post_id, self::_META_RECIPE_TOTAL_TIMES, true );
		$total_times_legacy = ! empty( $total_times_legacy ) ? $total_times_legacy : '';
		$total_times        = $this->convert_legacy_to_total_times( $total_times_legacy );

		return $config->total_times( $total_times );
	}

	/**
	 * Add action conditions to recipe configuration.
	 *
	 * Action conditions handling accounts for WordPress meta serialization quirks.
	 * Data may arrive as JSON string or pre-decoded array depending on cache state.
	 *
	 * @param Recipe_Config $config Base configuration object.
	 * @param int           $post_id WordPress post ID.
	 * @return Recipe_Config Updated configuration with action conditions.
	 * @since 7.0.0
	 */
	private function add_action_conditions_to_config( Recipe_Config $config, int $post_id ): Recipe_Config {

		$action_conditions_legacy = get_post_meta( $post_id, self::_META_ACTION_CONDITIONS, true );

		if ( empty( $action_conditions_legacy ) ) {
			return $config;
		}

		$legacy_data = is_string( $action_conditions_legacy )
			? json_decode( $action_conditions_legacy, true )
			: $action_conditions_legacy;

		if ( ! is_array( $legacy_data ) || empty( $legacy_data ) ) {
			return $config;
		}

		$action_conditions = $this->convert_legacy_to_action_conditions( $legacy_data, $post_id );

		/** Recipe_Config requires array format for fluent interface compatibility */
		return $config->action_conditions( $action_conditions->to_array() );
	}

	/**
	 * Determine if recipe contains triggers or actions from specified integration.
	 *
	 * Queries child posts (triggers/actions) to check integration meta values.
	 * Used for integration-specific recipe filtering where meta queries alone
	 * are insufficient due to the parent-child post relationship.
	 *
	 * @param Recipe $recipe Recipe domain object to examine.
	 * @param string $integration Integration code to match against.
	 * @return bool Whether recipe contains components from the integration.
	 * @since 7.0.0
	 */
	private function recipe_has_integration( Recipe $recipe, string $integration ): bool {
		$recipe_id = $recipe->get_recipe_id()->get_value();

		if ( null === $recipe_id ) {
			return false;
		}

		/** Query all triggers for this recipe */
		$triggers = get_posts(
			array(
				'post_type'      => 'uo-trigger',
				'post_parent'    => $recipe_id,
				'posts_per_page' => -1,
				'post_status'    => array( Recipe_Status::DRAFT, Recipe_Status::PUBLISH ),
			)
		);

		foreach ( $triggers as $trigger_post ) {
			$trigger_integration = get_post_meta( $trigger_post->ID, 'integration', true );
			if ( $trigger_integration === $integration ) {
				return true;
			}
		}

		/** Query all actions for this recipe */
		$actions = get_posts(
			array(
				'post_type'      => 'uo-action',
				'post_parent'    => $recipe_id,
				'posts_per_page' => -1,
				'post_status'    => array( Recipe_Status::DRAFT, Recipe_Status::PUBLISH ),
			)
		);

		foreach ( $actions as $action_post ) {
			$action_integration = get_post_meta( $action_post->ID, 'integration', true );
			if ( $action_integration === $integration ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Transform domain throttle settings into WordPress-compatible legacy format.
	 *
	 * Legacy format includes backup information for UI reconstruction and
	 * type-specific fields (scope only applies to user recipes). This maintains
	 * backward compatibility with existing recipe configurations.
	 *
	 * @param array  $throttle Domain throttle configuration array.
	 * @param string $recipe_type Recipe type determining available options.
	 * @return array WordPress-compatible serialized format with backup data.
	 * @since 7.0.0
	 */
	private function format_throttle( array $throttle, string $recipe_type ): array {
		if ( ! $throttle['enabled'] ) {
			return array();
		}

		$legacy_format = array(
			'ENABLED' => array(
				'type'   => 'string',
				'value'  => true,
				'backup' => array(
					'label'                  => esc_html_x( 'Enable throttling', 'Recipe throttle configuration label', 'uncanny-automator' ),
					'show_label_in_sentence' => true,
				),
			),
			'NUMBER'  => array(
				'type'   => 'string',
				'value'  => (string) $throttle['duration'],
				'backup' => array(
					'label'                  => esc_html_x( 'Throttle duration', 'Recipe throttle duration label', 'uncanny-automator' ),
					'show_label_in_sentence' => true,
				),
			),
			'UNIT'    => array(
				'type'   => 'string',
				'value'  => $throttle['unit'],
				'backup' => array(
					'label'                    => esc_html_x( 'Duration unit', 'Recipe throttle duration unit label', 'uncanny-automator' ),
					'show_label_in_sentence'   => true,
					'supports_custom_value'    => false,
					'supports_multiple_values' => false,
				),
			),
		);

		/** Scope field only applies to user recipes (anonymous recipes have no user context) */
		if ( 'user' === $recipe_type && ! empty( $throttle['scope'] ) ) {
			$legacy_format['PER_RECIPE_OR_USER'] = array(
				'type'   => 'string',
				'value'  => $throttle['scope'],
				'backup' => array(
					'label'                    => esc_html_x( 'Throttle scope', 'Recipe throttle scope label', 'uncanny-automator' ),
					'show_label_in_sentence'   => true,
					'supports_custom_value'    => false,
					'supports_multiple_values' => false,
				),
			);
		}

		return $legacy_format;
	}

	/**
	 * Parse legacy WordPress format into clean domain throttle configuration.
	 *
	 * Handles WordPress serialization variations and provides sensible defaults
	 * for missing or invalid data. Type-specific fields (scope) are conditionally
	 * included based on recipe type.
	 *
	 * @param mixed  $legacy_data WordPress meta value (may be serialized or array).
	 * @param string $recipe_type Recipe type affecting available configuration options.
	 * @return array Clean throttle configuration with validated values.
	 * @since 7.0.0
	 */
	private function convert_legacy_to_throttle( $legacy_data, string $recipe_type ): array {
		$defaults = array(
			'enabled'  => false,
			'duration' => 1,
			'unit'     => 'hours',
		);

		/** User recipes require scope configuration for throttling context */
		if ( 'user' === $recipe_type ) {
			$defaults['scope'] = 'recipe';
		}

		if ( is_null( $legacy_data ) ) {
			return $defaults;
		}

		/** WordPress meta handling is inconsistent - data may arrive pre-unserialized */
		$unserialized = is_array( $legacy_data ) ? $legacy_data : maybe_unserialize( $legacy_data );
		if ( ! is_array( $unserialized ) ) {
			return $defaults;
		}

		$enabled  = isset( $unserialized['ENABLED']['value'] ) ? (bool) $unserialized['ENABLED']['value'] : false;
		$duration = isset( $unserialized['NUMBER']['value'] ) ? (int) $unserialized['NUMBER']['value'] : 1;
		$unit     = isset( $unserialized['UNIT']['value'] ) ? $unserialized['UNIT']['value'] : 'hours';

		$result = array(
			'enabled'  => $enabled,
			'duration' => max( 1, $duration ),
			'unit'     => in_array( $unit, array( 'minutes', 'hours', 'days' ), true ) ? $unit : 'hours',
		);

		/** Scope validation ensures only valid throttling contexts are persisted */
		if ( 'user' === $recipe_type ) {
			$scope           = isset( $unserialized['PER_RECIPE_OR_USER']['value'] ) ? $unserialized['PER_RECIPE_OR_USER']['value'] : 'recipe';
			$result['scope'] = in_array( $scope, array( 'recipe', 'user' ), true ) ? $scope : 'recipe';
		}

		return $result;
	}

	/**
	 * Format per-user execution limit for WordPress meta storage.
	 *
	 * Wraps simple integer value in legacy structure containing backup
	 * information for UI reconstruction and form field labeling.
	 *
	 * @param int|null $times_per_user Maximum executions allowed per user.
	 * @return array Legacy format with backup metadata for UI compatibility.
	 * @since 7.0.0
	 */
	private function convert_times_per_user_to_legacy( ?int $times_per_user ): array {

		if ( is_null( $times_per_user ) ) {
			return array();
		}

		return array(
			'type'   => 'string',
			'value'  => (string) $times_per_user,
			'backup' => array(
				'label'                  => esc_html_x( 'Allowed runs per user', 'Recipe times per user configuration label', 'uncanny-automator' ),
				'show_label_in_sentence' => true,
			),
		);
	}

	/**
	 * Extract per-user execution limit from WordPress meta format.
	 *
	 * Provides graceful fallback to sensible default when legacy data
	 * is missing or malformed. Zero values are permitted for unlimited executions.
	 *
	 * @param mixed $legacy_data WordPress meta value containing execution limit.
	 * @return int|null Per-user execution limit (0 = unlimited).
	 * @since 7.0.0
	 */
	private function convert_legacy_to_times_per_user( $legacy_data ): ?int {
		if ( is_null( $legacy_data ) ) {
			return null;
		}

		/** Handle WordPress meta serialization inconsistencies */
		$unserialized = is_array( $legacy_data ) ? $legacy_data : maybe_unserialize( $legacy_data );
		if ( ! is_array( $unserialized ) ) {
			return null;
		}

		$value = isset( $unserialized['value'] ) ? (int) $unserialized['value'] : 1;
		return max( 0, $value );
	}

	/**
	 * Format global execution limit for WordPress meta storage.
	 *
	 * Handles nullable values by returning empty array when no limit is set.
	 * Non-empty values are wrapped in legacy structure for UI compatibility.
	 *
	 * @param int|null $total_times Maximum total executions across all users.
	 * @return array Legacy format or empty array for unlimited executions.
	 * @since 7.0.0
	 */
	private function convert_total_times_to_legacy( ?int $total_times ): array {
		if ( is_null( $total_times ) ) {
			return array();
		}

		return array(
			'type'   => 'string',
			'value'  => (string) $total_times,
			'backup' => array(
				'label'                  => esc_html_x( 'Total allowed runs', 'Recipe total times configuration label', 'uncanny-automator' ),
				'show_label_in_sentence' => true,
			),
		);
	}

	/**
	 * Extract global execution limit from WordPress meta format.
	 *
	 * Returns null for unlimited executions, maintaining distinction between
	 * "no limit set" (null) and "zero allowed" (0) for business logic clarity.
	 *
	 * @param mixed $legacy_data WordPress meta value containing total limit.
	 * @return int|null Total execution limit or null for unlimited.
	 * @since 7.0.0
	 */
	private function convert_legacy_to_total_times( $legacy_data ): ?int {
		if ( is_null( $legacy_data ) ) {
			return null;
		}

		/** Handle WordPress serialization variations gracefully */
		$unserialized = is_array( $legacy_data ) ? $legacy_data : maybe_unserialize( $legacy_data );
		if ( ! is_array( $unserialized ) ) {
			return null;
		}

		$value = isset( $unserialized['value'] ) ? (int) $unserialized['value'] : 1;
		return max( 0, $value );
	}

	/**
	 * Convert action conditions object to legacy or storage format.
	 *
	 * @param Recipe_Action_Conditions $action_conditions Action conditions.
	 * @param int|null                 $recipe_id Recipe ID.
	 * @return string Legacy format.
	 */
	private function convert_action_conditions_to_legacy(
		Recipe_Action_Conditions $action_conditions,
		?int $recipe_id = null
	): string {

		$legacy_format = array();

		foreach ( $action_conditions->get_all() as $group ) {

			$conditions_array = array();

			foreach ( $group->get_conditions() as $condition ) {
				// Get field definitions to generate _readable fields
				$enhanced_fields = $this->enhance_fields_with_readable_values(
					$condition->get_fields()->to_array(),
					$condition->get_integration(),
					$condition->get_condition_code()
				);

				$conditions_array[] = array(
					'id'          => $condition->get_condition_id()->get_value(),
					'integration' => $condition->get_integration(),
					'condition'   => $condition->get_condition_code(),
					'fields'      => $enhanced_fields,
					'backup'      => array(
						'nameDynamic'     => $condition->get_backup_info()->get_name_dynamic(),
						'titleHTML'       => htmlspecialchars( $condition->get_backup_info()->get_title_html(), ENT_QUOTES, 'UTF-8' ),
						'integrationName' => $condition->get_backup_info()->get_integration_name(),
					),
				);
			}

			$legacy_format[] = array(
				'id'         => $group->get_group_id()->get_value(),
				'priority'   => $group->get_priority(),
				'actions'    => $group->get_action_ids(), // Already an array of integers
				'mode'       => $group->get_mode()->get_value(),
				'parent_id'  => $group->get_parent_id()->get_value() ?? $recipe_id,
				'conditions' => $conditions_array,
			);
		}

		$encoded = wp_json_encode( $legacy_format );

		if ( false === $encoded ) {
			return '';
		}

		return $encoded;
	}


	/**
	 * Parse legacy WordPress format into domain action conditions.
	 *
	 * Reconstructs domain objects from WordPress meta storage, handling missing
	 * or invalid data gracefully. Generates new IDs for condition groups when
	 * legacy data lacks proper identifiers.
	 *
	 * @param array $legacy_data WordPress meta array containing condition groups.
	 * @param int   $recipe_id Recipe identifier for parent relationships.
	 * @return Recipe_Action_Conditions Reconstructed domain action conditions.
	 * @since 7.0.0
	 */
	private function convert_legacy_to_action_conditions(
		array $legacy_data,
		int $recipe_id
	): Recipe_Action_Conditions {

		$condition_groups = array();

		foreach ( $legacy_data as $group_data ) {
			if ( ! is_array( $group_data ) ) {
				continue;
			}

			$group = $this->build_condition_group_from_legacy( $group_data, $recipe_id );
			if ( null !== $group ) {
				$condition_groups[] = $group;
			}
		}

		return new Recipe_Action_Conditions( $condition_groups );
	}

	/**
	 * Build single condition group from legacy data.
	 *
	 * @param array $group_data Legacy group data.
	 * @param int   $recipe_id Recipe ID.
	 * @return Condition_Group|null Group object or null if invalid.
	 */
	private function build_condition_group_from_legacy( array $group_data, int $recipe_id ): ?Condition_Group {
		$action_ids = $this->extract_action_ids( $group_data );
		$mode       = $this->parse_group_mode( $group_data );

		if ( null === $mode ) {
			return null;
		}

		$individual_conditions = $this->build_individual_conditions( $group_data );
		$group_id_obj          = $this->parse_group_id( $group_data );
		$priority              = $this->parse_priority( $group_data );

		// Use the group's own parent_id from storage; fall back to recipe_id for legacy data.
		$parent_id = isset( $group_data['parent_id'] ) && (int) $group_data['parent_id'] > 0
			? (int) $group_data['parent_id']
			: $recipe_id;

		try {
			return new Condition_Group(
				$group_id_obj,
				$priority,
				$action_ids,
				$mode,
				new Recipe_Id( $parent_id ),
				$individual_conditions
			);
		} catch ( \Throwable $exception ) {
			// Skip invalid condition group.
			return null;
		}
	}

	/**
	 * Extract valid action IDs from group data.
	 *
	 * @param array $group_data Group data.
	 * @return array Valid action IDs.
	 */
	private function extract_action_ids( array $group_data ): array {
		return array_values(
			array_filter(
				array_map( 'intval', $group_data['actions'] ?? array() ),
				function ( $id ) {
					return $id > 0;
				}
			)
		);
	}

	/**
	 * Parse and validate group mode.
	 *
	 * @param array $group_data Group data.
	 * @return Condition_Group_Mode|null Mode object or null if invalid.
	 */
	private function parse_group_mode( array $group_data ): ?Condition_Group_Mode {
		$mode_value = is_string( $group_data['mode'] ?? null ) ? strtolower( $group_data['mode'] ) : 'all';
		if ( ! in_array( $mode_value, array( 'any', 'all' ), true ) ) {
			$mode_value = 'all';
		}

		try {
			return new Condition_Group_Mode( $mode_value );
		} catch ( \Throwable $exception ) {
			// Skip group due to invalid mode.
			return null;
		}
	}

	/**
	 * Build individual conditions from group data.
	 *
	 * @param array $group_data Group data.
	 * @return array Array of Individual_Condition objects.
	 */
	private function build_individual_conditions( array $group_data ): array {
		$individual_conditions = array();
		$items                 = is_array( $group_data['conditions'] ?? null ) ? $group_data['conditions'] : array();

		foreach ( $items as $condition_data ) {
			if ( ! is_array( $condition_data ) ) {
				continue;
			}

			$condition = $this->build_single_condition( $condition_data );
			if ( null !== $condition ) {
				$individual_conditions[] = $condition;
			}
		}

		return $individual_conditions;
	}

	/**
	 * Build single condition from legacy data.
	 *
	 * @param array $condition_data Condition data.
	 * @return Individual_Condition|null Condition object or null if invalid.
	 */
	private function build_single_condition( array $condition_data ): ?Individual_Condition {
		try {
			$backup_info_data = $condition_data['backup_info'] ?? ( $condition_data['backup'] ?? array() );
			$backup_info      = new Condition_Backup_Info(
				$backup_info_data['nameDynamic'] ?? ( $backup_info_data['name_dynamic'] ?? ( $backup_info_data['sentence'] ?? '' ) ),
				$backup_info_data['titleHTML'] ?? ( $backup_info_data['title_html'] ?? '' ),
				$backup_info_data['integrationName'] ?? ( $backup_info_data['integration_name'] ?? '' )
			);

			$condition_fields = new Condition_Fields( $condition_data['fields'] ?? array() );

			return Individual_Condition::create(
				(string) ( $condition_data['integration'] ?? '' ),
				(string) ( $condition_data['condition'] ?? '' ),
				$condition_fields,
				$backup_info,
				isset( $condition_data['id'] ) ? (string) $condition_data['id'] : null
			);
		} catch ( \Throwable $exception ) {
			// Skip invalid condition.
			return null;
		}
	}

	/**
	 * Parse group ID from data.
	 *
	 * @param array $group_data Group data.
	 * @return Condition_Group_Id Group ID object.
	 */
	private function parse_group_id( array $group_data ): Condition_Group_Id {
		$group_id = isset( $group_data['id'] ) ? (string) $group_data['id'] : '';

		return empty( $group_id ) || strlen( $group_id ) < 10 ?
			Condition_Group_Id::generate() :
			new Condition_Group_Id( $group_id );
	}

	/**
	 * Parse priority from data.
	 *
	 * @param array $group_data Group data.
	 * @return int Priority value (default 20).
	 */
	private function parse_priority( array $group_data ): int {
		$priority = isset( $group_data['priority'] ) && is_numeric( $group_data['priority'] ) ? (int) $group_data['priority'] : 20;
		return $priority <= 0 ? 20 : $priority;
	}

	/**
	 * Generate human-readable field values using WordPress filter system.
	 *
	 * Queries field definitions from the condition registry to transform raw
	 * field values (IDs, codes) into displayable text for administrative interfaces.
	 * Essential for maintaining usable condition displays in the WordPress admin.
	 *
	 * @param array  $fields Raw field values from condition storage.
	 * @param string $integration_code Integration identifier for field lookup.
	 * @param string $condition_code Condition identifier for field definitions.
	 * @return array Enhanced fields with _readable and _label suffixed variants.
	 * @since 7.0.0
	 */
	private function enhance_fields_with_readable_values( array $fields, string $integration_code, string $condition_code ): array {
		/** Query field definitions through WordPress filter system */
		$field_definitions = apply_filters( 'automator_pro_actions_conditions_fields', array(), $integration_code, $condition_code );

		if ( empty( $field_definitions ) ) {
			return $fields;
		}

		$enhanced_fields = $fields;

		/** Transform field values using definition metadata */
		foreach ( $field_definitions as $field_def ) {
			$field_code = $field_def['option_code'] ?? '';

			if ( empty( $field_code ) || ! isset( $fields[ $field_code ] ) ) {
				continue;
			}

			$field_value  = $fields[ $field_code ];
			$readable_key = $field_code . '_readable';

			/** Select/dropdown fields require option lookup for readable text */
			if ( isset( $field_def['input_type'] ) && in_array( $field_def['input_type'], array( 'select', 'dropdown' ), true ) ) {
				if ( isset( $field_def['options'] ) && is_array( $field_def['options'] ) ) {
					foreach ( $field_def['options'] as $option ) {
						if ( isset( $option['value'] ) && $option['value'] === $field_value ) {
							$enhanced_fields[ $readable_key ] = $option['text'] ?? $field_value;
							break;
						}
					}
				}
			}

			/** Fallback to raw value when no readable option is found */
			if ( ! isset( $enhanced_fields[ $readable_key ] ) ) {
				$enhanced_fields[ $readable_key ] = $field_value;
			}

			/** Include field labels for complete UI reconstruction */
			if ( isset( $field_def['label'] ) ) {
				$enhanced_fields[ $field_code . '_label' ] = $field_def['label'];
			}
		}

		return $enhanced_fields;
	}

	/**
	 * Get post type.
	 *
	 * @return string Post type.
	 */
	public function get_post_type(): string {
		return self::POST_TYPE;
	}
}
