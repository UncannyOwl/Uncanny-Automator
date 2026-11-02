<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Infrastructure\Database\Stores\Traits;

use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Block_Store;
use Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception;
use Uncanny_Automator\App\Recipe_Builder\Shared\Enums\Post_Status;

/**
 * Site-wide usage census for a CPT-backed recipe item store.
 *
 * Composed by the trigger and action stores; the using class
 * declares `const POST_TYPE`. A trait rather than a WP_Post_Store method:
 * recipes and blocks carry no `integration` / `code` meta, and modifiers
 * count through WP_Block_Modifier_Store::usage_census_by_parent_block_type().
 *
 * @package Uncanny_Automator\App\Infrastructure\Database\Stores\Traits
 */
trait Item_Usage_Counts {

	/**
	 * Usage per "INTEGRATION:CODE" for items of this store's type, at any
	 * nesting depth, in published and draft recipes.
	 *
	 * `all` counts live and switched-off items in published and draft
	 * recipes; `published` counts only live items in published recipes;
	 * `last_used` is the newest counted item's `post_date` as a GMT unix
	 * timestamp. `post_date` rather than `post_date_gmt`: WordPress leaves a
	 * draft's GMT date at zero until it is published.
	 *
	 * The recipe is the item's parent when that is the recipe, otherwise the
	 * parent block's `recipe_id` stamp, so legacy recipes (items parented to
	 * the recipe) and migrated recipes (items inside blocks) resolve in one
	 * pass. Items under an unfolded legacy `uo-loop`, or under a block with
	 * no stamp, do not count.
	 *
	 * @since 8.0.0
	 *
	 * @return array<string, array{published: int, all: int, last_used: int}>
	 *
	 * @throws Store_Exception When the query fails.
	 */
	public function usage_census_by_code(): array {
		global $wpdb;

		list( $scope, $args ) = $this->usage_scope_sql( array( Post_Status::PUBLISH, Post_Status::DRAFT ) );

		$query = "SELECT pm_int.meta_value  AS integration_code,
		                 pm_code.meta_value AS code,
		                 COUNT(DISTINCT p.ID) AS all_count,
		                 COUNT(DISTINCT CASE WHEN p.post_status = %s AND r.post_status = %s THEN p.ID END) AS published_count,
		                 MAX(p.post_date) AS last_date
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
	 * One row per published item of this store's type in a published recipe,
	 * at any nesting depth, resolved exactly as usage_census_by_code() resolves.
	 *
	 * Ordered by recipe, then the item's menu order, then its id, so a
	 * consumer that groups by recipe sees items in builder order. A database
	 * error yields an empty array.
	 *
	 * `parent_id` is the item's post_parent. Exactly one row per post; a duplicated meta row yields the first value by meta_id.
	 *
	 * @return list<array{recipe_id: int, post_id: int, parent_id: int, integration_code: string, code: string}>
	 */
	public function usage_rows(): array {
		global $wpdb;

		list( $scope, $args ) = $this->usage_scope_sql( array( Post_Status::PUBLISH ) );

		$query = "SELECT r.ID               AS recipe_id,
		                 p.ID               AS post_id,
		                 p.post_parent      AS parent_id,
		                 pm_int.meta_value  AS integration_code,
		                 pm_code.meta_value AS code
		          {$scope}
		          ORDER BY r.ID ASC, p.menu_order ASC, p.ID ASC, pm_int.meta_id ASC, pm_code.meta_id ASC"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Aggregate join over posts and postmeta that no WordPress API offers; live count, must not be cached. $scope is a constant fragment from usage_scope_sql(); every value travels through the placeholders.
		$rows = $wpdb->get_results( $wpdb->prepare( $query, ...$args ), ARRAY_A );

		// One row per post: a duplicated meta row would otherwise multiply the item, and the
		// first row by meta_id is what get_post_meta( …, true ) would have returned.
		$output = array();
		$seen   = array();
		foreach ( (array) $rows as $row ) {
			$post_id = (int) $row['post_id'];
			if ( isset( $seen[ $post_id ] ) ) {
				continue;
			}
			$seen[ $post_id ] = true;
			$output[]         = array(
				'recipe_id'        => (int) $row['recipe_id'],
				'post_id'          => $post_id,
				'parent_id'        => (int) $row['parent_id'],
				'integration_code' => (string) $row['integration_code'],
				'code'             => (string) $row['code'],
			);
		}

		return $output;
	}

	/**
	 * The FROM / JOIN / WHERE both usage queries share, with its prepare() values.
	 *
	 * Aliases: the item is `p`, its parent post `par`, the resolved recipe `r`,
	 * the integration meta `pm_int` and the code meta `pm_code`.
	 *
	 * @param string[] $statuses Post statuses the item and its recipe may each have.
	 *
	 * @return array{0: string, 1: array<int, string>} The fragment and its placeholder values in order.
	 */
	private function usage_scope_sql( array $statuses ): array {
		global $wpdb;

		$in = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

		$sql = "FROM {$wpdb->posts} p
		        INNER JOIN {$wpdb->posts} par        ON par.ID = p.post_parent
		        LEFT  JOIN {$wpdb->postmeta} brid    ON par.post_type = %s AND brid.post_id = par.ID AND brid.meta_key = %s
		        INNER JOIN {$wpdb->posts} r          ON r.ID = IF( par.post_type = %s, par.ID, brid.meta_value )
		        INNER JOIN {$wpdb->postmeta} pm_int  ON pm_int.post_id  = p.ID AND pm_int.meta_key  = %s
		        INNER JOIN {$wpdb->postmeta} pm_code ON pm_code.post_id = p.ID AND pm_code.meta_key = %s
		        WHERE p.post_type   = %s
		          AND p.post_status IN ( {$in} )
		          AND r.post_type   = %s
		          AND r.post_status IN ( {$in} )
		          AND pm_int.meta_value  != ''
		          AND pm_code.meta_value != ''";

		$args = array_merge(
			array(
				AUTOMATOR_POST_TYPE_BLOCK,
				WP_Block_Store::META_RECIPE_ID,
				AUTOMATOR_POST_TYPE_RECIPE,
				'integration',
				'code',
				static::POST_TYPE,
			),
			$statuses,
			array( AUTOMATOR_POST_TYPE_RECIPE ),
			$statuses
		);

		return array( $sql, $args );
	}
}
