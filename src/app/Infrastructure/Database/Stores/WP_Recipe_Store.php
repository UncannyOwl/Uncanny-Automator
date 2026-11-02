<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Infrastructure\Database\Stores;

use InvalidArgumentException;
use RuntimeException;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Recipe;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Recipe_Config;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_User_Type;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Status;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Id;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Store;
use Uncanny_Automator\App\Infrastructure\Cache\Recipe_Cache;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Settings\Enums\Recipe_Setting_Id;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Utilities\Field_Value_Normalizer;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Throttle;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Trigger_Logic;
use WP_Post;
use Uncanny_Automator\App\Events\Dispatcher;
use wpdb;

class WP_Recipe_Store extends WP_Post_Store implements Recipe_Store {

	const POST_TYPE                   = AUTOMATOR_POST_TYPE_RECIPE;
	const _META_RECIPE_TYPE           = 'uap_recipe_type';
	const _META_RECIPE_NOTES          = 'uap_recipe_notes';
	const _META_RECIPE_THROTTLE       = 'field_recipe_throttle';
	const _META_RECIPE_TIMES_PER_USER = 'field_recipe_times_per_user';
	const _META_RECIPE_TOTAL_TIMES    = 'field_recipe_total_times';

	/**
	 * Pre-rename meta key for the recipe-wide run cap; still read so older recipes keep their limit.
	 *
	 * @var string
	 */
	const _LEGACY_META_RECIPE_TOTAL_TIMES = 'recipe_max_completions_allowed';

	/**
	 * Pre-rename meta key for the per-user run cap; still read so older recipes keep their limit.
	 *
	 * @var string
	 */
	const _LEGACY_META_RECIPE_TIMES_PER_USER = 'recipe_completions_allowed';

	const _META_RECIPE_TRIGGER_LOGIC = 'automator_trigger_logic';

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
	 * Count recipe posts without hydrating every recipe on large sites.
	 *
	 * WordPress caches this grouped count and invalidates it on post changes.
	 *
	 * @return array<string, int> Site-wide counts keyed by post status.
	 */
	public function count_by_status(): array {
		return array_map( 'intval', (array) wp_count_posts( self::POST_TYPE ) );
	}

