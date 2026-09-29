<?php

namespace Uncanny_Automator\Integrations\Sucuri;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Sucuri_Helpers
 *
 * Shared logic for the Sucuri Security integration: the hardenable-directory
 * enum, the audit-log severity enum, and the guards the actions need before
 * calling into Sucuri.
 *
 * Sucuri stores every setting in a JSON file rather than `wp_options` and
 * exposes no queryable lists, so both enums are finite and built in PHP — but
 * they still reach the builder over REST like every other dropdown, via the
 * remote_data segments below.
 *
 * @package Uncanny_Automator\Integrations\Sucuri
 */
class Sucuri_Helpers extends Abstract_Helpers {

	// =========================================================================
	// Remote-data handlers — every dropdown loads via REST.
	//
	// Resolved via REST: POST /wp-json/uap/v2/remote-data/sucuri/{seg}.
	// Both enums are closed sets with no "Any" sentinel — the integration has
	// no triggers to offer one to — so there is no bare/`_strict` split here;
	// each segment name is already the strict list.
	// =========================================================================

	/**
	 * Remote-data handler: the directories Sucuri can harden.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_hardening_directories_strict( $request ): array {
		unset( $request );
		return $this->remote_data_success( $this->get_hardening_directories() );
	}

	/**
	 * Remote-data handler: the audit-log severities.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_severities_strict( $request ): array {
		unset( $request );
		return $this->remote_data_success( $this->get_severity_options() );
	}

	// =========================================================================
	// Option builders — the source of truth behind the segments above, and the
	// allowlists the actions validate a submitted value against.
	// =========================================================================

	/**
	 * The three directories Sucuri can harden. Values are the folder names
	 * Sucuri itself uses; resolve_directory() maps them to absolute paths.
	 *
	 * @return array
	 */
	public function get_hardening_directories() {
		return array(
			array(
				'value' => 'wp-content',
				'text'  => esc_html_x( 'wp-content', 'Sucuri Security', 'uncanny-automator' ),
			),
			array(
				'value' => 'wp-content/uploads',
				'text'  => esc_html_x( 'wp-content/uploads', 'Sucuri Security', 'uncanny-automator' ),
			),
			array(
				'value' => 'wp-includes',
				'text'  => esc_html_x( 'wp-includes', 'Sucuri Security', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Map a stored directory value to the absolute path Sucuri expects.
	 *
	 * Mirrors the paths Sucuri builds in src/settings-hardening.php
	 * (:245 uploads, :314 wp-content, :375 wp-includes).
	 *
	 * @param string $directory One of the get_hardening_directories() values.
	 *
	 * @return string Absolute path, or '' when the value is not recognized.
	 */
	public function resolve_directory( $directory ) {

		switch ( (string) $directory ) {
			case 'wp-content':
				return WP_CONTENT_DIR;
			case 'wp-content/uploads':
				return WP_CONTENT_DIR . '/uploads';
			case 'wp-includes':
				return ABSPATH . '/' . WPINC;
			default:
				return '';
		}
	}

	/**
	 * Audit-log severities, mapped to Sucuri's four report*Event() methods.
	 *
	 * @return array
	 */
	public function get_severity_options() {
		return array(
			array(
				'value' => 'info',
				'text'  => esc_html_x( 'Info', 'Sucuri Security', 'uncanny-automator' ),
			),
			array(
				'value' => 'warning',
				'text'  => esc_html_x( 'Warning', 'Sucuri Security', 'uncanny-automator' ),
			),
			array(
				'value' => 'error',
				'text'  => esc_html_x( 'Error', 'Sucuri Security', 'uncanny-automator' ),
			),
			array(
				'value' => 'critical',
				'text'  => esc_html_x( 'Critical', 'Sucuri Security', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Write a message to Sucuri's audit log at the given severity.
	 *
	 * Note that the queue write itself cannot fail from the caller's point of
	 * view: SucuriScanEvent::sendLogToQueue() (src/event.lib.php) discards the
	 * cache-add result and unconditionally returns true. So an unknown severity
	 * is the only way this returns false.
	 *
	 * @param string $severity One of the get_severity_options() values.
	 * @param string $message  The message to log.
	 *
	 * @return bool False when the severity is unknown.
	 */
	public function report_event( $severity, $message ) {

		$methods = array(
			'info'     => 'reportInfoEvent',
			'warning'  => 'reportWarningEvent',
			'error'    => 'reportErrorEvent',
			'critical' => 'reportCriticalEvent',
		);

		$severity = strtolower( (string) $severity );

		if ( ! isset( $methods[ $severity ] ) ) {
			return false;
		}

		return (bool) call_user_func( array( '\SucuriScanEvent', $methods[ $severity ] ), $message );
	}

	/**
	 * Whether the web server supports Sucuri's .htaccess-based hardening.
	 *
	 * Sucuri rejects Nginx and IIS outright in hardenDirectory()
	 * (src/hardening.lib.php:126), so applying hardening is gated on this.
	 * unhardenDirectory() (:160) carries no such check — it only refuses a
	 * directory that is not currently hardened — and removing hardening follows
	 * suit, using this only to explain why nothing is hardened on such a server.
	 *
	 * @return bool
	 */
	public function server_supports_hardening() {

		if ( ! class_exists( '\SucuriScan' ) ) {
			return false;
		}

		return ! \SucuriScan::isNginxServer() && ! \SucuriScan::isIISServer();
	}
}
