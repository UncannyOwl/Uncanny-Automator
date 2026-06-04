<?php

namespace Uncanny_Automator\Integrations\Saveto_Wishlist;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class SAVETO_WISHLIST_USER_MOVES_TO_CART
 *
 * Trigger: A user adds {{a product}} from their wishlist to their cart.
 *
 * Hook arg order: $collection_id, $product_id, $quantity, $variation_id.
 *
 * @property Saveto_Wishlist_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 */
class SAVETO_WISHLIST_USER_MOVES_TO_CART extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'USER_MOVES_TO_CART', 'SAVETO_WISHLIST' )
			->trigger_meta( 'WISHLIST_PRODUCT' )
			->hook( 'stwlite_after_product_added_to_cart', 10, 4 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_sentence(
			sprintf(
				/* translators: 1: Product */
				esc_html_x( 'A user adds {{a product:%1$s}} from their wishlist to their cart', 'SaveTo Wishlist', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'A user adds {{a product}} from their wishlist to their cart', 'SaveTo Wishlist', 'uncanny-automator' ) );
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
		);
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		// Guard matches what hydrate_tokens() consumes — the hook signature
		// is `(int $collection_id, int $product_id, int $quantity, int $variation_id)`.
		if ( ! isset( $hook_args[0], $hook_args[1], $hook_args[2], $hook_args[3] ) ) {
			return false;
		}

		list( $collection_id, $product_id, /* $quantity */, /* $variation_id */ ) = $hook_args;

		$selected_product = isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ? (string) $trigger['meta'][ $this->get_trigger_meta() ] : '';

		if ( Saveto_Wishlist_Helpers::ANY_VALUE !== $selected_product && (int) $selected_product !== (int) $product_id ) {
			return false;
		}

		$user_id = $this->item_helpers->get_wishlist_owner_id( $collection_id );
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

		list( $collection_id, $product_id, $quantity, $variation_id ) = $hook_args;

		return array_merge(
			$this->item_helpers->hydrate_product_tokens( $product_id, $variation_id, $quantity ),
			$this->item_helpers->hydrate_wishlist_tokens( $collection_id )
		);
	}
}
