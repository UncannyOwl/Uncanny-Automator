<?php

namespace Uncanny_Automator\Integrations\Cloudflare;

/**
 * Class Cloudflare_Purge_All
 *
 * @package Uncanny_Automator
 * @method \Uncanny_Automator\Integrations\Cloudflare\Cloudflare_Helpers get_item_helpers()
 */
class Cloudflare_Purge_All extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'CLOUDFLARE' );
		$this->set_action_code( 'CLOUDFLARE_PURGE_ALL' );
		$this->set_action_meta( 'CLOUDFLARE_PURGE_ALL_META' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_sentence( esc_html_x( 'Purge all Cloudflare cache', 'Cloudflare', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'Purge all Cloudflare cache', 'Cloudflare', 'uncanny-automator' ) );
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

		$result = $this->get_item_helpers()->purge_all_cache();

		if ( false === $result ) {
			$this->add_log_error( 'Cloudflare Hooks class is not available. Ensure the Cloudflare plugin is properly configured.' );
			return false;
		}

		return true;
	}
}
