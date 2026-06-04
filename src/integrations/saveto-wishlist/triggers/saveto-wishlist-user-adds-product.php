<?php

namespace Uncanny_Automator\Integrations\Saveto_Wishlist;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class SAVETO_WISHLIST_USER_ADDS_PRODUCT
 *
 * Trigger: A user adds {{a product}} to {{a wishlist}}.
 *
 * @property Saveto_Wishlist_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 */
class SAVETO_WISHLIST_USER_ADDS_PRODUCT extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'USER_ADDS_PRODUCT', 'SAVETO_WISHLIST' )
			->trigger_meta( 'WISHLIST_PRODUCT' )
			->hook( 'stwlite_after_product_added_to_wishlist', 10, 4 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_sentence(
			sprintf(
				/* translators: 1: Product 2: Wishlist */
				esc_html_x( 'A user adds {{a product:%1$s}} to {{a wishlist:%2$s}}', 'SaveTo Wishlist', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				'WISHLIST_ID:' . $this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'A user adds {{a product}} to {{a wishlist}}', 'SaveTo Wishlist', 'uncanny-automator' ) );
	}

	/**
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Product', 'SaveTo Wishlist', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'products' ),
			),
			array(
				'option_code'     => 'WISHLIST_ID',
				'label'           => esc_html_x( 'Wishlist', 'SaveTo Wishlist', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'wishlists' ),
			),
		);
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		if ( ! isset( $hook_args[0], $hook_args[2], $hook_args[3] ) ) {
			return false;
		}

		list( $product_id, /* $quantity */, $collection_id, $user_id ) = $hook_args;

		$selected_product  = isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ? (string) $trigger['meta'][ $this->get_trigger_meta() ] : '';
		$selected_wishlist = isset( $trigger['meta']['WISHLIST_ID'] ) ? (string) $trigger['meta']['WISHLIST_ID'] : '';

		if ( Saveto_Wishlist_Helpers::ANY_VALUE !== $selected_product && (int) $selected_product !== (int) $product_id ) {
			return false;
		}

		if ( Saveto_Wishlist_Helpers::ANY_VALUE !== $selected_wishlist && (int) $selected_wishlist !== (int) $collection_id ) {
			return false;
		}

		$user_id = (int) $user_id;
		if ( $user_id <= 0 ) {
			return false;
		}

		$this->set_user_id( $user_id );
		return true;
	}

	/**
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge(
			$tokens,
			$this->item_helpers->product_trigger_tokens(),
			$this->item_helpers->wishlist_trigger_tokens()
		);
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $product_id, $quantity, $collection_id, /* $user_id */ ) = $hook_args;

		// The product passed to the hook may be a variation; resolve the parent
		// for the canonical product ID, since the wishlist row stores parent_id.
		$variation_id = 0;
		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			if ( $product && $product->is_type( 'variation' ) ) {
				$variation_id = (int) $product_id;
				$product_id   = (int) $product->get_parent_id();
			}
		}

		return array_merge(
			$this->item_helpers->hydrate_product_tokens( $product_id, $variation_id, $quantity ),
			$this->item_helpers->hydrate_wishlist_tokens( $collection_id )
		);
	}
}
