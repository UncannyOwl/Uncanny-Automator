<?php

namespace Uncanny_Automator\Integrations\Wp_Super_Cache;

/**
 * Class Wp_Super_Cache_Purge_All
 *
 * @package Uncanny_Automator
 * @method \Uncanny_Automator\Integrations\Wp_Super_Cache\Wp_Super_Cache_Helpers get_item_helpers()
 */
class Wp_Super_Cache_Purge_All extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP_SUPER_CACHE' );
		$this->set_action_code( 'WP_SUPER_CACHE_PURGE_ALL' );
		$this->set_action_meta( 'WP_SUPER_CACHE_PURGE_ALL_META' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_sentence( esc_html_x( 'Purge all WP Super Cache caches', 'WP Super Cache', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'Purge all WP Super Cache caches', 'WP Super Cache', 'uncanny-automator' ) );
	}

	/**
	 * Define action options.
	 *
	 * @return array
	 */
	public function options() {
		return array();
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action configuration.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional arguments.
	 * @param array $parsed      Parsed token values.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		if ( ! $this->get_item_helpers()->purge_all_caches() ) {
			$this->add_log_error( esc_html_x( 'wp_cache_clear_cache function not found.', 'WP Super Cache', 'uncanny-automator' ) );
			return false;
		}

		return true;
	}
}
