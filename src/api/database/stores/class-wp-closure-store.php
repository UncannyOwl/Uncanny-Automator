<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Database\Stores;

use Exception;
use Uncanny_Automator\Api\Components\Closure\Closure;
use Uncanny_Automator\Api\Components\Closure\Closure_Config;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Id;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Status;
use Uncanny_Automator\Api\Database\Interfaces\Closure_Store;

/**
 * WordPress Closure Store.
 *
 * WordPress implementation of closure store using uo-closure post type.
 * Stores closure instances as posts with meta for all properties.
 *
 * @since 7.0.0
 */
class WP_Closure_Store implements Closure_Store {

	/**
	 * Post type for closures.
	 */
	const POST_TYPE = 'uo-closure';

	/**
	 * WordPress database object.
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Constructor.
	 *
	 * @param \wpdb $wpdb WordPress database object.
	 */
	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Save closure config to database and return closure instance.
	 *
	 * @param Closure_Config $config Closure config to save.
	 * @return Closure Created Closure instance.
	 * @throws \Exception If save fails.
	 */
	public function save( Closure_Config $config ): Closure {

		// Check if closure already exists for this code + recipe_id.
		$existing_id = $this->find_existing_closure( $config );

		if ( null !== $existing_id ) {
			// Set ID on config so Closure has it for update.
			$config->id( $existing_id );
			$closure = new Closure( $config );
			$this->update_closure( $closure );
		} else {
			$closure = new Closure( $config );
			$this->create_closure( $closure, $config );
			// Create new closure instance with updated config that now has the ID
			$closure = new Closure( $config );
		}

		return $closure;
	}

	/**
	 * Get closure by ID.
	 *
	 * @param string $closure_id Closure ID.
	 * @return Closure|null Closure or null if not found.
	 */
	public function get( string $closure_id ): ?Closure {
		$post = $this->get_wp_post( (int) $closure_id );

		if ( ! $post ) {
			return null;
		}

		try {
			return $this->build_closure_from_post( $post );
		} catch ( \Exception $e ) {
			// Return null - post exists but data is corrupted.
			return null;
		}
	}

	/**
	 * Get WP_Post object for closure.
	 *
	 * @param int $closure_id Closure ID.
	 * @return \WP_Post|null WP_Post object or null if not found.
	 */
	public function get_wp_post( int $closure_id ): ?\WP_Post {
		$post = get_post( $closure_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return $post;
	}

	/**
	 * Delete closure.
	 *
	 * @param Closure $closure Closure to delete.
	 * @return void
	 * @throws \Exception If delete fails.
	 */
	public function delete( Closure $closure ): void {

		$closure_id = $closure->get_id();

		// Ensure closure has been persisted (has an ID)
		if ( ! $closure_id ) {
			throw new Exception( esc_html_x( 'Cannot delete unsaved closure', 'Closure store delete error', 'uncanny-automator' ) );
		}

		// Verify post exists and is the correct type
		$post = get_post( $closure_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			// translators: %s is the closure ID.
			throw new Exception( sprintf( esc_html_x( 'Closure with ID %s not found', 'Closure store not found error', 'uncanny-automator' ), absint( $closure_id ) ) );
		}

		$result = wp_delete_post( $closure_id, true );

		if ( ! $result ) {
			// translators: %s is the closure ID.
			throw new Exception( sprintf( esc_html_x( 'Failed to delete closure with ID: %s', 'Closure store delete error with ID', 'uncanny-automator' ), absint( $closure_id ) ) );
		}
	}

	/**
	 * Get all closures with optional filters.
	 *
	 * @param array $filters Optional filters (recipe_id, integration, limit, etc.).
	 * @return Closure[] Array of closures.
	 */
	public function all( array $filters = array() ): array {

		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => array( Recipe_Status::PUBLISH, Recipe_Status::DRAFT ),
			'posts_per_page' => $filters['limit'] ?? 100,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		);

		// Only add post_parent if recipe_id is actually provided
		if ( ! empty( $filters['recipe_id'] ) ) {
			$args['post_parent'] = absint( $filters['recipe_id'] );
		}

		// Add meta queries for filters
		$meta_queries = array();

		if ( ! empty( $filters['integration'] ) ) {
			$meta_queries[] = array(
				'key'     => 'integration',
				'value'   => $filters['integration'],
				'compare' => '=',
			);
		}

		if ( ! empty( $meta_queries ) ) {
			$args['meta_query'] = array(
				'relation' => 'AND',
				...$meta_queries,
			);
		}

		$posts = get_posts( $args );

		// Error handling
		if ( is_wp_error( $posts ) ) {
			return array();
		}

		$closures = array();

		foreach ( $posts as $post ) {
			try {
				$closure    = $this->build_closure_from_post( $post );
				$closures[] = $closure;
			} catch ( \Exception $e ) {
				// Skip invalid closure - don't add to result set.
				continue;
			}
		}

		return $closures;
	}

