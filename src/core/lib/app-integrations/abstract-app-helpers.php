<?php

namespace Uncanny_Automator\App_Integrations;

use Exception;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Failure_Message;
use Uncanny_Automator\App\Infrastructure\Connections\Connection_Not_Found_Exception;
use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Abstract class for App integration helpers.
 * - Common methods to support the App integration to normalize naming and methods.
 *
 * Extends Abstract_Helpers so every App Integration helper inherits the
 * remote-data REST framework (filter registration, REST dispatcher,
 * field-option config builders, response shapers).
 *
 * @package Uncanny_Automator
 */
abstract class App_Helpers extends Abstract_Helpers {

	// Option data: prefix, key building, cached get/save, and common AJAX helpers.
	use App_Helpers_Option_Data;

	/**
	 * Integration.
	 *
	 * @var string
	 */
	protected $integration;

	/**
	 * Name.
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * Settings ID.
	 *
	 * @var string
	 */
	protected $settings_id;

	/**
	 * API server endpoint.
	 *
	 * @var string
	 */
	protected $api_endpoint;

	/**
	 * Credentials option name.
	 *
	 * @var string
	 */
	protected $credentials_option_name;

	/**
	 * Account option name.
	 *
	 * @var string
	 */
	protected $account_option_name;

	/**
	 * API instance for this integration.
	 * Will be set to the specific extended API class for this integration.
	 *
	 * @var Api_Caller|null
	 */
	protected $api = null;

	/**
	 * Webhooks instance for this integration.
	 * Will be set to the specific extended webhooks class for this integration.
	 *
	 * @var App_Webhooks|null
	 */
	protected $webhooks = null;

	/**
	 * __construct
	 *
	 * @param array $config The config.
	 *
	 * @return void
	 */
	public function __construct( $config ) {

		// Set integration properties from config.
		$this->set_integration( $config['integration'] ?? '' );
		$this->set_name( $config['name'] ?? '' );
		$this->set_api_endpoint( $config['api_endpoint'] ?? '' );
		$this->set_settings_id( $config['settings_id'] ?? '' );

		// Normalize settings ID for option names (convert dashes to underscores).
		$normalized_settings_id = str_replace( '-', '_', $this->get_settings_id() );

		// Set the option prefix for standardized option key building.
		$this->set_option_prefix( sprintf( 'automator_%s_', $normalized_settings_id ) );

		// Set a generated credentials option name.
		$this->set_credentials_option_name( $this->get_option_key( 'credentials' ) );

		// Set a generated account option name.
		$this->set_account_option_name( $this->get_option_key( 'account' ) );

		// Optional method to set additional properties.
		$this->set_properties();
	}

	////////////////////////////////////////////////////////////
	// Getter / Setter methods
	////////////////////////////////////////////////////////////

	/**
	 * Set the properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Intentionally left blank to be overridden by child classes if needed.
	}

	/**
	 * Set the integration.
	 *
	 * @param string $integration The integration.
	 *
	 * @return void
	 */
	public function set_integration( $integration ) {
		if ( empty( $integration ) ) {
			throw new Exception( 'Integration is required' );
		}
		$this->integration = $integration;
	}

	/**
	 * Get the integration.
	 *
	 * @return string
	 */
	public function get_integration() {
		return $this->integration;
	}

	/**
	 * Set the name.
	 *
	 * @param string $name The name.
	 *
	 * @return void
	 */
	public function set_name( $name ) {
		if ( empty( $name ) ) {
			throw new Exception( 'Name is required' );
		}
		$this->name = $name;
	}

	/**
	 * Get the name.
	 *
	 * @return string
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Set the settings ID.
	 *
	 * @param string $settings_id The settings ID.
	 *
	 * @return void
	 */
	public function set_settings_id( $settings_id ) {
		$settings_id = empty( $settings_id )
			? sanitize_title( $this->get_integration() )
			: sanitize_title( $settings_id );

		$this->settings_id = $settings_id;
	}

	/**
	 * Get the settings ID.
	 *
	 * @return string
	 */
	public function get_settings_id() {
		return $this->settings_id;
	}

