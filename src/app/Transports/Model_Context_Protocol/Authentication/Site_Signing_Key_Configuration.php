<?php
/**
 * WordPress site signing-key configuration.
 *
 * @since 7.5.1
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Authentication;

/**
 * Selects one strong wp-config.php secret for key wrapping.
 */
class Site_Signing_Key_Configuration {

	/**
	 * Supported secrets in preference order.
	 *
	 * @var string[]
	 */
	private const SECRET_CONSTANTS = array(
		'AUTOMATOR_MCP_SITE_SIGNING_KEY',
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
	 * Constant reader.
	 *
	 * @var callable|null
	 */
	private $constant_reader;

	/**
	 * Constructor.
	 *
	 * @param callable|null $constant_reader Constant reader.
	 */
	public function __construct( ?callable $constant_reader = null ) {
		$this->constant_reader = $constant_reader;
	}

	/**
	 * Select the first strong configured secret.
	 *
	 * @return array{source:string,material:string}|false
	 */
	public function select_secret() {
		foreach ( self::SECRET_CONSTANTS as $source ) {
			$material = $this->get_secret( $source );

			if ( false !== $material ) {
				return array(
					'source'   => $source,
					'material' => $material,
				);
			}
		}

		return false;
	}

	/**
	 * Read one stored secret source.
	 *
	 * @param string $source Constant name.
	 * @return string|false
	 */
	public function get_secret( string $source ) {
		if ( ! in_array( $source, self::SECRET_CONSTANTS, true ) ) {
			return false;
		}

		$value = null !== $this->constant_reader
			? call_user_func( $this->constant_reader, $source )
			: ( defined( $source ) ? constant( $source ) : null );

		return $this->is_strong_secret( $value ) ? $value : false;
	}

	/**
	 * Validate external key material.
	 *
	 * @param mixed $value Candidate secret.
	 * @return bool
	 */
	private function is_strong_secret( $value ): bool {
		if ( ! is_string( $value ) ) {
			return false;
		}

		$normalized = trim( $value );

		if ( strlen( $normalized ) < 32 ) {
			return false;
		}

		return count( count_chars( $normalized, 1 ) ) >= 12;
	}
}
