<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Infrastructure\Database\Stores;

use Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception;
use Exception;
use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;
use Uncanny_Automator\App\Events\Recipe_Change_Record;
use Uncanny_Automator\App\Infrastructure\Cache\Recipe_Cache;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Modifier_Store;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Shared\Enums\Post_Status;

/**
 * Block Modifier Store — CPT-backed implementation.
 *
 * Stores block-modifier rows as `uo-block-modifier` posts + `wp_postmeta`.
 * Same surface as a future {@see Block_Modifier_Store_Db} (custom-table
 * impl); switching is a one-line factory swap.
 *
 * Hot-path queries (`get_by_block`, `get_by_recipe`) use indexed `meta_key +
 * meta_value` lookups via WordPress core's `wp_postmeta` index — same time
 * class as a denormalised column on a custom table.
 */
final class WP_Block_Modifier_Store extends WP_Post_Store implements Block_Modifier_Store {

	/**
	 * Post type slug for modifier rows.
	 */
	const POST_TYPE = AUTOMATOR_POST_TYPE_BLOCK_MODIFIER;

	/**
	 * Discriminator post-meta key — narrows rows to a kind of modifier.
	 */
	const META_BLOCK_MODIFIER_TYPE = 'block_modifier_type';

	/**
	 * Cross-recipe lookup post-meta key. Indexed by WordPress core's
	 * `wp_postmeta(meta_key, meta_value)` index.
	 */
	const META_RECIPE_ID = 'recipe_id';

	/**
	 * Redundant-with-post_parent post-meta key, stamped explicitly so
	 * meta-only queries do not need to JOIN `wp_posts`.
	 */
	const META_PARENT_BLOCK_ID = 'parent_block_id';

	/**
	 * Integration code post-meta key (e.g. 'WP', 'GEN').
	 */
	const META_INTEGRATION_CODE = 'integration_code';

	/**
	 * Modifier code post-meta key (e.g. 'TOKEN_MEETS_CONDITION').
	 */
	const META_CODE = 'code';

	/**
	 * JSON-encoded FieldsOutput post-meta key.
	 */
	const META_FIELDS = 'fields';

	/**
	 * JSON-encoded integration display payload post-meta key.
	 */
	const META_BACKUP = 'backup';

	/**
	 * Legacy string-hash id post-meta key (filter conditions only).
	 */
	const META_LEGACY_ID = 'legacy_id';

	/**
	 * Universal backup breadcrumb post-meta key.
	 */
	const META_SYNTHESIS_KEY = '_synthesis_key';

	/**
	 * {@inheritDoc}
	 */
	public function create(
		int $recipe_id,
		int $parent_block_id,
		string $block_modifier_type,
		string $integration_code,
		string $code,
		array $fields = array(),
		array $backup = array(),
		int $menu_order = 0,
		?string $legacy_id = null,
		?string $synthesis_key = null
	): int {

		$meta_input = array(
			self::META_BLOCK_MODIFIER_TYPE => $block_modifier_type,
			self::META_RECIPE_ID           => $recipe_id,
			self::META_PARENT_BLOCK_ID     => $parent_block_id,
			self::META_INTEGRATION_CODE    => $integration_code,
			self::META_CODE                => $code,
		);

		if ( ! empty( $fields ) ) {
			// wp_slash: wp_insert_post runs wp_unslash on the entire input including meta_input.
			$meta_input[ self::META_FIELDS ] = wp_slash( wp_json_encode( $fields ) );
		}

		if ( ! empty( $backup ) ) {
			$meta_input[ self::META_BACKUP ] = wp_slash( wp_json_encode( $backup ) );
		}

		if ( null !== $legacy_id && '' !== $legacy_id ) {
			$meta_input[ self::META_LEGACY_ID ] = $legacy_id;
		}

		if ( null !== $synthesis_key && '' !== $synthesis_key ) {
			$meta_input[ self::META_SYNTHESIS_KEY ] = $synthesis_key;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_title'  => $this->generate_title( $integration_code, $code ),
				'post_parent' => $parent_block_id,
				'post_status' => Post_Status::PUBLISH,
				'menu_order'  => $menu_order,
				'meta_input'  => $meta_input,
			)
		);

