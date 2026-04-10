<?php

namespace Uncanny_Automator\Integrations\Instagram;

use Uncanny_Automator\App_Integrations\App_Helpers;
use Uncanny_Automator\Integrations\Facebook\Facebook_Bridge;
use Exception;

/**
 * Class Instagram_App_Helpers
 *
 * Instagram uses Facebook's credentials stored in the vault.
 * This class delegates credential validation to Facebook's API caller.
 *
 * @package Uncanny_Automator\Integrations\Instagram
 *
 * @property Instagram_Api_Caller $api
 */
class Instagram_App_Helpers extends App_Helpers {

	use Instagram_Publish_Retry;

	/**
	 * The facebook bridge.
	 *
	 * @var Facebook_Bridge
	 */
	private $facebook_bridge;

	////////////////////////////////////////////////////////////
	// Abstract Methods
	////////////////////////////////////////////////////////////

	/**
	 * Set class properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		$this->facebook_bridge = Facebook_Bridge::get_instance();
		// Set the main user credentials and account option names from Facebook.
		$this->set_credentials_option_name(
			$this->facebook_bridge->get_credentials_option_key()
		);
		$this->set_account_option_name(
			$this->facebook_bridge->get_pages_option_key()
		);
	}

	/**
	 * Is connected.
	 *
	 * @return bool
	 */
	public function is_connected() {
		// Check if we have any connected Instagram accounts
		$connected_accounts = $this->count_connected_instagram_accounts();
		return ! empty( $connected_accounts );
	}

	/**
	 * Validate credentials.
	 *
	 * Instagram uses Facebook's credentials stored in the vault.
	 *
	 * @param array $credentials The credentials to validate.
	 * @param array $args        Additional arguments.
	 *
	 * @return array The validated credentials.
	 * @throws Exception If credentials are invalid.
	 */
	public function validate_credentials( $credentials, $args = array() ) {
		if ( ! $this->facebook_bridge->is_vault_credentials( $credentials ) ) {
			throw new Exception(
				esc_html_x( 'Facebook is not connected. Instagram requires a connected Facebook account.', 'Instagram', 'uncanny-automator' )
			);
		}

		return $credentials;
	}

	/**
	 * Count the number of connected Instagram accounts.
	 *
	 * @return int The number of connected Instagram accounts.
	 */
	public function count_connected_instagram_accounts() {
		$total = 0;
		$pages = $this->facebook_bridge->get_facebook_pages_settings();

		foreach ( (array) $pages as $page ) {
			$account = $this->get_instagram_account( $page );
			if ( ! empty( $account ) ) {
				++$total;
			}
		}

		return $total;
	}

	/**
	 * Gets the Instagram account data if connected.
	 *
	 * @param array $page The Facebook page data array.
	 * @return array The account data or empty array if not connected.
	 */
	private function get_instagram_account( $page ) {
		$account = $page['ig_account'] ?? array();

		// Only return account data if it's actually connected.
		if ( empty( $account ) || ( $account['connection_status'] ?? '' ) !== 'connected' ) {
			return array();
		}

		return $account;
	}

	/**
	 * Get instagram accounts options.
	 *
	 * @return array Array of account options for select fields.
	 */
	public function get_instagram_accounts_options() {
		$options = array();
		$pages   = $this->facebook_bridge->get_facebook_pages_settings();

		if ( ! is_array( $pages ) || empty( $pages ) ) {
			return $options;
		}

		foreach ( $pages as $page ) {
			$account = $this->get_instagram_account( $page );
			if ( ! empty( $account ) ) {
				$options[] = array(
					'value' => $page['value'], // Facebook page ID.
					'text'  => $account['username'], // Instagram account username.
				);
			}
		}

		return $options;
	}

	/**
	 * Gets the Instagram business account ID for a facebook page.
	 *
	 * @param array $page The Facebook page data array.
	 * @return string The business account ID or empty string if not found.
	 */
	public function get_facebook_page_instagram_business_id( $page ) {
		$account = $this->get_instagram_account( $page );
		return $account['id'] ?? '';
	}

	/**
	 * Resync the Instagram account for the given page ID.
	 *
	 * @param string $page_id The Facebook page ID.
	 *
	 * @return array - Facebook page settings with instagram account data.
	 */
	public function resync_instagram_account( $page_id ) {
		// Fetch the Instagram account for the given page ID.
		$account = $this->api->fetch_instagram_account_for_page( $page_id );

		// Retrieve existing Facebook pages settings.
		$pages = $this->facebook_bridge->get_facebook_pages_settings();

		// Prepare account data with metadata
		$account_data = array(
			'connection_status' => empty( $account ) || is_wp_error( $account )
				? 'not_connected'
				: 'connected',
		);

		// If we have valid account data, merge it
		if ( ! empty( $account ) && ! is_wp_error( $account ) ) {
			$account_data = array_merge( $account_data, $account );
		}

		// Merge data into existing Facebook pages settings.
		foreach ( $pages as $index => $page ) {
			if ( (string) $page['value'] === (string) $page_id ) {
				$pages[ $index ]['ig_account'] = $account_data;
			}
		}

		// Update Facebook pages settings.
		$this->facebook_bridge->update_facebook_pages_settings( $pages );

		// Return the updated page settings.
		return $pages;
	}
}
