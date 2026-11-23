<?php

namespace Uncanny_Automator\Integrations\Mainwp;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Mainwp_Child_Site_Removed
 *
 * Trigger: A child site is removed.
 *
 * MainWP fires the hook after it deletes the site's rows, even when the delete
 * removed nothing. The trigger runs only when the row is gone, and every token
 * comes from the site object the hook passes.
 *
 * @property Mainwp_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 * @since 8.1.0
 */
class Mainwp_Child_Site_Removed extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'MAINWP_CHILD_SITE_REMOVED', 'MAINWP' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'MAINWP_SITE' )
			->hook( 'mainwp_delete_site', 10, 1 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_sentence( esc_html_x( 'A child site is removed', 'MainWP', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A child site is removed', 'MainWP', 'uncanny-automator' ) );
	}

	/**
	 * @param array $trigger The trigger settings.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge(
			$tokens,
			Mainwp_Tokens::site_tokens(),
			Mainwp_Tokens::site_admin_tokens(),
			Mainwp_Tokens::site_client_tokens()
		);
	}

	/**
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args `$site`, read before the delete.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$site    = $hook_args[0] ?? null;
		$site_id = is_object( $site ) ? $this->item_helpers->resolve_site_id( $site ) : 0;

		return 0 < $site_id && null === $this->item_helpers->get_site( $site_id );
	}

	/**
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args `$site`, read before the delete.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$site   = $hook_args[0] ?? null;
		$client = is_object( $site ) ? $this->item_helpers->get_client( $site->client_id ?? 0 ) : null;

		return array_merge(
			Mainwp_Tokens::hydrate_site_tokens( $site ),
			Mainwp_Tokens::hydrate_site_admin_tokens( $site ),
			Mainwp_Tokens::hydrate_site_client_tokens( $client )
		);
	}
}
