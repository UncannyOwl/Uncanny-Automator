<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Addons;

use Uncanny_Automator\App\Plan\Addons\Value_Objects\Addon;

/**
 * Fetches the signed zip URL for an addon from the store.
 *
 * Pure HTTP: license validity and tier entitlement are decided upstream by
 * Addon_Installer_Service, which is also the only caller. A successful
 * response yields a short-lived download URL the WordPress upgrader (or the
 * user) can pull the package from.
 */
final class Addon_Download_Client {

	/**
	 * Store path serving signed addon downloads.
	 *
	 * @var string
	 */
	const DOWNLOAD_PATH = 'wp-json/automator-addons/v1/addon';

	/**
	 * Resolve the signed download URL for an addon.
	 *
	 * @param Addon  $addon       The addon to download.
	 * @param string $license_key The site's license key.
	 *
	 * @return string
	 * @throws Addon_Download_Exception When the URL cannot be obtained.
	 */
	public function get_download_url( Addon $addon, string $license_key ): string {

		$key = sanitize_key( $license_key );

		if ( '' === $key ) {
			throw new Addon_Download_Exception(
				esc_html_x( 'Invalid request. A license key is required.', 'Addons', 'uncanny-automator' ),
				400
			);
		}

		$response = wp_remote_get( $this->request_url( $addon, $key ) );

		if ( is_wp_error( $response ) ) {
			throw new Addon_Download_Exception(
				sprintf(
					// translators: %s is the transport error message.
					esc_html__( 'Request failed: %s', 'uncanny-automator' ),
					esc_html( $response->get_error_message() )
				),
				500
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$body = is_array( $body ) ? $body : array();
		$url  = isset( $body['url'] ) ? (string) $body['url'] : '';

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) || false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
			throw new Addon_Download_Exception(
				isset( $body['message'] )
					? esc_html( (string) $body['message'] )
					: esc_html_x( 'Failed to get addon download URL.', 'Addons', 'uncanny-automator' ),
				422
			);
		}

		return $url;
	}

	/**
	 * Build the signed-download request URL.
	 *
	 * @param Addon  $addon       The addon to download.
	 * @param string $license_key The sanitized license key.
	 *
	 * @return string
	 */
	private function request_url( Addon $addon, string $license_key ): string {
		return AUTOMATOR_STORE_URL .
			trailingslashit( self::DOWNLOAD_PATH ) .
			implode(
				'/',
				array(
					absint( $addon->id() ),
					$license_key,
					sanitize_key( sanitize_title( $addon->name() ) ),
				)
			);
	}
}
