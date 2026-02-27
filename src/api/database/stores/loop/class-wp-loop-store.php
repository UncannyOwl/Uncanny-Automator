<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Database\Stores\Loop;

use Exception;
use Uncanny_Automator\Api\Components\Loop\Loop;
use Uncanny_Automator\Api\Components\Loop\Loop_Config;
use Uncanny_Automator\Api\Components\Loop\Enums\Loop_Status;
use Uncanny_Automator\Api\Database\Interfaces\Loop\Loop_Store;
use Uncanny_Automator\Api\Database\Interfaces\Loop\Filter_Store;

/**
 * WordPress Loop Store.
 *
 * WordPress implementation of loop store using uo-loop post type.
 * Stores loop instances as posts with meta for all properties.
 * Delegates filter operations to the Filter store.
 *
 * @since 7.0.0
 */
class WP_Loop_Store implements Loop_Store {

	/**
	 * Post type for loops.
	 */
	const POST_TYPE = 'uo-loop';

	/**
	 * WordPress database object.
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Filter store for managing filter entities.
	 *
	 * @var Filter_Store
	 */
	private Filter_Store $filter_store;

	/**
	 * Constructor.
	 *
	 * @param \wpdb        $wpdb         WordPress database object.
	 * @param Filter_Store $filter_store Filter store instance.
	 */
	public function __construct( \wpdb $wpdb, Filter_Store $filter_store ) {
		$this->wpdb         = $wpdb;
		$this->filter_store = $filter_store;
	}

	/**
	 * Save loop to database.
	 *
	 * @param Loop $loop Loop to save.
	 * @return Loop The saved Loop with ID and all persisted values.
	 * @throws \Exception If save fails.
	 */
	public function save( Loop $loop ): Loop {

		if ( $loop->is_persisted() ) {
			return $this->update_loop( $loop );
		}

		return $this->create_loop( $loop );
	}

	/**
	 * Get loop by ID.
	 *
	 * @param int $loop_id Loop ID.
	 * @return Loop|null Loop or null if not found.
	 */
	public function get( int $loop_id ): ?Loop {
		$post = $this->get_wp_post( $loop_id );

		if ( ! $post ) {
			return null;
		}

		return $this->build_loop_from_post( $post );
	}

	/**
	 * Get WP_Post object for loop.
	 *
	 * @param int $loop_id Loop ID.
	 * @return \WP_Post|null WP_Post object or null if not found.
	 */
	public function get_wp_post( int $loop_id ): ?\WP_Post {
		$post = get_post( $loop_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return $post;
	}

	/**
	 * Delete loop and its filters.
	 *
	 * @param Loop $loop Loop to delete.
	 * @return void
	 * @throws \Exception If delete fails.
	 */
	public function delete( Loop $loop ): void {
		if ( ! $loop->is_persisted() ) {
			throw new Exception( esc_html_x( 'Cannot delete unsaved loop', 'Loop store delete error', 'uncanny-automator' ) );
		}

		$loop_id = $loop->get_loop_id()->get_value();

		// Delete all filters first via filter store
		$this->filter_store->delete_loop_filters( $loop_id );

		// Delete the loop post
		$result = wp_delete_post( $loop_id, true );

		if ( ! $result ) {
			// translators: %s is the loop ID.
			throw new Exception( sprintf( esc_html_x( 'Failed to delete loop with ID: %s', 'Loop store delete error with ID', 'uncanny-automator' ), absint( $loop_id ) ) );
		}
	}

	/**
	 * Get all loops with optional filters.
	 *
	 * @param array $filters Optional filters (recipe_id, status, limit, etc.).
	 * @return Loop[] Array of loops.
	 */
	public function all( array $filters = array() ): array {

		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => array( Loop_Status::PUBLISH, Loop_Status::DRAFT ),
			'posts_per_page' => $filters['limit'] ?? 100,
			'orderby'        => 'ID',
			'order'          => 'ASC',
		);

		if ( ! empty( $filters['recipe_id'] ) ) {
			$args['post_parent'] = absint( $filters['recipe_id'] );
		}

		if ( ! empty( $filters['status'] ) ) {
			$args['post_status'] = $filters['status'];
		}

		$posts = get_posts( $args );

		if ( is_wp_error( $posts ) ) {
			return array();
		}

		$loops = array();

		foreach ( $posts as $post ) {
			$loop = $this->build_loop_from_post( $post );
			if ( $loop ) {
				$loops[] = $loop;
			}
		}

		return $loops;
	}

	/**
	 * Get loops for a specific recipe.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return Loop[] Array of loops for the recipe.
	 */
	public function get_recipe_loops( int $recipe_id ): array {
		return $this->all( array( 'recipe_id' => $recipe_id ) );
	}

	/**
	 * Check if a loop exists.
	 *
	 * @param int $id Loop ID.
	 * @return bool True if exists.
	 */
	public function exists( int $id ): bool {
		$post = get_post( $id );
		return $post && self::POST_TYPE === $post->post_type;
	}

	/**
	 * Get the filter store.
	 *
	 * @return Filter_Store Filter store instance.
	 */
	public function get_filter_store(): Filter_Store {
		return $this->filter_store;
	}

