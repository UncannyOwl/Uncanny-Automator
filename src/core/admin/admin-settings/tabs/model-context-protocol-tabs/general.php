<?php

namespace Uncanny_Automator;

use Throwable;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Mcp_Resource_Identifier;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server\OAuth_Artifact_Store;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server\Site_Address_Settings;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Token_Manager;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server\OAuth_Client_Registry;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server\OAuth_Pending_Registration_Cleanup;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage existing WordPress connections. New connections start in Automator MCP.
 *
 * @since 7.6
 * @package Uncanny_Automator
 */
class Admin_Settings_Model_Context_Protocol_General {

	public const REVOKE_CLIENT_ACTION = 'automator_mcp_revoke_client_id';

	public const DELETE_CLIENT_ACTION = 'automator_mcp_delete_client_id';

	public const REVOKE_ALL_CLIENTS_ACTION = 'automator_mcp_revoke_all_client_ids';

	public const DELETE_ALL_CLIENTS_ACTION = 'automator_mcp_delete_all_client_ids';

	private const STATUS_QUERY_KEY = 'mcp-client-status';

	/**
	 * OAuth client registry.
	 *
	 * @var OAuth_Client_Registry
	 */
	private $registry;

	/** @var OAuth_Artifact_Store */
	private $artifact_store;

	/** @var Token_Manager */
	private $token_manager;

	/** @var Site_Address_Settings */
	private $address_settings;

	/**
	 * Class constructor.
	 *
	 * @param OAuth_Client_Registry|null $registry Client registry.
	 * @param Token_Manager|null         $token_manager Approval credential reader.
	 * @param OAuth_Artifact_Store|null  $artifact_store OAuth renewal grants.
	 * @param Site_Address_Settings|null $address_settings Where this site's address can be changed.
	 */
	public function __construct( ?OAuth_Client_Registry $registry = null, ?Token_Manager $token_manager = null, ?OAuth_Artifact_Store $artifact_store = null, ?Site_Address_Settings $address_settings = null ) {
		$this->registry         = $registry ?? new OAuth_Client_Registry();
		$this->token_manager    = $token_manager ?? new Token_Manager();
		$this->artifact_store   = $artifact_store ?? new OAuth_Artifact_Store();
		$this->address_settings = $address_settings ?? new Site_Address_Settings();

		add_filter( 'automator_settings_model_context_protocol_tabs', array( $this, 'create_tab' ), 10, 1 );
		add_action( 'admin_post_' . self::REVOKE_CLIENT_ACTION, array( $this, 'handle_revoke_client' ) );
		add_action( 'admin_post_' . self::DELETE_CLIENT_ACTION, array( $this, 'handle_delete_client' ) );
		add_action( 'admin_post_' . self::REVOKE_ALL_CLIENTS_ACTION, array( $this, 'handle_revoke_all_clients' ) );
		add_action( 'admin_post_' . self::DELETE_ALL_CLIENTS_ACTION, array( $this, 'handle_delete_all_clients' ) );
	}

	/**
	 * Register the Connection subtab.
	 *
	 * @param array<string,object> $tabs Registered subtabs.
	 * @return array<string,object>
	 */
	public function create_tab( $tabs ) {
		$tabs['general'] = (object) array(
			'name'     => esc_html__( 'Connections', 'uncanny-automator' ),
			'function' => array( $this, 'tab_output' ),
			'preload'  => false,
			'icon'     => 'plug',
		);

		return $tabs;
	}

	/**
	 * Where to change this site's address while it starts with http://, which
	 * AI apps can't connect to.
	 *
	 * @since 8.0.0
	 *
	 * @return string A Site_Address_Settings location, or empty when the address starts with https://.
	 */
	private function get_http_address_location(): string {
		return ( new Mcp_Resource_Identifier() )->uses_https() ? '' : $this->address_settings->location();
	}

