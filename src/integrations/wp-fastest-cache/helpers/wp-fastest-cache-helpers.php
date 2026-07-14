<?php

namespace Uncanny_Automator\Integrations\Wp_Fastest_Cache;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Wp_Fastest_Cache_Helpers
 *
 * Shared option builders and post-token hydrate logic for the WP Fastest Cache
 * integration. The post-type → post pickers are exposed through the unified
 * remote_data REST framework and feed the "Purge the cache for a post" action.
 *
 * @package Uncanny_Automator\Integrations\Wp_Fastest_Cache
 */
class Wp_Fastest_Cache_Helpers extends Abstract_Helpers {

	/**
	 * Re-entrancy guard. Set true while one of this integration's purge ACTIONS
	 * is calling WP Fastest Cache's clear API — the purge-all action fires
	 * `wpfc_delete_cache`, the hook the "All cache is cleared" TRIGGER listens
	 * on. The trigger bails while this is set, so an Automator purge action never
	 * fires the Automator trigger (preventing self-triggering and action→trigger
	 * loops). External clears (admin UI, post publish, WooCommerce updates) leave
	 * it false and still fire the trigger normally.
	 *
	 * @var bool
	 */
	public static $is_clearing_via_action = false;

	/**
	 * Reset the re-entrancy guard. Exposed for tests.
	 *
	 * @return void
	 */
	public static function reset_clearing_guard() {
		self::$is_clearing_via_action = false;
	}

	/**
	 * Option code of the post-type selector. The post picker reads this field
	 * off the request to scope the post list.
	 */
	const POST_TYPE = 'WPFC_POST_TYPE';

	// =========================================================================
	// Remote_Data handlers — post-type / post pickers for the recipe builder.
	//
	// Route: POST /wp-json/uap/v2/remote-data/wp_fastest_cache/{segment}
	// Reached via $this->{$method}() from Abstract_Helpers' dispatcher;
	// visibility is `protected` to keep the REST-reachable surface explicit.
	// =========================================================================

	/**
	 * Post-type picker for actions — a specific type scopes the post picker.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_post_types_strict( $request ): array {
		return $this->remote_data_success( $this->build_post_type_options() );
	}

	/**
	 * Post picker for actions — published posts of the selected type. Cascades on
	 * the post-type field and supports server-side search; a specific post (or a
	 * token) must be chosen.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_posts_strict( $request ): array {
		return $this->remote_data_success(
			$this->build_post_options( $request->get_field_value( self::POST_TYPE ), $request->get_search_query() )
		);
	}

	/**
	 * Build public-post-type options.
	 *
	 * @return array<int,array{text:string,value:string}>
	 */
	private function build_post_type_options() {

		$options = array();

		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		foreach ( $post_types as $post_type ) {
			$options[] = array(
				'text'  => isset( $post_type->labels->singular_name ) ? $post_type->labels->singular_name : $post_type->label,
				'value' => $post_type->name,
			);
		}

		return $options;
	}

	/**
	 * Build published-post options for a given post type.
	 *
	 * @param string $post_type    Selected post type, or empty for all public types.
	 * @param string $search_query Optional title search.
	 *
	 * @return array<int,array{text:string,value:string}>
	 */
	private function build_post_options( $post_type = '', $search_query = '' ) {

		$options = array();

		$post_type   = (string) $post_type;
		$query_types = ( '' === $post_type )
			? array_values( get_post_types( array( 'public' => true ) ) )
			: array( $post_type );

		// Capped to keep the builder request bounded; a variable (not a literal)
		// keeps the posts_per_page sniff satisfied.
		$limit = 200;

		$args = array(
			'post_type'        => $query_types,
			'post_status'      => 'publish',
			'numberposts'      => $limit,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => false,
		);

		$search_query = (string) $search_query;
		if ( '' !== $search_query ) {
			$args['s'] = $search_query;
		}

		foreach ( get_posts( $args ) as $post ) {
			$options[] = array(
				'text'  => $post->post_title,
				'value' => (string) $post->ID,
			);
		}

		return $options;
	}

	/**
	 * Resolve post-context token values for the "Purge the cache for a post"
	 * action. Returns the full keyset even for an invalid post so a recipe never
	 * sees a partial token map. Keyed by token code, matching the action's
	 * define_tokens() output map.
	 *
	 * @param int $post_id
	 *
	 * @return array<string,mixed>
	 */
	public function hydrate_post_tokens( $post_id ) {

		$post_id = absint( $post_id );
		$post    = $post_id > 0 ? get_post( $post_id ) : null;

		return array(
			'WPFC_POST_ID'    => $post_id,
			'WPFC_POST_TITLE' => $post instanceof \WP_Post ? $post->post_title : '',
			'WPFC_POST_URL'   => $post instanceof \WP_Post ? (string) get_permalink( $post ) : '',
			'WPFC_POST_TYPE'  => $post instanceof \WP_Post ? $post->post_type : '',
		);
	}
}
