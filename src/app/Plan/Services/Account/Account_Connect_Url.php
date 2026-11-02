<?php
/**
 * Account Connect Url
 *
 * @package Uncanny_Automator\App\Plan\Services\Account
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Plan\Services\Account;

use Uncanny_Automator\Automator_Helpers_Recipe;

/**
 * The signed store link that links the site to a free Automator account.
 *
 * The store sends the owner back to the return URL with the new license key,
 * and `Admin_Menu::activate_license()` checks the redirect nonce the return URL
 * carries. `client` is the return URL encrypted with the `state` secret.
 *
 * The store reads `client`, `state` and `redirect_url` as query parameters,
 * so `redirect_url` is encoded: unencoded, its own `state` would replace the
 * secret and its query string would cut it short, and the store would fall
 * back to a Dashboard URL it builds itself.
 */
final class Account_Connect_Url {

	/**
	 * The link that returns the owner to the Dashboard.
	 */
	public function to_dashboard(): string {
		return $this->returning_to(
			add_query_arg(
				array(
					'post_type' => AUTOMATOR_POST_TYPE_RECIPE,
					'page'      => 'uncanny-automator-dashboard',
					'state'     => wp_create_nonce( 'automator_setup_wizard_redirect_nonce' ),
				),
				admin_url( 'edit.php' )
			)
		);
	}

	/**
	 * The link that returns the owner to an admin URL carrying the redirect nonce.
	 */
	public function returning_to( string $return_url ): string {
		$secret = wp_create_nonce( 'automator_setup_wizard_client' );

		return add_query_arg(
			array(
				'client'       => Automator_Helpers_Recipe::encrypt( array( 'redirect_url' => $return_url ), $secret ),
				'state'        => $secret,
				'__version'    => AUTOMATOR_PLUGIN_VERSION,
				'requested'    => time(),
				'redirect_url' => rawurlencode( $return_url ),
			),
			AUTOMATOR_STORE_URL . AUTOMATOR_FREE_STORE_CONNECT_URL
		);
	}
}