	/**
	 * Whether an author holds a recipe in one of the given statuses, leaving
	 * out the given recipes.
	 *
	 * @since 8.0.0
	 *
	 * @param int      $user_id       The author; 0 or less holds none.
	 * @param string[] $statuses      Recipe statuses that count (`Recipe_Status`: publish, draft, trash); at least one.
	 * @param int[]    $excluding_ids Recipes to leave out.
	 *
	 * @return bool
	 *
	 * @throws InvalidArgumentException When no status is given, or one is not a recipe status.
	 */
	public function author_has_recipe( int $user_id, array $statuses, array $excluding_ids = array() ): bool {

		// WP_Query reads an empty status list as "publish" only.
		if ( array() === $statuses ) {
			throw new InvalidArgumentException( 'Name at least one recipe status.' );
		}

		foreach ( $statuses as $status ) {
			if ( ! Recipe_Status::is_valid( $status ) ) {
				throw new InvalidArgumentException(
					sprintf( 'Unknown recipe status "%s"; expected publish, draft or trash.', esc_html( $status ) )
				);
			}
		}

		// WP_Query reads author 0 as "any author".
		if ( $user_id <= 0 ) {
			return false;
		}

		$query = new \WP_Query(
			array(
				'post_type'              => self::POST_TYPE,
				'post_status'            => $statuses,
				'author'                 => $user_id,
				'post__not_in'           => array_map( 'intval', $excluding_ids ),
				'fields'                 => 'ids',
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return array() !== $query->posts;
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
			// The version is the legacy storage migration's watermark. Moving it on
			// an edit would mark an unmigrated recipe migrated and strand its
			// conditions and delays.
			unset( $meta_input[ self::$recipe_version_key ] );
			$this->update_recipe_post( $recipe_id, $recipe );
			$this->persist_recipe_meta( $recipe_id, $meta_input );
		}

		Recipe_Cache::invalidate( $recipe_id );

		return $this->get( $recipe_id );
	}

	/**
	 * Targeted writer — set the recipe's post status.
	 *
	 * Wraps `wp_update_post` then invalidates the recipe's caches.
	 *
	 * @inheritDoc
	 */
	public function update_status( int $recipe_id, string $status ): void {
		if ( ! in_array( $status, array( Recipe_Status::PUBLISH, Recipe_Status::DRAFT ), true ) ) {
			throw new InvalidArgumentException(
				sprintf(
					// translators: %s is the invalid status value that was passed.
					esc_html_x( "Status must be 'publish' or 'draft', got '%s'", 'Recipe store setting update error', 'uncanny-automator' ),
					esc_html( $status )
				)
			);
		}

		if ( ! $this->is_recipe_post( $recipe_id ) ) {
			// translators: %d is the post ID.
			throw new RuntimeException( sprintf( esc_html_x( 'Failed to update recipe status: post %d is not a recipe.', 'Recipe store status update error', 'uncanny-automator' ), absint( $recipe_id ) ) );
		}

		$result = wp_update_post(
			array(
				'ID'          => $recipe_id,
				'post_status' => $status,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			// translators: %s is the error message.
			throw new RuntimeException( sprintf( esc_html_x( 'Failed to update recipe status: %s', 'Recipe store status update error', 'uncanny-automator' ), esc_html( $result->get_error_message() ) ) );
		}

		Recipe_Cache::invalidate( $recipe_id );
	}

	/**
	 * Targeted writer — set the recipe's free-text notes.
	 *
	 * @inheritDoc
	 */
	public function update_notes( int $recipe_id, string $notes ): void {
		if ( ! $this->is_recipe_post( $recipe_id ) ) {
			// translators: %d is the post ID.
			throw new RuntimeException( sprintf( esc_html_x( 'Failed to update recipe notes: post %d is not a recipe.', 'Recipe store notes update error', 'uncanny-automator' ), absint( $recipe_id ) ) );
		}

		if ( '' === $notes ) {
			delete_post_meta( $recipe_id, self::_META_RECIPE_NOTES );
		} else {
			update_post_meta( $recipe_id, self::_META_RECIPE_NOTES, $notes );
		}

		Recipe_Cache::invalidate( $recipe_id );
	}

	/**
	 * Targeted reader — the recipe's free-text notes ('' when unset).
	 *
	 * @inheritDoc
	 */
	public function get_notes( int $recipe_id ): string {
		return (string) get_post_meta( $recipe_id, self::_META_RECIPE_NOTES, true );
	}

	/**
	 * Raw stored recipe type, or null when no type has been chosen.
	 *
	 * @inheritDoc
	 */
	public function get_recipe_type_raw( int $recipe_id ): ?string {
		$value = get_post_meta( $recipe_id, self::_META_RECIPE_TYPE, true );
		return in_array( $value, array( 'user', 'anonymous' ), true ) ? (string) $value : null;
	}

	/**
	 * Targeted writer — set the recipe's post title.
	 *
	 * @inheritDoc
	 */
	public function update_title( int $recipe_id, string $title ): void {
		if ( ! $this->is_recipe_post( $recipe_id ) ) {
			// translators: %d is the post ID.
			throw new RuntimeException( sprintf( esc_html_x( 'Failed to update recipe title: post %d is not a recipe.', 'Recipe store title update error', 'uncanny-automator' ), absint( $recipe_id ) ) );
		}

		$result = wp_update_post(
			array(
				'ID'         => $recipe_id,
				'post_title' => $title,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			// translators: %s is the error message.
			throw new RuntimeException( sprintf( esc_html_x( 'Failed to update recipe title: %s', 'Recipe store title update error', 'uncanny-automator' ), esc_html( $result->get_error_message() ) ) );
		}

		Recipe_Cache::invalidate( $recipe_id );
	}

	/**
	 * Whether an id names a recipe post, so a targeted writer never changes another post.
	 *
	 * `get_post( 0 )` answers the global post, so a non-positive id is refused before the lookup.
	 *
	 * @since 8.0.0
	 *
	 * @param int $recipe_id Post id the caller names as a recipe.
	 * @return bool
	 */
	private function is_recipe_post( int $recipe_id ): bool {
		return 0 < $recipe_id && null !== $this->get_wp_post( $recipe_id );
	}

	/**
	 * Targeted writer — set the recipe's user type.
	 *
	 * @inheritDoc
	 */
	public function set_recipe_type( int $recipe_id, string $recipe_type ): bool {
		// update_post_meta() returns false for an unchanged value as well as for a
		// failed write, so a no-op is treated as success here.
		if ( (string) get_post_meta( $recipe_id, self::_META_RECIPE_TYPE, true ) === $recipe_type ) {
			Recipe_Cache::invalidate( $recipe_id );

			return true;
		}

		$result = update_post_meta( $recipe_id, self::_META_RECIPE_TYPE, $recipe_type );
		Recipe_Cache::invalidate( $recipe_id );

		return false !== $result;
	}

	/**
	 * Targeted writer — replace the recipe's recipe_category terms.
	 *
	 * @inheritDoc
	 */
	public function update_categories( int $recipe_id, array $slugs ): void {
		wp_set_object_terms( $recipe_id, array_map( 'sanitize_title', $slugs ), 'recipe_category' );
		Recipe_Cache::invalidate( $recipe_id );
	}

	/**
	 * Targeted writer — replace the recipe's recipe_tag terms.
	 *
	 * @inheritDoc
	 */
	public function update_tags( int $recipe_id, array $slugs ): void {
		wp_set_object_terms( $recipe_id, array_map( 'sanitize_title', $slugs ), 'recipe_tag' );
		Recipe_Cache::invalidate( $recipe_id );
	}

	/**
	 * The recipe's assigned terms in a taxonomy.
	 *
	 * @inheritDoc
	 */
	public function get_assigned_terms( int $recipe_id, string $taxonomy ): array {
		$terms = wp_get_object_terms( $recipe_id, $taxonomy );

		return is_wp_error( $terms ) ? array() : $terms;
	}

	/**
	 * Every term in a taxonomy (incl. unused).
	 *
	 * @inheritDoc
	 */
	public function get_all_terms( string $taxonomy ): array {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		return is_wp_error( $terms ) ? array() : $terms;
	}

	/**
	 * Replace the recipe's terms in a taxonomy by term id.
	 *
	 * @inheritDoc
	 */
	public function assign_terms( int $recipe_id, array $term_ids, string $taxonomy ): void {
		wp_set_object_terms( $recipe_id, array_map( 'intval', $term_ids ), $taxonomy, false );
		Recipe_Cache::invalidate( $recipe_id );
	}

	/**
	 * Create a term by name; resolve `term_exists` to the existing id.
	 *
	 * @inheritDoc
	 */
	public function create_term( string $name, string $taxonomy, int $parent = 0 ) {
		$args   = $parent > 0 ? array( 'parent' => $parent ) : array();
		$result = wp_insert_term( $name, $taxonomy, $args );

		if ( is_wp_error( $result ) ) {
			if ( 'term_exists' === $result->get_error_code() ) {
				return array( 'term_id' => (int) $result->get_error_data() );
			}
			return $result;
		}

		return array( 'term_id' => (int) $result['term_id'] );
	}

	/**
	 * Targeted writer — set one editable recipe-level setting.
	 *
	 * Resolves the meta key via `Recipe_Setting_Id::meta_key()`, converts
	 * the wire-shape FieldsOutput to the legacy on-disk format using the
	 * store's existing formatters, writes the meta, then flushes the
	 * caches.
	 *
	 * @inheritDoc
	 */
	public function update_setting( int $recipe_id, string $setting_id, array $fields ): void {
		if ( ! Recipe_Setting_Id::is_editable( $setting_id ) ) {
			throw new InvalidArgumentException(
				sprintf(
					// translators: %s is the recipe-setting ID that was rejected.
					esc_html_x( "Setting id '%s' is not editable", 'Recipe store setting update error', 'uncanny-automator' ),
					esc_html( $setting_id )
				)
			);
		}

		$meta_key = Recipe_Setting_Id::meta_key( $setting_id );
		if ( null === $meta_key ) {
			// Defensive — is_editable should have guaranteed a key.
			throw new InvalidArgumentException(
				sprintf(
					// translators: %s is the recipe-setting ID that was rejected.
					esc_html_x( "No meta key resolved for editable setting '%s'", 'Recipe store setting update error', 'uncanny-automator' ),
					esc_html( $setting_id )
				)
			);
		}

		$legacy = $this->convert_setting_fields_to_legacy( $setting_id, $fields, $recipe_id );

		update_post_meta( $recipe_id, $meta_key, $legacy );

		Recipe_Cache::invalidate( $recipe_id );
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

		// Request WordPress errors: a refused insert otherwise answers 0, and get( 0 ) reads the global post.
		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_title'  => $recipe->get_recipe_title()->get_value(),
				'post_status' => $recipe->get_recipe_status()->get_value(),
				'meta_input'  => $meta_input,
			),
			true
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

		// Request WordPress errors so a rejected status write cannot emit a save event.
		$result = wp_update_post(
			array(
				'ID'          => $recipe_id,
				'post_title'  => $recipe->get_recipe_title()->get_value(),
				'post_status' => $recipe->get_recipe_status()->get_value(),
			),
			true
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

		Recipe_Cache::invalidate( $recipe_id );
	}

	/**
	 * Retrieve all recipes with optional filtering and sorting.
	 *
	 * Supports filtering by status, type, title search, and meta queries.
	 * Type is filtered before pagination.
	 *
	 * @param array $filters {
	 *     Optional filtering parameters.
	 *     @type string $status Recipe status ('draft', 'publish', 'trash').
	 *     @type string $type Recipe type ('user', 'anonymous').
	 *     @type string $title Text search in recipe titles.
	 *     @type int    $limit Maximum results to return.
	 *     @type int    $offset Number of matching results to skip.
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

		if ( isset( $filters['offset'] ) ) {
			$query_args['offset'] = max( 0, (int) $filters['offset'] );
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

		// Apply type before pagination; legacy empty or missing type means user.
		if ( ! empty( $filters['type'] ) ) {
			$type       = ( new Recipe_User_Type( $filters['type'] ) )->get_value();
			$type_query = array(
				'key'   => self::_META_RECIPE_TYPE,
				'value' => $type,
			);
			if ( 'user' === $type ) {
				$type_query = array(
					'relation' => 'OR',
					array(
						'key'     => self::_META_RECIPE_TYPE,
						'value'   => array( 'user', '', '0' ),
						'compare' => 'IN',
					),
					array(
						'key'     => self::_META_RECIPE_TYPE,
						'compare' => 'NOT EXISTS',
					),
				);
			}
			$query_args['meta_query'][] = $type_query;
		}

		return $query_args;
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
		return $config
			->times_per_user( $this->read_limit( $post_id, self::_META_RECIPE_TIMES_PER_USER, self::_LEGACY_META_RECIPE_TIMES_PER_USER ) )
			->total_times( $this->read_limit( $post_id, self::_META_RECIPE_TOTAL_TIMES, self::_LEGACY_META_RECIPE_TOTAL_TIMES ) );
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
		return $config->total_times(
			$this->read_limit( $post_id, self::_META_RECIPE_TOTAL_TIMES, self::_LEGACY_META_RECIPE_TOTAL_TIMES )
		);
	}

	/**
	 * Resolve a completion limit to a canonical int|null.
	 *
	 * Present-vs-absent invariant: when the new field meta was NEVER written
	 * (get_post_meta returns ''), fall back to the legacy-shaped meta (one-way
	 * read for old/duplicated/imported recipes). When the field is present —
	 * including a cleared empty array — coerce it and never consult the legacy
	 * key, so duplication can't resurrect a deliberately-cleared limit.
	 *
	 * @param int    $post_id WordPress post ID.
	 * @param string $meta_key New-field meta key.
	 * @param string $legacy_meta_key Legacy-shaped meta key.
	 * @return int|null Positive limit, or null for unlimited.
	 */
	private function read_limit( int $post_id, string $meta_key, string $legacy_meta_key ): ?int {
		// Read the raw meta directly here — do NOT route limits through the
		// throttle path's extract_meta_fields()/`! empty()` collapse, which
		// flattens an empty array to '' and would erase the cleared-vs-absent
		// distinction this invariant depends on.
		$raw = get_post_meta( $post_id, $meta_key, true );

		if ( '' === $raw ) {
			return $this->coerce_limit_meta(
				get_post_meta( $post_id, $legacy_meta_key, true )
			);
		}

		return $this->coerce_limit_meta( $raw );
	}

	/**
	 * Extract the limit scalar from the new-field storage shape
	 * ({type,value,backup} or []) — or a bare legacy scalar — and coerce it
	 * to a canonical int|null. 0 is a real (blocking) limit; negative / -1 /
	 * empty / non-numeric → null (unset).
	 *
	 * @param mixed $stored New-field meta value or bare legacy scalar.
	 * @return int|null
	 */
	private function coerce_limit_meta( $stored ): ?int {
		$unserialized = is_array( $stored ) ? $stored : automator_safe_unserialize( $stored );
		$scalar       = is_array( $unserialized ) ? ( $unserialized['value'] ?? null ) : $unserialized;
		if ( ! is_numeric( $scalar ) ) {
			return null;
		}
		$int = (int) $scalar;
		return $int < 0 ? null : $int; // 0 is a real limit; negative/-1 => unset.
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
	 * Branch on setting_id and produce the on-disk legacy format from
	 * wire FieldsOutput input. Reuses the existing private formatters so
	 * the on-disk shape matches what a full aggregate save() would write.
	 *
	 * @return array Legacy on-disk format for the meta value.
	 */
	private function convert_setting_fields_to_legacy( string $setting_id, array $fields, int $recipe_id ): array {
		switch ( $setting_id ) {
			case Recipe_Setting_Id::RECIPE_THROTTLE:
				$recipe_type = $this->resolve_recipe_type_for_write( $recipe_id );
				$domain      = $this->fields_to_throttle_domain( $fields, $recipe_type );
				return $this->format_throttle( $domain, $recipe_type );

			case Recipe_Setting_Id::RECIPE_TOTAL_TIMES:
				return $this->convert_total_times_to_legacy( $this->fields_to_times_value( $fields ) );

			case Recipe_Setting_Id::RECIPE_TIMES_PER_USER:
				return $this->convert_times_per_user_to_legacy( $this->fields_to_times_value( $fields ) );
		}
		throw new InvalidArgumentException(
			sprintf(
				// translators: %s is the recipe-setting ID that was unhandled.
				esc_html_x( "Unhandled editable setting '%s'", 'Recipe store setting update error', 'uncanny-automator' ),
				esc_html( $setting_id )
			)
		);
	}

	/**
	 * Extract a clean throttle domain shape from wire FieldsOutput input.
	 *
	 * @return array{enabled: bool, duration: int|null, unit: string|null, scope?: string|null}
	 */
	private function fields_to_throttle_domain( array $fields, string $recipe_type ): array {
		$enabled = isset( $fields['ENABLED']['value'] ) ? Field_Value_Normalizer::to_bool( $fields['ENABLED']['value'] ) : false;
		if ( ! $enabled ) {
			$shape = array(
				'enabled'  => false,
				'duration' => null,
				'unit'     => null,
			);
			if ( 'user' === $recipe_type ) {
				$shape['scope'] = null;
			}
			return $shape;
		}

		$duration = isset( $fields['NUMBER']['value'] ) ? (int) $fields['NUMBER']['value'] : Recipe_Throttle::DEFAULT_DURATION;
		$unit     = isset( $fields['UNIT']['value'] ) ? (string) $fields['UNIT']['value'] : Recipe_Throttle::DEFAULT_UNIT;

		$shape = array(
			'enabled'  => true,
			'duration' => max( Recipe_Throttle::DEFAULT_DURATION, $duration ),
			'unit'     => Recipe_Throttle::coerce_unit( $unit ),
		);

		if ( 'user' === $recipe_type ) {
			$scope          = isset( $fields['PER_RECIPE_OR_USER']['value'] ) ? (string) $fields['PER_RECIPE_OR_USER']['value'] : Recipe_Throttle::DEFAULT_SCOPE;
			$shape['scope'] = Recipe_Throttle::coerce_scope( $scope );
		}

		return $shape;
	}

	/**
	 * Extract a nullable int from wire FieldsOutput's TIMES envelope.
	 *
	 * Plain extraction only: a missing TIMES value → null, otherwise the int.
	 * The "non-positive means no limit" clamp lives once in limit_to_legacy_meta()
	 * so every write path applies it identically.
	 *
	 * @param array $fields Wire FieldsOutput for the limit setting.
	 * @return int|null
	 */
	private function fields_to_times_value( array $fields ): ?int {
		return isset( $fields['TIMES']['value'] ) ? (int) $fields['TIMES']['value'] : null;
	}

	/**
	 * Read the recipe_type meta directly (used by update_setting to know
	 * whether throttle gets a scope field). Cheap single-meta read; the
	 * full hydration in get() is unnecessary here.
	 */
	private function resolve_recipe_type_for_write( int $recipe_id ): string {
		$type = get_post_meta( $recipe_id, self::_META_RECIPE_TYPE, true );
		return ! empty( $type ) ? (string) $type : 'user';
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
			'duration' => Recipe_Throttle::DEFAULT_DURATION,
			'unit'     => Recipe_Throttle::DEFAULT_UNIT,
		);

		/** User recipes require scope configuration for throttling context */
		if ( 'user' === $recipe_type ) {
			$defaults['scope'] = Recipe_Throttle::DEFAULT_SCOPE;
		}

		if ( is_null( $legacy_data ) ) {
			return $defaults;
		}

		/** WordPress meta handling is inconsistent - data may arrive pre-unserialized */
		$unserialized = is_array( $legacy_data ) ? $legacy_data : automator_safe_unserialize( $legacy_data );
		if ( ! is_array( $unserialized ) ) {
			return $defaults;
		}

		$enabled  = isset( $unserialized['ENABLED']['value'] ) ? Field_Value_Normalizer::to_bool( $unserialized['ENABLED']['value'] ) : false;
		$duration = isset( $unserialized['NUMBER']['value'] ) ? (int) $unserialized['NUMBER']['value'] : Recipe_Throttle::DEFAULT_DURATION;
		$unit     = isset( $unserialized['UNIT']['value'] ) ? $unserialized['UNIT']['value'] : Recipe_Throttle::DEFAULT_UNIT;

		$result = array(
			'enabled'  => $enabled,
			'duration' => max( Recipe_Throttle::DEFAULT_DURATION, $duration ),
			'unit'     => Recipe_Throttle::coerce_unit( $unit ),
		);

		/** Scope validation ensures only valid throttling contexts are persisted */
		if ( 'user' === $recipe_type ) {
			$scope           = isset( $unserialized['PER_RECIPE_OR_USER']['value'] ) ? $unserialized['PER_RECIPE_OR_USER']['value'] : Recipe_Throttle::DEFAULT_SCOPE;
			$result['scope'] = Recipe_Throttle::coerce_scope( $scope );
		}

		return $result;
	}

	/**
	 * Format per-user execution limit for WordPress meta storage.
	 *
	 * @param int|null $times_per_user Maximum executions allowed per user.
	 * @return array Legacy format with backup metadata for UI compatibility.
	 * @since 7.0.0
	 */
	private function convert_times_per_user_to_legacy( ?int $times_per_user ): array {
		return $this->limit_to_legacy_meta(
			$times_per_user,
			esc_html_x( 'Allowed runs per user', 'Recipe times per user configuration label', 'uncanny-automator' )
		);
	}

	/**
	 * Format global execution limit for WordPress meta storage.
	 *
	 * @param int|null $total_times Maximum total executions across all users.
	 * @return array Legacy format or empty array for unlimited executions.
	 * @since 7.0.0
	 */
	private function convert_total_times_to_legacy( ?int $total_times ): array {
		return $this->limit_to_legacy_meta(
			$total_times,
			esc_html_x( 'Total allowed runs', 'Recipe total times configuration label', 'uncanny-automator' )
		);
	}

	/**
	 * Build the legacy post-meta shape for a completion limit. A null or
	 * non-positive value (no limit) → empty array (the on-disk "unset"
	 * sentinel); a positive value → the legacy `{type,value,backup}` shape.
	 * The `backup` block is retained for UI/sentence compatibility.
	 *
	 * @param int|null $value Limit value (positive = a limit; <= 0 / null = no limit).
	 * @param string   $label Field label for the backup block.
	 * @return array
	 */
	private function limit_to_legacy_meta( ?int $value, string $label ): array {
		// The single write-clamp every write path converges on (settings-update
		// AND full CRUD/create): a non-positive (or null) limit means "no limit"
		// → persist the empty-array sentinel. New writes therefore never store a
		// real 0. A stored 0 can only be pre-existing legacy data (there is no
		// blanket migration), which the read path (coerce_limit_meta) resolves
		// as a real, blocking limit — matching released enforcement.
		if ( is_null( $value ) || $value <= 0 ) {
			return array();
		}

		return array(
			'type'   => 'string',
			'value'  => (string) $value,
			'backup' => array(
				'label'                  => $label,
				'show_label_in_sentence' => true,
			),
		);
	}


	/**
	 * The plugin version a recipe was last written by.
	 *
	 * @param int $recipe_id Recipe post ID.
	 *
	 * @return string Version string, or empty when never stamped.
	 */
	public function get_version( int $recipe_id ): string {
		if ( $recipe_id < 1 ) {
			return '';
		}

		return (string) get_post_meta( $recipe_id, self::$recipe_version_key, true );
	}

	/**
	 * Read the recipe vintage straight from the table, bypassing the per-request meta cache.
	 *
	 * @param int $recipe_id Recipe post ID.
	 *
	 * @return string The stored version, '' when none.
	 */
	public function get_version_uncached( int $recipe_id ): string {
		if ( $recipe_id < 1 ) {
			return '';
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$version = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s LIMIT 1",
				$recipe_id,
				self::$recipe_version_key
			)
		);

		return (string) $version;
	}

	/**
	 * {@inheritDoc}
	 */
	public function census_rows(): array {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT p.ID AS id,
				        p.post_status,
				        COALESCE( NULLIF( t.meta_value, '' ), 'user' ) AS recipe_type,
				        COALESCE( v.meta_value, '' ) AS version
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} t ON t.post_id = p.ID AND t.meta_key = %s
				 LEFT JOIN {$wpdb->postmeta} v ON v.post_id = p.ID AND v.meta_key = %s
				 WHERE p.post_type = %s
				   AND p.post_status != 'trash'
				 ORDER BY p.ID ASC, t.meta_id ASC, v.meta_id ASC",
				self::_META_RECIPE_TYPE,
				self::$recipe_version_key,
				self::POST_TYPE
			),
			ARRAY_A
		);

		// One row per recipe: the first joined meta row by meta_id is the stored value.
		$output = array();
		$seen   = array();
		foreach ( (array) $rows as $row ) {
			$id = (int) $row['id'];
			if ( isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$output[]    = array(
				'id'          => $id,
				'post_status' => (string) $row['post_status'],
				'recipe_type' => (string) $row['recipe_type'],
				'version'     => (string) $row['version'],
			);
		}

		return $output;
	}

	/**
	 * Stamp the recipe with a plugin version, exactly as a save would.
	 *
	 * @param int    $recipe_id Recipe post ID.
	 * @param string $version   Version to record.
	 *
	 * @return void
	 */
	public function set_version( int $recipe_id, string $version ): void {
		if ( $recipe_id < 1 ) {
			return;
		}

		update_post_meta( $recipe_id, self::$recipe_version_key, $version );
	}

	/**
	 * Deprecated one-line accessor retained for 8.0.0.
	 *
	 * Its callers (the recipe-item REST classes) move off it in a later PR;
	 * deleting it here would break them in the interim. Removed in the
	 * post-8.0.0 cleanup pass together with those callers.
	 *
	 * @deprecated 8.0.0
	 */
	public function get_post_type(): string {
		return self::POST_TYPE;
	}
}
