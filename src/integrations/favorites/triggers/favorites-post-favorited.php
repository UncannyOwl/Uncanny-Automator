<?php

namespace Uncanny_Automator\Integrations\Favorites;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Favorites_Post_Favorited
 *
 * @package Uncanny_Automator
 * @since 8.1.0
 *
 * @property Favorites_Helpers $item_helpers
 */
class Favorites_Post_Favorited extends Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 */
	public static function definition() {
		return self::new_definition( 'FAVORITES_POST_FAVORITED', 'FAVORITES' )
			->trigger_meta( 'FAVORITES_POST' )
			->hook( 'favorites_after_favorite', 10, 4 );
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
		/* translators: %1$s: Post */
		$this->set_sentence( sprintf( esc_html_x( 'A user favourites {{a post:%1$s}}', 'Favorites', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'A user favourites {{a post}}', 'Favorites', 'uncanny-automator' ) );
	}

	/**
	 * Define trigger options.
	 *
	 * @return array[]
	 */
	public function options() {
		return $this->item_helpers->get_trigger_post_type_and_post_options( $this->get_trigger_meta() );
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
				'tokenId'   => 'POST_ID',
				'tokenName' => esc_html_x( 'Post ID', 'Favorites', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'POST_TITLE',
				'tokenName' => esc_html_x( 'Post title', 'Favorites', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POST_URL',
				'tokenName' => esc_html_x( 'Post URL', 'Favorites', 'uncanny-automator' ),
				'tokenType' => 'url',
			),
			array(
				'tokenId'   => 'POST_TYPE',
				'tokenName' => esc_html_x( 'Post type', 'Favorites', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DIRECTION',
				'tokenName' => esc_html_x( 'Direction', 'Favorites', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SITE_ID',
				'tokenName' => esc_html_x( 'Site ID', 'Favorites', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'FAVORITE_COUNT',
				'tokenName' => esc_html_x( 'Favourite count', 'Favorites', 'uncanny-automator' ),
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

		if ( count( $hook_args ) < 4 ) {
			return false;
		}

		list( $post_id, $status, $site_id, $user ) = $hook_args;

		// Guests toggle the same hook with no user to attribute the run to.
		if ( empty( $user ) ) {
			return false;
		}

		if ( ! $this->item_helpers->post_passes_filters( $trigger, $this->get_trigger_meta(), $post_id, $site_id ) ) {
			return false;
		}

		// $status is the browser's claim; the saved state is what the plugin wrote.
		if ( ! $this->item_helpers->is_user_favorite( $post_id, $site_id, $user ) ) {
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

		list( $post_id, $status, $site_id ) = $hook_args;

		return array_merge(
			$this->item_helpers->post_token_values( $post_id, $site_id ),
			array( 'DIRECTION' => esc_html_x( 'Favourited', 'Favorites', 'uncanny-automator' ) )
		);
	}
}