	/**
	 * Set the endpoint.
	 *
	 * @param string $api_endpoint The API server endpoint.
	 *
	 * @return void
	 */
	public function set_api_endpoint( $api_endpoint ) {
		if ( empty( $api_endpoint ) ) {
			throw new Exception( 'API server endpoint is required' );
		}
		$this->api_endpoint = $api_endpoint;
	}

	/**
	 * Get the endpoint.
	 *
	 * @return string
	 */
	public function get_api_endpoint() {
		return $this->api_endpoint;
	}

	/**
	 * Set the credentials option name.
	 *
	 * @param string $credentials_option_name The credentials option name.
	 *
	 * @return void
	 */
	public function set_credentials_option_name( $credentials_option_name ) {
		if ( empty( $credentials_option_name ) ) {
			throw new Exception( 'Credentials option name is required' );
		}
		$this->credentials_option_name = $credentials_option_name;
	}

	/**
	 * Get the credentials option name.
	 *
	 * @return string
	 */
	public function get_credentials_option_name() {
		return $this->credentials_option_name;
	}

	/**
	 * Set the account option name.
	 *
	 * @param string $account_option_name The account option name.
	 *
	 * @return void
	 */
	public function set_account_option_name( $account_option_name ) {
		if ( empty( $account_option_name ) ) {
			throw new Exception( 'Account option name is required' );
		}
		$this->account_option_name = $account_option_name;
	}

	/**
	 * Get the account option name.
	 *
	 * @return string
	 */
	public function get_account_option_name() {
		return $this->account_option_name;
	}

	/**
	 * Set dependencies for this helper.
	 * This sets direct properties for clean access to API, webhooks, and other dependencies.
	 *
	 * @param stdClass $dependencies The dependencies object.
	 *
	 * @return void
	 */
	public function set_dependencies( $dependencies ) {
		$this->api      = $dependencies->api ?? null;
		$this->webhooks = $dependencies->webhooks ?? null;
	}

	/**
	 * Get the settings page URL.
	 *
	 * @param array $params The query parameters.
	 *
	 * @return string
	 */
	public function get_settings_page_url( $params = array() ) {
		// Get the settings page URL.
		$url = automator_get_premium_integrations_settings_url( $this->settings_id );

		// Add additional query parameters if provided.
		return empty( $params ) || ! is_array( $params )
			? $url
			: add_query_arg( $params, $url );
	}

