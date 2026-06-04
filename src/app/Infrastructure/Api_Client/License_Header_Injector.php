<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Api_Client;

use Uncanny_Automator\App\Infrastructure\License\License_Provider_Interface;

/**
 * Injects license and site identity headers into outbound API requests.
 *
 * Adds license-key, site-name, and item-name headers so the Automator
 * API can identify the caller. No cryptographic signing — this is
 * plaintext header injection only.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\App\Infrastructure\Api_Client
 */
class License_Header_Injector {

	/**
	 * @var License_Provider_Interface
	 */
	private $license_provider;

	/**
	 * @param License_Provider_Interface $license_provider The license provider.
	 */
	public function __construct( License_Provider_Interface $license_provider ) {
		$this->license_provider = $license_provider;
	}

	/**
	 * Inject license headers into wp_remote_request arguments.
	 *
	 * @param array $wp_args The WordPress HTTP API arguments.
	 *
	 * @return array The modified arguments with license headers.
	 */
	public function inject( array $wp_args ): array {
		$license_key = $this->license_provider->get_key();

		if ( ! empty( $license_key ) ) {
			$wp_args['headers']['license-key'] = $license_key;
			$wp_args['headers']['site-name']   = $this->license_provider->get_site_name();
			$wp_args['headers']['item-name']   = $this->license_provider->get_item_name();
		}

		return $wp_args;
	}
}
