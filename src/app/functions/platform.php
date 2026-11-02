<?php
/**
 * Public helpers for the Automator Platform (App Integration Platform).
 *
 * @package Uncanny_Automator\App
 */

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Status;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'automator_get_platform_status' ) ) {
	/**
	 * Report-safe snapshot of this site's connection to the App Integration
	 * Platform. Every field is non-sensitive and safe to include in a support
	 * report — the signing secret is never surfaced.
	 *
	 * Reusable across surfaces: the system status page, the licensing screen,
	 * or any admin view that needs to show the connection state. Never throws;
	 * returns a fully-populated array with safe defaults on any failure.
	 *
	 * @since 8.0.0
	 *
	 * @return array{
	 *   connected: bool,
	 *   status: string,
	 *   status_label: string,
	 *   site_id: string,
	 *   site_uuid: string,
	 *   callback_url: string,
	 *   license_active: bool,
	 *   fingerprint: string,
	 *   credentials_rotated_at: int,
	 *   last_register_attempt: int,
	 *   registration_state: string,
	 *   registration_detail: string
	 * }
	 */
	function automator_get_platform_status(): array {

		try {
			return ( new Platform_Status() )->to_array();
		} catch ( \Throwable $e ) {
			return array(
				'connected'              => false,
				'status'                 => 'disconnected',
				'status_label'           => esc_html_x( 'Not connected', 'Automator App Platform', 'uncanny-automator' ),
				'site_id'                => '',
				'site_uuid'              => '',
				'callback_url'           => '',
				'license_active'         => false,
				'fingerprint'            => '',
				'credentials_rotated_at' => 0,
				'last_register_attempt'  => 0,
				'registration_state'     => '',
				'registration_detail'    => '',
			);
		}
	}
}
