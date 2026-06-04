<?php

namespace Uncanny_Automator\Integrations\Saveto_Wishlist;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Saveto_Wishlist_Helpers
 *
 * Shared option builders and token hydration for the SaveTo Wishlist integration.
 *
 * @package Uncanny_Automator
 */
class Saveto_Wishlist_Helpers extends Abstract_Helpers {

	const ANY_VALUE = '-1';

	// =========================================================================
	// Remote_Data handlers — option-data endpoints for the recipe builder.
	//
	// Route: POST /wp-json/uap/v2/remote-data/saveto_wishlist/{segment}
	// Reached via $this->{$method}() from Abstract_Helpers' dispatcher;
	// visibility is `protected` to keep the surface explicit.
	// =========================================================================

	/**
	 * Wishlist picker for triggers — includes the "Any wishlist" sentinel.
	 *
	 * Search query (when present) filters by wishlist name.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_wishlists( $request ): array {
		return $this->remote_data_success( $this->build_wishlists_options( true, $request->get_search_query() ) );
	}

	/**
	 * Wishlist picker for actions — no "Any" sentinel; users must pick a
	 * specific wishlist (or leave the field empty to fall back to defaults).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_wishlists_strict( $request ): array {
		return $this->remote_data_success( $this->build_wishlists_options( false, $request->get_search_query() ) );
	}

	/**
	 * Build wishlist options. Internal — fed to remote-data handlers above.
	 *
	 * Lists published wishlists store-wide, ordered by name. Owner email is
	 * appended for disambiguation across users with identically-named lists.
	 *
	 * @param bool   $include_any  Prepend the "Any wishlist" sentinel.
	 * @param string $search_query Optional case-insensitive name filter.
	 *
	 * @return array<int,array{text:string,value:string}>
	 */
	private function build_wishlists_options( $include_any = true, $search_query = '' ) {

		$options = array();

		if ( $include_any ) {
			$options[] = array(
				'text'  => esc_html_x( 'Any wishlist', 'SaveTo Wishlist', 'uncanny-automator' ),
				'value' => self::ANY_VALUE,
			);
		}

		if ( ! $this->saveto_lite_active() ) {
			return $options;
		}

		$args = array(
			'status'         => 'publish',
			'posts_per_page' => 500,
			'sort_column'    => 'name',
			'sort_order'     => 'ASC',
		);

		$search_query = (string) $search_query;
		if ( '' !== $search_query ) {
			$args['search'] = $search_query;
		}

		$result = \SaveToWishlist\Classes\Frontend\Wishlist::instance()->get_wishlists( $args );

		if ( empty( $result['data'] ) || ! is_array( $result['data'] ) ) {
			return $options;
		}

		foreach ( $result['data'] as $wishlist ) {
			$id    = isset( $wishlist->id ) ? (int) $wishlist->id : 0;
			$name  = isset( $wishlist->name ) ? (string) $wishlist->name : '';
			$owner = isset( $wishlist->user_id ) ? get_userdata( (int) $wishlist->user_id ) : false;
			$label = $owner ? sprintf( '%s (%s)', $name, $owner->user_email ) : $name;

			$options[] = array(
				'text'  => esc_html( $label ),
				'value' => (string) $id,
			);
		}

		return $options;
	}