	/**
	 * Get closures for a specific recipe.
	 *
	 * @param string $recipe_id Recipe ID.
	 * @return Closure[] Array of closures for the recipe.
	 */
	public function get_recipe_closures( string $recipe_id ): array {
		return $this->all( array( 'recipe_id' => $recipe_id ) );
	}

	/**
	 * Get closures by integration.
	 *
	 * @param string $integration Integration code (e.g., 'WP', 'WC').
	 * @return Closure[] Array of closures from the integration.
	 */
	public function get_by_integration( string $integration ): array {
		return $this->all( array( 'integration' => $integration ) );
	}

	/**
	 * Create new closure.
	 *
	 * @param Closure        $closure Closure to create.
	 * @param Closure_Config $config Original config object to update with ID.
	 * @return void
	 * @throws \Exception If creation fails.
	 */
	public function create_closure( Closure $closure, Closure_Config $config ): void {
		// Prepare meta data.
		$meta_data = $this->prepare_meta_data( $closure );

		// Determine status based on REDIRECTURL - empty means draft.
		$redirect_url = $meta_data['REDIRECTURL'] ?? '';
		$post_status  = ! empty( $redirect_url )
			? Recipe_Status::PUBLISH
			: Recipe_Status::DRAFT;

		// Create post.
		$post_data = array(
			'post_type'   => self::POST_TYPE,
			'post_title'  => $this->generate_closure_title( $closure ),
			'post_parent' => $closure->get_recipe_id()->get_value(),
			'post_status' => $post_status,
			'meta_input'  => $meta_data,
		);

		$closure_id = wp_insert_post( $post_data );

		if ( is_wp_error( $closure_id ) ) {
			// translators: %s is the error message.
			throw new Exception( sprintf( esc_html_x( 'Failed to create closure: %s', 'Closure store creation error with message', 'uncanny-automator' ), esc_html( $closure_id->get_error_message() ) ) );
		}

		if ( ! $closure_id ) {
			throw new Exception( esc_html_x( 'Failed to create closure: unknown error', 'Closure store creation unknown error', 'uncanny-automator' ) );
		}

		// Set ID in original config for persistence state
		$config->id( (int) $closure_id );
	}

	/**
	 * Update existing closure.
	 *
	 * @param Closure $closure Closure to update.
	 * @return void
	 * @throws \Exception If update fails.
	 */
	public function update_closure( Closure $closure ): void {
		$closure_id = $closure->get_id();

		// Update post
		$post_data = array(
			'ID'         => $closure_id,
			'post_title' => $this->generate_closure_title( $closure ),
		);

		$result = wp_update_post( $post_data );

		if ( is_wp_error( $result ) ) {
			// translators: %s is the error message.
			throw new Exception( sprintf( esc_html_x( 'Failed to update closure: %s', 'Closure store update error with message', 'uncanny-automator' ), esc_html( $result->get_error_message() ) ) );
		}

		// Update meta data
		$meta_data = $this->prepare_meta_data( $closure );
		foreach ( $meta_data as $key => $value ) {
			update_post_meta( $closure_id, $key, $value );
		}
	}

