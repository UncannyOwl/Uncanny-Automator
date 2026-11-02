<?php

namespace Uncanny_Automator;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\Mcp_Auth_Log;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Model Context Protocol authentication log settings and request handlers.
 *
 * Support at scale needs "send us your logs" to be a real answer; this tab
 * is where an administrator gets that log.
 *
 * @since 7.6
 * @package Uncanny_Automator
 */
class Admin_Settings_Model_Context_Protocol_Authentication_Log {

	public const DOWNLOAD_LOG_ACTION = 'automator_mcp_download_auth_log';

	public const CLEAR_LOG_ACTION = 'automator_mcp_clear_auth_log';

	private const LOG_STATUS_QUERY_KEY = 'mcp-log-status';

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_filter( 'automator_settings_model_context_protocol_tabs', array( $this, 'create_tab' ), 50, 1 );
		add_action( 'admin_post_' . self::DOWNLOAD_LOG_ACTION, array( $this, 'handle_download_log' ) );
		add_action( 'admin_post_' . self::CLEAR_LOG_ACTION, array( $this, 'handle_clear_log' ) );
	}

	/**
	 * Register the Authentication log subtab.
	 *
	 * @param array<string,object> $tabs Registered subtabs.
	 * @return array<string,object>
	 */
	public function create_tab( $tabs ) {
		$tabs['authentication-log'] = (object) array(
			'name'     => esc_html__( 'Connection log', 'uncanny-automator' ),
			'function' => array( $this, 'tab_output' ),
			'preload'  => false,
			'icon'     => 'list-check',
		);

		return $tabs;
	}

	/**
	 * Output the Authentication log subtab.
	 *
	 * @return void
	 */
	public function tab_output() {
		$can_manage_log = $this->can_manage();
		$auth_log       = $can_manage_log ? array_slice( Mcp_Auth_Log::all(), 0, 100 ) : array();
		$log_status     = automator_filter_has_var( self::LOG_STATUS_QUERY_KEY )
			? sanitize_key( automator_filter_input( self::LOG_STATUS_QUERY_KEY ) )
			: '';

		include Utilities::automator_get_view( 'admin-settings/tab/model-context-protocol/authentication-log.php' );
	}

	/**
	 * Decide what the Clear log request did, so the page can say so.
	 *
	 * An expired nonce from a long-open tab is reported on its own, because
	 * it is neither a permission problem nor a failed clear.
	 */
	private function resolve_clear_log_status(): string {
		if ( ! $this->can_manage() ) {
			return 'clear-failed';
		}

		if ( ! wp_verify_nonce( $this->get_post_string( '_wpnonce' ), self::CLEAR_LOG_ACTION ) ) {
			return 'session-expired';
		}

		return Mcp_Auth_Log::clear() ? 'cleared' : 'clear-failed';
	}

	/**
	 * Stream the authentication log as a plain text file for support.
	 *
	 * @return void
	 */
	public function handle_download_log() {
		if ( ! $this->can_manage() ) {
			wp_die( esc_html__( 'You need administrator access to download the authentication log.', 'uncanny-automator' ), 403 );
		}

		// An expired nonce from a long-open tab is not a permission problem.
		if ( ! wp_verify_nonce( $this->get_post_string( '_wpnonce' ), self::DOWNLOAD_LOG_ACTION ) ) {
			wp_safe_redirect( add_query_arg( self::LOG_STATUS_QUERY_KEY, 'session-expired', Admin_Settings_Model_Context_Protocol::utility_get_model_context_protocol_page_link( 'authentication-log' ) ), 303 );
			exit;
		}

		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="automator-mcp-auth-log.txt"' );
		echo Mcp_Auth_Log::export_text(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text attachment assembled from sanitized log fields.
		exit;
	}

	/**
	 * Clear the authentication log.
	 *
	 * @return void
	 */
	public function handle_clear_log() {
		wp_safe_redirect(
			add_query_arg(
				self::LOG_STATUS_QUERY_KEY,
				$this->resolve_clear_log_status(),
				Admin_Settings_Model_Context_Protocol::utility_get_model_context_protocol_page_link( 'authentication-log' )
			),
			303
		);
		exit;
	}

	/**
	 * Require both Automator settings access and strict administration.
	 */
	private function can_manage(): bool {
		return current_user_can( automator_get_admin_capability() ) // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Automator settings capability is configurable.
			&& current_user_can( 'manage_options' );
	}

	/**
	 * Read one scalar POST value without coercing arrays into strings.
	 */
	private function get_post_string( string $key ): string {
		if ( ! isset( $_POST[ $key ] ) || ! is_string( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The caller validates the action-specific nonce after reading it through this helper.
			return '';
		}

		return sanitize_text_field( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The caller validates the action-specific nonce before mutation.
	}
}

new Admin_Settings_Model_Context_Protocol_Authentication_Log();
