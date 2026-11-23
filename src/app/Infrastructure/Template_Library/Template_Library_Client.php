<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Template_Library;

use WP_Error;

/**
 * HTTP client for the automatorplugin.com template-library feed, version 2.
 *
 * Owns the upstream URLs (overridable via the
 * AUTOMATOR_PLUGIN_TEMPLATE_LIBRARY_URL / _DIRECTORY constants) and the feed
 * caches. Feed v2 lives in the library's `v2/` folder: an index
 * (`library.json`: version, generation, categories, page names) over pages of
 * cards (`library-{hash}.json`). The index is cached for 12 hours
 * and each page in its own transient, keyed by its name: a page's name changes
 * whenever its content does, so a cached page is never stale, and no single
 * cache entry has to hold the whole library. A cached page keeps only the card
 * fields the library uses (CARD_FIELDS), compressed: about a tenth of the page,
 * so a 10,000-template library takes under 1 MB on the site. Failures are surfaced as WP_Error
 * and never cached: the index is cached only once every page loaded, so
 * consumers fail loudly instead of rendering a stale or partial library. A library
 * with no cards is an invalid feed and is never cached either. A cached
 * index whose pages can't all be read (the library rebuilt and removed one) is
 * dropped and the current index read once.
 * Template documents (`v2/{id}.json`, Automator recipe exports) are not cached:
 * preview and import always read the current file.
 */
final class Template_Library_Client {

	/**
	 * Bump when the consumed feed contract changes; part of the transient
	 * key so a new plugin release invalidates the cache naturally.
	 *
	 * @var string
	 */
	const LIBRARY_VERSION = '2';

	/**
	 * Prefix of the feed index's transient key; feed_transient() adds the feed URL's hash.
	 *
	 * @since 8.1.0
	 *
	 * @var string
	 */
	const FEED_TRANSIENT_PREFIX = 'automator_template_library_feed_v' . self::LIBRARY_VERSION . '_';

	/**
	 * Cache TTL for the feed transient.
	 *
	 * @var int
	 */
	const CACHE_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Cache TTL for a page. A page's name changes with its content, so a cached page is never stale;
	 * it outlives the index so the last complete library can still be read while the feed is down.
	 *
	 * @since 8.1.0
	 *
	 * @var int
	 */
	const PAGE_CACHE_TTL = WEEK_IN_SECONDS;

	/**
	 * Prefix of the option holding the last index whose every page loaded; last_index_option() adds the feed URL's hash.
	 *
	 * @since 8.1.0
	 *
	 * @var string
	 */
	const LAST_INDEX_OPTION_PREFIX = 'automator_template_library_last_index_v' . self::LIBRARY_VERSION . '_';