	/**
	 * Create new loop.
	 *
	 * @param Loop $loop Loop to create.
	 * @return Loop The created Loop with generated ID.
	 * @throws \Exception If creation fails.
	 */
	private function create_loop( Loop $loop ): Loop {
		$loop_data = $loop->to_array();

		$post_data = array(
			'post_type'   => self::POST_TYPE,
			'post_title'  => $this->generate_loop_title( $loop ),
			'post_parent' => $loop_data['recipe_id'],
			'post_status' => $loop_data['status'] ?? Loop_Status::DRAFT,
			'menu_order'  => $loop_data['_ui_order'] ?? 2,
			'meta_input'  => $this->prepare_meta_data( $loop ),
		);

		$loop_id = wp_insert_post( $post_data );

		if ( is_wp_error( $loop_id ) ) {
			// translators: %s is the error message.
			throw new Exception( sprintf( esc_html_x( 'Failed to create loop: %s', 'Loop store creation error with message', 'uncanny-automator' ), esc_html( $loop_id->get_error_message() ) ) );
		}

		if ( ! $loop_id ) {
			throw new Exception( esc_html_x( 'Failed to create loop: unknown error', 'Loop store creation unknown error', 'uncanny-automator' ) );
		}

		// Save filters via filter store
		$order = 0;
		foreach ( $loop->get_filters() as $filter ) {
			$this->filter_store->save( $loop_id, $filter, $order );
			++$order;
		}

		// Reload and return the persisted loop
		$persisted_loop = $this->get( $loop_id );

		if ( null === $persisted_loop ) {
			// translators: %s is the loop ID.
			throw new Exception( sprintf( esc_html_x( 'Failed to reload loop after creation: %s', 'Loop store reload error', 'uncanny-automator' ), absint( $loop_id ) ) );
		}

		return $persisted_loop;
	}

	/**
	 * Update existing loop.
	 *
	 * @param Loop $loop Loop to update.
	 * @return Loop The updated Loop with all persisted values.
	 * @throws \Exception If update fails.
	 */
	private function update_loop( Loop $loop ): Loop {
		$loop_data = $loop->to_array();
		$loop_id   = $loop_data['id'];

		$post_data = array(
			'ID'          => $loop_id,
			'post_title'  => $this->generate_loop_title( $loop ),
			'post_status' => $loop_data['status'] ?? Loop_Status::DRAFT,
			'post_parent' => $loop_data['recipe_id'],
			'menu_order'  => $loop_data['_ui_order'] ?? 2,
		);

		$result = wp_update_post( $post_data );

		if ( is_wp_error( $result ) ) {
			// translators: %s is the error message.
			throw new Exception( sprintf( esc_html_x( 'Failed to update loop: %s', 'Loop store update error with message', 'uncanny-automator' ), esc_html( $result->get_error_message() ) ) );
		}

		// Update meta data
		$meta_data = $this->prepare_meta_data( $loop );
		foreach ( $meta_data as $key => $value ) {
			update_post_meta( $loop_id, $key, $value );
		}

		// Sync filters via filter store
		$this->filter_store->sync( $loop_id, $loop->get_filters() );

		// Reload and return the updated loop
		$updated_loop = $this->get( $loop_id );

		if ( null === $updated_loop ) {
			// translators: %s is the loop ID.
			throw new Exception( sprintf( esc_html_x( 'Failed to reload loop after update: %s', 'Loop store reload error', 'uncanny-automator' ), absint( $loop_id ) ) );
		}

		return $updated_loop;
	}

	/**
	 * Build loop from WordPress post.
	 *
	 * @param \WP_Post $post WordPress post.
	 * @return Loop|null Loop or null if invalid.
	 */
	public function build_loop_from_post( \WP_Post $post ): ?Loop {
		if ( ! isset( $post->ID ) ) {
			return null;
		}

		// Get iterable expression from meta
		$iterable_expression = get_post_meta( $post->ID, 'iterable_expression', true );
		if ( ! is_array( $iterable_expression ) ) {
			$iterable_expression = array( 'type' => 'users' );
		}

		// Get run_on from meta
		$run_on = get_post_meta( $post->ID, 'run_on', true );

		// Get filters for this loop via filter store
		$filters = $this->filter_store->get_loop_filter_data( $post->ID );

		// Get items (action IDs) for this loop
		$items = $this->get_loop_items( $post->ID );

		// Build config
		$config = ( new Loop_Config() )
			->id( (int) $post->ID )
			->recipe_id( $post->post_parent )
			->status( $post->post_status )
			->ui_order( $post->menu_order ? $post->menu_order : 2 )
			->iterable_expression( $iterable_expression )
			->run_on( $run_on ? $run_on : null )
			->filters( $filters )
			->items( $items );

		try {
			return new Loop( $config );
		} catch ( \Exception $e ) {
			return null;
		}
	}

	/**
	 * Get items (action IDs) for a loop.
	 *
	 * @param int $loop_id Loop ID.
	 * @return array Array of action IDs.
	 */
	private function get_loop_items( int $loop_id ): array {
		$action_posts = get_posts(
			array(
				'post_type'      => 'uo-action',
				'post_parent'    => $loop_id,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);

		return array_map( 'intval', $action_posts );
	}

	/**
	 * Prepare meta data for saving.
	 *
	 * @param Loop $loop Loop entity.
	 * @return array Meta data array.
	 */
	private function prepare_meta_data( Loop $loop ): array {
		$loop_data = $loop->to_array();

		$meta_data = array(
			'iterable_expression' => $loop_data['iterable_expression'],
		);

		if ( null !== $loop_data['run_on'] ) {
			$meta_data['run_on'] = $loop_data['run_on'];
		}

		return $meta_data;
	}

	/**
	 * Generate post title for loop.
	 *
	 * @param Loop $loop Loop entity.
	 * @return string Generated title.
	 */
	private function generate_loop_title( Loop $loop ): string {
		$expression = $loop->get_expression();
		$type       = $expression->get_type()->get_value();

		// translators: %s is the iteration type (users, posts, token).
		return sprintf( esc_html_x( 'Loop: %s', 'Loop post title', 'uncanny-automator' ), esc_html( ucfirst( $type ) ) );
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
