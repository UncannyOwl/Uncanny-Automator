<?php
/**
 * MCP Client payload service.
 *
 * Responsible for building and encrypting the payload shared with the MCP service.
 *
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Client;

use Uncanny_Automator\Api\Services\Integration\Integration_Registry_Service;

/**
 * Class Client_Payload_Service
 */
class Client_Payload_Service {

	/**
	 * Token service dependency.
	 *
	 * @var Client_Token_Service
	 */
	private Client_Token_Service $token_service;

	/**
	 * Public key manager dependency.
	 *
	 * @var Client_Public_Key_Manager
	 */
	private Client_Public_Key_Manager $public_key_manager;

	/**
	 * Context provider for payload values.
	 *
	 * @var Client_Payload_Context
	 */
	private Client_Payload_Context $context;

	/**
	 * Create a new builder instance.
	 *
	 * @return Client_Payload_Service_Builder
	 */
	public static function builder(): Client_Payload_Service_Builder {
		return new Client_Payload_Service_Builder();
	}

	/**
	 * Constructor.
	 *
	 * @param Client_Token_Service|null      $token_service Optional token service.
	 * @param Client_Public_Key_Manager|null $public_key_manager Optional public key manager.
	 * @param Client_Payload_Context|null    $context Optional context overrides.
	 */
	public function __construct(
		?Client_Token_Service $token_service = null,
		?Client_Public_Key_Manager $public_key_manager = null,
		?Client_Payload_Context $context = null
	) {
		$this->token_service      = $token_service ? $token_service : new Client_Token_Service();
		$this->public_key_manager = $public_key_manager ? $public_key_manager : new Client_Public_Key_Manager();
		$this->context            = $context ? $context : new Client_Payload_Context();
	}

	/**
	 * Generate an encrypted payload for the MCP chat client.
	 *
	 * @param array<string,mixed> $overrides Optional payload overrides.
	 * @return string Empty string on failure.
	 */
	public function generate_encrypted_payload( array $overrides = array() ): string {
		$payload_data = $this->build_payload_data( $overrides );

		$payload_json = wp_json_encode( $payload_data );
		if ( false === $payload_json ) {
			return '';
		}

		$public_key = $this->public_key_manager->get_public_key();

		if ( '' === $public_key ) {
			return '';
		}

		return $this->encrypt_payload( $payload_json, $public_key );
	}

	/**
	 * Build the data payload array.
	 *
	 * @param array<string,mixed> $overrides Optional overrides.
	 * @return array<string,mixed>
	 */
	public function build_payload_data( array $overrides = array() ): array {
		$user = $this->get_current_user();

		$payload = array(
			'email'                  => isset( $user->user_email ) ? sanitize_email( (string) $user->user_email ) : '',
			'active_integrations'    => $this->get_active_integrations(),
			'user_firstname'         => isset( $user->user_firstname ) ? sanitize_text_field( (string) $user->user_firstname ) : '',
			'site_domain'            => sanitize_text_field( (string) wp_parse_url( get_site_url(), PHP_URL_HOST ) ),
			'page_url'               => $this->resolve_page_url( $overrides ),
			'mcp_url'                => esc_url_raw( $this->context->get_mcp_rest_url() ),
			'bearer_token'           => $this->token_service->get_bearer_token(),
			'license_key'            => sanitize_text_field( (string) $this->context->get_license_key() ),
			'site_name'              => sanitize_text_field( (string) $this->context->get_site_name() ),
			'item_name'              => sanitize_text_field( (string) $this->context->get_item_name() ),
			'license_type'           => sanitize_text_field( (string) $this->context->get_license_type() ),
			'plan_id'                => sanitize_text_field( (string) $this->context->get_plan_id() ),
			'renewal_date_formatted' => sanitize_text_field( (string) $this->context->get_renewal_date_formatted() ),
			'url_get_credits'        => esc_url_raw( (string) $this->context->get_url_get_credits() ),
			'nonce'                  => (string) $this->context->generate_uuid(),
			'issued_at'              => (int) $this->context->get_timestamp(),
		);

		foreach ( $overrides as $key => $value ) {
			if ( 'page_url' === $key ) {
				continue;
			}

			$payload[ $key ] = $value;
		}

		return $payload;
	}

