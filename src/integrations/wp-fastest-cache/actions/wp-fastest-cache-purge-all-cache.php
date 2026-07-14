<?php

namespace Uncanny_Automator\Integrations\Wp_Fastest_Cache;

use Uncanny_Automator\Recipe\Action;

/**
 * Class Wpfc_Purge_All_Cache
 *
 * Purges the entire WP Fastest Cache via the plugin's global `wpfc_clear_all_cache()`
 * API. The "Also clear minified CSS/JS" toggle maps to the function's `$minified` flag.
 *
 * @package Uncanny_Automator\Integrations\Wp_Fastest_Cache
 *
 * @property Wp_Fastest_Cache_Helpers $item_helpers
 */
class Wpfc_Purge_All_Cache extends Action {

	/**
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'WP_FASTEST_CACHE' );
		$this->set_action_code( 'WPFC_PURGE_ALL_CACHE' );
		$this->set_action_meta( 'WPFC_INCLUDE_MINIFIED' );
		$this->set_is_pro( false );
		// Cache clearing needs no recipe user (works in anonymous)
		$this->set_requires_user( false );

		$this->set_sentence( esc_html_x( 'Purge all caches', 'WP Fastest Cache', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'Purge all caches', 'WP Fastest Cache', 'uncanny-automator' ) );
	}

	/**
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_action_meta(),
				'label'           => esc_html_x( 'Also clear minified CSS/JS', 'WP Fastest Cache', 'uncanny-automator' ),
				'input_type'      => 'checkbox',
				'is_toggle'       => true,
				'required'        => false,
				'default_value'   => false,
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		if ( ! function_exists( 'wpfc_clear_all_cache' ) ) {
			$this->add_log_error( esc_html_x( 'WP Fastest Cache is not available.', 'WP Fastest Cache', 'uncanny-automator' ) );
			return false;
		}

		$minified_raw = isset( $parsed[ $this->get_action_meta() ] ) ? $parsed[ $this->get_action_meta() ] : false;
		$minified     = in_array( $minified_raw, array( true, 'true', '1', 1, 'on', 'yes' ), true );

		// Guard against this purge firing the integration's own cache-cleared
		// triggers (wpfc_clear_all_cache → wpfc_delete_cache fires synchronously here).
		Wp_Fastest_Cache_Helpers::$is_clearing_via_action = true;
		try {
			$result = wpfc_clear_all_cache( $minified );
		} finally {
			Wp_Fastest_Cache_Helpers::$is_clearing_via_action = false;
		}

		// The global function returns array( 'success' => false, ... ) only when
		// cache clearing is disabled via the WPFC_DISABLE_HOOK_CLEAR_ALL_CACHE
		// constant; otherwise it returns null after firing the clear action.
		if ( is_array( $result ) && isset( $result['success'] ) && false === $result['success'] ) {
			$message = isset( $result['message'] ) ? sanitize_text_field( $result['message'] ) : esc_html_x( 'Cache clearing is disabled on this site.', 'WP Fastest Cache', 'uncanny-automator' );
			$this->add_log_error( $message );
			return false;
		}

		return true;
	}
}
