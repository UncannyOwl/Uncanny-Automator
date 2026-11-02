<?php
/**
 * API Infrastructure - Global Service Functions
 *
 * Lightweight DI container via global functions with static caching.
 * Production code uses these to get service instances.
 * Tests can override via the $override parameter.
 *
 * @package Uncanny_Automator\App\Infrastructure
 */

declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Client;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Hmac_Validator;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Json_Canonical;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\License_Header_Injector;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Http;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Site_Credentials_Store;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Site_Heartbeat_Cron;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Site_Identity_Service;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Source_Url_Token;
use Uncanny_Automator\App\Infrastructure\Connections\Connection_Adoption;
use Uncanny_Automator\App\Infrastructure\Connections\Connection_Error_Handler;
use Uncanny_Automator\App\Infrastructure\Connections\Connection_Registry;
use Uncanny_Automator\App\Infrastructure\Connections\Connection_Sync;
use Uncanny_Automator\App\Infrastructure\Connections\Platform_Connections_Api;
use Uncanny_Automator\App\Infrastructure\Connections\Credential_Lift_Observer;
use Uncanny_Automator\App\Infrastructure\Connections\Disconnect_Service;
use Uncanny_Automator\App\Infrastructure\Connections\Reconnect_Service;
use Uncanny_Automator\App\Infrastructure\Connections\Legacy_Upgrade_Observer;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Webhooks\Webhook_Router;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Api_Log_Store;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Wire_Cipher;
use Uncanny_Automator\App\Infrastructure\File_Serve\Allowed_Path_Policy;
use Uncanny_Automator\App\Infrastructure\File_Serve\File_Serve_Controller;
use Uncanny_Automator\App\Infrastructure\File_Serve\Signed_Url;
use Uncanny_Automator\App\Infrastructure\License\Credit_Manager;
use Uncanny_Automator\App\Infrastructure\License\License_Manager;
use Uncanny_Automator\App\Feature_State\Application\Get_Feature_State;
use Uncanny_Automator\App\Feature_State\Infrastructure\Automator_Policy_State_Adapter;
use Uncanny_Automator\App\Feature_State\Infrastructure\Mcp_Allocation_Facts_Reader;
use Uncanny_Automator\App\Feature_State\Infrastructure\WP_Last_Known_Feature_State_Store;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the License Manager instance.
 *
 * @param License_Manager|null $override Optional override for testing.
 * @return License_Manager
 */
function automator_license_manager( ?License_Manager $override = null, bool $reset = false ): ?License_Manager {
	static $instance = null;
	if ( $reset ) {
		$instance = null;
		return null;
	}
	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}
	if ( null === $instance ) {
		$instance = new License_Manager();
	}
	return $instance;
}

/**
 * Get the Wire_Cipher instance — used to unwrap `enc:v1:`-prefixed
 * sensitive fields from platform responses (Contract H extension,
 * specced 2026-04-29 in the salvage playbook).
 *
 * @param Wire_Cipher|null $override Optional override for testing.
 * @return Wire_Cipher
 */
function automator_wire_cipher( ?Wire_Cipher $override = null, bool $reset = false ): ?Wire_Cipher {
	static $instance = null;
	if ( $reset ) {
		$instance = null;
		return null;
	}
	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}
	if ( null === $instance ) {
		$instance = new Wire_Cipher( automator_site_credentials_store() );
	}
	return $instance;
}

/**
 * Get the request-wide Automator feature-state query.
 *
 * Construction is lazy and the result lives only for this PHP request. The
 * query itself memoizes success or failure so every UI consumer sees one
 * coherent Axis snapshot. Production also supplies the license-bound store used
 * to preserve the most recent successful snapshot across requests.
 *
 * @param Get_Feature_State|null $override Optional override for testing.
 * @param bool                   $reset    Whether to reset the cached query.
 *
 * @return Get_Feature_State|null
 */
