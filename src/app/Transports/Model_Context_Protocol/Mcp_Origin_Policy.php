<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol;

use Uncanny_Automator\App\Application\Mcp\Hosted_App;

/**
 * Explicit Origin policy for Streamable HTTP requests.
 */
final class Mcp_Origin_Policy {

	/**
	 * An absent Origin is valid for non-browser, server-to-server clients.
	 */
	public function allows( string $origin ): bool {
		$origin = trim( $origin );
		if ( '' === $origin ) {
			return true;
		}

		$canonical = $this->canonicalize( $origin );
		if ( null === $canonical ) {
			return false;
		}

		// A site installed in a subdirectory has a path in home_url(); browsers
		// send only scheme://host[:port] as the Origin, so compare origins.
		// The hosted app's browser origin is the third allowed origin.
		$allowed = array(
			$this->origin_of( home_url( '/' ) ),
			$this->origin_of( site_url( '/' ) ),
			$this->origin_of( Hosted_App::base_url() ),
		);

		/**
		 * Add trusted browser origins for MCP.
		 *
		 * Values must be complete origins such as https://client.example.
		 * Wildcards are intentionally unsupported.
		 *
		 * @param string[] $allowed Candidate allowed origins.
		 */
		$allowed = apply_filters( 'automator_mcp_allowed_origins', $allowed );
		if ( ! is_array( $allowed ) ) {
			return false;
		}

		foreach ( $allowed as $candidate ) {
			if ( is_string( $candidate ) && $canonical === $this->canonicalize( $candidate ) ) {
				return true;
			}
		}

		return false;
	}
	/**
	 * Reduce a site URL to its origin, dropping any path, query or fragment.
	 *
	 * @param string $url Site or service URL.
	 * @return string scheme://host[:port], or '' when the URL has no host.
	 */
	private function origin_of( string $url ): string {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) ) {
			return '';
		}

		return (string) $parts['scheme'] . '://' . (string) $parts['host'] . ( isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '' );
	}

	/**
	 * Canonicalize.
	 *
	 * @param string $origin The origin.
	 * @return ?
	 */
	private function canonicalize( string $origin ): ?string {
		if ( '' === $origin || 'null' === strtolower( $origin ) || '*' === $origin || preg_match( '/\s/', $origin ) ) {
			return null;
		}

		$parts = wp_parse_url( $origin );
		if (
			! is_array( $parts )
			|| ! isset( $parts['scheme'], $parts['host'] )
			|| ! in_array( strtolower( (string) $parts['scheme'] ), array( 'http', 'https' ), true )
			|| isset( $parts['user'] )
			|| isset( $parts['pass'] )
			|| ( isset( $parts['path'] ) && ! in_array( $parts['path'], array( '', '/' ), true ) )
			|| isset( $parts['query'] )
			|| isset( $parts['fragment'] )
		) {
			return null;
		}

		$scheme = strtolower( (string) $parts['scheme'] );
		$host   = strtolower( (string) $parts['host'] );
		$port   = isset( $parts['port'] ) ? (int) $parts['port'] : null;
		if ( ( 'https' === $scheme && 443 === $port ) || ( 'http' === $scheme && 80 === $port ) ) {
			$port = null;
		}

		return $scheme . '://' . $host . ( null === $port ? '' : ':' . $port );
	}
}
