<?php

namespace Uncanny_Automator\Integrations\Saveto_Wishlist;

use Uncanny_Automator\Recipe\Action;

/**
 * Class SAVETO_WISHLIST_REMOVE_PRODUCT
 *
 * Action: Remove {{a product}} from {{a wishlist}}.
 *
 * Empty wishlist field removes the product from every wishlist the user owns.
 *
 * @property Saveto_Wishlist_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 */
class SAVETO_WISHLIST_REMOVE_PRODUCT extends Action {

	/**
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'SAVETO_WISHLIST' );
		$this->set_action_code( 'SAVETO_WISHLIST_REMOVE_PRODUCT' );
		$this->set_action_meta( 'WISHLIST_PRODUCT' );
		$this->set_requires_user( true );
		$this->set_sentence(
			sprintf(
				/* translators: 1: Product 2: Wishlist */
				esc_html_x( 'Remove {{a product:%1$s}} from {{a wishlist:%2$s}}', 'SaveTo Wishlist', 'uncanny-automator' ),
				$this->get_action_meta(),
				'WISHLIST_ID:' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Remove {{a product}} from {{a wishlist}}', 'SaveTo Wishlist', 'uncanny-automator' ) );
	}

	/**
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_action_meta(),
				'label'           => esc_html_x( 'Product', 'SaveTo Wishlist', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'products_strict' ),
			),
			array(
				'option_code'            => 'WISHLIST_ID',
				'label'                  => esc_html_x( 'Wishlist', 'SaveTo Wishlist', 'uncanny-automator' ),
				'description'            => esc_html_x( "Leave empty to remove the product from all of the user's wishlists.", 'SaveTo Wishlist', 'uncanny-automator' ),
				'input_type'             => 'select',
				'required'               => false,
				'options'                => array(),
				'relevant_tokens'        => array(),
				// Keep the pill prefix off — the selected wishlist name is self-explanatory.
				'show_label_in_sentence' => false,
				'remote_data'            => $this->item_helpers->remote_data_load_config( 'wishlists_strict' ),
			),
		);
	}

	/**
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'WISHLIST_DELETED_COUNT' => array(
				'name' => esc_html_x( 'Items removed', 'SaveTo Wishlist', 'uncanny-automator' ),
				'type' => 'int',
			),
		);
	}

	/**
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		if ( ! $this->item_helpers->saveto_lite_active() ) {
			$this->add_log_error( 'SaveTo Wishlist Lite is not active.' );
			return false;
		}

		$product_id    = absint( $parsed[ $this->get_action_meta() ] ?? 0 );
		$collection_id = absint( $parsed['WISHLIST_ID'] ?? 0 );
		$user_id       = absint( $user_id );

		if ( $product_id <= 0 ) {
			$this->add_log_error( 'A valid product is required.' );
			return false;
		}

		if ( $user_id <= 0 ) {
			$this->add_log_error( 'A valid user is required to remove a wishlist item.' );
			return false;
		}

		$collections = \SaveToWishlist\Classes\Factories\Collections::instance();

		if ( $collection_id > 0 ) {
			// Scope to a specific wishlist. The 3rd param ($reverse_condition)
			// must be false so the API deletes only products in the list,
			// not products outside it.
			$deleted = $collections->delete_collection_items( $collection_id, array( $product_id ), false );

			// Upstream fires `stwlite_after_product_removed_from_wishlist`
			// from the AJAX wrapper, not from the factory we just called —
			// re-fire it here so this action is observable by the matching
			// trigger. We deliberately skip this dispatch in the
			// "remove from all" branch below because the trigger expects a
			// concrete wishlist ID; firing with `collection_id = 0` would
			// hydrate to all-empty wishlist tokens.
			do_action( 'stwlite_after_product_removed_from_wishlist', $product_id, $collection_id, array() );
		} else {
			// Remove this product from every wishlist the user owns. The
			// trigger is not emitted here — see comment above.
			$deleted = $collections->delete_collection_items_by_user( array( $product_id ), $user_id );
		}

		$this->hydrate_tokens(
			array(
				'WISHLIST_DELETED_COUNT' => (int) $deleted,
			)
		);

		return true;
	}
}