function automator_feature_state_query( ?Get_Feature_State $override = null, bool $reset = false ): ?Get_Feature_State {
	static $instance = null;

	if ( $reset ) {
		$instance = null;
		return null;
	}

	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}

	if ( null === $instance ) {
		$licenses = automator_license_manager();

		if ( ! $licenses instanceof License_Manager ) {
			throw new \LogicException( 'The Automator license manager is unavailable.' );
		}

		$instance = new Get_Feature_State(
			new Automator_Policy_State_Adapter(
				$licenses,
				new Mcp_Allocation_Facts_Reader( $licenses )
			),
			new WP_Last_Known_Feature_State_Store()
		);
	}

	return $instance;
}

/**
 * Get the Api_Log_Store instance.
 *
 * Defaults to the wpdb-backed implementation registered through
 * {@see Database::get_api_log_store()}. Tests pass an override.
 *
 * @param Api_Log_Store|null $override Optional override for testing.
 * @return Api_Log_Store
 */
function automator_api_log_store( ?Api_Log_Store $override = null, bool $reset = false ): ?Api_Log_Store {
	static $instance = null;
	if ( $reset ) {
		$instance = null;
		return null;
	}
	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}
	if ( null === $instance ) {
		$instance = Database::get_api_log_store();
	}
	return $instance;
}

/**
 * Get the Automator Platform Client instance.
 *
 * @param Client|null $override Optional override for testing.
 * @return Client
 */
function automator_platform_client( ?Client $override = null, bool $reset = false ): ?Client {
	static $instance = null;
	if ( $reset ) {
		$instance = null;
		return null;
	}
	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}
	if ( null === $instance ) {
		$license_manager = automator_license_manager();
		$signer          = new License_Header_Injector( $license_manager );
		$logger          = automator_api_log_store();
		$instance        = new Client( $signer, $logger );

		// Wire the circular dependency: License_Manager needs the client for license data fetches.
		$license_manager->set_api_client( $instance );
	}
	return $instance;
}

/**
 * Back-compat alias for the renamed client DI helper.
 *
 * Retained so third-party extensions calling `automator_api_client()` keep
 * resolving to the same cached instance. Delegates to
 * {@see automator_platform_client()}.
 *
 * @param Client|null $override Optional override for testing.
 * @param bool        $reset   Reset the cached instance.
 * @return Client|null
 */
function automator_api_client( ?Client $override = null, bool $reset = false ): ?Client {
	return automator_platform_client( $override, $reset );
}

/**
 * Get the API Client bound to the Usage Reports Service.
 *
 * Same client, different host: usage reports go to
 * AUTOMATOR_USAGE_REPORTS_URL rather than AUTOMATOR_API_URL.
 *
 * Deliberately NOT automator_platform_client() with a swapped base URL — that
 * instance is the singleton License_Manager holds for license and credit
 * calls, and repointing it would send those to the reports host. This is a
 * second, separate instance; it also skips the set_api_client() back-wire for
 * the same reason.
 *
 * @param Client|null $override Optional override for testing.
 * @param bool            $reset    Reset the cached instance.
 *
 * @return Client|null
 */
function automator_usage_reports_client( ?Client $override = null, bool $reset = false ): ?Client {
	static $instance = null;
	if ( $reset ) {
		$instance = null;
		return null;
	}
	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}
	if ( null === $instance ) {
		$instance = new Client(
			new License_Header_Injector( automator_license_manager() ),
			automator_api_log_store(),
			AUTOMATOR_USAGE_REPORTS_URL
		);
	}
	return $instance;
}

/**
 * Get the Credit Manager instance.
 *
 * @param Credit_Manager|null $override Optional override for testing.
 * @return Credit_Manager
 */
function automator_credit_manager( ?Credit_Manager $override = null, bool $reset = false ): ?Credit_Manager {
	static $instance = null;
	if ( $reset ) {
		$instance = null;
		return null;
	}
	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}
	if ( null === $instance ) {
		$instance = new Credit_Manager(
			automator_license_manager(),
			automator_api_client()
		);
	}
	return $instance;
}

