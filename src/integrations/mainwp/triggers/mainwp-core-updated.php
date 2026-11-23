<?php

namespace Uncanny_Automator\Integrations\Mainwp;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Mainwp_Core_Updated
 *
 * Trigger: WordPress core is updated on a child site.
 *
 * MainWP fires `mainwp_website_updated` after every update request it sends to
 * a child, whichever screen, REST call or cron sent it, and on every outcome.
 * The trigger keeps only a core update (`wp`) that succeeded and reports the
 * version it installed: when core is already current, the child still answers
 * SUCCESS, with no version.
 *
 * @property Mainwp_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 * @since 8.1.0
 */
class Mainwp_Core_Updated extends Trigger {

	/**
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'MAINWP_CORE_UPDATED', 'MAINWP' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'MAINWP_SITE' )
			->hook( 'mainwp_website_updated', 10, 4 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_sentence( esc_html_x( 'WordPress core is updated on a child site', 'MainWP', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'WordPress core is updated on a child site', 'MainWP', 'uncanny-automator' ) );
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
			array(
				array(
					'tokenId'   => 'OLD_VERSION',
					'tokenName' => esc_html_x( 'Previous version', 'MainWP', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'NEW_VERSION',
					'tokenName' => esc_html_x( 'New version', 'MainWP', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
			)
		);
	}

	/**
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args `$website`, `$type`, `$list`, `$information`.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$type        = $hook_args[1] ?? '';
		$information = $hook_args[3] ?? null;

		if ( 'wp' !== $type || ! is_array( $information ) ) {
			return false;
		}

		if ( ! isset( $information['upgrade'] ) || 'SUCCESS' !== $information['upgrade'] || empty( $information['version'] ) ) {
			return false;
		}

		return 0 < $this->item_helpers->resolve_site_id( $hook_args[0] ?? null );
	}

	/**
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args `$website`, `$type`, `$list`, `$information`.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$information = is_array( $hook_args[3] ?? null ) ? $hook_args[3] : array();

		return array_merge(
			Mainwp_Tokens::hydrate_site_tokens( $hook_args[0] ?? null ),
			array(
				'OLD_VERSION' => is_scalar( $information['old_version'] ?? null ) ? (string) $information['old_version'] : '',
				'NEW_VERSION' => is_scalar( $information['version'] ?? null ) ? (string) $information['version'] : '',
			)
		);
	}
}
