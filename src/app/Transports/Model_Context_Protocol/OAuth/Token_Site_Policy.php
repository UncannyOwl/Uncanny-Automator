<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth;

// phpcs:disable PSR2.Methods.FunctionClosingBrace.SpacingBeforeClose -- Deliberate breathing room at function boundaries.

/**
 * Defines the site identity and storage boundary for MCP credentials.
 *
 * WordPress user metadata is network-global on multisite. This policy keeps
 * every current credential key site-specific and owns removal of the former
 * network-global stores.
 *
 * @since 7.4.1
 */
final class Token_Site_Policy {

	/**
	 * Base user meta key for encrypted token records.
	 *
	 * @var string
	 */
	const TOKEN_META_KEY = 'automator_mcp_tokens_encrypted';

	/**
	 * Base user meta key for per-token usage timestamps.
	 *
	 * @var string
	 */
	const TOKEN_LAST_USED_META_KEY = 'automator_mcp_token_last_used';

	/**
	 * Base user meta key for the recoverable internal token cache.
	 *
	 * @var string
	 */
	const INTERNAL_TOKEN_META_KEY = 'automator_mcp_internal_token';

	/**
	 * Get the current WordPress site ID.
	 *
	 * @return int Current site ID.
	 */
	public function get_site_id(): int {

		return max( 1, (int) get_current_blog_id() );

	}

	/**
	 * Get the canonical audience for newly issued credentials.
	 *
	 * The home option is used directly so request protocol and proxy state
	 * cannot change the authenticated audience between requests.
	 *
	 * @return string Canonical current-site audience.
	 */
	public function get_audience(): string {

		return untrailingslashit( (string) get_option( 'home' ) );

	}

	/**
	 * Resolve the current site's primary credential meta key.
	 *
	 * @return string User meta key.
	 */
	public function get_token_meta_key(): string {

		return $this->get_site_meta_key( self::TOKEN_META_KEY );

	}

	/**
	 * Resolve the current site's recoverable cache meta key.
	 *
	 * @return string User meta key.
	 */
	public function get_internal_token_meta_key(): string {

		return $this->get_site_meta_key( self::INTERNAL_TOKEN_META_KEY );

	}

	/**
	 * Resolve a current-site per-token usage meta key.
	 *
	 * @param string $token_hash SHA-256 token hash.
	 * @return string User meta key.
	 */
	public function get_token_last_used_meta_key( string $token_hash ): string {

		$base_key = $this->get_site_meta_key( self::TOKEN_LAST_USED_META_KEY );

		return $base_key . '_' . $token_hash;

	}

	/**
	 * Get the primary credential encryption context.
	 *
	 * Single-site installs deliberately retain the empty legacy context.
	 * Multisite binds ciphertext to the current site in addition to using a
	 * site-specific storage key and authenticated token fields.
	 *
	 * @return string Encryption context.
	 */
	public function get_primary_encryption_context(): string {

		if ( ! is_multisite() ) {
			return '';
		}

		$identity = $this->get_site_id() . "\0" . $this->get_audience();

		return 'mcp_primary_token_v1:' . hash( 'sha256', $identity );

	}

	/**
	 * Determine whether authenticated token data belongs to this site.
	 *
	 * Missing audience data is accepted only for legacy single-site records.
	 * Network-global legacy records are never read on multisite.
	 *
	 * @param mixed $token_data Decrypted token data.
	 * @return bool Whether the token is valid for the current site.
	 */
	public function is_token_for_current_site( $token_data ): bool {

		if ( ! is_array( $token_data ) ) {
			return false;
		}

		if ( $this->is_legacy_single_site_token( $token_data ) ) {
			return true;
		}

		if ( ! $this->has_valid_site_identity( $token_data ) ) {
			return false;
		}

		return $this->get_site_id() === (int) $token_data['site_id']
			&& hash_equals( $this->get_audience(), $token_data['audience'] );

	}

	/**
	 * Delete both former network-global credential stores on multisite.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return bool Whether both legacy stores are absent.
	 */
	public function remove_legacy_network_credentials( int $user_id ): bool {

		if ( ! is_multisite() ) {
			return true;
		}

		$legacy_meta_keys = array(
			self::INTERNAL_TOKEN_META_KEY,
			self::TOKEN_META_KEY,
		);
		$removed          = true;

		foreach ( $legacy_meta_keys as $meta_key ) {
			$removed = $this->remove_meta_if_present( $user_id, $meta_key ) && $removed;
		}

		return $removed;

	}

	/**
	 * Resolve one base meta key inside the current site boundary.
	 *
	 * @param string $base_key Network-global base key.
	 * @return string Current-site key.
	 */
	private function get_site_meta_key( string $base_key ): string {

		if ( ! is_multisite() ) {
			return $base_key;
		}

		return $base_key . '_site_' . $this->get_site_id();

	}

	/**
	 * Determine whether token data is a supported legacy single-site record.
	 *
	 * @param array $token_data Decrypted token data.
	 * @return bool Whether both modern identity fields are intentionally absent.
	 */
	private function is_legacy_single_site_token( array $token_data ): bool {

		$has_site_id  = array_key_exists( 'site_id', $token_data );
		$has_audience = array_key_exists( 'audience', $token_data );

		return ! is_multisite() && ! $has_site_id && ! $has_audience;

	}

	/**
	 * Determine whether modern token identity fields have valid scalar types.
	 *
	 * @param array $token_data Decrypted token data.
	 * @return bool Whether both site identity fields are usable.
	 */
	private function has_valid_site_identity( array $token_data ): bool {

		if ( ! array_key_exists( 'site_id', $token_data ) || ! array_key_exists( 'audience', $token_data ) ) {
			return false;
		}

		return is_numeric( $token_data['site_id'] ) && is_string( $token_data['audience'] );

	}

	/**
	 * Remove one metadata key only when a row currently exists.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $meta_key User meta key.
	 * @return bool Whether the row is absent after the operation.
	 */
	private function remove_meta_if_present( int $user_id, string $meta_key ): bool {

		if ( ! metadata_exists( 'user', $user_id, $meta_key ) ) {
			return true;
		}

		return (bool) delete_user_meta( $user_id, $meta_key );

	}
}