/**
 * Get the Platform_Http transport instance.
 *
 * @param Platform_Http|null $override Optional override for testing.
 * @param bool               $reset   Reset the cached instance.
 * @return Platform_Http|null
 */
function automator_platform_http( ?Platform_Http $override = null, bool $reset = false ): ?Platform_Http {
	static $instance = null;
	if ( $reset ) {
		$instance = null;
		return null;
	}
	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}
	if ( null === $instance ) {
		$instance = new Platform_Http();
	}
	return $instance;
}

/**
 * Get the Site_Credentials_Store instance.
 *
 * @param Site_Credentials_Store|null $override Optional override for testing.
 * @param bool                        $reset   Reset the cached instance.
 * @return Site_Credentials_Store|null
 */
function automator_site_credentials_store( ?Site_Credentials_Store $override = null, bool $reset = false ): ?Site_Credentials_Store {
	static $instance = null;
	if ( $reset ) {
		$instance = null;
		return null;
	}
	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}
	if ( null === $instance ) {
		$instance = new Site_Credentials_Store();
	}
	return $instance;
}

/**
 * Get the Hmac_Validator instance.
 *
 * @param Hmac_Validator|null $override Optional override for testing.
 * @param bool                $reset   Reset the cached instance.
 * @return Hmac_Validator|null
 */
function automator_platform_hmac_validator( ?Hmac_Validator $override = null, bool $reset = false ): ?Hmac_Validator {
	static $instance = null;
	if ( $reset ) {
		$instance = null;
		return null;
	}
	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}
	if ( null === $instance ) {
		$instance = new Hmac_Validator( automator_site_credentials_store() );
	}
	return $instance;
}

/**
 * Get the Site_Identity_Service instance (MVP plan A.2).
 *
 * @param Site_Identity_Service|null $override Optional override for testing.
 * @param bool                       $reset   Reset the cached instance.
 * @return Site_Identity_Service|null
 */
function automator_site_identity_service(
	?Site_Identity_Service $override = null,
	bool $reset = false
): ?Site_Identity_Service {
	static $instance = null;
	if ( $reset ) {
		$instance = null;
		return null;
	}
	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}
	if ( null === $instance ) {
		$instance = new Site_Identity_Service();
	}
	return $instance;
}

/**
 * Get the Platform_Site_Registration instance (MVP plan A.3).
 *
 * Replaces the killed `automator_credentials_handshake_service()`
 * helper. The platform-pushed handshake flow is dropped per A.6;
 * registration is plugin-initiated against /app/v1/sites/register.
 *
 * @param Platform_Site_Registration|null $override Optional override for testing.
 * @param bool                            $reset   Reset the cached instance.
 * @return Platform_Site_Registration|null
 */
function automator_platform_site_registration(
	?Platform_Site_Registration $override = null,
	bool $reset = false
): ?Platform_Site_Registration {
	static $instance = null;
	if ( $reset ) {
		$instance = null;
		return null;
	}
	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}
	if ( null === $instance ) {
		$instance = new Platform_Site_Registration(
			automator_site_identity_service(),
			automator_site_credentials_store(),
			automator_platform_http()
		);
	}
	return $instance;
}

/**
 * Get the Site_Heartbeat_Cron instance.
 *
 * @param Site_Heartbeat_Cron|null $override Optional override for testing.
 * @param bool                     $reset   Reset the cached instance.
 * @return Site_Heartbeat_Cron|null
 */
function automator_site_heartbeat_cron( ?Site_Heartbeat_Cron $override = null, bool $reset = false ): ?Site_Heartbeat_Cron {
	static $instance = null;
	if ( $reset ) {
		$instance = null;
		return null;
	}
	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}
	if ( null === $instance ) {
		$instance = new Site_Heartbeat_Cron( automator_platform_http() );
	}
	return $instance;
}