	/**
	 * Whether this helper's api_endpoint targets the `/v3/*` surface.
	 *
	 * Mirrors App_Integration::supports_v3() on the helper side so callers
	 * that only hold the helper (typical for action/trigger handlers) can
	 * route requests correctly. Default inspects the api_endpoint string.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function supports_v3() {
		return 0 === strpos( (string) $this->api_endpoint, 'v3/' );
	}

	/**
	 * Get credentials — MVP G.4 façade returning the standard envelope.
	 *
	 * Per Contract H §"Account-info envelope (display CII)", v3 dispatches
	 * receive a flat array of four fields:
	 *
	 *   [
	 *     'connection_id'        => 'cnx_<ULID>',                 // request body
	 *     'connection_signature' => '<envelope_version>.<hmac>',  // request body
	 *     'identity_id'          => '<uuid>',                     // platform Identity FK
	 *     'external_user_id'     => '<id>',                       // vendor-scoped opaque id
	 *                                                             // (dropbox account_id, google sub, etc.)
	 *   ]
	 *
	 * `connection_id` + `connection_signature` are the dispatch credentials
	 * (Contract G — Laravel reads both from request body); plugin's core
	 * `Automator_Platform_Caller::api_request()` merges them directly into
	 * v3+ request bodies. The signature is platform-issued at
	 * OAuth-callback time and treated as opaque by the plugin.
	 *
	 * NO display PII (name, email, avatar) on this path — those live on
	 * `get_account_info()` only, populated once at OAuth-callback time and
	 * cached locally. Data minimization: the dispatch path stays
	 * pseudonymous so `display.*` fields never propagate through audit
	 * trails or per-request response envelopes.
	 *
	 * Three branches by registry row `platform_version`:
	 *
	 *  - **No row OR row is `'v2'` legacy (pre-lift)** + v2 endpoint:
	 *    legacy uap_options path, returns whatever the integration
	 *    historically stored. Identical to pre-MVP behaviour.
	 *
	 *  - **No usable row** + v3 endpoint: throws
	 *    {@see Connection_Not_Found_Exception} rather than dispatching. Its
	 *    message says why ({@see Connection_Registry::lookup_default()},
	 *    worded by {@see Platform_Failure_Message::for_lookup()}): no `/v3`
	 *    connection to dispatch with (none, only a `'v2'` row, or only
	 *    active rows none of which is the default) gets the connect prompt;
	 *    a failed database read is not read as a missing connection; a row
	 *    that is switched off or being re-checked, revoked, or marked for
	 *    re-authorization each gets its own sentence. It is an
	 *    `Action_Error_Exception` carrying the error code the message owner
	 *    gives that reason, so the action log records that code.
	 *
	 *  - **Row is `'v3'`** + v3 endpoint:
	 *    returns the standard three-key envelope above. Auth on the wire
	 *    is the per-Site signing_secret HMAC computed by the Client; no
	 *    per-connection signature exists in MVP.
	 *
	 * Vendor-specific keys like `dropbox_id`, `google_user_id`,
	 * `vault_signature` are NOT in the v3 return shape. Integration code
	 * reads `external_user_id` for vendor-side correlation; legacy
	 * `vault_signature` reads are dead code under MVP Contract G — Phase
	 * C transition removes them.
	 *
	 * @since 8.0.0 MVP plan G.4 — drops `connection_signature`, adds
	 *              legacy-row branch.
	 * @since 8.0.0 Contract H envelope — v3 return reshaped to flat
	 *              {connection_id, identity_id, external_user_id} array;
	 *              `display` block moved to `get_account_info()`.
	 *
	 * @return mixed array/string (legacy v2 path) OR flat array with
	 *               connection_id + identity_id + external_user_id (v3).
	 *
	 * @throws \Uncanny_Automator\App\Infrastructure\Connections\Connection_Not_Found_Exception
	 */
	public function get_credentials() {

		// V2 endpoint: always legacy uap_options path. A v3 row may
		// coexist if a future Contract H lift signal hasn't fired yet;
		// the v2 dispatch ignores the registry until the endpoint flips.
		// Checked BEFORE the registry lookup so the (vast) v2 majority
		// never pays the per-call connection query.
		if ( ! $this->supports_v3() ) {
			$credentials = automator_get_option( $this->get_credentials_option_name(), '' );
			return $this->validate_credentials( $credentials );
		}

		$lookup = \Uncanny_Automator\App\Infrastructure\automator_connection_registry()->lookup_default( $this->integration )->for_v3_dispatch();

		// No connection to dispatch with. Why decides what the customer is
		// told, and the error code the action records: there is none (or
		// only a pre-lift legacy row, or no default among the active rows),
		// the lookup failed, or a row exists but is switched off, being
		// re-checked, revoked or marked for re-authorization — each its own
		// sentence.
		if ( ! $lookup->is_found() ) {
			$not_found = Platform_Failure_Message::for_lookup( $lookup, (string) $this->get_name() )->to_connection_not_found_exception();
			throw $not_found;
		}

		$default  = $lookup->connection();
		$metadata = $default->metadata();

		return array(
			'connection_id'        => $default->connection_id(),
			'connection_signature' => (string) $default->connection_signature(),
			'identity_id'          => (string) $default->identity_id(),
			'external_user_id'     => isset( $metadata['external_user_id'] )
				? (string) $metadata['external_user_id']
				: '',
		);
	}

	/**
	 * Validate credentials.
	 * - Override in child classes to validate credentials.
	 *
	 * @param array $credentials -The credentials.
	 * @param array $args - Optional arguments.
	 *
	 * @return mixed - Array or string of credentials
	 */
	public function validate_credentials( $credentials, $args = array() ) {
		return $credentials;
	}

	/**
	 * Store credentials.
	 *
	 * @param array $credentials The credentials.
	 *
	 * @return bool True if credentials were stored, false otherwise.
	 */
	public function store_credentials( $credentials ) {
		// Check if this is a first-time connection (no prior credentials).
		$had_credentials = ! empty( automator_get_option( $this->get_credentials_option_name(), '' ) );

		// Prepare credentials for storage.
		$credentials = $this->prepare_credentials_for_storage( $credentials );
		$result      = automator_update_option( $this->get_credentials_option_name(), $credentials );

		return $result;
	}

