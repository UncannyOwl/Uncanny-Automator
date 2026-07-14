<?php

namespace Uncanny_Automator\Integrations\Wp_Fastest_Cache;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Wpfc_All_Cache_Cleared
 *
 * Fires after WP Fastest Cache finishes clearing all cache (`wpfc_delete_cache`,
 * 0 args). Anonymous by design: a full cache clear can originate from cron, a
 * plugin, WooCommerce, or an admin, so there is no inherent recipe user. The
 * trigger runs unattributed rather than binding to whoever happens to be logged
 * in.
 *
 * @package Uncanny_Automator\Integrations\Wp_Fastest_Cache
 *
 * @property Wp_Fastest_Cache_Helpers $item_helpers
 */
class Wpfc_All_Cache_Cleared extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'WPFC_ALL_CACHE_CLEARED', 'WP_FASTEST_CACHE' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'WPFC_ALL_CACHE' )
			->hook( 'wpfc_delete_cache', 10, 0 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {

		$this->set_is_pro( false );
		$this->set_is_login_required( false );

		$this->set_sentence( esc_html_x( 'All cache is cleared', 'WP Fastest Cache', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'All cache is cleared', 'WP Fastest Cache', 'uncanny-automator' ) );
	}

	/**
	 * No options — the trigger fires on every full cache clear.
	 *
	 * @return array
	 */
	public function options() {
		return array();
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		// Don't fire from this integration's own purge action — that would let a
		// recipe's "Purge all caches" action trip this trigger (and loop).
		if ( Wp_Fastest_Cache_Helpers::$is_clearing_via_action ) {
			return false;
		}

		// Anonymous system event — no inherent recipe user, so the trigger fires
		// unattributed. Intentionally no set_user_id() / current-user gate.
		return true;
	}

	/**
	 * No custom tokens — this trigger carries no contextual data.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		return array();
	}
}
