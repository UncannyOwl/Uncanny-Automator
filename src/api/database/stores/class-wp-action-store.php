<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Database\Stores;

use Exception;
use Uncanny_Automator\Api\Components\Action\Action;
use Uncanny_Automator\Api\Components\Action\Action_Config;
use Uncanny_Automator\Api\Components\Action\Enums\Action_Status;
use Uncanny_Automator\Api\Database\Interfaces\Action_Store;

/**
 * WordPress Action Store.
 *
 * WordPress implementation of action store using uo-action post type.
 * Stores action instances as posts with meta for all properties.
 *
 * @since 7.0.0
 */
class WP_Action_Store implements Action_Store {

	/**
	 * Post type for actions.
	 */
	const POST_TYPE = 'uo-action';

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
	 * Save action to database.
	 *
	 * @param Action $action Action to save.
	 * @return Action The saved Action with ID and all persisted values.
	 * @throws \Exception If save fails.
	 */
	public function save( Action $action ): Action {

		if ( $action->is_persisted() ) {
			return $this->update_action( $action );
		}

		return $this->create_action( $action );
	}

	/**
	 * Get action by ID.
	 *
	 * @param int $action_id Action ID.
	 * @return Action|null Action or null if not found.
	 */
	public function get( int $action_id ): ?Action {
		$post = $this->get_wp_post( $action_id );

		if ( ! $post ) {
			return null;
		}

		return $this->build_action_from_post( $post );
	}

	/**
	 * Get WP_Post object for action.
	 *
	 * @param int $action_id Action ID.
	 * @return \WP_Post|null WP_Post object or null if not found.
	 */
	public function get_wp_post( int $action_id ): ?\WP_Post {
		$post = get_post( $action_id );

		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return $post;
	}

	/**
	 * Delete action.
	 *
	 * @param Action $action Action to delete.
	 * @return void
	 * @throws \Exception If delete fails.
	 */
	public function delete( Action $action ): void {
		if ( ! $action->is_persisted() ) {
			throw new Exception( esc_html_x( 'Cannot delete unsaved action', 'Action store delete error', 'uncanny-automator' ) );
		}

		$action_id = $action->get_action_id()->get_value();
		$result    = wp_delete_post( $action_id, true );

		if ( ! $result ) {
			// translators: %s is the action ID.
			throw new Exception( sprintf( esc_html_x( 'Failed to delete action with ID: %s', 'Action store delete error with ID', 'uncanny-automator' ), absint( $action_id ) ) );
		}
	}

	/**
	 * Get all actions with optional filters.
	 *
	 * @param array $filters Optional filters (recipe_id, integration, limit, etc.).
	 * @return Action[] Array of actions.
	 */
	public function all( array $filters = array() ): array {

		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => array( Action_Status::PUBLISH, Action_Status::DRAFT ),
			'posts_per_page' => $filters['limit'] ?? 100, // Set reasonable default instead of -1
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

		$actions = array();

		foreach ( $posts as $post ) {
			$action = $this->build_action_from_post( $post );
			if ( $action ) {
				$actions[] = $action;
			}
		}

		return $actions;
	}

	/**
	 * Get actions for a specific recipe.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return Action[] Array of actions for the recipe.
	 */
	public function get_recipe_actions( int $recipe_id ): array {
		return $this->all( array( 'recipe_id' => $recipe_id ) );
	}

	/**
	 * Get actions by integration.
	 *
	 * @param string $integration Integration code (e.g., 'WP', 'WC').
	 * @return Action[] Array of actions from the integration.
	 */
	public function get_by_integration( string $integration ): array {
		return $this->all( array( 'integration' => $integration ) );
	}

