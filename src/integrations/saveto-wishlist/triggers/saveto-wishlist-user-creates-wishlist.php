<?php

namespace Uncanny_Automator\Integrations\Saveto_Wishlist;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class SAVETO_WISHLIST_USER_CREATES_WISHLIST
 *
 * Trigger: A user creates a wishlist.
 *
 * `stwlite_after_save_collection` fires for both CREATE and UPDATE — we
 * distinguish by inspecting the inbound `$params['id']`. We additionally
 * skip the implicit "My Wishlist" the plugin auto-creates on first
 * wishlist access; see Saveto_Wishlist_Integration::mark_auto_create_start.
 *
 * @property Saveto_Wishlist_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 */
class SAVETO_WISHLIST_USER_CREATES_WISHLIST extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'USER_CREATES_WISHLIST', 'SAVETO_WISHLIST' )
			->trigger_meta( 'WISHLIST_CREATED' )
			->hook( 'stwlite_after_save_collection', 10, 2 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_sentence( esc_html_x( 'A user creates a wishlist', 'SaveTo Wishlist', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A user creates a wishlist', 'SaveTo Wishlist', 'uncanny-automator' ) );
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		if ( Saveto_Wishlist_Integration::is_auto_creating_default() ) {
			// Plugin is implicitly creating the default "My Wishlist"; that is
			// not a user-driven create.
			return false;
		}

		if ( ! isset( $hook_args[0], $hook_args[1] ) ) {
			return false;
		}

		list( $collection, $params ) = $hook_args;

		// `id` present in inbound params means this was an UPDATE — only fire
		// for creates.
		if ( ! empty( $params['id'] ) ) {
			return false;
		}

		$user_id = isset( $collection->user_id ) ? (int) $collection->user_id : 0;
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
		return array_merge( $tokens, $this->item_helpers->wishlist_trigger_tokens() );
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$collection    = $hook_args[0];
		$collection_id = isset( $collection->id ) ? (int) $collection->id : 0;

		return $this->item_helpers->hydrate_wishlist_tokens( $collection_id );
	}
}
