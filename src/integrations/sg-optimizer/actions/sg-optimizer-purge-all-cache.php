<?php

namespace Uncanny_Automator\Integrations\Sg_Optimizer;

/**
 * Class Sg_Optimizer_Purge_All_Cache
 *
 * @package Uncanny_Automator
 * @method \Uncanny_Automator\Integrations\Sg_Optimizer\Sg_Optimizer_Helpers get_item_helpers()
 */
class Sg_Optimizer_Purge_All_Cache extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'SG_OPTIMIZER' );
		$this->set_action_code( 'SG_OPTIMIZER_PURGE_ALL_CACHE' );
		$this->set_action_meta( 'SG_OPTIMIZER_PURGE_ALL' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_sentence( esc_html_x( 'Purge all SG Optimizer caches', 'SG Optimizer', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'Purge all SG Optimizer caches', 'SG Optimizer', 'uncanny-automator' ) );
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