	/**
	 * Create new action.
	 *
	 * @param Action $action Action to create.
	 * @return Action The created Action with generated ID.
	 * @throws \Exception If creation fails.
	 */
	private function create_action( Action $action ): Action {
		$action_data = $action->to_array();

		// Determine post_parent: use parent_id if explicitly set, otherwise use recipe_id
		$parent_id = null !== $action->get_parent_id() && null !== $action->get_parent_id()->get_value()
			? $action->get_parent_id()->get_value()
			: $action_data['recipe_id'];

		// Create post
		$post_data = array(
			'post_type'   => self::POST_TYPE,
			'post_title'  => $this->generate_action_title( $action ),
			'post_parent' => $parent_id,
			'post_status' => $action->get_status() ? $action->get_status()->get_value() : Action_Status::DRAFT,
			'meta_input'  => $this->prepare_meta_data( $action ),
		);

		$action_id = wp_insert_post( $post_data );

		if ( is_wp_error( $action_id ) ) {
			// translators: %s is the error message.
			throw new Exception( sprintf( esc_html_x( 'Failed to create action: %s', 'Action store creation error with message', 'uncanny-automator' ), esc_html( $action_id->get_error_message() ) ) );
		}

		if ( ! $action_id ) {
			throw new Exception( esc_html_x( 'Failed to create action: unknown error', 'Action store creation unknown error', 'uncanny-automator' ) );
		}

		// Reload and return the persisted action with all values
		$persisted_action = $this->get( $action_id );

		if ( null === $persisted_action ) {
			// translators: %s is the action ID.
			throw new Exception( sprintf( esc_html_x( 'Failed to reload action after creation: %s', 'Action store reload error', 'uncanny-automator' ), absint( $action_id ) ) );
		}

		return $persisted_action;
	}

	/**
	 * Update existing action.
	 *
	 * @param Action $action Action to update.
	 * @return Action The updated Action with all persisted values.
	 * @throws \Exception If update fails.
	 */
	private function update_action( Action $action ): Action {
		$action_data = $action->to_array();
		$action_id   = $action_data['action_id'];

		// Determine post_parent: use parent_id if explicitly set, otherwise use recipe_id
		$parent_id = null !== $action->get_parent_id() && null !== $action->get_parent_id()->get_value()
			? $action->get_parent_id()->get_value()
			: $action_data['recipe_id'];

		// Update post
		$post_data = array(
			'ID'          => $action_id,
			'post_title'  => $this->generate_action_title( $action ),
			'post_status' => $action->get_status() ? $action->get_status()->get_value() : Action_Status::DRAFT,
			'post_parent' => $parent_id,
		);

		$result = wp_update_post( $post_data );

		if ( is_wp_error( $result ) ) {
			// translators: %s is the error message.
			throw new Exception( sprintf( esc_html_x( 'Failed to update action: %s', 'Action store update error with message', 'uncanny-automator' ), esc_html( $result->get_error_message() ) ) );
		}

		// Update meta data
		$meta_data = $this->prepare_meta_data( $action );
		foreach ( $meta_data as $key => $value ) {
			update_post_meta( $action_id, $key, $value );
		}

		// Reload and return the updated action
		$updated_action = $this->get( $action_id );

		if ( null === $updated_action ) {
			// translators: %s is the action ID.
			throw new Exception( sprintf( esc_html_x( 'Failed to reload action after update: %s', 'Action store reload error', 'uncanny-automator' ), absint( $action_id ) ) );
		}

		return $updated_action;
	}