	/**
	 * Output the Connection subtab.
	 *
	 * @return void
	 */
	public function tab_output() {
		$can_manage         = $this->can_manage();
		$clients            = array();
		$client_list_failed = false;
		if ( $can_manage ) {
			try {
				// Also retire abandoned attempts when this host does not run WP-Cron.
				( new OAuth_Pending_Registration_Cleanup( $this->registry, $this->artifact_store, $this->token_manager ) )->run( 1 );
			} catch ( Throwable $throwable ) {
				// A failed cleanup must not hide the registrations or their controls.
				unset( $throwable );
			}
		}
		try {
			$clients = $can_manage ? $this->registry->list_clients() : array();
			$clients = array_values(
				array_filter(
					$clients,
					static function ( $client ) {
						return is_array( $client ) && isset( $client['client_id'] ) && is_string( $client['client_id'] ) && '' !== $client['client_id'];
					}
				)
			);
		} catch ( Throwable $throwable ) {
			$client_list_failed = true;
		}
		$client_ids = array_column(
			array_filter(
				$clients,
				static function ( $client ) {
					return ! empty( $client['active'] );
				}
			),
			'client_id'
		);
		$approvers  = array();
		if ( ! empty( $client_ids ) ) {
			try {
				$access_users  = $this->token_manager->get_active_client_users( $client_ids );
				$renewal_users = $this->artifact_store->get_active_client_users( $client_ids );
				foreach ( $client_ids as $client_id ) {
					$approvers[ $client_id ] = null === $access_users || null === $renewal_users
						? null
						: array_values( array_unique( array_merge( $access_users[ $client_id ], $renewal_users[ $client_id ] ) ) );
				}
			} catch ( Throwable $throwable ) {
				$approvers = array_fill_keys( $client_ids, null );
			}
		}
		foreach ( $clients as &$client ) {
			$client['approved_user_ids'] = ! empty( $client['active'] ) ? $approvers[ $client['client_id'] ] : array();
		}
		unset( $client );
		// A registration alone gives no access, so list only the ones someone
		// approved. If grant evidence is unavailable, retain the row instead of
		// hiding a connection. Cleanup retires the rest.
		$all_clients       = $clients;
		$clients           = array_values(
			array_filter(
				$all_clients,
				static function ( $client ) {
					return empty( $client['active'] ) || array() !== $client['approved_user_ids'];
				}
			)
		);
		$unfinished_count  = count(
			array_filter(
				$all_clients,
				static function ( $client ) {
					return 'dcr' === ( $client['source'] ?? '' )
						&& ! empty( $client['active'] )
						&& empty( $client['approved_at'] )
						&& ! empty( $client['pending_expires_at'] )
						&& array() === $client['approved_user_ids'];
				}
			)
		);
		$approved_users    = array();
		$approved_user_ids = array();
		foreach ( $approvers as $user_ids ) {
			$approved_user_ids = array_merge( $approved_user_ids, $user_ids ?? array() );
		}
		if ( ! empty( $approved_user_ids ) ) {
			foreach ( get_users(
				array(
					'include' => array_unique( $approved_user_ids ),
					'fields' => 'all',
					'blog_id' => 0,
				)
			) as $user ) {
				$approved_users[ $user->ID ] = $user;
			}
		}
		$status                  = automator_filter_has_var( self::STATUS_QUERY_KEY )
			? sanitize_key( automator_filter_input( self::STATUS_QUERY_KEY ) )
			: '';
		$bulk_counts             = array(
			'updated' => automator_filter_has_var( 'mcp-client-updated' ) ? absint( automator_filter_input( 'mcp-client-updated' ) ) : 0,
			'failed'  => automator_filter_has_var( 'mcp-client-failed' ) ? absint( automator_filter_input( 'mcp-client-failed' ) ) : 0,
		);
		$tools_url               = Admin_Settings_Model_Context_Protocol::utility_get_model_context_protocol_page_link( 'tools' );
		$automator_abilities_url = Admin_Settings_Model_Context_Protocol::utility_get_model_context_protocol_page_link( 'automator-abilities' );
		$wordpress_abilities_url = Admin_Settings_Model_Context_Protocol::utility_get_model_context_protocol_page_link( 'wordpress-abilities' );
		$http_address_location   = $this->get_http_address_location();
		$registration_count      = count( $all_clients );
		$active_registrations    = count(
			array_filter(
				$all_clients,
				static function ( $client ) {
					return ! empty( $client['active'] );
				}
			)
		);

		include Utilities::automator_get_view( 'admin-settings/tab/model-context-protocol/general.php' );
	}

