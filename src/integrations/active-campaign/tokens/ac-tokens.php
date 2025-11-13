<?php

namespace Uncanny_Automator\Integrations\Active_Campaign;

/**
 * ActiveCampaign Token Manager
 */
class AC_TOKENS {

	/**
	 * Define contact tokens for ActiveCampaign triggers
	 *
	 * @return array
	 */
	public static function define_contact_tokens() {
		return array(
			array(
				'tokenId'   => 'EMAIL',
				'tokenName' => esc_html_x( 'Email address', 'ActiveCampaign', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'TAGS',
				'tokenName' => esc_html_x( 'All contact tags (comma separated)', 'ActiveCampaign', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'FIRST_NAME',
				'tokenName' => esc_html_x( 'First name', 'ActiveCampaign', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LAST_NAME',
				'tokenName' => esc_html_x( 'Last name', 'ActiveCampaign', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'PHONE',
				'tokenName' => esc_html_x( 'Phone', 'ActiveCampaign', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CUSTOMER_ACCT_NAME',
				'tokenName' => esc_html_x( 'Account', 'ActiveCampaign', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Define tag tokens for ActiveCampaign triggers
	 *
	 * @return array
	 */
	public static function define_tag_tokens() {
		return array(
			array(
				'tokenId'   => 'TAG',
				'tokenName' => esc_html_x( 'Tag', 'ActiveCampaign', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Define contact and tag tokens combined
	 *
	 * @return array
	 */
	public static function define_contact_tag_tokens() {
		return array_merge(
			self::define_tag_tokens(),
			self::define_contact_tokens()
		);
	}

	/**
	 * Hydrate contact tokens with data from ActiveCampaign webhook
	 *
	 * @param array $hook_args The hook arguments from the webhook
	 * @return array
	 */
	public static function hydrate_contact_tokens( $hook_args ) {
		if ( empty( $hook_args ) || ! is_array( $hook_args ) ) {
			return array();
		}

		$data    = $hook_args[0];
		$contact = $data['contact'] ?? array();

		return array(
			'TAGS'               => $contact['tags'] ?? '',
			'EMAIL'              => $contact['email'] ?? '',
			'FIRST_NAME'         => $contact['first_name'] ?? '',
			'LAST_NAME'          => $contact['last_name'] ?? '',
			'PHONE'              => $contact['phone'] ?? '',
			'CUSTOMER_ACCT_NAME' => $contact['customer_acct_name'] ?? '',
		);
	}

	/**
	 * Hydrate tag tokens with data from ActiveCampaign webhook
	 *
	 * @param array $hook_args The hook arguments from the webhook
	 * @return array
	 */
	public static function hydrate_tag_tokens( $hook_args ) {
		if ( empty( $hook_args ) || ! is_array( $hook_args ) ) {
			return array();
		}

		$data = $hook_args[0] ?? array();

		return array(
			'TAG' => $data['tag'] ?? '',
		);
	}

	/**
	 * Hydrate contact and tag tokens combined
	 *
	 * @param array $hook_args The hook arguments from the webhook
	 * @return array
	 */
	public static function hydrate_contact_tag_tokens( $hook_args ) {
		return array_merge(
			self::hydrate_tag_tokens( $hook_args ),
			self::hydrate_contact_tokens( $hook_args )
		);
	}
}