	/**
	 * Product picker for triggers — includes the "Any product" sentinel.
	 *
	 * Search query (when present) does a title-and-ID match.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_products( $request ): array {
		return $this->remote_data_success( $this->build_products_options( true, $request->get_search_query() ) );
	}

	/**
	 * Product picker for actions — no "Any" sentinel; users must pick a
	 * specific product.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_products_strict( $request ): array {
		return $this->remote_data_success( $this->build_products_options( false, $request->get_search_query() ) );
	}

	/**
	 * Build WooCommerce product options for the recipe-builder dropdown.
	 *
	 * Returns name + ID for each published product. Numeric search queries
	 * are treated as direct ID lookups so users can paste a product ID and
	 * still find a match.
	 *
	 * @param bool   $include_any  Prepend the "Any product" sentinel.
	 * @param string $search_query Optional title fragment, or a numeric ID.
	 *
	 * @return array<int,array{text:string,value:string}>
	 */
	private function build_products_options( $include_any = true, $search_query = '' ) {

		$options = array();

		if ( $include_any ) {
			$options[] = array(
				'text'  => esc_html_x( 'Any product', 'SaveTo Wishlist', 'uncanny-automator' ),
				'value' => self::ANY_VALUE,
			);
		}

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'posts_per_page' => apply_filters( 'automator_saveto_wishlist_product_search_limit', 50 ),
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		$search_query = trim( (string) $search_query );

		if ( '' !== $search_query ) {
			if ( is_numeric( $search_query ) ) {
				$args['post__in'] = array( absint( $search_query ) );
			} else {
				$args['s'] = $search_query;
			}
		}

		$products = get_posts( $args );

		foreach ( $products as $product ) {
			$title = ! empty( $product->post_title )
				? $product->post_title
				/* translators: %d is the product ID */
				: sprintf( esc_html_x( 'ID: %d (no title)', 'SaveTo Wishlist', 'uncanny-automator' ), $product->ID );

			$options[] = array(
				'text'  => esc_html( $title ),
				'value' => (string) $product->ID,
			);
		}

		return $options;
	}

	/**
	 * Variation picker, chained on the selected product. Lets the Set Quantity /
	 * Move Product actions offer a variation dropdown instead of a hand-entered
	 * variation ID — the source of the "variation 0 not found" mismatch on
	 * variable products.
	 *
	 * The first option is always "No variation" (value 0), which is correct for
	 * simple products: Lite stores their wishlist rows with variation_id = 0
	 * (see Lite Wishlist add path). Variable products additionally list each
	 * child variation. A missing or non-variable product yields just the sentinel.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_product_variations( $request ): array {
		$product_id = absint( $request->get_field_value( 'WISHLIST_PRODUCT' ) );
		return $this->remote_data_success( $this->build_product_variations_options( $product_id ) );
	}

	/**
	 * Build variation options for a product. Internal — fed to the handler above.
	 *
	 * @param int $product_id Selected (parent) product ID.
	 *
	 * @return array<int,array{text:string,value:string}>
	 */
	private function build_product_variations_options( $product_id ) {

		$product_id = absint( $product_id );
		$product    = ( $product_id > 0 && function_exists( 'wc_get_product' ) ) ? wc_get_product( $product_id ) : null;

		// Variable product: list ONLY its variations. No "No variation" option —
		// a variable product's wishlist row always carries a real variation_id,
		// so offering 0 just invites the "variation 0 not found" mismatch.
		if ( $product instanceof \WC_Product && $product->is_type( 'variable' ) ) {

			$options = array();

			foreach ( $product->get_children() as $variation_id ) {

				$variation = wc_get_product( $variation_id );
				if ( ! $variation instanceof \WC_Product_Variation ) {
					continue;
				}

				$summary = wc_get_formatted_variation( $variation, true );
				$label   = '' !== $summary
					? sprintf( '%s (ID: %d)', $summary, (int) $variation_id )
					/* translators: %d is the variation ID */
					: sprintf( esc_html_x( 'Variation %d', 'SaveTo Wishlist', 'uncanny-automator' ), (int) $variation_id );

				$options[] = array(
					'text'  => esc_html( $label ),
					'value' => (string) absint( $variation_id ),
				);
			}

			return $options;
		}

		// Simple product (or unknown): only the "No variation" sentinel (value 0),
		// matching how Lite stores a simple product's wishlist row.
		return array(
			array(
				'text'  => esc_html_x( 'No variation (simple product)', 'SaveTo Wishlist', 'uncanny-automator' ),
				'value' => '0',
			),
		);
	}