/**
 * Get the Connection_Registry instance.
 *
 * @param Connection_Registry|null $override Optional override for testing.
 * @param bool                     $reset   Reset the cached instance.
 * @return Connection_Registry|null
 */
function automator_connection_registry( ?Connection_Registry $override = null, bool $reset = false ): ?Connection_Registry {
	static $instance = null;
	if ( $reset ) {
		$instance = null;
		return null;
	}
	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}
	if ( null === $instance ) {
		$instance = new Connection_Registry();
	}
	return $instance;
}

/**
 * Get the Connection_Error_Handler instance (plan-P WI11).
 *
 * Resolves Response error types to Connection state transitions. Wired
 * in `Application_Bootstrap` as a passive observer on
 * `automator_platform_response` — most call sites don't need to
 * construct it directly. Exposed for tests and future call sites that
 * want an explicit classification outside the observer path.
 *
 * @param Connection_Error_Handler|null $override Optional override for testing.
 * @param bool                          $reset    Reset the cached instance.
 * @return Connection_Error_Handler|null
 */
function automator_connection_error_handler( ?Connection_Error_Handler $override = null, bool $reset = false ): ?Connection_Error_Handler {
	static $instance = null;
	if ( $reset ) {
		$instance = null;
		return null;
	}
	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}
	if ( null === $instance ) {
		$instance = new Connection_Error_Handler( automator_connection_registry() );
	}
	return $instance;
}

/**
 * Get the Credential_Lift_Observer instance (MVP plan C.1, Contract H).
 *
 * Listens on `automator_platform_response`; on `X-Automator-Credentials-Lifted: 1`
 * responses, upserts a `wp_uap_connections` row + wipes the integration's
 * pre-lift credentials option.
 *
 * @param Credential_Lift_Observer|null $override Optional override for testing.
 * @param bool                          $reset    Reset the cached instance.
 * @return Credential_Lift_Observer|null
 */
function automator_credential_lift_observer( ?Credential_Lift_Observer $override = null, bool $reset = false ): ?Credential_Lift_Observer {
	static $instance = null;
	if ( $reset ) {
		$instance = null;
		return null;
	}
	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}
	if ( null === $instance ) {
		$instance = new Credential_Lift_Observer( automator_connection_registry() );
	}
	return $instance;
}

/**
 * Get the Legacy_Upgrade_Observer instance (MVP plan G.7,
 * Contract H §"v3 promotion envelope").
 *
 * Listens on `automator_platform_response`; on responses carrying
 * `data.upgraded_connection_id` + `upgraded_connection_signature` +
 * `upgraded_identity_id` + `platform_version: 'v3'`, locates the local
 * `legacy_*` row by the request body's outgoing connection_id and
 * UPDATEs it in place to the new v3 shape. Wipes the pre-promotion
 * credentials option.
 *
 * Spec-only on platform side per the MVP scope note — observer is
 * harmless until promotion traffic exists (no envelope → no-op).
 *
 * @param Legacy_Upgrade_Observer|null $override Optional override for testing.
 * @param bool                         $reset    Reset the cached instance.
 * @return Legacy_Upgrade_Observer|null
 */
function automator_legacy_upgrade_observer( ?Legacy_Upgrade_Observer $override = null, bool $reset = false ): ?Legacy_Upgrade_Observer {
	static $instance = null;
	if ( $reset ) {
		$instance = null;
		return null;
	}
	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}
	if ( null === $instance ) {
		$instance = new Legacy_Upgrade_Observer( automator_connection_registry() );
	}
	return $instance;
}

/**
 * Get the Disconnect_Service instance (plan-P WI10).
 *
 * Coordinates `DELETE /app/v1/connections/{id}` with local uap_connections
 * state. Its runtime caller is {@see automator_reconnect_service()}, which
 * the integration settings Disconnect and Reconnect actions go through.
 *
 * @param Disconnect_Service|null $override Optional override for testing.
 * @param bool                    $reset    Reset the cached instance.
 * @return Disconnect_Service|null
 */
