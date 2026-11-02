<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol;

/**
 * Canonical identity of the protected MCP resource.
 *
 * All token, OAuth, handshake, and transport code must use this service rather
 * than reconstructing the endpoint from the site origin or a presumed REST
 * prefix.
 */
final class Mcp_Resource_Identifier {

	/**
	 * Return the deployed MCP endpoint URL without a trailing slash.
	 */
	public function get(): string {
		return untrailingslashit( $this->get_rest_url( Mcp_Rest_Controller::ROUTE_NAMESPACE . '/' . Mcp_Rest_Controller::ROUTE_BASE ) );
	}

	/**
	 * Whether the site's saved address starts with https://. A site can serve
	 * HTTPS while its address still starts with http://.
	 *
	 * @since 8.0.0
	 */
	public function uses_https(): bool {
		return 'https' === strtolower( (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_SCHEME ) );
	}

	/**
	 * Build a REST URL whose scheme does not change between web, cron and CLI.
	 */
	public function get_rest_url( string $path = '' ): string {
		$scheme = $this->uses_https() ? 'https' : 'http';

		// Core may still force HTTPS from the current request even when an
		// explicit scheme is supplied. Reapply the site-configured scheme after
		// filters so background cleanup resolves the same credential audience.
		return set_url_scheme( rest_url( $path, $scheme ), $scheme );
	}

	/**
	 * Whether WordPress is routing REST requests through a query parameter.
	 */
	public function uses_query_based_routing(): bool {
		$query = wp_parse_url( $this->get(), PHP_URL_QUERY );

		return is_string( $query ) && '' !== $query;
	}
}