	/**
	 * Wishlist token definitions used by triggers.
	 *
	 * @return array<int,array{tokenId:string,tokenName:string,tokenType:string}>
	 */
	public function wishlist_trigger_tokens() {
		return array(
			array(
				'tokenId'   => 'WISHLIST_ID',
				'tokenName' => esc_html_x( 'Wishlist ID', 'SaveTo Wishlist', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'WISHLIST_NAME',
				'tokenName' => esc_html_x( 'Wishlist name', 'SaveTo Wishlist', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'WISHLIST_URL_CODE',
				'tokenName' => esc_html_x( 'Wishlist URL code', 'SaveTo Wishlist', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'WISHLIST_OWNER_ID',
				'tokenName' => esc_html_x( 'Wishlist owner ID', 'SaveTo Wishlist', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'WISHLIST_OWNER_EMAIL',
				'tokenName' => esc_html_x( 'Wishlist owner email', 'SaveTo Wishlist', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'WISHLIST_OWNER_DISPLAY_NAME',
				'tokenName' => esc_html_x( 'Wishlist owner display name', 'SaveTo Wishlist', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'WISHLIST_IS_DEFAULT',
				'tokenName' => esc_html_x( 'Wishlist is default', 'SaveTo Wishlist', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'WISHLIST_IS_PUBLIC',
				'tokenName' => esc_html_x( 'Wishlist is public', 'SaveTo Wishlist', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'WISHLIST_ITEM_COUNT',
				'tokenName' => esc_html_x( 'Wishlist item count', 'SaveTo Wishlist', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
		);
	}

	/**
	 * Product token definitions used by triggers that reference a single
	 * wishlist item.
	 *
	 * @return array<int,array{tokenId:string,tokenName:string,tokenType:string}>
	 */
	public function product_trigger_tokens() {
		return array(
			array(
				'tokenId'   => 'WISHLIST_PRODUCT_ID',
				'tokenName' => esc_html_x( 'Product ID', 'SaveTo Wishlist', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'WISHLIST_PRODUCT_VARIATION_ID',
				'tokenName' => esc_html_x( 'Product variation ID', 'SaveTo Wishlist', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'WISHLIST_PRODUCT_TITLE',
				'tokenName' => esc_html_x( 'Product title', 'SaveTo Wishlist', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'WISHLIST_PRODUCT_SKU',
				'tokenName' => esc_html_x( 'Product SKU', 'SaveTo Wishlist', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'WISHLIST_PRODUCT_PRICE',
				'tokenName' => esc_html_x( 'Product price', 'SaveTo Wishlist', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'WISHLIST_PRODUCT_URL',
				'tokenName' => esc_html_x( 'Product URL', 'SaveTo Wishlist', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'WISHLIST_PRODUCT_IMAGE_URL',
				'tokenName' => esc_html_x( 'Product image URL', 'SaveTo Wishlist', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'WISHLIST_PRODUCT_QUANTITY',
				'tokenName' => esc_html_x( 'Product quantity', 'SaveTo Wishlist', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
		);
	}

	/**
	 * Hydrate wishlist tokens from a collection ID.
	 *
	 * Defensively returns every key as an empty string when the collection
	 * cannot be loaded, so trigger consumers never see a partial map.
	 *
	 * @param int $collection_id
	 *
	 * @return array<string,string|int>
	 */
	public function hydrate_wishlist_tokens( $collection_id ) {

		$defaults = wp_list_pluck( $this->wishlist_trigger_tokens(), 'tokenId' );
		$tokens   = array_fill_keys( $defaults, '' );

		$collection_id = absint( $collection_id );
		if ( 0 === $collection_id || ! $this->saveto_lite_active() ) {
			return $tokens;
		}

		$collection = \SaveToWishlist\Classes\Factories\Collections::instance()->get_collection( $collection_id );
		if ( empty( $collection ) || ! isset( $collection->id ) ) {
			return $tokens;
		}

		$owner = isset( $collection->user_id ) ? get_userdata( (int) $collection->user_id ) : false;

		$tokens['WISHLIST_ID']                 = (int) $collection->id;
		$tokens['WISHLIST_NAME']               = isset( $collection->name ) ? (string) $collection->name : '';
		$tokens['WISHLIST_URL_CODE']           = isset( $collection->url_code ) ? (string) $collection->url_code : '';
		$tokens['WISHLIST_OWNER_ID']           = isset( $collection->user_id ) ? (int) $collection->user_id : 0;
		$tokens['WISHLIST_OWNER_EMAIL']        = $owner ? $owner->user_email : '';
		$tokens['WISHLIST_OWNER_DISPLAY_NAME'] = $owner ? $owner->display_name : '';
		$tokens['WISHLIST_IS_DEFAULT']         = ! empty( $collection->is_default ) ? 'yes' : 'no';
		$tokens['WISHLIST_IS_PUBLIC']          = ! empty( $collection->is_public ) ? 'yes' : 'no';
		$tokens['WISHLIST_ITEM_COUNT']         = $this->count_collection_items( $collection_id );

		return $tokens;
	}

	/**
	 * Hydrate product tokens for a wishlist item.
	 *
	 * @param int $product_id   Parent (or simple) product ID.
	 * @param int $variation_id Variation ID, or 0 for simple products.
	 * @param int $quantity     Quantity stored on the wishlist item.
	 *
	 * @return array<string,string|int>
	 */
	public function hydrate_product_tokens( $product_id, $variation_id = 0, $quantity = 0 ) {

		$defaults = wp_list_pluck( $this->product_trigger_tokens(), 'tokenId' );
		$tokens   = array_fill_keys( $defaults, '' );

		$product_id   = absint( $product_id );
		$variation_id = absint( $variation_id );

		if ( 0 === $product_id || ! function_exists( 'wc_get_product' ) ) {
			return $tokens;
		}

		$target_id = $variation_id > 0 ? $variation_id : $product_id;
		$product   = wc_get_product( $target_id );
		if ( ! $product ) {
			return $tokens;
		}

		$image_id  = $product->get_image_id();
		$image_url = $image_id ? wp_get_attachment_url( $image_id ) : '';

		$tokens['WISHLIST_PRODUCT_ID']           = $product_id;
		$tokens['WISHLIST_PRODUCT_VARIATION_ID'] = $variation_id;
		$tokens['WISHLIST_PRODUCT_TITLE']        = $product->get_name();
		$tokens['WISHLIST_PRODUCT_SKU']          = $product->get_sku();
		$tokens['WISHLIST_PRODUCT_PRICE']        = wp_strip_all_tags( wc_price( (float) $product->get_price() ) );
		$tokens['WISHLIST_PRODUCT_URL']          = get_permalink( $target_id );
		$tokens['WISHLIST_PRODUCT_IMAGE_URL']    = $image_url ? $image_url : '';
		$tokens['WISHLIST_PRODUCT_QUANTITY']     = absint( $quantity );

		return $tokens;
	}

	/**
	 * Resolve the owning user for a wishlist by ID.
	 *
	 * @param int $collection_id
	 *
	 * @return int 0 if not found.
	 */
	public function get_wishlist_owner_id( $collection_id ) {

		$collection_id = absint( $collection_id );
		if ( 0 === $collection_id || ! $this->saveto_lite_active() ) {
			return 0;
		}

		$collection = \SaveToWishlist\Classes\Factories\Collections::instance()->get_collection( $collection_id );
		if ( empty( $collection ) || ! isset( $collection->user_id ) ) {
			return 0;
		}

		return (int) $collection->user_id;
	}

	/**
	 * Resolve a user's default published wishlist ID, or 0.
	 *
	 * @param int $user_id
	 *
	 * @return int
	 */
	public function get_user_default_wishlist_id( $user_id ) {

		$user_id = absint( $user_id );
		if ( 0 === $user_id || ! $this->saveto_lite_active() ) {
			return 0;
		}

		$result = \SaveToWishlist\Classes\Frontend\Wishlist::instance()->get_wishlists(
			array(
				'user_id'    => $user_id,
				'is_default' => 1,
				'status'     => 'publish',
			)
		);

		if ( empty( $result['data'] ) ) {
			return 0;
		}

		$first = reset( $result['data'] );
		return isset( $first->id ) ? (int) $first->id : 0;
	}

	/**
	 * Count items in a wishlist.
	 *
	 * @param int $collection_id
	 *
	 * @return int
	 */
	public function count_collection_items( $collection_id ) {

		global $wpdb;

		$collection_id = absint( $collection_id );
		if ( 0 === $collection_id || ! $this->saveto_lite_active() ) {
			return 0;
		}

		$table = \SaveToWishlist\Helpers\Helper_Database::get_collection_items_table_name();

		// $table is sourced from the plugin's own helper — not user input.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE collection_id = %d", $collection_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Whether the SaveTo Wishlist Lite plugin classes are loaded.
	 *
	 * @return bool
	 */
	public function saveto_lite_active() {
		return class_exists( '\SaveToWishlist\Classes\Frontend\Wishlist' )
			&& class_exists( '\SaveToWishlist\Classes\Factories\Collections' );
	}
}
