<?php
declare(strict_types=1);

namespace Uncanny_Automator\Core\Lib\AI\Http\Component;

/**
 * Immutable API endpoint URL value object.
 *
 * Validates and stores API endpoint URLs for AI requests.
 *
 * @since 5.6
 */
final class Endpoint {

	/**
	 * @var string
	 */
	private $url;

	/**
	 * Initialize with URL validation.
	 *
	 * @param string $url API endpoint URL
	 *
	 * @throws \InvalidArgumentException If URL is invalid
	 */
	public function __construct( string $url ) {
		if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			throw new \InvalidArgumentException( esc_html( "Invalid endpoint URL: {$url}" ) );
		}
		$this->url = $url;
	}

	/**
	 * Get URL as string.
	 *
	 * @return string API endpoint URL
	 */
	public function __toString(): string {
		return $this->url;
	}
}
