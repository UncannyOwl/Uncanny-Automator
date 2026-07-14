<?php

namespace Uncanny_Automator\Integrations\Wordfence;

use Uncanny_Automator\Recipe\Abstract_Helpers;
use WP_User;

/**
 * Class Wordfence_Helpers
 *
 * Shared logic for the Wordfence Security integration: the WP_User token set
 * reused by the 2FA and login triggers, plus small value helpers. Wordfence
 * exposes no option dropdowns for the MVP items (IPs/reasons are free text and
 * the slash selectors are fixed enums), so there are no remote_data segments.
 *
 * @package Uncanny_Automator\Integrations\Wordfence
 */
class Wordfence_Helpers extends Abstract_Helpers {

	/**
	 * "Any" sentinel shared by the trigger slash-selectors.
	 *
	 * @var string
	 */
	const ANY = '-1';

	/**
	 * Token definitions for a WP_User, shared by the 2FA and login triggers.
	 *
	 * @return array
	 */
	public function get_user_token_definitions() {
		return array(
			array(
				'tokenId'   => 'WF_USER_ID',
				'tokenName' => esc_html_x( 'User ID', 'Wordfence Security', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'WF_USER_EMAIL',
				'tokenName' => esc_html_x( 'User email', 'Wordfence Security', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'WF_USER_LOGIN',
				'tokenName' => esc_html_x( 'Username', 'Wordfence Security', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'WF_USER_DISPLAY_NAME',
				'tokenName' => esc_html_x( 'User display name', 'Wordfence Security', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'WF_USER_ROLES',
				'tokenName' => esc_html_x( 'User roles', 'Wordfence Security', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Hydrate the WP_User token values. Returns the full keyset (empty strings
	 * for an invalid user) so a recipe never resolves a partial map.
	 *
	 * @param mixed $user A WP_User instance (or anything else — guarded).
	 *
	 * @return array
	 */
	public function hydrate_user_tokens( $user ) {

		$tokens = array(
			'WF_USER_ID'           => 0,
			'WF_USER_EMAIL'        => '',
			'WF_USER_LOGIN'        => '',
			'WF_USER_DISPLAY_NAME' => '',
			'WF_USER_ROLES'        => '',
		);

		if ( ! $user instanceof WP_User || 0 === (int) $user->ID ) {
			return $tokens;
		}

		$tokens['WF_USER_ID']           = (int) $user->ID;
		$tokens['WF_USER_EMAIL']        = (string) $user->user_email;
		$tokens['WF_USER_LOGIN']        = (string) $user->user_login;
		$tokens['WF_USER_DISPLAY_NAME'] = (string) $user->display_name;
		$tokens['WF_USER_ROLES']        = is_array( $user->roles ) ? implode( ', ', $user->roles ) : '';

		return $tokens;
	}
}
