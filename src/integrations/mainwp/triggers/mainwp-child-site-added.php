<?php

namespace Uncanny_Automator\Integrations\Mainwp;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Mainwp_Child_Site_Added
 *
 * Trigger: A child site is added.
 *
 * The Staging extension's clone path fires the same hook before it checks the
 * insert, with the **source** site as the second argument. So the site is read
 * by the ID, never from the second argument, and a failed clone is ignored.
 *
 * @property Mainwp_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 * @since 8.1.0
 */
class Mainwp_Child_Site_Added extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'MAINWP_CHILD_SITE_ADDED', 'MAINWP' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'MAINWP_SITE' )
			->hook( 'mainwp_added_new_site', 10, 2 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_sentence( esc_html_x( 'A child site is added', 'MainWP', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A child site is added', 'MainWP', 'uncanny-automator' ) );
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
			Mainwp_Tokens::site_tags_tokens()
		);
	}

	/**
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args `$id`, `$website`.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		// A failed clone passes false as the ID.
		return null !== $this->item_helpers->get_site( $hook_args[0] ?? 0 );
	}

	/**
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args `$id`, `$website`.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$site = $this->item_helpers->get_site( $hook_args[0] ?? 0, true );

		return array_merge(
			Mainwp_Tokens::hydrate_site_tokens( $site ),
			Mainwp_Tokens::hydrate_site_admin_tokens( $site ),
			Mainwp_Tokens::hydrate_site_tags_tokens( $site )
		);
	}
}
