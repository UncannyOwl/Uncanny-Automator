<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Connections;

/**
 * This site's "share my app connections" switch as the platform answered it
 * (staging-copies contract §3.1) — or why there is no answer.
 *
 * Nothing about the switch is remembered locally: the settings screen shows
 * this value, read from the platform each time, so it can never show a value
 * the platform does not hold. An unknown value never reads as on.
 *
 * @since 8.0.0
 */
final class Connection_Sharing_State {

	/**
	 * The platform has no registration for this site (SITE_NOT_REGISTERED, 404
	 * from the route or 401 from the signature check ahead of it).
	 */
	public const FAILURE_NOT_REGISTERED = 'not_registered';

	/**
	 * The platform refused the request (400 VALIDATION_ERROR, or another typed 4xx).
	 */
	public const FAILURE_REFUSED = 'refused';

	/**
	 * No usable answer: a transport failure, a 5xx, a throttle, or a reply
	 * without the value.
	 */
	public const FAILURE_UNAVAILABLE = 'unavailable';

	/**
	 * @var bool|null
	 */
	private $enabled;

	/**
	 * @var string
	 */
	private $failure;

	/**
	 * @param bool|null $enabled The platform's value, null when unknown.
	 * @param string    $failure One of the FAILURE_* constants, '' when known.
	 */
	private function __construct( ?bool $enabled, string $failure ) {

		$this->enabled = $enabled;
		$this->failure = $failure;
	}

	/**
	 * @param bool $enabled The platform's value.
	 *
	 * @return self
	 */
	public static function known( bool $enabled ): self {

		return new self( $enabled, '' );
	}

	/**
	 * @param string $failure One of the FAILURE_* constants.
	 *
	 * @return self
	 */
	public static function unknown( string $failure ): self {

		return new self( null, $failure );
	}

	/**
	 * @return bool
	 */
	public function is_known(): bool {
		return null !== $this->enabled;
	}

	/**
	 * @return bool True only when the platform said the switch is on.
	 */
	public function is_enabled(): bool {
		return true === $this->enabled;
	}

	/**
	 * @return string One of the FAILURE_* constants, '' when the value is known.
	 */
	public function failure(): string {
		return $this->failure;
	}
}
