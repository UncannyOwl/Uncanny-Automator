<?php
/**
 * MCP public key fetcher.
 *
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Client;

use WP_Error;

/**
 * Class Client_Public_Key_Fetcher
 *
 * Handles remote HTTP requests to retrieve the public key.
 */
class Client_Public_Key_Fetcher {

	/**
	 * HTTP client callback.
	 *
	 * @var callable
	 */
	private $http_post;

	/**
	 * Logger callback.
	 *
	 * @var callable|null
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param callable|null $http_post HTTP client.
	 * @param callable|null $logger    Logger callback.
	 */
	public function __construct( ?callable $http_post = null, ?callable $logger = null ) {
		$this->http_post = $http_post ? $http_post : 'wp_remote_post';
		$this->logger    = $logger;
	}

	/**
	 * Fetch the public key from the remote API.
	 *
	 * @param string $base_url     Base inference URL.
	 * @param array  $license_data License payload.
	 * @param int    $now          Current timestamp.
	 * @return Client_Public_Key_Record|null
	 */
	public function fetch( string $base_url, array $license_data, int $now ): ?Client_Public_Key_Record {
		if ( ! $this->has_required_license_data( $license_data ) ) {
			$this->log( 'Missing license data for MCP public key request.' );
			return null;
		}

		$response = call_user_func(
			$this->http_post,
			trailingslashit( $base_url ) . 'api/public-key',
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $license_data ),
			)
		);

		if ( $response instanceof WP_Error ) {
			$this->log( 'Failed to reach MCP public key endpoint: ' . $response->get_error_message() );
			return null;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$this->log( 'MCP public key endpoint returned HTTP ' . wp_remote_retrieve_response_code( $response ) );
			return null;
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $decoded ) ) {
			$this->log( 'Invalid JSON from MCP public key endpoint.' );
			return null;
		}

		$base64_key = isset( $decoded['public_key'] ) ? trim( (string) $decoded['public_key'] ) : '';

		if ( '' === $base64_key ) {
			$this->log( 'API response missing public key data.' );
			return null;
		}

		if ( false === base64_decode( $base64_key, true ) ) {  // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- OAuth/JWT encoding.
			$this->log( 'API returned an invalid base64 public key.' );
			return null;
		}

		$version = isset( $decoded['version'] ) ? (string) $decoded['version'] : md5( $base64_key );

		return new Client_Public_Key_Record( $version, $base64_key, $now, 'mcp_api', 'ok' );
	}

	/**
	 * Determine if the license payload is complete.
	 *
	 * @param array $license_data License payload.
	 * @return bool
	 */
	private function has_required_license_data( array $license_data ): bool {
		return ! empty( $license_data['license_key'] )
			&& ! empty( $license_data['item_name'] )
			&& ! empty( $license_data['site_name'] );
	}

	/**
	 * Log a message if a logger is available.
	 *
	 * @param string $message Message to log.
	 * @return void
	 */
	private function log( string $message ): void {
		if ( $this->logger && is_callable( $this->logger ) ) {
			call_user_func( $this->logger, $message, 'MCP Public Key' );
		} elseif ( function_exists( 'automator_log' ) ) {
			\automator_log( $message, 'MCP Public Key' );
		}
	}
}