	/**
	 * Build action from WordPress post.
	 *
	 * @param \WP_Post $post WordPress post.
	 * @return Action|null Action or null if invalid.
	 */
	public function build_action_from_post( \WP_Post $post ): ?Action {
		// Validate post object has required properties
		if ( ! isset( $post->ID ) ) {
			return null;
		}

		// Get all post meta for this action
		$all_meta = get_post_meta( $post->ID );

		// Build meta array from all post meta fields
		$meta = array();

		// Core fields that shouldn't go into meta (they're handled separately)
		$core_fields = array( 'integration', 'code', 'meta_code', 'user_type', 'parent_id', 'status' );

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

		// Build config using the original fluent methods
		$integration_code = get_post_meta( $post->ID, 'integration', true );
		$code             = get_post_meta( $post->ID, 'code', true );
		$meta_code        = get_post_meta( $post->ID, 'meta_code', true );
		$user_type        = get_post_meta( $post->ID, 'user_type', true );
		$parent_id        = get_post_meta( $post->ID, 'parent_id', true );
		$status           = get_post_meta( $post->ID, 'status', true );

		$config = ( new Action_Config() )
			->id( (int) $post->ID )
			->recipe_id( $post->post_parent )
			->integration_code( ! empty( $integration_code ) ? $integration_code : '' )
			->code( ! empty( $code ) ? $code : '' )
			->meta_code( ! empty( $meta_code ) ? $meta_code : '' )
			->user_type( ! empty( $user_type ) ? $user_type : 'user' )
			->status( ! empty( $status ) ? $status : Action_Status::DRAFT )
			->meta( $meta );

		// Set parent_id - defaults to recipe_id as Recipe_Id for backward compatibility
		if ( ! empty( $parent_id ) && is_numeric( $parent_id ) ) {
			$config->parent_id( new \Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Id( (int) $parent_id ) );
		} elseif ( ! empty( $post->post_parent ) && $post->post_parent > 0 ) {
			$config->parent_id( new \Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Id( $post->post_parent ) );
		}

		try {
			return new Action( $config );
		} catch ( \Exception $e ) {
			// Skip invalid action.
			return null;
		}
	}

	/**
	 * Prepare meta data for saving.
	 *
	 * @param Action $action Action entity.
	 * @return array Meta data array.
	 */
	public function prepare_meta_data( Action $action ): array {
		$action_data = $action->to_array();

		// Core fields that must be saved as post meta for legacy compatibility
		$meta_data = array(
			'integration' => $action->get_action_integration_code()->get_value(),
			'code'        => $action->get_action_code()->get_value(),
			'user_type'   => $action->get_action_type()->get_value(),
			'recipe_id'   => $action->get_action_recipe_id()->get_value(),
			'parent_id'   => null !== $action->get_parent_id() ? $action->get_parent_id()->get_value() : null,
			'status'      => null !== $action->get_status() ? $action->get_status()->get_value() : Action_Status::DRAFT,
		);

		// Add sentence fields from action data
		if ( ! empty( $action_data['sentence_human_readable'] ) ) {
			$meta_data['sentence_human_readable'] = $action_data['sentence_human_readable'];
		}

		if ( ! empty( $action_data['sentence_human_readable_html'] ) ) {
			$meta_data['sentence_human_readable_html'] = $action_data['sentence_human_readable_html'];
		}

		// Add all user configuration fields
		if ( ! empty( $action_data['config'] ) && is_array( $action_data['config'] ) ) {
			foreach ( $action_data['config'] as $key => $value ) {
				// Save all config fields as individual post meta entries
				$meta_data[ $key ] = $value;
			}
		}

		// Add async configuration fields as flat structure for legacy compatibility
		if ( ! empty( $action_data['async'] ) && is_array( $action_data['async'] ) ) {
			foreach ( $action_data['async'] as $key => $value ) {
				// Save async fields with 'async_' prefix to maintain flat structure
				// e.g., async_mode, async_delay_number, async_schedule_date, etc.
				$meta_data[ $key ] = $value;
			}
		}

		return $meta_data;
	}

	/**
	 * Generate post title for action.
	 *
	 * @param Action $action Action entity.
	 * @return string Generated title.
	 */
	private function generate_action_title( Action $action ): string {

		$code = $action->get_action_code()->get_value();
		$meta = $action->get_action_meta()->get_value();

		// translators: %s is the action code.
		$sentence_human_readable = $meta['sentence_human_readable'] ?? sprintf( esc_html_x( 'Action: %s', 'Default action title with code', 'uncanny-automator' ), esc_html( $code ) );
		$nice_title              = preg_replace( '/{{([^:}]+):[^}]+}}/', '{{$1}}', $sentence_human_readable );

		return str_replace( array( '{', '}' ), '', $nice_title );
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
