<?php
/**
 * WordPress site signing-key manager.
 *
 * @since 7.5.1
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Authentication;

use WP_Error;

/**
 * Creates one Ed25519 signing identity for each WordPress blog.
 */
class Site_Signing_Key_Manager {

	/**
	 * Encrypted key storage.
	 *
	 * @var Site_Signing_Key_Storage
	 */
	private Site_Signing_Key_Storage $storage;

	/**
	 * Constructor.
	 *
	 * @param Site_Signing_Key_Storage|null $storage Encrypted key storage.
	 */
	public function __construct( ?Site_Signing_Key_Storage $storage = null ) {
		$this->storage = $storage ?? new Site_Signing_Key_Storage();
	}

	/**
	 * Return the public site identity.
	 *
	 * @return array{public_key:string,fingerprint:string}|WP_Error
	 */
	public function get_public_key_record() {
		$key_pair = $this->load_or_create_key_pair();

		if ( $key_pair instanceof WP_Error ) {
			return $key_pair;
		}

		$record = $this->build_public_key_record( $key_pair['public_key'] );

		$this->clear_private_key( $key_pair['private_key'] );

		return $record;
	}

	/**
	 * Return an error when the site identity cannot be loaded or created.
	 *
	 * This check never creates a new identity.
	 *
	 * @return WP_Error|null Readiness error or null.
	 */
	public function get_readiness_error(): ?WP_Error {
		if ( ! function_exists( 'sodium_crypto_sign_detached' ) ) {
			return $this->build_error( 'automator_mcp_site_signing_unavailable' );
		}

		if ( ! $this->storage->has_record() ) {
			if (
				! function_exists( 'sodium_crypto_sign_keypair' )
				|| ! function_exists( 'sodium_crypto_sign_publickey' )
				|| ! function_exists( 'sodium_crypto_sign_secretkey' )
				|| ! $this->storage->is_ready_to_create()
			) {
				return $this->build_error( 'automator_mcp_site_signing_unavailable' );
			}

			return null;
		}

		$key_pair = $this->storage->load();

		if ( ! is_array( $key_pair ) ) {
			if ( $this->storage->has_wrapping_secret_changed() && $this->storage->is_ready_to_create() ) {
				return null;
			}

			return $this->build_error( 'automator_mcp_site_signing_key_unreadable' );
		}

		$this->clear_private_key( $key_pair['private_key'] );

		return null;
	}

	/**
	 * Sign canonical challenge bytes and return the matching public record.
	 *
	 * @param string $message Canonical message.
	 * @return array{public_key:string,fingerprint:string,signature:string}|WP_Error
	 */
	public function sign_with_public_key_record( string $message ) {
		$key_pair = $this->load_or_create_key_pair();

		if ( $key_pair instanceof WP_Error ) {
			return $key_pair;
		}

		try {
			$signature = sodium_crypto_sign_detached( $message, $key_pair['private_key'] );
			$record    = $this->build_public_key_record( $key_pair['public_key'] );
		} catch ( \Throwable $exception ) {
			return $this->build_error( 'automator_mcp_site_signing_unavailable' );
		} finally {
			$this->clear_private_key( $key_pair['private_key'] );
		}

		$record['signature'] = $this->encode_base64url( $signature );

		return $record;
	}

	/**
	 * Build the public part of one site identity.
	 *
	 * @param string $public_key Public-key bytes.
	 * @return array{public_key:string,fingerprint:string}
	 */
	private function build_public_key_record( string $public_key ): array {
		return array(
			'public_key'  => $this->encode_base64url( $public_key ),
			'fingerprint' => $this->encode_base64url( hash( 'sha256', $public_key, true ) ),
		);
	}

	/**
	 * Load the current key or create the first key.
	 *
	 * @return array{public_key:string,private_key:string}|WP_Error
	 */
	private function load_or_create_key_pair() {
		$key_pair = $this->storage->load();

		if ( is_array( $key_pair ) ) {
			return $key_pair;
		}

		$has_record = $this->storage->has_record();
		// Replace an existing identity only after storage identifies a wrapping-secret change.
		$rotate = $has_record && $this->storage->has_wrapping_secret_changed();

		if ( $has_record && ! $rotate ) {
			return $this->build_error( 'automator_mcp_site_signing_key_unreadable' );
		}

		if (
			! function_exists( 'sodium_crypto_sign_keypair' )
			|| ! function_exists( 'sodium_crypto_sign_publickey' )
			|| ! function_exists( 'sodium_crypto_sign_secretkey' )
		) {
			return $this->build_error( 'automator_mcp_site_signing_unavailable' );
		}

		$key_pair_bytes = '';

		try {
			$key_pair_bytes = sodium_crypto_sign_keypair();
			$public_key     = sodium_crypto_sign_publickey( $key_pair_bytes );
			$private_key    = sodium_crypto_sign_secretkey( $key_pair_bytes );
		} catch ( \Throwable $exception ) {
			return $this->build_error( 'automator_mcp_site_signing_unavailable' );
		} finally {
			$this->clear_private_key( $key_pair_bytes );
		}

		$stored = $rotate
			? $this->storage->replace( $public_key, $private_key )
			: $this->storage->create( $public_key, $private_key );

		if ( ! $stored ) {
			$winner = $this->storage->load( true );
			$this->clear_private_key( $private_key );

			if ( is_array( $winner ) ) {
				return $winner;
			}

			return $this->build_error( 'automator_mcp_site_signing_key_storage_failed' );
		}

		if ( $rotate ) {
			$winner = $this->storage->load( true );
			$this->clear_private_key( $private_key );

			return is_array( $winner )
				? $winner
				: $this->build_error( 'automator_mcp_site_signing_key_storage_failed' );
		}

		return array(
			'public_key'  => $public_key,
			'private_key' => $private_key,
		);
	}

	/**
	 * Build a stable key error.
	 *
	 * @param string $code Error code.
	 * @return WP_Error
	 */
	private function build_error( string $code ): WP_Error {
		if ( 'automator_mcp_site_signing_key_unreadable' === $code ) {
			$message = esc_html_x(
				'Uncanny Agent cannot read this site identity key.',
				'MCP site signing key error',
				'uncanny-automator'
			);
		} elseif ( 'automator_mcp_site_signing_key_storage_failed' === $code ) {
			$message = esc_html_x(
				'Uncanny Agent cannot store this site identity key.',
				'MCP site signing key error',
				'uncanny-automator'
			);
		} else {
			$message = esc_html_x(
				'Uncanny Agent cannot create this site identity key.',
				'MCP site signing key error',
				'uncanny-automator'
			);
		}

		return new WP_Error(
			$code,
			$message,
			array( 'status' => 500 )
		);
	}

	/**
	 * Encode binary data as unpadded base64url.
	 *
	 * @param string $value Binary value.
	 * @return string
	 */
	private function encode_base64url( string $value ): string {
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Protocol encoding.
	}

	/**
	 * Clear one in-memory private-key copy.
	 *
	 * @param string $private_key Private-key bytes.
	 * @return void
	 */
	private function clear_private_key( string &$private_key ): void {
		if ( function_exists( 'sodium_memzero' ) ) {
			sodium_memzero( $private_key );
		}
	}
}
