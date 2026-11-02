<?php
/**
 * The official checksums of the installed WordPress, from api.wordpress.org.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Infrastructure\Files;

use Throwable;
use Uncanny_Automator\App\Site_Commands\Domain\Files\WordPress\Core_Checksum_Source;
use Uncanny_Automator\App\Site_Commands\Domain\Files\WordPress\Core_Checksums;

/**
 * Fetches the list for the installed package's language, then for en_US, within one
 * deadline, over verified HTTPS only and without following redirects.
 */
final class WordPress_Core_Checksums implements Core_Checksum_Source {

	private const ENDPOINT = 'https://api.wordpress.org/core/checksums/1.0/';

	private const ATTEMPT_SECONDS = 3.0;

	private const MAX_RESPONSE_BYTES = 4194304;

	private const EXHAUSTED = 'checksum request time budget exhausted';

	/**
	 * Current time in seconds.
	 *
	 * @var callable(): float
	 */
	private $clock;

	/**
	 * The installed version and package language: function(): array{0: string, 1: string}.
	 *
	 * @var callable|null
	 */
	private $installed;

	/**
	 * Constructor.
	 *
	 * @param callable|null $clock     Current time in seconds; microtime( true ) by default.
	 * @param callable|null $installed The installed version and package language; read from version.php by default.
	 */
	public function __construct( ?callable $clock = null, ?callable $installed = null ) {
		$this->clock     = $clock ?? static function (): float {
			return microtime( true );
		};
		$this->installed = $installed;
	}

	/**
	 * Fetch the list within this many seconds.
	 *
	 * Core's get_core_checksums() retries a failed HTTPS request over plain
	 * HTTP and sets its own timeout, so the request is made here: integrity
	 * data never arrives over an unauthenticated connection, and the
	 * fallback to en_US shares one deadline.
	 *
	 * @param float $seconds_budget Seconds the fetch may take in all.
	 * @return Core_Checksums|null
	 */
	public function core_checksums( float $seconds_budget ): ?Core_Checksums {
		if ( ! function_exists( 'wp_remote_get' ) ) {
			return null;
		}
		$deadline                 = $this->now() + $seconds_budget;
		list( $version, $locale ) = null === $this->installed ? self::installed() : call_user_func( $this->installed );
		$error                    = 'api.wordpress.org returned no valid checksum list';

		foreach ( array_unique( array( $locale, 'en_US' ) ) as $candidate ) {
			$remaining = $deadline - $this->now();
			if ( $remaining <= 0.0 ) {
				return new Core_Checksums( $version, $locale, null, self::EXHAUSTED );
			}
			$locale = $candidate;
			try {
				$response = wp_remote_get(
					self::ENDPOINT . '?' . http_build_query(
						array(
							'version' => $version,
							'locale'  => $locale,
						)
					),
					array(
						'timeout'             => min( self::ATTEMPT_SECONDS, $remaining ),
						'redirection'         => 0,
						'sslverify'           => true,
						'limit_response_size' => self::MAX_RESPONSE_BYTES,
						// WordPress's default names the site's address; the version is enough.
						'user-agent'          => 'WordPress/' . $version,
					)
				);
				if ( $this->now() >= $deadline ) {
					return new Core_Checksums( $version, $locale, null, self::EXHAUSTED );
				}
				if ( is_wp_error( $response ) ) {
					$error = $response->get_error_message();
					continue;
				}
				if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
					continue;
				}
				$checksums = self::checksums( wp_remote_retrieve_body( $response ) );
				if ( null === $checksums ) {
					continue;
				}

				return new Core_Checksums( $version, $locale, $checksums, null );
			} catch ( Throwable $failure ) {
				return new Core_Checksums( $version, $locale, null, $failure->getMessage() );
			}
		}

		return new Core_Checksums( $version, $locale, null, $error );
	}

	/**
	 * The relative path => MD5 list in a response body, or null when any
	 * entry is malformed.
	 *
	 * @param string $body Response body.
	 * @return array<string, string>|null
	 */
	public static function checksums( string $body ): ?array {
		$decoded   = json_decode( $body, true );
		$checksums = is_array( $decoded ) ? ( $decoded['checksums'] ?? null ) : null;
		if ( ! is_array( $checksums ) || array() === $checksums ) {
			return null;
		}
		$normalized = array();
		foreach ( $checksums as $file => $hash ) {
			if ( ! is_string( $file ) || '' === $file || ! is_string( $hash ) || 1 !== preg_match( '/^[a-f0-9]{32}$/iD', $hash ) ) {
				return null;
			}
			$normalized[ $file ] = $hash;
		}

		return $normalized;
	}

	/**
	 * The installed version and the language of the installed package, read
	 * from version.php as `core version` does, because a plugin can change
	 * the globals. The package's language is the one its files were checked
	 * against, as in WordPress's own upgrader, whatever the site's language.
	 *
	 * @return array{0: string, 1: string}
	 */
	private static function installed(): array {
		$wp_version       = '';
		$wp_local_package = '';
		require ABSPATH . WPINC . '/version.php';

		return array( (string) $wp_version, '' === (string) $wp_local_package ? 'en_US' : (string) $wp_local_package );
	}

	/**
	 * Current time in seconds.
	 *
	 * @return float
	 */
	private function now(): float {
		return (float) call_user_func( $this->clock );
	}
}