	/**
	 * Prepare credentials for storage.
	 *
	 * @param array $credentials The credentials.
	 *
	 * @return array
	 */
	public function prepare_credentials_for_storage( $credentials ) {
		return $credentials;
	}

	/**
	 * Delete credentials.
	 *
	 * @return bool True if credentials were deleted, false otherwise.
	 */
	public function delete_credentials() {
		$this->clear_account_info_cache();
		$result = automator_delete_option( $this->get_credentials_option_name() );

		return $result;
	}

	/**
	 * Get account info.
	 *
	 * @return mixed - Array or string of account info
	 */
	public function get_account_info() {
		// V3 integrations have no local account_info — display CII lives in
		// the platform vault, fetched on demand via the integration's
		// `get-account-info` endpoint per Contract H. Route through the
		// 7-day transient cache so settings pages don't hit the vault on
		// every render.
		if ( $this->supports_v3() ) {
			return $this->get_account_info_from_vault_cached();
		}

		$account_info = automator_get_option( $this->get_account_option_name(), '' );
		return $this->validate_account_info( $account_info );
	}

	/**
	 * Validate account info.
	 * - Override in child classes to validate account info.
	 *
	 * @param array $account_info - The account info.
	 *
	 * @return mixed - Array or string of account info
	 */
	public function validate_account_info( $account_info ) {
		return $account_info;
	}

	/**
	 * Store account info.
	 *
	 * @param array $account_info The account info.
	 *
	 * @return bool True if account info was stored, false otherwise.
	 */
	public function store_account_info( $account_info ) {
		// Prepare account info for storage.
		$account_info = $this->prepare_account_info_for_storage( $account_info );
		return automator_update_option( $this->get_account_option_name(), $account_info );
	}

	/**
	 * Prepare account info for storage.
	 *
	 * @param array $account_info The account info.
	 *
	 * @return array
	 */
	public function prepare_account_info_for_storage( $account_info ) {
		return $account_info;
	}

	/**
	 * Delete account info.
	 *
	 * Also clears the 7-day cached vault copy so a subsequent
	 * {@see get_account_info_from_vault_cached()} re-fetches against the
	 * current connection rather than serving a stale display block.
	 *
	 * @return bool True if account info was deleted, false otherwise.
	 */
	public function delete_account_info() {
		$this->clear_account_info_cache();
		return automator_delete_option( $this->get_account_option_name() );
	}

	/**
	 * Cached display-CII fetch (G.8 — Contract H account-info envelope).
	 *
	 * Fetches the standard {@link https://contract-H} display block
	 * `{name, email, avatar_url}` from the platform vault and caches it for
	 * 7 days in a transient. The dispatch path
	 * ({@see get_credentials()}) intentionally returns the four-key
	 * pseudonymous envelope only — display PII never rides the per-request
	 * wire. Settings pages that want to render the connected account call
	 * this method instead of poking `automator_get_option`.
	 *
	 * Behaviour:
	 *  - Cache hit  → returns the cached array.
	 *  - Cache miss → POSTs `get-account-info` to the integration's modern
	 *                 endpoint (core caller flips `action → URL suffix` on
	 *                 v3+), caches the `data.display` block for 7 days,
	 *                 returns it.
	 *  - On error / no v3 connection → falls back to whatever
	 *                 {@see get_account_info()} has stored locally so the
	 *                 settings page still renders something.
	 *
	 * Invalidation: {@see clear_account_info_cache()} on disconnect /
	 * reconnect, and automatically from {@see delete_account_info()}.
	 *
	 * @since 8.0.0 G.8 — replaces ad-hoc per-integration caching.
	 *
	 * @return array Display block `{name, email, avatar_url}` or whatever
	 *               the local fallback returns when vault is unreachable.
	 */
	protected function get_account_info_from_vault_cached() {

		$transient_key = $this->get_account_info_transient_key();

		$cached = get_transient( $transient_key );
		if ( false !== $cached ) {
			return $cached;
		}

		// Vault fetch only works on v3+ — bail to local fallback otherwise.
		// Read the local option directly (NOT through `get_account_info()`)
		// to avoid recursion: `get_account_info()` for v3 routes back here.
		if ( ! $this->supports_v3() || null === $this->api ) {
			return $this->read_local_account_info();
		}

		try {
			$response = $this->api->api_request(
				array( 'action' => 'get_account_info' )
			);

			// Standard CII envelope per Contract H — platform normalizes
			// every vendor's userinfo into `{name, email, avatar_url}`
			// (with optional vendor correlation IDs alongside) before
			// returning. Plugin reads it flat.
			$envelope = isset( $response['data'] ) && is_array( $response['data'] )
				? $response['data']
				: array();

			if ( ! empty( $envelope ) ) {
				set_transient( $transient_key, $envelope, 7 * DAY_IN_SECONDS );
				return $envelope;
			}
		} catch ( \Exception $e ) {
			// Silent fallback — settings page must still render.
			unset( $e );
		}

		return $this->read_local_account_info();
	}

