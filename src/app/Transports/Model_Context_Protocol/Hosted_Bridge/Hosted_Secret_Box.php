<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Hosted_Bridge;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Internal_Token_Configuration;

/**
 * AES-256-GCM sealing for the bridge's secrets at rest.
 *
 * The key comes from strong wp-config.php material only. The authenticated
 * data always includes this blog and site address, so a sealed value copied
 * to another site, or opened with different binding values, reads as nothing.
 */
final class Hosted_Secret_Box {

	private const METHOD = 'aes-256-gcm';

	private const NONCE_LENGTH = 12;

	private const TAG_LENGTH = 16;

	private string $label;

	private Internal_Token_Configuration $keys;

	/**
	 * Constructor.
	 *
	 * @param string $label Domain separation label; each use has its own key.
	 */
	public function __construct( string $label, ?Internal_Token_Configuration $keys = null ) {
		$this->label = $label;
		$this->keys  = $keys ?? new Internal_Token_Configuration();
	}

	/**
	 * Whether a key is available here.
	 */
	public function is_available(): bool {
		return null !== $this->key();
	}

	/**
	 * Seal a value, or null without a key.
	 *
	 * @param array<int,int|string> $binding Values the envelope is bound to.
	 */
	public function seal( string $plaintext, array $binding ): ?string {
		$key = $this->key();
		if ( null === $key ) {
			return null;
		}

		$nonce      = random_bytes( self::NONCE_LENGTH );
		$tag        = '';
		$ciphertext = openssl_encrypt( $plaintext, self::METHOD, $key, OPENSSL_RAW_DATA, $nonce, $tag, $this->additional_data( $binding ), self::TAG_LENGTH );

		return false === $ciphertext || self::TAG_LENGTH !== strlen( $tag )
			? null
			: base64_encode( $nonce . $tag . $ciphertext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary AEAD envelope.
	}

	/**
	 * Open an envelope, or null when it was moved, edited or has no key here.
	 *
	 * @param array<int,int|string> $binding Values the envelope must be bound to.
	 */
	public function open( string $envelope, array $binding ): ?string {
		$key = $this->key();
		$raw = base64_decode( $envelope, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Binary AEAD envelope.
		if ( null === $key || false === $raw || strlen( $raw ) <= self::NONCE_LENGTH + self::TAG_LENGTH ) {
			return null;
		}

		$plaintext = openssl_decrypt(
			substr( $raw, self::NONCE_LENGTH + self::TAG_LENGTH ),
			self::METHOD,
			$key,
			OPENSSL_RAW_DATA,
			substr( $raw, 0, self::NONCE_LENGTH ),
			substr( $raw, self::NONCE_LENGTH, self::TAG_LENGTH ),
			$this->additional_data( $binding )
		);

		return is_string( $plaintext ) ? $plaintext : null;
	}

	/**
	 * The AES key derived for this label, or null without strong key material.
	 */
	private function key(): ?string {
		$material = $this->keys->get_key_material();

		return false === $material ? null : hash_hmac( 'sha256', $this->label, $material, true );
	}

	/**
	 * What the encryption authenticates besides the plaintext. The site address
	 * is the stored option, as Token_Site_Policy reads it: home_url() is
	 * filtered per request by some plugins (a language path, for example).
	 *
	 * @param array<int,int|string> $binding Caller binding values.
	 */
	private function additional_data( array $binding ): string {
		return (string) wp_json_encode( array_merge( array( $this->label, get_current_blog_id(), untrailingslashit( (string) get_option( 'home' ) ) ), array_values( $binding ) ) );
	}
}