function automator_disconnect_service( ?Disconnect_Service $override = null, bool $reset = false ): ?Disconnect_Service {
	static $instance = null;
	if ( $reset ) {
		$instance = null;
		return null;
	}
	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}
	if ( null === $instance ) {
		$instance = new Disconnect_Service( automator_api_client(), automator_connection_registry() );
	}
	return $instance;
}

/**
 * Get the Reconnect_Service instance (MVP plan G.6).
 *
 * Thin coordinator over Disconnect_Service that disconnects the
 * integration's default v3 connection before the rest-processing trait
 * redirects the browser to the OAuth start URL. A failed disconnect keeps
 * the row and stops the flow — see {@see Reconnect_Service::reconnect()}
 * for the result contract.
 *
 * @param Reconnect_Service|null $override Optional override for testing.
 * @param bool                   $reset    Reset the cached instance.
 *
 * @return Reconnect_Service
 */
function automator_reconnect_service( ?Reconnect_Service $override = null, bool $reset = false ): Reconnect_Service {

	static $instance = null;

	if ( $reset ) {
		$instance = null;
	}

	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}

	if ( null === $instance ) {
		$disconnect = automator_disconnect_service();
		if ( null === $disconnect ) {
			$disconnect = new Disconnect_Service( automator_api_client(), automator_connection_registry() );
		}
		$instance = new Reconnect_Service( $disconnect, automator_connection_registry() );
	}

	return $instance;
}

/**
 * Get the Platform_Connections_Api instance — the platform routes a site
 * reads and repairs its own connections through, and shares them with its
 * other sites (staging-copies contract §3).
 *
 * @since 8.0.0
 *
 * @param Platform_Connections_Api|null $override Optional override for testing.
 * @param bool                          $reset    Reset the cached instance.
 *
 * @return Platform_Connections_Api|null
 */
function automator_platform_connections_api( ?Platform_Connections_Api $override = null, bool $reset = false ): ?Platform_Connections_Api {
	static $instance = null;
	if ( $reset ) {
		$instance = null;
		return null;
	}
	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}
	if ( null === $instance ) {
		$instance = new Platform_Connections_Api( automator_platform_http() );
	}
	return $instance;
}

/**
 * Get the Connection_Sync instance — rebuilds the local connection rows from
 * the platform's list (staging-copies R5). Wired in `Application_Bootstrap`.
 *
 * @since 8.0.0
 *
 * @param Connection_Sync|null $override Optional override for testing.
 * @param bool                 $reset    Reset the cached instance.
 *
 * @return Connection_Sync|null
 */
function automator_connection_sync( ?Connection_Sync $override = null, bool $reset = false ): ?Connection_Sync {
	static $instance = null;
	if ( $reset ) {
		$instance = null;
		return null;
	}
	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}
	if ( null === $instance ) {
		$instance = new Connection_Sync(
			automator_platform_connections_api(),
			automator_connection_registry(),
			automator_platform_site_registration()
		);
	}
	return $instance;
}

/**
 * Get the Connection_Adoption instance — a staging copy attaching to the live
 * site's accounts (staging-copies R3). Wired in `Application_Bootstrap`.
 *
 * @since 8.0.0
 *
 * @param Connection_Adoption|null $override Optional override for testing.
 * @param bool                     $reset    Reset the cached instance.
 *
 * @return Connection_Adoption|null
 */
function automator_connection_adoption( ?Connection_Adoption $override = null, bool $reset = false ): ?Connection_Adoption {
	static $instance = null;
	if ( $reset ) {
		$instance = null;
		return null;
	}
	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}
	if ( null === $instance ) {
		$instance = new Connection_Adoption(
			automator_platform_connections_api(),
			automator_connection_registry(),
			automator_platform_site_registration(),
			automator_connection_sync()
		);
	}
	return $instance;
}