	/**
	 * Direct read of the local-options account_info — used as the
	 * vault-fetch fallback inside `get_account_info_from_vault_cached`.
	 * Bypasses `get_account_info()` so the v3 routing in that public
	 * method can't recurse back into the cached path.
	 *
	 * @return mixed Whatever was stored locally, or empty string if none.
	 */
	private function read_local_account_info() {

		$account_info = automator_get_option( $this->get_account_option_name(), '' );
		return $this->validate_account_info( $account_info );
	}

	/**
	 * Build the transient key for the cached account-info envelope.
	 *
	 * MVP is single-connection-per-integration, so we key on the
	 * integration slug only. This makes invalidation reliable on
	 * disconnect: at the time `clear_account_info_cache()` is called
	 * via `delete_account_info()`, the registry row has typically
	 * already been hard-deleted by `Reconnect_Service`, so a
	 * connection-id-based key would resolve to a stale fallback and
	 * leak the previous account's display block into the next
	 * connection. Multi-connection cache scoping is future work; will
	 * key on connection_id when recipe steps gain connection pinning.
	 *
	 * @since 8.0.0 G.8.
	 * @since 8.0.0 (post-MVP-disconnect-fix) keyed by integration only.
	 *
	 * @return string Transient key.
	 */
	protected function get_account_info_transient_key() {

		$integration_code = strtolower( (string) $this->get_integration() );

		return "automator_{$integration_code}_account_info";
	}

	/**
	 * Clear the cached account-info envelope.
	 *
	 * Call on disconnect, reconnect, or any flow that invalidates the
	 * displayed identity. Auto-fired from {@see delete_account_info()}.
	 *
	 * @since 8.0.0 G.8.
	 *
	 * @return void
	 */
	public function clear_account_info_cache() {
		delete_transient( $this->get_account_info_transient_key() );
	}

	/**
	 * Get class const.
	 *
	 * @param  string $const
	 *
	 * @return string
	 */
	public function get_const( $const_name ) {
		return constant( static::class . '::' . $const_name );
	}

	////////////////////////////////////////////////////////////
	// Recipe builder methods
	////////////////////////////////////////////////////////////

	/**
	 * Check if the value is a custom value text.
	 *
	 * @param string $string_to_check
	 *
	 * @return bool
	 */
	public function is_token_custom_value_text( $string_to_check ) {
		return esc_attr__( 'Use a token/custom value', 'uncanny-automator' ) === $string_to_check;
	}

	////////////////////////////////////////////////////////////
	// Encryption helpers
	////////////////////////////////////////////////////////////

	/**
	 * Prefix marking the authenticated (AES-256-GCM) at-rest format. Legacy
	 * blobs carry no prefix — they begin with a raw 16-byte IV — so this lets
	 * decrypt_data() tell the two formats apart and stay backward compatible.
	 *
	 * @var string
	 */
	const ENCRYPTION_V2_PREFIX = 'uoa:v2:';

