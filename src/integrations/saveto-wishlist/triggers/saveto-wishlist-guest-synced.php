<?php

namespace Uncanny_Automator\Integrations\Saveto_Wishlist;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class SAVETO_WISHLIST_GUEST_SYNCED
 *
 * Trigger: A guest wishlist is synced on user login.
 *
 * Hook arg layout from `stwlite_after_store_wishlist_from_local_storage`:
 *   [0] $wishlist_items (array of items just synced)
 *   [1] $wishlist       (return value of get_collections() — array with `data` key)
 *   [2] $user_id
 *
 * @property Saveto_Wishlist_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 */
class SAVETO_WISHLIST_GUEST_SYNCED extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'GUEST_SYNCED', 'SAVETO_WISHLIST' )
			->trigger_meta( 'WISHLIST_SYNCED' )
			->hook( 'stwlite_after_store_wishlist_from_local_storage', 10, 3 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		// The Lite sync handler is registered on wp_ajax_nopriv_ too, so the
		// hook can fire before the current user is established. validate()
		// resolves the user from the hook arg, so skip the login gate that
		// would otherwise block the trigger before validate() runs. Stays a
		// 'user' trigger so it remains under the logged-in recipe type.
		$this->set_is_login_required( false );

		$this->set_sentence( esc_html_x( 'A guest wishlist is synced to a user account on login', 'SaveTo Wishlist', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A guest wishlist is synced to a user account on login', 'SaveTo Wishlist', 'uncanny-automator' ) );
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		if ( ! isset( $hook_args[2] ) ) {
			return false;
		}

		$user_id = (int) $hook_args[2];
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
			$this->item_helpers->wishlist_trigger_tokens(),
			array(
				array(
					'tokenId'   => 'WISHLIST_ITEMS_SYNCED',
					'tokenName' => esc_html_x( 'Items synced count', 'SaveTo Wishlist', 'uncanny-automator' ),
					'tokenType' => 'int',
				),
			)
		);
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$wishlist_items = isset( $hook_args[0] ) && is_array( $hook_args[0] ) ? $hook_args[0] : array();
		$wishlist       = isset( $hook_args[1] ) ? $hook_args[1] : null;

		// $wishlist is the return of get_collections() — array with `data` key
		// holding Collection objects. Pull the first.
		$collection_id = 0;
		if ( is_array( $wishlist ) && ! empty( $wishlist['data'] ) ) {
			$first         = reset( $wishlist['data'] );
			$collection_id = isset( $first->id ) ? (int) $first->id : 0;
		} elseif ( is_object( $wishlist ) && isset( $wishlist->id ) ) {
			$collection_id = (int) $wishlist->id;
		}

		$tokens                          = $this->item_helpers->hydrate_wishlist_tokens( $collection_id );
		$tokens['WISHLIST_ITEMS_SYNCED'] = count( $wishlist_items );

		return $tokens;
	}
}
