<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Template_Library;

use WP_Error;

/**
 * HTTP client for the automatorplugin.com template-library feed.
 *
 * Owns the upstream URLs (overridable via the
 * AUTOMATOR_PLUGIN_TEMPLATE_LIBRARY_URL / _DIRECTORY constants) and the
 * feed transient. The raw feed is cached for 12 hours; failures are
 * surfaced as WP_Error and never cached — consumers fail loudly instead
 * of rendering stale or empty library data. Single-template exports are
 * intentionally NOT cached here: the preview service caches its
 * transformed payload, and import must always fetch fresh.
 */
final class Template_Library_Client {

	/**
	 * Bump when the consumed feed contract changes; part of the transient
	 * key so a new plugin release invalidates the cache naturally.
	 *
	 * @var string
	 */
	const LIBRARY_VERSION = '1';

	/**
	 * Feed transient key.
	 *
	 * @var string
	 */
	const FEED_TRANSIENT = 'automator_template_library_feed_v' . self::LIBRARY_VERSION;

	/**
	 * Cache TTL for the feed transient.
	 *
	 * @var int
	 */
	const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Default upstream base URL.
	 *
	 * @var string
	 */
	const DEFAULT_API_BASE = 'https://automatorplugin.com/';

	/**
	 * Default upstream library directory.
	 *
	 * @var string
	 */
	const DEFAULT_LIBRARY_DIRECTORY = 'wp-content/uploads/automator-template-library/';

	/**
	 * Feed file name.
	 *
	 * @var string
	 */
	const LIBRARY_FILE = 'library.json';

	/**
	 * Shared instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Shared-instance accessor.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Fetch the decoded library feed, transient-cached.
	 *
	 * @return array<string,mixed>|WP_Error Feed array, or the failure.
	 */
	public function get_feed() {
		$cached = get_transient( self::FEED_TRANSIENT );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$response = wp_remote_get( $this->base_url() . self::LIBRARY_FILE, array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'template_library_unreachable', $response->get_error_message() );
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error(
				'template_library_unreachable',
				esc_html_x( 'The template library feed could not be fetched.', 'Template library', 'uncanny-automator' )
			);
		}

		$feed = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $feed ) || ! isset( $feed['templates'] ) ) {
			return new WP_Error(
				'template_library_invalid_feed',
				esc_html_x( 'The template library feed returned invalid data.', 'Template library', 'uncanny-automator' )
			);
		}

		set_transient( self::FEED_TRANSIENT, $feed, self::CACHE_TTL );

		return $feed;
	}

	/**
	 * Fetch a single template export (legacy or portable-document JSON).
	 *
	 * Not cached — preview caches its transformed payload, import always
	 * wants the current upstream file.
	 *
	 * @param int $template_id Upstream template id.
	 * @return object|WP_Error Decoded export object, or the failure
	 *                         (`template_not_found` on upstream 404).
	 */
	public function get_template_export( int $template_id ) {
		$response = wp_remote_get( $this->base_url() . $template_id . '.json', array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'template_library_unreachable', $response->get_error_message() );
		}
		if ( 404 === (int) wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error(
				'template_not_found',
				esc_html_x( 'Template not found.', 'Template library', 'uncanny-automator' )
			);
		}

		$export = json_decode( wp_remote_retrieve_body( $response ) );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_object( $export ) ) {
			return new WP_Error(
				'template_library_invalid_export',
				esc_html_x( 'The template export returned invalid data.', 'Template library', 'uncanny-automator' )
			);
		}

		return $export;
	}

	/**
	 * Upstream base URL (`{api_base}{library_directory}`), honoring the
	 * wp-config override constants.
	 *
	 * @return string
	 */
	private function base_url(): string {
		$api_base  = defined( 'AUTOMATOR_PLUGIN_TEMPLATE_LIBRARY_URL' )
			? AUTOMATOR_PLUGIN_TEMPLATE_LIBRARY_URL
			: self::DEFAULT_API_BASE;
		$directory = defined( 'AUTOMATOR_PLUGIN_TEMPLATE_LIBRARY_DIRECTORY' )
			? AUTOMATOR_PLUGIN_TEMPLATE_LIBRARY_DIRECTORY
			: self::DEFAULT_LIBRARY_DIRECTORY;

		return trailingslashit( $api_base ) . $directory;
	}
}