		if ( is_wp_error( $post_id ) ) {
			// translators: %s is the error message.
			throw new Exception( sprintf( esc_html_x( 'Failed to create block modifier: %s', 'Block modifier store creation error', 'uncanny-automator' ), esc_html( $post_id->get_error_message() ) ) );
		}

		if ( ! $post_id ) {
			throw new Exception( esc_html_x( 'Failed to create block modifier: unknown error', 'Block modifier store creation error', 'uncanny-automator' ) );
		}

		return $post_id;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get( int $modifier_id ): ?array {
		$post = $this->get_wp_post( $modifier_id );

		if ( ! $post ) {
			return null;
		}

		return $this->build_data_from_post( $post );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_by_block( int $parent_block_id, ?string $block_modifier_type = null ): array {
		$query_args = array(
			'post_type'      => self::POST_TYPE,
			'post_parent'    => $parent_block_id,
			'post_status'    => Post_Status::get_all(),
			'posts_per_page' => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
		);

		if ( null !== $block_modifier_type ) {
			$query_args['meta_query'] = $this->build_discriminator_meta_query( $block_modifier_type ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}

		$posts     = get_posts( $query_args );
		$modifiers = array();
		$expected  = $block_modifier_type ?? '';

		foreach ( $posts as $post ) {
			if ( '' !== $expected ) {
				$this->maybe_backfill_discriminator( $post->ID, $expected );
			}
			$modifiers[] = $this->build_data_from_post( $post );
		}

		return $modifiers;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_by_recipe( int $recipe_id, ?string $block_modifier_type = null ): array {
		$meta_query = array(
			array(
				'key'   => self::META_RECIPE_ID,
				'value' => $recipe_id,
			),
		);

		if ( null !== $block_modifier_type ) {
			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key'   => self::META_BLOCK_MODIFIER_TYPE,
					'value' => $block_modifier_type,
				),
				array(
					'key'     => self::META_BLOCK_MODIFIER_TYPE,
					'compare' => 'NOT EXISTS',
				),
			);
		}

		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => Post_Status::get_all(),
				'posts_per_page' => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			)
		);

		$modifiers = array();
		foreach ( $posts as $post ) {
			$modifiers[] = $this->build_data_from_post( $post );
		}

