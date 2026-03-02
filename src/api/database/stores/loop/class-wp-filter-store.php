<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Database\Stores\Loop;

use Exception;
use Uncanny_Automator\Api\Components\Loop\Filter\Filter;
use Uncanny_Automator\Api\Components\Loop\Filter\Config as Filter_Config;
use Uncanny_Automator\Api\Database\Interfaces\Loop\Filter_Store;

/**
 * WordPress Filter Store.
 *
 * Persists Filter entities to uo-loop-filter post type.
 * Simple persistence layer - no business logic or transformations.
 *
 * @since 7.0.0
 */
class WP_Filter_Store implements Filter_Store {

	const POST_TYPE = 'uo-loop-filter';

	private \wpdb $wpdb;

	/**
	 * Constructor.
	 *
	 * @param \wpdb $wpdb WordPress database instance.
	 */
	public function __construct( \wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}
	/**
	 * Save.
	 *
	 * @param int $loop_id The ID.
	 * @param Filter $filter The filter.
	 * @param int $menu_order The order.
	 * @return Filter
	 */
	public function save( int $loop_id, Filter $filter, int $menu_order = 0 ): Filter {
		return $filter->is_persisted()
			? $this->update_filter( $filter, $menu_order )
			: $this->create_filter( $loop_id, $filter, $menu_order );
	}
	/**
	 * Get.
	 *
	 * @param int $filter_id The ID.
	 * @return ?
	 */
	public function get( int $filter_id ): ?Filter {
		$post = $this->get_wp_post( $filter_id );
		return $post ? $this->build_filter_from_post( $post ) : null;
	}
	/**
	 * Get wp post.
	 *
	 * @param int $filter_id The ID.
	 * @return ?
	 */
	public function get_wp_post( int $filter_id ): ?\WP_Post {
		$post = get_post( $filter_id );
		return ( $post && self::POST_TYPE === $post->post_type ) ? $post : null;
	}
	/**
	 * Delete.
	 *
	 * @param Filter $filter The filter.
	 */
	public function delete( Filter $filter ): void {
		if ( ! $filter->is_persisted() ) {
			throw new Exception( 'Cannot delete unsaved filter' );
		}
		$this->delete_by_id( $filter->get_id()->get_value() );
	}
	/**
	 * Delete by id.
	 *
	 * @param int $id The ID.
	 */
	public function delete_by_id( int $id ): void {
		wp_delete_post( $id, true );
	}
	/**
	 * Get loop filters.
	 *
	 * @param int $loop_id The ID.
	 * @return array
	 */
	public function get_loop_filters( int $loop_id ): array {
		$posts   = $this->query_loop_filters( $loop_id );
		$filters = array();

		foreach ( $posts as $post ) {
			$filter = $this->build_filter_from_post( $post );
			if ( $filter ) {
				$filters[] = $filter;
			}
		}

		return $filters;
	}
	/**
	 * Get loop filter data.
	 *
	 * @param int $loop_id The ID.
	 * @return array
	 */
	public function get_loop_filter_data( int $loop_id ): array {
		$posts   = $this->query_loop_filters( $loop_id );
		$filters = array();

		foreach ( $posts as $post ) {
			$filters[] = $this->extract_filter_data( $post );
		}

		return $filters;
	}
	/**
	 * Delete loop filters.
	 *
	 * @param int $loop_id The ID.
	 */
	public function delete_loop_filters( int $loop_id ): void {
		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_parent'    => $loop_id,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
		}
	}
	/**
	 * Sync.
	 *
	 * @param int $loop_id The ID.
	 * @param array $filters The filter.
	 */
	public function sync( int $loop_id, array $filters ): void {
		$existing_ids = array_column( $this->get_loop_filter_data( $loop_id ), 'id' );
		$keep_ids     = array();
		$order        = 0;

		foreach ( $filters as $filter ) {
			$filter_id = $filter->get_id()->get_value();

			if ( $filter_id && in_array( $filter_id, $existing_ids, true ) ) {
				$this->update_filter( $filter, $order );
				$keep_ids[] = $filter_id;
			} else {
				$this->create_filter( $loop_id, $filter, $order );
			}

			++$order;
		}

		foreach ( array_diff( $existing_ids, $keep_ids ) as $delete_id ) {
			$this->delete_by_id( $delete_id );
		}
	}
	/**
	 * Exists.
	 *
	 * @param int $id The ID.
	 * @return bool
	 */
	public function exists( int $id ): bool {
		$post = get_post( $id );
		return $post && self::POST_TYPE === $post->post_type;
	}
	/**
	 * Get post type.
	 *
	 * @return string
	 */
	public function get_post_type(): string {
		return self::POST_TYPE;
	}
	/**
	 * Query loop filters.
	 *
	 * @param int $loop_id The ID.
	 * @return array
	 */
	private function query_loop_filters( int $loop_id ): array {
		return get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_parent'    => $loop_id,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
			)
		);
	}
	/**
	 * Create filter.
	 *
	 * @param int $loop_id The ID.
	 * @param Filter $filter The filter.
	 * @param int $menu_order The order.
	 * @return Filter
	 */
	private function create_filter( int $loop_id, Filter $filter, int $menu_order = 0 ): Filter {
		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_title'  => $filter->get_code()->get_value(),
				'post_parent' => $loop_id,
				'post_status' => 'publish',
				'menu_order'  => $menu_order,
				'meta_input'  => $this->build_meta( $filter ),
			)
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			throw new Exception( 'Failed to create filter' );
		}

		$filter = $this->get( $post_id );
		if ( null === $filter ) {
			throw new Exception( 'Failed to reload filter after creation' );
		}
		return $filter;
	}
	/**
	 * Update filter.
	 *
	 * @param Filter $filter The filter.
	 * @param int $menu_order The order.
	 * @return Filter
	 */
	private function update_filter( Filter $filter, int $menu_order = 0 ): Filter {
		$filter_id = $filter->get_id()->get_value();

		$result = wp_update_post(
			array(
				'ID'          => $filter_id,
				'post_title'  => $filter->get_code()->get_value(),
				'post_status' => 'publish',
				'menu_order'  => $menu_order,
			)
		);

		if ( is_wp_error( $result ) ) {
			throw new Exception( 'Failed to update filter' );
		}

		foreach ( $this->build_meta( $filter ) as $key => $value ) {
			update_post_meta( $filter_id, $key, $value );
		}

		$filter = $this->get( $filter_id );
		if ( null === $filter ) {
			throw new Exception( 'Failed to reload filter after update' );
		}
		return $filter;
	}
	/**
	 * Build meta.
	 *
	 * @param Filter $filter The filter.
	 * @return array
	 */
	private function build_meta( Filter $filter ): array {
		$data = $filter->to_array();

		return array(
			'code'                    => $data['code'],
			'integration_code'        => $data['integration_code'],
			'integration'             => $data['integration_code'],
			'integration_name'        => $data['integration_name'] ?? $data['integration_code'],
			'type'                    => $data['filter_type'] ?? 'lite',
			'user_type'               => $data['user_type'] ?? 'user',
			'fields'                  => wp_json_encode( $data['fields'] ),
			'backup'                  => wp_json_encode( $data['backup'] ),
			'uap_loop-filter_version' => $data['version'] ?? AUTOMATOR_PLUGIN_VERSION,
		);
	}
	/**
	 * Build filter from post.
	 *
	 * @param \ $post The post.
	 * @return ?
	 */
	private function build_filter_from_post( \WP_Post $post ): ?Filter {
		try {
			return new Filter( Filter_Config::from_array( $this->extract_filter_data( $post ) ) );
		} catch ( \Exception $e ) {
			return null;
		}
	}
	/**
	 * Extract filter data.
	 *
	 * @param \ $post The post.
	 * @return array
	 */
	private function extract_filter_data( \WP_Post $post ): array {
		$fields_json = get_post_meta( $post->ID, 'fields', true );
		$backup_json = get_post_meta( $post->ID, 'backup', true );
		$type        = (string) get_post_meta( $post->ID, 'type', true );
		$user_type   = (string) get_post_meta( $post->ID, 'user_type', true );

		return array(
			'id'               => (int) $post->ID,
			'code'             => strtoupper( (string) get_post_meta( $post->ID, 'code', true ) ),
			'integration_code' => strtoupper( (string) get_post_meta( $post->ID, 'integration_code', true ) ),
			'integration_name' => (string) get_post_meta( $post->ID, 'integration_name', true ),
			'type'             => '' !== $type ? $type : 'lite',
			'user_type'        => '' !== $user_type ? $user_type : 'user',
			'fields'           => is_string( $fields_json ) ? json_decode( $fields_json, true ) ?? array() : (array) $fields_json,
			'backup'           => is_string( $backup_json ) ? json_decode( $backup_json, true ) ?? array() : (array) $backup_json,
			'version'          => (string) get_post_meta( $post->ID, 'uap_loop-filter_version', true ),
		);
	}
}
