<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth;

use WP_Error;

/**
 * Resolves strong external key material for recoverable internal MCP tokens.
 *
 * Database-backed WordPress salt fallbacks are intentionally excluded because
 * they can be compromised together with the encrypted bearer they protect.
 *
 * @since 7.4.1
 */
class Internal_Token_Configuration {

	/**
	 * Stable error code exposed to application and REST boundaries.
	 *
	 * @var string
	 */
	const ERROR_CODE = 'automator_mcp_internal_token_key_missing';

	/**
	 * Supported wp-config.php constants in preference order.
	 *
	 * @var string[]
	 */
	const SECRET_CONSTANTS = array(
		'AUTOMATOR_MCP_INTERNAL_TOKEN_KEY',
		'AUTH_KEY',
		'SECURE_AUTH_KEY',
		'LOGGED_IN_KEY',
		'NONCE_KEY',
		'AUTH_SALT',
		'SECURE_AUTH_SALT',
		'LOGGED_IN_SALT',
		'NONCE_SALT',
	);

	/**
	 * Optional constant reader used by focused configuration tests.
	 *
	 * The callback receives a constant name and returns its value. Returning
	 * null represents an undefined constant.
	 *
	 * @var callable|null
	 */
	private $constant_reader;

	/**
	 * Constructor.
	 *
	 * @param callable|null $constant_reader Optional constant reader.
	 */
	public function __construct( ?callable $constant_reader = null ) {
		$this->constant_reader = $constant_reader;
	}

	/**
	 * Determine whether strong external key material is available.
	 *
	 * @return bool Whether internal token encryption can be safely enabled.
	 */
	public function is_ready(): bool {
		return false !== $this->get_key_material();
	}

	/**
	 * Build deterministic key material from every qualifying constant.
	 *
	 * @return string|false Key material or false when configuration is unsafe.
	 */
	public function get_key_material() {
		$key_material = '';

		foreach ( self::SECRET_CONSTANTS as $constant_name ) {
			$secret = $this->read_constant( $constant_name );
			if ( ! $this->is_strong_secret( $secret ) ) {
				continue;
			}

			$key_material .= $constant_name . "\0" . strlen( $secret ) . "\0" . $secret . "\0";
		}

		return '' === $key_material ? false : $key_material;
	}

	/**
	 * Build the actionable application error for missing strong configuration.
	 *
	 * @return WP_Error Configuration error.
	 */
	public function get_error(): WP_Error {
		return new WP_Error(
			self::ERROR_CODE,
			esc_html_x(
				'Uncanny Agent cannot create a secure internal token. Configure a strong AUTOMATOR_MCP_INTERNAL_TOKEN_KEY or replace the placeholder WordPress security keys in wp-config.php.',
				'MCP internal token configuration error',
				'uncanny-automator'
			),
			array( 'status' => 500 )
		);
	}

	/**
	 * Determine whether a candidate is suitable external key material.
	 *
	 * @param mixed $secret Candidate secret.
	 * @return bool Whether the value is long, varied, and non-placeholder.
	 */
	public function is_strong_secret( $secret ): bool {
		if ( ! is_string( $secret ) || strlen( $secret ) < 32 ) {
			return false;
		}

		$normalized   = strtolower( trim( $secret ) );
		$placeholders = array(
			'put your unique phrase here',
			'change this to a unique phrase',
			'change me',
			'changeme',
			'example',
			'password',
			'secret',
			'development',
			'test-only',
		);

		foreach ( $placeholders as $placeholder ) {
			if ( false !== strpos( $normalized, $placeholder ) ) {
				return false;
			}
		}

		// Reject low-variety values that only appear strong because of length.
		return count( count_chars( $secret, 1 ) ) >= 12;
	}

	/**
	 * Read one configured constant without falling back to database state.
	 *
	 * @param string $constant_name Constant name.
	 * @return mixed Constant value or null when undefined.
	 */
	private function read_constant( string $constant_name ) {
		if ( null !== $this->constant_reader ) {
			return call_user_func( $this->constant_reader, $constant_name );
		}

		return defined( $constant_name ) ? constant( $constant_name ) : null;
	}
}