		return $modifiers;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_by_synthesis_key( string $synthesis_key ): ?array {
		if ( '' === $synthesis_key ) {
			return null;
		}

		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => Post_Status::get_all(),
				'posts_per_page' => 1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Single-row lookup; the sniff flags any posts_per_page it can't prove bounded.
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => self::META_SYNTHESIS_KEY,
						'value' => $synthesis_key,
					),
				),
			)
		);

		if ( empty( $posts ) ) {
			return null;
		}

		return $this->build_data_from_post( $posts[0] );
	}

	/**
	 * {@inheritDoc}
	 */
	public function update( int $modifier_id, array $data ): bool {
		if ( ! $this->exists( $modifier_id ) ) {
			throw new Exception( esc_html_x( 'Block modifier not found', 'Block modifier store update error', 'uncanny-automator' ) );
		}

		$post_data = array( 'ID' => $modifier_id );

		if ( isset( $data['menu_order'] ) ) {
			$post_data['menu_order'] = (int) $data['menu_order'];
		}

		// Refresh post_title when integration_code or code changes so admin
		// search and `wp post list` continue to reflect the row.
		$integration_code = isset( $data[ self::META_INTEGRATION_CODE ] ) ? (string) $data[ self::META_INTEGRATION_CODE ] : null;
		$code             = isset( $data[ self::META_CODE ] ) ? (string) $data[ self::META_CODE ] : null;

		if ( null !== $integration_code || null !== $code ) {
			$current_integration = $integration_code ?? (string) get_post_meta( $modifier_id, self::META_INTEGRATION_CODE, true );
			$current_code        = $code ?? (string) get_post_meta( $modifier_id, self::META_CODE, true );

			$post_data['post_title'] = $this->generate_title( $current_integration, $current_code );
		}

		if ( null !== $integration_code ) {
			update_post_meta( $modifier_id, self::META_INTEGRATION_CODE, $integration_code );
		}

		if ( null !== $code ) {
			update_post_meta( $modifier_id, self::META_CODE, $code );
		}

		$fields_changed = false;
		if ( array_key_exists( self::META_FIELDS, $data ) ) {
			$fields = is_array( $data[ self::META_FIELDS ] ) ? $data[ self::META_FIELDS ] : array();

			// Write-time invalidation (spec Stage 3): a whole-bag write has no
			// field-by-field update_post_meta() return to read, so the stored
			// bag is decoded and strict-compared against the incoming one —
			// same representation (this store's own JSON), before the write.
			// First materialization is configuration, not an edit (spec
			// Stage 3 first-save rule) — key absence means no prior values.
			$had_stored_bag = metadata_exists( 'post', $modifier_id, self::META_FIELDS );
			$stored_fields  = json_decode( (string) get_post_meta( $modifier_id, self::META_FIELDS, true ), true );
			$fields_changed = $had_stored_bag && ( ! is_array( $stored_fields ) || $stored_fields !== $fields );

			Recipe_Change_Record::note_bag_fields( Wire_Node_Type::BLOCK_MODIFIER, $modifier_id, (int) get_post_meta( $modifier_id, self::META_RECIPE_ID, true ), is_array( $stored_fields ) ? $stored_fields : array(), $fields );

			update_post_meta( $modifier_id, self::META_FIELDS, wp_slash( wp_json_encode( $fields ) ) );
		}

		if ( array_key_exists( self::META_BACKUP, $data ) ) {
			$backup = is_array( $data[ self::META_BACKUP ] ) ? $data[ self::META_BACKUP ] : array();
			update_post_meta( $modifier_id, self::META_BACKUP, wp_slash( wp_json_encode( $backup ) ) );
		}

		if ( array_key_exists( self::META_LEGACY_ID, $data ) ) {
			$legacy_id = (string) $data[ self::META_LEGACY_ID ];
			if ( '' === $legacy_id ) {
				delete_post_meta( $modifier_id, self::META_LEGACY_ID );
			} else {
				update_post_meta( $modifier_id, self::META_LEGACY_ID, $legacy_id );
			}
		}

		if ( array_key_exists( self::META_SYNTHESIS_KEY, $data ) ) {
			$synthesis_key = (string) $data[ self::META_SYNTHESIS_KEY ];
			if ( '' === $synthesis_key ) {
				delete_post_meta( $modifier_id, self::META_SYNTHESIS_KEY );
			} else {
				update_post_meta( $modifier_id, self::META_SYNTHESIS_KEY, $synthesis_key );
			}
		}

		// Request WordPress errors: a refused update otherwise answers 0 and passes as saved.
		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			// translators: %s is the error message.
			throw new Exception( sprintf( esc_html_x( 'Failed to update block modifier: %s', 'Block modifier store update error', 'uncanny-automator' ), esc_html( $result->get_error_message() ) ) );
		}

		if ( $fields_changed ) {
			Dispatcher::action( 'automator_node_fields_changed', 'block_modifier', $modifier_id );
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete( int $modifier_id ): bool {
		if ( ! $this->exists( $modifier_id ) ) {
			throw new Exception( esc_html_x( 'Block modifier not found', 'Block modifier store delete error', 'uncanny-automator' ) );
		}

		$result = wp_delete_post( $modifier_id, true );

		if ( ! $result ) {
			// translators: %s is the modifier ID.
			throw new Exception( sprintf( esc_html_x( 'Failed to delete block modifier with ID: %s', 'Block modifier store delete error', 'uncanny-automator' ), absint( $modifier_id ) ) );
		}

		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete_by_block( int $parent_block_id ): void {
		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_parent'    => $parent_block_id,
				'post_status'    => 'any',
				'posts_per_page' => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
				'fields'         => 'ids',
			)
		);

		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete_by_recipe( int $recipe_id ): void {
		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => self::META_RECIPE_ID,
						'value' => $recipe_id,
					),
				),
			)
		);

		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function exists( int $modifier_id ): bool {
		return null !== $this->get_wp_post( $modifier_id );
	}

	/**
	 * {@inheritDoc}
	 */
	public function adopt_post(
		int $post_id,
		string $block_modifier_type,
		?int $recipe_id = null,
		?int $parent_block_id = null,
		?string $synthesis_key = null
	): void {
		if ( $post_id <= 0 || '' === $block_modifier_type ) {
			return;
		}

		$existing = get_post( $post_id );
		if ( ! $existing ) {
			return;
		}

		$rows = array( self::META_BLOCK_MODIFIER_TYPE => $block_modifier_type );

		if ( null !== $recipe_id && $recipe_id > 0 ) {
			$rows[ self::META_RECIPE_ID ] = (string) $recipe_id;
		}

		$resolved_parent = null !== $parent_block_id && $parent_block_id > 0
			? $parent_block_id
			: (int) $existing->post_parent;

		if ( $resolved_parent > 0 ) {
			$rows[ self::META_PARENT_BLOCK_ID ] = (string) $resolved_parent;
		}

		if ( null !== $synthesis_key && '' !== $synthesis_key ) {
			$rows[ self::META_SYNTHESIS_KEY ] = $synthesis_key;
		}

		$this->adopt_rows( $existing, $rows );

		Recipe_Cache::invalidate( $recipe_id ?? (int) get_post_meta( $post_id, self::META_RECIPE_ID, true ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function count_usage_by_parent_block_type( string $block_type ): array {
		global $wpdb;

		list( $scope, $args ) = $this->usage_scope_sql( $block_type, array( Post_Status::PUBLISH ) );

		$query = "SELECT pm_int.meta_value AS integration_code,
		                 pm_code.meta_value AS code,
		                 COUNT(DISTINCT m.ID) AS usage_count
		          {$scope}
		          GROUP BY pm_int.meta_value, pm_code.meta_value"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Aggregate join over posts and postmeta that no WordPress API offers; live count, must not be cached. $scope is a constant fragment from usage_scope_sql(); every value travels through the placeholders.
		$rows = $wpdb->get_results( $wpdb->prepare( $query, ...$args ), ARRAY_A );

		$counts = array();
		foreach ( (array) $rows as $row ) {
			$key            = $row['integration_code'] . ':' . $row['code'];
			$counts[ $key ] = absint( $row['usage_count'] );
		}

		return $counts;
	}

	/**
	 * {@inheritDoc}
	 */
	public function usage_census_by_parent_block_type( string $block_type ): array {
		global $wpdb;

		list( $scope, $args ) = $this->usage_scope_sql( $block_type, array( Post_Status::PUBLISH, Post_Status::DRAFT ) );

		$query = "SELECT pm_int.meta_value AS integration_code,
		                 pm_code.meta_value AS code,
		                 COUNT(DISTINCT m.ID) AS all_count,
		                 COUNT(DISTINCT CASE WHEN m.post_status = %s AND r.post_status = %s THEN m.ID END) AS published_count,
		                 MAX(m.post_date) AS last_date
		          {$scope}
		          GROUP BY pm_int.meta_value, pm_code.meta_value"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Aggregate join over posts and postmeta that no WordPress API offers; the stored census caches it. $scope is a constant fragment from usage_scope_sql(); every value travels through the placeholders.
		$rows = $wpdb->get_results( $wpdb->prepare( $query, Post_Status::PUBLISH, Post_Status::PUBLISH, ...$args ), ARRAY_A );

		if ( '' !== $wpdb->last_error ) {
			throw new Store_Exception( 'The usage census could not be read: ' . esc_html( $wpdb->last_error ) );
		}

		$census = array();
		foreach ( (array) $rows as $row ) {
			$census[ $row['integration_code'] . ':' . $row['code'] ] = array(
				'published' => absint( $row['published_count'] ),
				'all'       => absint( $row['all_count'] ),
				'last_used' => empty( $row['last_date'] ) ? 0 : max( 0, (int) get_gmt_from_date( $row['last_date'], 'U' ) ),
			);
		}

		return $census;
	}

	/**
	 * {@inheritDoc}
	 */
	public function rows_in_published_recipes( string $block_type ): array {
		global $wpdb;

		list( $scope, $args ) = $this->usage_scope_sql( $block_type, array( Post_Status::PUBLISH ) );

		$query = "SELECT m.ID               AS modifier_id,
		                 r.ID               AS recipe_id,
		                 b.ID               AS parent_block_id,
		                 pm_int.meta_value  AS integration_code,
		                 pm_code.meta_value AS code
		          {$scope}
		          ORDER BY r.ID ASC, b.ID ASC, m.menu_order ASC, m.ID ASC, pm_int.meta_id ASC, pm_code.meta_id ASC"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Aggregate join over posts and postmeta that no WordPress API offers; live count, must not be cached. $scope is a constant fragment from usage_scope_sql(); every value travels through the placeholders.
		$rows = $wpdb->get_results( $wpdb->prepare( $query, ...$args ), ARRAY_A );

		// One row per modifier: the first joined meta row by meta_id is the stored value.
		$output = array();
		$seen   = array();
		foreach ( (array) $rows as $row ) {
			$modifier_id = (int) $row['modifier_id'];
			if ( isset( $seen[ $modifier_id ] ) ) {
				continue;
			}
			$seen[ $modifier_id ] = true;
			$output[]             = array(
				'modifier_id'      => $modifier_id,
				'recipe_id'        => (int) $row['recipe_id'],
				'parent_block_id'  => (int) $row['parent_block_id'],
				'integration_code' => (string) $row['integration_code'],
				'code'             => (string) $row['code'],
			);
		}

		return $output;
	}

	/**
	 * The FROM / JOIN / WHERE both usage queries share, with its prepare() values.
	 *
	 * Aliases: the modifier is `m`, its parent block `b` (type checked through `bt`), the
	 * recipe `r` through the modifier's own stamp, the integration meta `pm_int` and the
	 * code meta `pm_code`.
	 *
	 * @param string   $block_type Parent block type ('filter' | 'loop').
	 * @param string[] $statuses   Post statuses the modifier and its recipe may each have.
	 *
	 * @return array{0: string, 1: array<int, string>} The fragment and its placeholder values in order.
	 */
	private function usage_scope_sql( string $block_type, array $statuses ): array {
		global $wpdb;

		$in = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

		$sql = "FROM {$wpdb->posts} m
		        INNER JOIN {$wpdb->posts} b         ON m.post_parent = b.ID AND b.post_type = %s
		        INNER JOIN {$wpdb->postmeta} bt     ON b.ID = bt.post_id    AND bt.meta_key = %s
		        INNER JOIN {$wpdb->postmeta} pm_rid ON m.ID = pm_rid.post_id AND pm_rid.meta_key = %s
		        INNER JOIN {$wpdb->posts} r         ON pm_rid.meta_value = r.ID
		        INNER JOIN {$wpdb->postmeta} pm_int  ON m.ID = pm_int.post_id  AND pm_int.meta_key  = %s
		        INNER JOIN {$wpdb->postmeta} pm_code ON m.ID = pm_code.post_id AND pm_code.meta_key = %s
		        WHERE m.post_type = %s
		          AND m.post_status IN ( {$in} )
		          AND bt.meta_value = %s
		          AND r.post_type = %s
		          AND r.post_status IN ( {$in} )
		          AND pm_int.meta_value  != ''
		          AND pm_code.meta_value != ''";

		$args = array_merge(
			array(
				AUTOMATOR_POST_TYPE_BLOCK,
				WP_Block_Store::META_BLOCK_TYPE,
				self::META_RECIPE_ID,
				self::META_INTEGRATION_CODE,
				self::META_CODE,
				self::POST_TYPE,
			),
			$statuses,
			array(
				$block_type,
				AUTOMATOR_POST_TYPE_RECIPE,
			),
			$statuses
		);

		return array( $sql, $args );
	}

	/**
	 * Build a permissive `meta_query` clause for the discriminator.
	 *
	 * Matches rows whose `block_modifier_type` equals the requested value OR
	 * is missing entirely (pre-discriminator rows). The missing-meta branch
	 * is self-healed on first read via {@see self::maybe_backfill_discriminator()}.
	 *
	 * @param string $block_modifier_type Discriminator value.
	 *
	 * @return array
	 */
	private function build_discriminator_meta_query( string $block_modifier_type ): array {
		return array(
			'relation' => 'OR',
			array(
				'key'   => self::META_BLOCK_MODIFIER_TYPE,
				'value' => $block_modifier_type,
			),
			array(
				'key'     => self::META_BLOCK_MODIFIER_TYPE,
				'compare' => 'NOT EXISTS',
			),
		);
	}

	/**
	 * Stamp `block_modifier_type=$expected` post-meta on a row that does not
	 * have it yet. Self-heals rows created before the discriminator was
	 * introduced so subsequent reads hit the strict-match branch.
	 *
	 * @param int    $post_id  Modifier post id.
	 * @param string $expected Discriminator value to stamp.
	 *
	 * @return void
	 */
	private function maybe_backfill_discriminator( int $post_id, string $expected ): void {
		$current = get_post_meta( $post_id, self::META_BLOCK_MODIFIER_TYPE, true );

		// Missing meta reads back as '' — treat any non-string or empty value as
		// "never stamped" so a row is only ever backfilled once.
		if ( ! is_string( $current ) || '' === $current ) {
			update_post_meta( $post_id, self::META_BLOCK_MODIFIER_TYPE, $expected );
		}
	}

	/**
	 * Hydrate a modifier row from a WP_Post.
	 *
	 * @param \WP_Post $post WordPress post.
	 *
	 * @return array
	 */
	private function build_data_from_post( \WP_Post $post ): array {
		$fields_raw = get_post_meta( $post->ID, self::META_FIELDS, true );
		$fields     = is_string( $fields_raw ) ? json_decode( $fields_raw, true ) : array();

		$backup_raw = get_post_meta( $post->ID, self::META_BACKUP, true );
		$backup     = is_string( $backup_raw ) ? json_decode( $backup_raw, true ) : array();

		$legacy_id     = (string) get_post_meta( $post->ID, self::META_LEGACY_ID, true );
		$synthesis_key = (string) get_post_meta( $post->ID, self::META_SYNTHESIS_KEY, true );

		return array(
			'id'                  => (int) $post->ID,
			'parent_block_id'     => (int) $post->post_parent,
			'recipe_id'           => (int) get_post_meta( $post->ID, self::META_RECIPE_ID, true ),
			'block_modifier_type' => (string) get_post_meta( $post->ID, self::META_BLOCK_MODIFIER_TYPE, true ),
			'integration_code'    => (string) get_post_meta( $post->ID, self::META_INTEGRATION_CODE, true ),
			'code'                => (string) get_post_meta( $post->ID, self::META_CODE, true ),
			'fields'              => is_array( $fields ) ? $fields : array(),
			'backup'              => is_array( $backup ) ? $backup : array(),
			'legacy_id'           => '' === $legacy_id ? null : $legacy_id,
			'synthesis_key'       => '' === $synthesis_key ? null : $synthesis_key,
			'menu_order'          => (int) $post->menu_order,
		);
	}

	/**
	 * Generate post_title for a modifier row. Title is informational only —
	 * never read for routing.
	 *
	 * @param string $integration_code Integration code.
	 * @param string $code             Modifier code.
	 *
	 * @return string
	 */
	private function generate_title( string $integration_code, string $code ): string {
		return sprintf( '%s: %s', $integration_code, $code );
	}
}
