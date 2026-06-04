<?php

namespace Uncanny_Automator\Integrations\Saveto_Wishlist;

use Uncanny_Automator\Recipe\Action;

/**
 * Class SAVETO_WISHLIST_ADD_PRODUCT
 *
 * Action: Add {{a product}} to {{a wishlist}}.
 *
 * Empty wishlist field falls back to the user's default published wishlist;
 * if the user has none we create one named per `get_default_wishlist_label()`.
 *
 * @property Saveto_Wishlist_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 */
class SAVETO_WISHLIST_ADD_PRODUCT extends Action {

	/**
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'SAVETO_WISHLIST' );
		$this->set_action_code( 'SAVETO_WISHLIST_ADD_PRODUCT' );
		$this->set_action_meta( 'WISHLIST_PRODUCT' );
		$this->set_requires_user( true );
		$this->set_sentence(
			sprintf(
				/* translators: 1: Product 2: Wishlist */
				esc_html_x( 'Add {{a product:%1$s}} to {{a wishlist:%2$s}}', 'SaveTo Wishlist', 'uncanny-automator' ),
				$this->get_action_meta(),
				'WISHLIST_ID:' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Add {{a product}} to {{a wishlist}}', 'SaveTo Wishlist', 'uncanny-automator' ) );
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
				'description'            => esc_html_x( "Leave empty to use the user's default wishlist.", 'SaveTo Wishlist', 'uncanny-automator' ),
				'input_type'             => 'select',
				'required'               => false,
				'options'                => array(),
				'relevant_tokens'        => array(),
				// Keep the pill prefix off — the selected wishlist name is self-explanatory.
				'show_label_in_sentence' => false,
				'remote_data'            => $this->item_helpers->remote_data_load_config( 'wishlists_strict' ),
			),
			array(
				'option_code' => 'QUANTITY',
				'label'       => esc_html_x( 'Quantity', 'SaveTo Wishlist', 'uncanny-automator' ),
				'input_type'  => 'int',
				'required'    => false,
				'default_value' => 1,
			),
		);
	}

	/**
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'WISHLIST_ID'   => array(
				'name' => esc_html_x( 'Wishlist ID', 'SaveTo Wishlist', 'uncanny-automator' ),
				'type' => 'int',
			),
			'WISHLIST_NAME' => array(
				'name' => esc_html_x( 'Wishlist name', 'SaveTo Wishlist', 'uncanny-automator' ),
				'type' => 'text',
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
		$quantity      = absint( $parsed['QUANTITY'] ?? 1 );
		$user_id       = absint( $user_id );

		if ( 0 === $quantity ) {
			$quantity = 1;
		}

		if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) || ! wc_get_product( $product_id ) ) {
			$this->add_log_error( sprintf( 'A valid product is required. Got product ID %d.', $product_id ) );
			return false;
		}

		if ( $user_id <= 0 ) {
			$this->add_log_error( 'A valid user is required to add a wishlist item.' );
			return false;
		}

		// Resolve the destination wishlist. Empty input means "user's default";
		// create one if the user has none.
		if ( 0 === $collection_id ) {
			$collection_id = $this->item_helpers->get_user_default_wishlist_id( $user_id );

			if ( 0 === $collection_id ) {
				$created       = \SaveToWishlist\Classes\Factories\Collections::instance()->save_collection(
					array(
						'user_id'    => $user_id,
						'name'       => $this->default_wishlist_label(),
						'status'     => 'publish',
						'is_default' => 1,
						'is_public'  => 0,
					)
				);
				$collection_id = isset( $created->id ) ? (int) $created->id : 0;
			}
		}

		if ( 0 === $collection_id ) {
			$this->add_log_error( 'Could not resolve or create a destination wishlist.' );
			return false;
		}

		// Lite's `add_product_item_to_wishlist()` returns `true` on every code
		// path regardless of whether the row was inserted, so we can't rely on
		// its return value. Verify the membership in the destination wishlist
		// instead — `check_if_product_in_wishlist()` reads the DB through
		// Lite's own helper and reflects the actual post-call state.
		\SaveToWishlist\Classes\Frontend\Wishlist::instance()->add_product_item_to_wishlist(
			$product_id,
			$quantity,
			$collection_id,
			$user_id
		);

		$membership = \SaveToWishlist\Classes\Frontend\Wishlist::instance()->check_if_product_in_wishlist( $product_id, $collection_id, false, false );
		if ( empty( $membership ) ) {
			$this->add_log_error( sprintf( 'Failed to add product %d to wishlist %d.', $product_id, $collection_id ) );
			return false;
		}

		// Upstream fires `stwlite_after_product_added_to_wishlist` from the
		// AJAX wrapper (`add_to_wishlist()`), NOT from the method we just
		// called. Emit it here so this action is observable by the matching
		// trigger — Automator's own recursion guard handles the rare case
		// where the user wires an "add product" action and "user adds product"
		// trigger in the same recipe.
		do_action( 'stwlite_after_product_added_to_wishlist', $product_id, $quantity, $collection_id, $user_id );

		$collection = \SaveToWishlist\Classes\Factories\Collections::instance()->get_collection( $collection_id );

		$this->hydrate_tokens(
			array(
				'WISHLIST_ID'   => $collection_id,
				'WISHLIST_NAME' => isset( $collection->name ) ? (string) $collection->name : '',
			)
		);

		return true;
	}

	/**
	 * Default name used when creating a fallback wishlist for a user without one.
	 *
	 * @return string
	 */
	private function default_wishlist_label() {
		return esc_html_x( 'My Wishlist', 'SaveTo Wishlist', 'uncanny-automator' );
	}
}
