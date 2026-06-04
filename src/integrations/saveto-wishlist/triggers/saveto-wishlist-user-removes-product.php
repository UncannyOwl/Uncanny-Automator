<?php

namespace Uncanny_Automator\Integrations\Saveto_Wishlist;

use Uncanny_Automator\Recipe\Trigger;
use Uncanny_Automator\Integrations\Saveto_Wishlist\Dispatchers\Removal_Dispatcher;

/**
 * Class SAVETO_WISHLIST_USER_REMOVES_PRODUCT
 *
 * Trigger: A user removes {{a product}} from {{a wishlist}}.
 *
 * Removal happens through several SaveTo paths firing different hooks (legacy
 * AJAX, front-end REST, admin bulk save); {@see Removal_Dispatcher} normalizes
 * them into one internal action per removed product. The hook carries no user
 * ID, so we resolve it from the wishlist owner in validate().
 *
 * @property Saveto_Wishlist_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 */
class SAVETO_WISHLIST_USER_REMOVES_PRODUCT extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'USER_REMOVES_PRODUCT', 'SAVETO_WISHLIST' )
			->trigger_meta( 'WISHLIST_PRODUCT' )
			->hook( Removal_Dispatcher::HOOK, 10, 3 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_sentence(
			sprintf(
				/* translators: 1: Product 2: Wishlist */
				esc_html_x( 'A user removes {{a product:%1$s}} from {{a wishlist:%2$s}}', 'SaveTo Wishlist', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				'WISHLIST_ID:' . $this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'A user removes {{a product}} from {{a wishlist}}', 'SaveTo Wishlist', 'uncanny-automator' ) );
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

		// Guard matches what hydrate_tokens() consumes — the hook signature
		// is `(int $product_id, int $collection_id, array $variation_ids)`.
		if ( ! isset( $hook_args[0], $hook_args[1], $hook_args[2] ) ) {
			return false;
		}

		list( $product_id, $collection_id, /* $variation_ids */ ) = $hook_args;

		$selected_product  = isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ? (string) $trigger['meta'][ $this->get_trigger_meta() ] : '';
		$selected_wishlist = isset( $trigger['meta']['WISHLIST_ID'] ) ? (string) $trigger['meta']['WISHLIST_ID'] : '';

		if ( Saveto_Wishlist_Helpers::ANY_VALUE !== $selected_product && (int) $selected_product !== (int) $product_id ) {
			return false;
		}

		if ( Saveto_Wishlist_Helpers::ANY_VALUE !== $selected_wishlist && (int) $selected_wishlist !== (int) $collection_id ) {
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

		list( $product_id, $collection_id, $variation_ids ) = $hook_args;

		$variation_id = is_array( $variation_ids ) && ! empty( $variation_ids ) ? (int) reset( $variation_ids ) : 0;

		return array_merge(
			$this->item_helpers->hydrate_product_tokens( $product_id, $variation_id, 0 ),
			$this->item_helpers->hydrate_wishlist_tokens( $collection_id )
		);
	}
}