	/**
	 * Simple encryption for data at rest (emails etc).
	 *
	 * @param mixed  $data The data to encrypt.
	 * @param int    $id   The record id the key is derived from.
	 * @param string $type The data type the key is derived from.
	 *
	 * @return string
	 */
	public function encrypt_data( $data, $id, $type ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Safe serialization for data at rest encryption
		$serialized = serialize( $data );

		// Preferred path: authenticated AES-256-GCM. The key is derived from the
		// per-record id, the site secret NONCE_SALT, and the type. GCM provides
		// confidentiality AND integrity, unlike the previous repeating-key XOR,
		// whose keystream was recoverable from the predictable serialize() output
		// via known-plaintext analysis.
		if ( function_exists( 'openssl_encrypt' ) ) {
			$key        = hash( 'sha256', $id . NONCE_SALT . $type, true );
			$nonce      = random_bytes( 12 );
			$tag        = '';
			$ciphertext = openssl_encrypt( $serialized, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag );

			if ( false !== $ciphertext ) {
				// Format: prefix . nonce(12) . tag(16) . ciphertext, base64-encoded.
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Used for data encoding, not obfuscation
				return base64_encode( self::ENCRYPTION_V2_PREFIX . $nonce . $tag . $ciphertext );
			}
		}

		// Fallback for the rare install without OpenSSL: legacy repeating-key XOR,
		// kept only so at-rest storage still functions there. decrypt_data() reads
		// both formats.
		$iv  = random_bytes( 16 );
		$key = hash( 'sha256', $id . NONCE_SALT . $type . $iv );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- last-resort XOR fallback, handles any data size
		$encrypted = $serialized ^ str_repeat( $key, ceil( strlen( $serialized ) / strlen( $key ) ) );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Used for data encoding, not obfuscation
		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt data encrypted by encrypt_data().
	 *
	 * @param string $encrypted_data
	 * @param int    $id
	 * @param string $type
	 *
	 * @return array
	 */
	public function decrypt_data( $encrypted_data, $id, $type ) {
		// Handle empty or invalid input
		if ( empty( $encrypted_data ) ) {
			return array();
		}

		// If the data is already an array (unencrypted), return it directly
		if ( is_array( $encrypted_data ) ) {
			return $encrypted_data;
		}

		// Ensure we have a string for base64_decode
		if ( ! is_string( $encrypted_data ) ) {
			return array();
		}

		// Decode and validate minimum length (16 bytes for a legacy IV)
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Used for data decoding, not obfuscation
		$decoded = base64_decode( $encrypted_data );
		if ( false === $decoded || strlen( $decoded ) < 16 ) {
			return array();
		}

		$prefix_length = strlen( self::ENCRYPTION_V2_PREFIX );

		// New authenticated format: prefix . nonce(12) . tag(16) . ciphertext.
		if ( function_exists( 'openssl_decrypt' ) && 0 === strncmp( $decoded, self::ENCRYPTION_V2_PREFIX, $prefix_length ) ) {
			$nonce      = substr( $decoded, $prefix_length, 12 );
			$tag        = substr( $decoded, $prefix_length + 12, 16 );
			$ciphertext = substr( $decoded, $prefix_length + 28 );
			$key        = hash( 'sha256', $id . NONCE_SALT . $type, true );

			$decrypted = openssl_decrypt( $ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag );
			if ( false === $decrypted ) {
				return array();
			}

			// Note: Do NOT change serialize/unserialize to json — existing user data is stored in serialized format.
			// allowed_classes => false prevents object injection while preserving backward compatibility.
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Safe unserialization for data at rest decryption
			$data = unserialize( $decrypted, array( 'allowed_classes' => false ) );
			return is_array( $data ) ? $data : array();
		}

		// Legacy repeating-key XOR format: iv(16) . ciphertext. Retained so data
		// written before the AES-GCM upgrade still decrypts.
		$iv        = substr( $decoded, 0, 16 );
		$encrypted = substr( $decoded, 16 );

		// Recreate the same unique key used for legacy encryption
		$key = hash( 'sha256', $id . NONCE_SALT . $type . $iv );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- reads pre-upgrade XOR blobs only
		$decrypted = $encrypted ^ str_repeat( $key, ceil( strlen( $encrypted ) / strlen( $key ) ) );

		// Unserialize and return original data
		// Note: Do NOT change serialize/unserialize to json — existing user data is stored in serialized format.
		// allowed_classes => false prevents object injection while preserving backward compatibility.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Safe unserialization for data at rest decryption
		$data = unserialize( $decrypted, array( 'allowed_classes' => false ) );
		return is_array( $data ) ? $data : array();
	}
}