/**
 * Get the Webhook_Router instance.
 *
 * @param Webhook_Router|null $override Optional override for testing.
 * @param bool                $reset   Reset the cached instance.
 * @return Webhook_Router|null
 */
function automator_webhook_router( ?Webhook_Router $override = null, bool $reset = false ): ?Webhook_Router {
	static $instance = null;
	if ( $reset ) {
		$instance = null;
		return null;
	}
	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}
	if ( null === $instance ) {
		$instance = new Webhook_Router();
	}
	return $instance;
}

/**
 * Get the File_Serve Signed_Url instance — mints + verifies the
 * `?automator_serve=1` URLs the platform GETs/HEADs (Phase 2 plan
 * Task 5a, Contract R1).
 *
 * @param Signed_Url|null $override Optional override for testing.
 * @param bool            $reset    Reset the cached instance.
 * @return Signed_Url|null
 */
function automator_file_serve_signer( ?Signed_Url $override = null, bool $reset = false ): ?Signed_Url {
	static $instance = null;
	if ( $reset ) {
		$instance = null;
		return null;
	}
	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}
	if ( null === $instance ) {
		$instance = new Signed_Url( automator_site_credentials_store() );
	}
	return $instance;
}

/**
 * Get the Json_Canonical instance — RFC 8785-style canonical JSON
 * encoder used by both {@see Source_Url_Token::mint()} (P7 token
 * payload) and the upload-action body persistence path (R6 idempotency
 * — same logical request must produce byte-identical bytes for the
 * platform's 24h cache to replay).
 *
 * Pure compute, no state — single shared instance is fine.
 *
 * @param Json_Canonical|null $override Optional override for testing.
 * @param bool                $reset    Reset the cached instance.
 * @return Json_Canonical|null
 */
function automator_json_canonical( ?Json_Canonical $override = null, bool $reset = false ): ?Json_Canonical {
	static $instance = null;
	if ( $reset ) {
		$instance = null;
		return null;
	}
	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}
	if ( null === $instance ) {
		$instance = new Json_Canonical();
	}
	return $instance;
}

/**
 * Get the Source_Url_Token instance — mints the
 * `X-Automator-Source-Token` header for upload-from-url platform calls
 * (Phase 2 plan task 5b, P7).
 *
 * @param Source_Url_Token|null $override Optional override for testing.
 * @param bool                  $reset    Reset the cached instance.
 * @return Source_Url_Token|null
 */
function automator_source_url_token( ?Source_Url_Token $override = null, bool $reset = false ): ?Source_Url_Token {
	static $instance = null;
	if ( $reset ) {
		$instance = null;
		return null;
	}
	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}
	if ( null === $instance ) {
		$instance = new Source_Url_Token(
			automator_site_credentials_store(),
			automator_json_canonical()
		);
	}
	return $instance;
}

/**
 * Get the File_Serve_Controller instance (Phase 2 plan Task 5a).
 *
 * Wired from {@see Application_Bootstrap::wire_file_serve()}; this
 * accessor exists so tests can swap a fake without touching the
 * static cache directly.
 *
 * @param File_Serve_Controller|null $override Optional override for testing.
 * @param bool                       $reset    Reset the cached instance.
 * @return File_Serve_Controller|null
 */
function automator_file_serve_controller( ?File_Serve_Controller $override = null, bool $reset = false ): ?File_Serve_Controller {
	static $instance = null;
	if ( $reset ) {
		$instance = null;
		return null;
	}
	if ( null !== $override ) {
		$instance = $override;
		return $instance;
	}
	if ( null === $instance ) {
		$instance = new File_Serve_Controller(
			automator_file_serve_signer(),
			new Allowed_Path_Policy()
		);
	}
	return $instance;
}