	/**
	 * How long the last complete library is served after a failed refresh before the feed is tried again.
	 *
	 * @since 8.1.0
	 *
	 * @var int
	 */
	const FALLBACK_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Default upstream base URL: the store, as Addons_Feed_Client reads its feed. The live site,
	 * or the marketing staging site on staging sites (the API Mode MU-plugin's staging mode);
	 * AUTOMATOR_PLUGIN_TEMPLATE_LIBRARY_URL overrides it.
	 *
	 * @var string
	 */
	const DEFAULT_API_BASE = AUTOMATOR_STORE_URL;

	/**
	 * Default upstream library directory.
	 *
	 * @var string
	 */
	const DEFAULT_LIBRARY_DIRECTORY = 'wp-content/uploads/automator-template-library/';

	/**
	 * Feed v2 folder inside the library directory.
	 *
	 * @var string
	 */
	const FEED_DIRECTORY = 'v2/';

	/**
	 * Index file name.
	 *
	 * @var string
	 */
	const LIBRARY_FILE = 'library.json';

	/**
	 * Page transient key prefix; page_transient() adds the hash of the feed URL and the page name.
	 *
	 * @var string
	 */
	const PAGE_TRANSIENT_PREFIX = 'automator_template_library_page_';

	/**
	 * The card fields the library uses; a cached page keeps only these.
	 *
	 * @var array<int,string>
	 */
	const CARD_FIELDS = array( 'id', 'title', 'description', 'tier', 'integrations', 'blocks', 'categories', 'priority', 'min_version' );

	/**
	 * What a page name looks like (`library-{hash}.json`).
	 *
	 * @var string
	 */
	const PAGE_NAME_PATTERN = '/^library-[A-Za-z0-9-]+\.json$/';

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
	 * The library: the index's generation and categories, and every page's cards in page order.
	 *
	 * When the feed can't be read (the index or any page fails), the last complete library is served
	 * from its cached pages for FALLBACK_TTL; the feed is tried again after that. Only a site that
	 * never loaded a complete library gets the error.
	 *
	 * @since 8.1.0 Serves the last complete library when a refresh fails.
	 *
	 * @return array{generation: string, categories: array<int,array<string,mixed>>, cards: array<int,array<string,mixed>>}|WP_Error
	 */
	public function get_feed() {
		$index = get_transient( self::feed_transient() );
		if ( is_array( $index ) ) {
			$feed = $this->assemble( $index );
			if ( ! is_wp_error( $feed ) ) {
				return $feed;
			}
			// The library rebuilt since this index was cached and removed a page it lists: read the current index once.
			delete_transient( self::feed_transient() );
		}

		$feed = $this->fetch_feed();
		if ( is_wp_error( $feed ) ) {
			return $this->last_complete_feed( $feed );
		}

		return $feed;
	}

	/**
	 * Option name holding the last complete index of the feed currently configured.
	 *
	 * @since 8.1.0
	 *
	 * @return string
	 */
	public static function last_index_option(): string {
		return self::LAST_INDEX_OPTION_PREFIX . md5( self::feed_url() );
	}

	/**
	 * Fetch the current index and every page it lists; once every page loaded, cache the index, drop the
	 * cached pages the previous complete library no longer shares, and keep it as the last complete one.
	 *
	 * A library with no cards (no pages, or pages with no cards) is an invalid feed, so it is never cached
	 * or kept, and a bad publish upstream can't replace the last complete library.
	 *
	 * @since 8.1.0
	 *
	 * @return array{generation: string, categories: array<int,array<string,mixed>>, cards: array<int,array<string,mixed>>}|WP_Error
	 */
	private function fetch_feed() {
		$index = $this->fetch_index();
		if ( is_wp_error( $index ) ) {
			return $index;
		}

		$feed = $this->assemble( $index );
		if ( is_wp_error( $feed ) ) {
			return $feed;
		}
		if ( array() === $feed['cards'] ) {
			return $this->invalid_feed();
		}

		set_transient( self::feed_transient(), $index, self::CACHE_TTL );
		$this->drop_unlisted_pages( $index );
		update_option( self::last_index_option(), $index, false );

		return $feed;
	}

	/**
	 * Delete the cached pages the previous complete library listed and this one no longer does.
	 *
	 * @since 8.1.0
	 *
	 * @param array{pages: array<int,string>} $index The index that just loaded completely.
	 * @return void
	 */
	private function drop_unlisted_pages( array $index ): void {
		$previous = get_option( self::last_index_option() );
		if ( ! is_array( $previous ) ) {
			return;
		}

		$unlisted = array_diff( (array) ( $previous['pages'] ?? array() ), (array) ( $index['pages'] ?? array() ) );
		foreach ( $unlisted as $page ) {
			delete_transient( self::page_transient( (string) $page ) );
		}
	}

	/**
	 * The last complete library, read from cached pages only, after a failed refresh; the refresh's error when there is none.
	 *
	 * The last index is cached for FALLBACK_TTL, so the requests in between neither wait on a failing feed nor retry it.
	 *
	 * @since 8.1.0
	 *
	 * @param WP_Error $error The refresh's failure.
	 * @return array{generation: string, categories: array<int,array<string,mixed>>, cards: array<int,array<string,mixed>>}|WP_Error
	 */
	private function last_complete_feed( WP_Error $error ) {
		$index = get_option( self::last_index_option() );
		if ( ! is_array( $index ) ) {
			return $error;
		}

		$feed = $this->assemble( $index, true );
		if ( is_wp_error( $feed ) ) {
			return $error;
		}

		set_transient( self::feed_transient(), $index, self::FALLBACK_TTL );

		return $feed;
	}

	/**
	 * The library from an index: its generation and categories, and every page's cards in page order.
	 *
	 * @since 8.1.0 With $cached_only, reads cached pages only.
	 *
	 * @param array{generation: string, categories: array, pages: array<int,string>} $index
	 * @param bool                                                                   $cached_only Fail on a page that isn't cached instead of fetching it.
	 * @return array{generation: string, categories: array<int,array<string,mixed>>, cards: array<int,array<string,mixed>>}|WP_Error
	 */
	private function assemble( array $index, bool $cached_only = false ) {
		$cards = array();
		foreach ( (array) ( $index['pages'] ?? array() ) as $page ) {
			$page_cards = $this->get_page( (string) $page, $cached_only );
			if ( is_wp_error( $page_cards ) ) {
				return $page_cards;
			}
			$cards = array_merge( $cards, $page_cards );
		}

		return array(
			'generation' => (string) ( $index['generation'] ?? '' ),
			'categories' => (array) ( $index['categories'] ?? array() ),
			'cards'      => $cards,
		);
	}

	/**
	 * Transient key of the feed index, for the feed currently configured.
	 *
	 * Keyed on the feed URL, so re-pointing AUTOMATOR_STORE_URL or
	 * AUTOMATOR_PLUGIN_TEMPLATE_LIBRARY_URL reads the new host on the next
	 * request rather than the previous host's cached index.
	 *
	 * @since 8.1.0
	 *
	 * @return string
	 */
	public static function feed_transient(): string {
		return self::FEED_TRANSIENT_PREFIX . md5( self::feed_url() );
	}

	/**
	 * Transient key of a page of the feed currently configured. Keyed on the
	 * feed URL and the page name, so two hosts' pages never share an entry.
	 *
	 * @since 8.1.0
	 *
	 * @param string $page Page file name.
	 * @return string
	 */
	public static function page_transient( string $page ): string {
		return self::PAGE_TRANSIENT_PREFIX . md5( self::feed_url() . $page );
	}

	/**
	 * Fetch and check the index.
	 *
	 * @return array{generation: string, categories: array, pages: array<int,string>}|WP_Error
	 */
	private function fetch_index() {
		$index = $this->fetch_json( self::LIBRARY_FILE );
		if ( is_wp_error( $index ) ) {
			return $index;
		}

		$pages = $index['pages'] ?? null;
		if ( 2 !== (int) ( $index['version'] ?? 0 ) || ! is_array( $pages ) || ! is_array( $index['categories'] ?? null ) ) {
			return $this->invalid_feed();
		}
		foreach ( $pages as $page ) {
			if ( ! is_string( $page ) || ! preg_match( self::PAGE_NAME_PATTERN, $page ) ) {
				return $this->invalid_feed();
			}
		}

		return array(
			'generation' => (string) ( $index['generation'] ?? '' ),
			'categories' => array_values( array_filter( $index['categories'], 'is_array' ) ),
			'pages'      => array_values( $pages ),
		);
	}

	/**
	 * A page's cards, from its transient or upstream.
	 *
	 * @since 8.1.0 With $cached_only, a page that isn't cached is an error instead of a fetch.
	 *
	 * @param string $page        Page file name.
	 * @param bool   $cached_only Fail instead of fetching when the page isn't cached.
	 * @return array<int,array<string,mixed>>|WP_Error
	 */
	private function get_page( string $page, bool $cached_only = false ) {
		$cached = self::unpack_cards( get_transient( self::page_transient( $page ) ) );
		if ( null !== $cached ) {
			return $cached;
		}
		if ( $cached_only ) {
			return $this->invalid_feed();
		}

		$body = $this->fetch_json( $page );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		if ( ! is_array( $body['cards'] ?? null ) ) {
			return $this->invalid_feed();
		}

		$keep  = array_flip( self::CARD_FIELDS );
		$cards = array();
		foreach ( $body['cards'] as $card ) {
			if ( is_array( $card ) ) {
				$cards[] = array_intersect_key( $card, $keep );
			}
		}
		$packed = self::pack_cards( $cards );
		if ( null !== $packed ) {
			set_transient( self::page_transient( $page ), $packed, self::PAGE_CACHE_TTL );
		}

		return $cards;
	}

	/**
	 * Cards as stored in a page transient: JSON, compressed when zlib is available.
	 * Null when the cards can't be encoded, so nothing unreadable is cached.
	 *
	 * @since 8.1.0 Null when the cards can't be encoded or compressed.
	 *
	 * @param array<int,array<string,mixed>> $cards
	 * @return string|null
	 */
	private static function pack_cards( array $cards ): ?string {
		$json = wp_json_encode( $cards );
		if ( false === $json ) {
			return null;
		}
		if ( ! function_exists( 'gzcompress' ) ) {
			return $json;
		}

		$compressed = gzcompress( $json, 6 );
		if ( false === $compressed ) {
			return null;
		}

		return base64_encode( $compressed ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- binary-safe storage of compressed cards in an option row.
	}

	/**
	 * Cards from a page transient, or null when there are none to read (missing, or not a packed page).
	 *
	 * @param mixed $packed Transient value.
	 * @return array<int,array<string,mixed>>|null
	 */
	private static function unpack_cards( $packed ): ?array {
		if ( ! is_string( $packed ) || '' === $packed ) {
			return null;
		}

		$json = $packed;
		if ( '[' !== $packed[0] && function_exists( 'gzuncompress' ) ) {
			$binary = base64_decode( $packed, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- see pack_cards().
			$json   = false === $binary ? false : @gzuncompress( $binary ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a corrupt entry is a cache miss.
		}
		$cards = is_string( $json ) ? json_decode( $json, true ) : null;

		return is_array( $cards ) ? $cards : null;
	}

	/**
	 * Fetch and decode one feed file.
	 *
	 * @param string $file File name inside the feed folder.
	 * @return array<string,mixed>|WP_Error
	 */
	private function fetch_json( string $file ) {
		$response = wp_remote_get( self::feed_url() . $file, array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'template_library_unreachable', $response->get_error_message() );
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error(
				'template_library_unreachable',
				esc_html_x( 'The template library feed could not be fetched.', 'Template library', 'uncanny-automator' )
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		return is_array( $data ) ? $data : $this->invalid_feed();
	}

	/**
	 * The invalid-feed error.
	 *
	 * @return WP_Error
	 */
	private function invalid_feed(): WP_Error {
		return new WP_Error(
			'template_library_invalid_feed',
			esc_html_x( 'The template library feed returned invalid data.', 'Template library', 'uncanny-automator' )
		);
	}

	/**
	 * Fetch a template's document: an Automator recipe export (portable document JSON).
	 *
	 * Not cached: preview and import always read the current upstream file.
	 *
	 * @param int $template_id Upstream template id.
	 * @return object|WP_Error Decoded export object, or the failure
	 *                         (`template_not_found` on upstream 404,
	 *                         `template_library_unreachable` on any other non-200).
	 *
	 * @since 8.1.0 Any status other than 200 or 404 reads as unreachable.
	 */
	public function get_template_export( int $template_id ) {
		$response = wp_remote_get( self::feed_url() . $template_id . '.json', array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'template_library_unreachable', $response->get_error_message() );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( 404 === $status ) {
			return new WP_Error(
				'template_not_found',
				esc_html_x( 'Template not found.', 'Template library', 'uncanny-automator' )
			);
		}
		if ( 200 !== $status ) {
			return new WP_Error(
				'template_library_unreachable',
				esc_html_x( 'The template library feed could not be fetched.', 'Template library', 'uncanny-automator' )
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
	 * The feed v2 folder's URL (`{api_base}{library_directory}v2/`).
	 *
	 * @return string
	 */
	private static function feed_url(): string {
		return self::base_url() . self::FEED_DIRECTORY;
	}

	/**
	 * Upstream base URL (`{api_base}{library_directory}`), honoring the
	 * wp-config override constants.
	 *
	 * @return string
	 */
	private static function base_url(): string {
		$api_base  = defined( 'AUTOMATOR_PLUGIN_TEMPLATE_LIBRARY_URL' )
			? AUTOMATOR_PLUGIN_TEMPLATE_LIBRARY_URL
			: self::DEFAULT_API_BASE;
		$directory = defined( 'AUTOMATOR_PLUGIN_TEMPLATE_LIBRARY_DIRECTORY' )
			? AUTOMATOR_PLUGIN_TEMPLATE_LIBRARY_DIRECTORY
			: self::DEFAULT_LIBRARY_DIRECTORY;

		return trailingslashit( $api_base ) . $directory;
	}
}