	/**
	 * Handle authenticated client revocation.
	 *
	 * @return void
	 */
	public function handle_revoke_client() {
		$client_id = $this->get_post_opaque_string( 'client_id' );
		$revoked   = false;

		try {
			if (
				$this->can_manage()
				&& wp_verify_nonce( $this->get_post_string( '_wpnonce' ), self::REVOKE_CLIENT_ACTION . ':' . $client_id )
				&& $this->registry->has_stored_client( $client_id )
			) {
				$revoked = $this->registry->revoke( $client_id );
			}
		} catch ( Throwable $throwable ) {
			error_log( sprintf( '[Automator MCP] Client revocation failed (%s).', get_class( $throwable ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Log only the exception type.
		}

		wp_safe_redirect( add_query_arg( self::STATUS_QUERY_KEY, $revoked ? 'revoked' : 'revoke-failed', $this->get_page_url() ), 303 );
		exit;
	}

	/**
	 * Handle authenticated deletion of a disconnected client record.
	 *
	 * @return void
	 */
	public function handle_delete_client() {
		$client_id = $this->get_post_opaque_string( 'client_id' );
		$deleted   = false;

		try {
			if (
				$this->can_manage()
				&& wp_verify_nonce( $this->get_post_string( '_wpnonce' ), self::DELETE_CLIENT_ACTION . ':' . $client_id )
				&& $this->registry->has_stored_client( $client_id )
			) {
				$deleted = $this->registry->delete( $client_id );
			}
		} catch ( Throwable $throwable ) {
			error_log( sprintf( '[Automator MCP] Client deletion failed (%s).', get_class( $throwable ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Log only the exception type.
		}

		wp_safe_redirect( add_query_arg( self::STATUS_QUERY_KEY, $deleted ? 'deleted' : 'delete-failed', $this->get_page_url() ), 303 );
		exit;
	}

	/**
	 * Disconnect every currently active stored client after explicit confirmation.
	 *
	 * @return void
	 */
	public function handle_revoke_all_clients() {
		$result = array(
			'processed' => 0,
			'failed'    => 1,
		);

		try {
			$result = $this->process_revoke_all_request(
				$this->get_post_string( '_wpnonce' ),
				'1' === $this->get_post_string( 'confirmed' )
			);
		} catch ( Throwable $throwable ) {
			error_log( sprintf( '[Automator MCP] Bulk client revocation failed (%s).', get_class( $throwable ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Log only the exception type.
		}

		$status = 0 === $result['failed'] ? 'all-revoked' : 'bulk-revoke-failed';
		wp_safe_redirect(
			add_query_arg(
				array(
					self::STATUS_QUERY_KEY => $status,
					'mcp-client-updated'   => $result['processed'],
					'mcp-client-failed'    => $result['failed'],
				),
				$this->get_page_url()
			),
			303
		);
		exit;
	}

	/**
	 * Delete every stored client only after all of them have been disconnected.
	 *
	 * @return void
	 */
	public function handle_delete_all_clients() {
		$result = array(
			'processed' => 0,
			'failed'    => 1,
		);

		try {
			$result = $this->process_delete_all_request(
				$this->get_post_string( '_wpnonce' ),
				'1' === $this->get_post_string( 'confirmed' )
			);
		} catch ( Throwable $throwable ) {
			error_log( sprintf( '[Automator MCP] Bulk client deletion failed (%s).', get_class( $throwable ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Log only the exception type.
		}

		$status = 0 === $result['failed'] ? 'all-deleted' : 'bulk-delete-failed';
		wp_safe_redirect(
			add_query_arg(
				array(
					self::STATUS_QUERY_KEY => $status,
					'mcp-client-updated'   => $result['processed'],
					'mcp-client-failed'    => $result['failed'],
				),
				$this->get_page_url()
			),
			303
		);
		exit;
	}

	/**
	 * Revoke active clients from a fresh server-side registry listing.
	 *
	 * @return array{processed:int,failed:int}
	 */
	private function process_revoke_all_request( string $nonce, bool $confirmed ): array {
		if ( ! $this->can_manage() || ! $confirmed || ! wp_verify_nonce( $nonce, self::REVOKE_ALL_CLIENTS_ACTION ) ) {
			return array(
				'processed' => 0,
				'failed'    => 1,
			);
		}

		$result = array(
			'processed' => 0,
			'failed'    => 0,
		);
		foreach ( $this->registry->list_clients() as $client ) {
			$client_id = isset( $client['client_id'] ) && is_string( $client['client_id'] ) ? $client['client_id'] : '';
			if ( '' === $client_id || empty( $client['active'] ) ) {
				continue;
			}

			try {
				$updated = $this->registry->has_stored_client( $client_id ) && $this->registry->revoke( $client_id );
			} catch ( Throwable $throwable ) {
				$updated = false;
			}
			if ( $updated ) {
				++$result['processed'];
			} else {
				++$result['failed'];
			}
		}

		return $result;
	}

	/**
	 * Delete disconnected clients only when no stored client is still active.
	 *
	 * @return array{processed:int,failed:int}
	 */
	private function process_delete_all_request( string $nonce, bool $confirmed ): array {
		if ( ! $this->can_manage() || ! $confirmed || ! wp_verify_nonce( $nonce, self::DELETE_ALL_CLIENTS_ACTION ) ) {
			return array(
				'processed' => 0,
				'failed'    => 1,
			);
		}

		$clients = $this->registry->list_clients();
		foreach ( $clients as $client ) {
			$client_id = isset( $client['client_id'] ) && is_string( $client['client_id'] ) ? $client['client_id'] : '';
			if ( '' === $client_id || ! empty( $client['active'] ) || ! $this->registry->is_revoked( $client_id ) ) {
				return array(
					'processed' => 0,
					'failed'    => 1,
				);
			}
		}

		$result = array(
			'processed' => 0,
			'failed'    => 0,
		);
		foreach ( $clients as $client ) {
			$client_id = (string) $client['client_id'];
			try {
				$updated = $this->registry->has_stored_client( $client_id ) && $this->registry->delete( $client_id );
			} catch ( Throwable $throwable ) {
				$updated = false;
			}
			if ( $updated ) {
				++$result['processed'];
			} else {
				++$result['failed'];
			}
		}

		return $result;
	}

	/**
	 * Require both Automator settings access and strict administration.
	 */
	private function can_manage(): bool {
		return current_user_can( automator_get_admin_capability() ) // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Automator settings capability is configurable.
			&& current_user_can( 'manage_options' );
	}

	/**
	 * The Connection page URL.
	 */
	private function get_page_url(): string {
		return Admin_Settings_Model_Context_Protocol::utility_get_model_context_protocol_page_link( 'general' );
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

	/**
	 * Read an exact OAuth identifier or redirect URI without URL normalization.
	 */
	private function get_post_opaque_string( string $key ): string {
		if ( ! isset( $_POST[ $key ] ) || ! is_string( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The caller validates the identifier-bound nonce.
			return '';
		}

		$value = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Compared byte-for-byte with a stored server-validated client ID before mutation.

		return strlen( $value ) <= 2048 && 0 === preg_match( '/[\x00-\x1F\x7F]/', $value ) ? $value : '';
	}
}

new Admin_Settings_Model_Context_Protocol_General();