	/**
	 * Build closure from WordPress post.
	 *
	 * @param \WP_Post $post WordPress post.
	 * @return Closure|null Closure or null if invalid.
	 * @throws \Exception If post data is invalid or VO validation fails.
	 */
	public function build_closure_from_post( \WP_Post $post ): ?Closure {
		// Validate post object has required properties
		if ( ! isset( $post->ID ) ) {
			throw new \Exception( esc_html_x( 'Invalid post object: missing ID property', 'Closure store invalid post error', 'uncanny-automator' ) );
		}

		// Retrieve required fields
		$code        = get_post_meta( $post->ID, 'code', true );
		$integration = get_post_meta( $post->ID, 'integration', true );
		$recipe_id   = $post->post_parent;

		// Validate required fields are present
		if ( empty( $code ) ) {
			// translators: %s is the post ID.
			throw new \Exception( sprintf( esc_html_x( 'Closure post %s missing required "code" field', 'Closure store missing code field', 'uncanny-automator' ), absint( $post->ID ) ) );
		}
		if ( empty( $integration ) ) {
			// translators: %s is the post ID.
			throw new \Exception( sprintf( esc_html_x( 'Closure post %s missing required "integration" field', 'Closure store missing integration field', 'uncanny-automator' ), absint( $post->ID ) ) );
		}
		if ( empty( $recipe_id ) ) {
			// translators: %s is the post ID.
			throw new \Exception( sprintf( esc_html_x( 'Closure post %s missing required "recipe_id" (post_parent)', 'Closure store missing recipe_id field', 'uncanny-automator' ), absint( $post->ID ) ) );
		}

		// Get all post meta for this closure
		$all_meta = get_post_meta( $post->ID );

		// Build meta array from all post meta fields
		$meta = array();

		// Core fields that shouldn't go into meta (they're handled separately)
		$core_fields = array( 'integration', 'integration_name', 'code', 'sentence_human_readable', 'sentence_human_readable_html' );

		foreach ( $all_meta as $key => $value_array ) {
			// Skip core fields - they're handled separately
			if ( in_array( $key, $core_fields, true ) ) {
				continue;
			}

			// WordPress stores meta as arrays, get first value
			$meta[ $key ] = $value_array[0] ?? '';

			// Convert recipe_id to integer
			if ( 'recipe_id' === $key && is_numeric( $meta[ $key ] ) ) {
				$meta[ $key ] = (int) $meta[ $key ];
			}
		}

		// Build config with validated required fields
		$integration_name             = get_post_meta( $post->ID, 'integration_name', true );
		$sentence_human_readable      = get_post_meta( $post->ID, 'sentence_human_readable', true );
		$sentence_human_readable_html = get_post_meta( $post->ID, 'sentence_human_readable_html', true );

		$config = ( new Closure_Config() )
			->id( (int) $post->ID )
			->code( $code )
			->recipe_id( new Recipe_Id( (int) $recipe_id ) )
			->integration( $integration )
			->integration_name( ! empty( $integration_name ) ? $integration_name : '' )
			->sentence_human_readable( ! empty( $sentence_human_readable ) ? $sentence_human_readable : '' )
			->sentence_human_readable_html( ! empty( $sentence_human_readable_html ) ? $sentence_human_readable_html : '' );

		// Set meta data
		foreach ( $meta as $key => $value ) {
			$config->set_meta( $key, $value );
		}

		try {
			return new Closure( $config );
		} catch ( \Exception $e ) {
			// translators: %1$s is the post ID, %2$s is the error message.
			throw new \Exception( sprintf( esc_html_x( 'Failed to build closure from post %1$s: %2$s', 'Closure store build error', 'uncanny-automator' ), absint( $post->ID ), esc_html( $e->getMessage() ) ) );
		}
	}

	/**
	 * Prepare meta data for saving.
	 *
	 * @param Closure $closure Closure entity.
	 * @return array Meta data array.
	 */
	public function prepare_meta_data( Closure $closure ): array {
		$config = $closure->to_config();

		// Core fields that must be saved as post meta for legacy compatibility
		$meta_data = array(
			'integration'      => $config->get_integration(),
			'integration_name' => $config->get_integration_name(),
			'code'             => $config->get_code(),
			'recipe_id'        => $config->get_recipe_id() instanceof \Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Id
				? $config->get_recipe_id()->get_value()
				: $config->get_recipe_id(),
		);

		// Add sentence fields from closure
		$meta_data['sentence_human_readable']      = $closure->get_sentence_human_readable();
		$meta_data['sentence_human_readable_html'] = $closure->get_sentence_human_readable_html();

		// Add all user configuration fields
		$all_meta = $config->get_meta();
		if ( ! empty( $all_meta ) && is_array( $all_meta ) ) {
			foreach ( $all_meta as $key => $value ) {
				$meta_data[ $key ] = $value;
			}
		}

		return $meta_data;
	}

	/**
	 * Generate post title for closure.
	 *
	 * @param Closure $closure Closure entity.
	 * @return string Generated title.
	 */
	private function generate_closure_title( Closure $closure ): string {
		$sentence_human_readable = $closure->get_sentence_human_readable();
		$nice_title              = preg_replace( '/{{([^:}]+):[^}]+}}/', '{{$1}}', $sentence_human_readable );

		return $nice_title;
	}

	/**
	 * Find existing closure by config.
	 *
	 * @param Closure_Config $config Closure config.
	 * @return int|null Post ID if exists, null otherwise.
	 */
	private function find_existing_closure( Closure_Config $config ): ?int {
		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => array( Recipe_Status::PUBLISH, Recipe_Status::DRAFT ),
			'posts_per_page' => 1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Intentionally checking for single closure existence.
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => 'code',
					'value' => $config->get_code(),
				),
				array(
					'key'   => 'recipe_id',
					'value' => $config->get_recipe_id()->get_value(),
				),
			),
		);

		$posts = get_posts( $args );
		return ! empty( $posts ) ? (int) $posts[0] : null;
	}

	/**
	 * Get post type.
	 *
	 * @return string Post type.
	 */
	public static function get_post_type(): string {
		return self::POST_TYPE;
	}
}