	/**
	 * Encrypt the payload using hybrid RSA/AES encryption.
	 *
	 * @param string $payload_json JSON payload.
	 * @param string $public_key Public key.
	 * @return string Empty string on failure.
	 */
	private function encrypt_payload( string $payload_json, string $public_key ): string {
		$aes_key = $this->generate_aes_key();

		if ( '' === $aes_key ) {
			return '';
		}

		$encrypted_payload = $this->encrypt_with_aes( $payload_json, $aes_key );

		if ( '' === $encrypted_payload ) {
			return '';
		}

		$encrypted_key = $this->encrypt_aes_key_with_rsa( $aes_key, $public_key );

		if ( '' === $encrypted_key ) {
			return '';
		}

		return $this->create_encryption_package( $encrypted_key, $encrypted_payload );
	}

	/**
	 * Generate a random AES key encoded as hex.
	 *
	 * @return string
	 */
	private function generate_aes_key(): string {
		$bytes = openssl_random_pseudo_bytes( 16 );

		return false === $bytes ? '' : bin2hex( $bytes );
	}

	/**
	 * Encrypt a string using AES-256-CBC.
	 *
	 * @param string $payload_json Payload JSON.
	 * @param string $aes_key AES key.
	 * @return string Empty string on failure.
	 */
	private function encrypt_with_aes( string $payload_json, string $aes_key ): string {
		$iv = openssl_random_pseudo_bytes( 16 );

		if ( false === $iv ) {
			return '';
		}

		$encrypted = openssl_encrypt(
			$payload_json,
			'AES-256-CBC',
			$this->format_aes_key( $aes_key ),
			OPENSSL_RAW_DATA,
			$iv
		);

		if ( false === $encrypted ) {
			return '';
		}

		return base64_encode( $iv . $encrypted );  // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- OAuth/JWT encoding.
	}

	/**
	 * Pad the AES key to the correct length.
	 *
	 * @param string $aes_key Key.
	 * @return string
	 */
	private function format_aes_key( string $aes_key ): string {
		return str_pad( $aes_key, 32, "\0" );
	}

	/**
	 * Encrypt the AES key with the provided public key.
	 *
	 * @param string $aes_key AES key.
	 * @param string $public_key Public key.
	 * @return string Empty string on failure.
	 */
	private function encrypt_aes_key_with_rsa( string $aes_key, string $public_key ): string {
		$key_resource = openssl_pkey_get_public( $public_key );

		if ( false === $key_resource ) {
			return '';
		}

		$encrypted_key = '';
		$success       = openssl_public_encrypt( $aes_key, $encrypted_key, $key_resource, OPENSSL_PKCS1_OAEP_PADDING );

		if ( ! $success ) {
			return '';
		}

		return base64_encode( $encrypted_key );  // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- OAuth/JWT encoding.
	}

	/**
	 * Create the final encryption package.
	 *
	 * @param string $encrypted_key Encrypted AES key.
	 * @param string $encrypted_payload Encrypted payload.
	 * @return string Empty string on failure.
	 */
	private function create_encryption_package( string $encrypted_key, string $encrypted_payload ): string {
		$package = array(
			'encrypted_key'     => $encrypted_key,
			'encrypted_payload' => $encrypted_payload,
		);

		$encoded = wp_json_encode( $package );

		return false === $encoded ? '' : $encoded;
	}

	/**
	 * Resolve the page URL with sanitisation.
	 *
	 * @param array<string,mixed> $overrides Overrides array.
	 * @return string
	 */
	private function resolve_page_url( array $overrides ): string {
		if ( isset( $overrides['page_url'] ) && is_string( $overrides['page_url'] ) ) {
			return esc_url_raw( sanitize_text_field( $overrides['page_url'] ) );
		}

		return esc_url_raw( $this->context->get_request_uri() );
	}

	/**
	 * Retrieve the current user object.
	 *
	 * @return object|\WP_User
	 */
	private function get_current_user() {
		return $this->context->get_current_user();
	}

	/**
	 * Get list of active integrations with codes and names.
	 *
	 * Returns an array of integration objects containing both the integration code
	 * (used in API calls) and the human-readable name (for user queries).
	 *
	 * Delegates to Integration_Registry_Service for proper encapsulation.
	 *
	 * @return array Array of objects with 'code' and 'name' for each active integration.
	 */
	private function get_active_integrations(): array {
		$registry = Integration_Registry_Service::get_instance();
		return $registry->get_active_integrations();
	}
}
