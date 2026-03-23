<?php

namespace Uncanny_Automator\Integrations\Litespeed_Cache;

/**
 * Class Litespeed_Cache_Purge_All
 *
 * @package Uncanny_Automator
 * @method \Uncanny_Automator\Integrations\Litespeed_Cache\Litespeed_Cache_Helpers get_item_helpers()
 */
class Litespeed_Cache_Purge_All extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'LITESPEED_CACHE' );
		$this->set_action_code( 'LITESPEED_CACHE_PURGE_ALL' );
		$this->set_action_meta( 'LITESPEED_CACHE_PURGE_ALL_META' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_sentence( esc_html_x( 'Purge all LiteSpeed caches', 'LiteSpeed Cache', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'Purge all LiteSpeed caches', 'LiteSpeed Cache', 'uncanny-automator' ) );
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
		$this->get_item_helpers()->purge_all_caches();
		return true;
	}
}
