<?php

namespace Uncanny_Automator\Integrations\Favorites;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Favorites_Cleared
 *
 * @package Uncanny_Automator
 * @since 8.1.0
 *
 * @property Favorites_Helpers $item_helpers
 */
class Favorites_Cleared extends Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 */
	public static function definition() {
		return self::new_definition( 'FAVORITES_CLEARED', 'FAVORITES' )
			->trigger_meta( 'FAVORITES_CLEARED_META' )
			->hook( 'automator_favorites_favourites_cleared', 10, 3 );
	}

	/**
	 * Setup trigger configuration.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().
		$this->set_is_pro( false );
		$this->set_uses_api( false );
		$this->set_sentence( esc_html_x( 'A user clears all their favourites', 'Favorites', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A user clears all their favourites', 'Favorites', 'uncanny-automator' ) );
	}

	/**
	 * Define trigger options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array();
	}

	/**
	 * Define available tokens.
	 *
	 * @param array $trigger The trigger settings.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array(
			array(
				'tokenId'   => 'SITE_ID',
				'tokenName' => esc_html_x( 'Site ID', 'Favorites', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'USER_ID',
				'tokenName' => esc_html_x( 'User ID', 'Favorites', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'CLEARED_POST_IDS',
				'tokenName' => esc_html_x( 'Cleared post IDs', 'Favorites', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CLEARED_COUNT',
				'tokenName' => esc_html_x( 'Cleared count', 'Favorites', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
		);
	}

	/**
	 * Validate trigger against hook arguments.
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		if ( count( $hook_args ) < 3 ) {
			return false;
		}

		list( $site_id, $user, $post_ids ) = $hook_args;

		// Guests clear the same way with no user to attribute the run to.
		if ( empty( $user ) ) {
			return false;
		}

		$this->set_user_id( absint( $user ) );

		return true;
	}

	/**
	 * Hydrate token values from hook arguments.
	 *
	 * @param array $trigger   The completed trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $site_id, $user, $post_ids ) = $hook_args;

		$post_ids = array_map( 'absint', (array) $post_ids );

		return array(
			'SITE_ID'          => absint( $site_id ),
			'USER_ID'          => absint( $user ),
			'CLEARED_POST_IDS' => implode( ',', $post_ids ),
			'CLEARED_COUNT'    => count( $post_ids ),
		);
	}
}
