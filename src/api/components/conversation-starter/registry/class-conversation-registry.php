<?php
/**
 * Conversation starter registry.
 *
 * @package Uncanny_Automator\Api\Components\Conversation_Starter\Registry
 * @since 7.2
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Conversation_Starter\Registry;

use InvalidArgumentException;
use Uncanny_Automator\Api\Components\Conversation_Starter\Domain\Conversation_Starter;

/**
 * Loads conversation starters from the bundled JSON resource.
 */
class Conversation_Registry {

	/**
	 * Transient key prefix.
	 *
	 * @var string
	 */
	private const TRANSIENT_PREFIX = 'automator_conversation_starters_';

	/**
	 * Load all conversation starters.
	 *
	 * @return Conversation_Starter[]
	 */
	public function load_all(): array {

		$starters = array();

		foreach ( $this->load_rows() as $index => $row ) {
			$starter = $this->starter_from_row( $row, $index + 1 );

			if ( $starter instanceof Conversation_Starter ) {
				$starters[] = $starter;
			}
		}

		return $starters;
	}

	/**
	 * Load conversation starters whose URL regex and post type match context.
	 *
	 * @param string      $url       URL to match.
	 * @param string|null $post_type Current post type.
	 *
	 * @return Conversation_Starter[]
	 */
	public function load_by_context( string $url, ?string $post_type ): array {

		$url       = trim( $url );
		$post_type = $this->normalize_post_type( $post_type );

		if ( '' === $url ) {
			return $this->load_default_starters();
		}

		if ( '' === $post_type ) {
			$post_type = $this->infer_post_type_from_url( $url );
		}

		$starters       = array();
		$url_candidates = $this->build_url_match_candidates( $url );

		foreach ( $this->load_rows() as $index => $row ) {
			if ( ! $this->row_matches_context( $row, $url_candidates, $post_type ) ) {
				continue;
			}

			$starter = $this->starter_from_row( $row, $index + 1 );

			if ( $starter instanceof Conversation_Starter ) {
				$starters[] = $starter;
			}
		}

		return ! empty( $starters ) ? $starters : $this->load_default_starters();
	}

	/**
	 * Load default starters used when no contextual row matches.
	 *
	 * @return Conversation_Starter[]
	 */
	private function load_default_starters(): array {

		$starters = array();

		foreach ( $this->load_rows() as $index => $row ) {
			if ( ! $this->row_is_default_starter( $row ) ) {
				continue;
			}

			$starter = $this->starter_from_row( $row, $index + 1 );

			if ( $starter instanceof Conversation_Starter ) {
				$starters[] = $starter;
			}
		}

		return $starters;
	}

	/**
	 * Load raw registry rows from cache or JSON.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function load_rows(): array {

		$transient_key = $this->get_transient_key();
		$cached        = get_transient( $transient_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$rows = $this->load_rows_from_resource();

		set_transient( $transient_key, $rows, $this->get_cache_ttl() );

		return $rows;
	}

	/**
	 * Read and decode the bundled JSON resource.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function load_rows_from_resource(): array {

		$resource_path = $this->get_resource_path();

		if ( ! is_readable( $resource_path ) ) {
			return array();
		}

		$contents = file_get_contents( $resource_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $contents ) {
			return array();
		}

		$decoded = json_decode( $contents, true );

		if ( ! is_array( $decoded ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$decoded,
				static function ( $row ): bool {
					return is_array( $row );
				}
			)
		);
	}

	/**
	 * Convert a raw registry row into the SDK-facing domain object.
	 *
	 * @param array<string,mixed> $row Row data.
	 * @param int                 $fallback_id Fallback ID.
	 *
	 * @return Conversation_Starter|null
	 */
	private function starter_from_row( array $row, int $fallback_id ): ?Conversation_Starter {

		$id     = $this->starter_id_from_row( $row, $fallback_id );
		$label  = $this->string_value( $row['Starter'] ?? '' );
		$prompt = $this->string_value( $row['Prompt'] ?? '' );

		try {
			return new Conversation_Starter( $id, $label, $prompt );
		} catch ( InvalidArgumentException $e ) {
			return null;
		}
	}

	/**
	 * Resolve the starter ID from the JSON row.
	 *
	 * @param array<string,mixed> $row Row data.
	 * @param int                 $fallback_id Fallback ID.
	 *
	 * @return int
	 */
	private function starter_id_from_row( array $row, int $fallback_id ): int {

		$row_id = $this->string_value( $row['#'] ?? '' );

		if ( ctype_digit( $row_id ) && (int) $row_id > 0 ) {
			return (int) $row_id;
		}

		return $fallback_id;
	}

	/**
	 * Check whether a raw row matches the supplied context.
	 *
	 * @param array<string,mixed> $row Row data.
	 * @param string[]            $urls URLs to match.
	 * @param string              $post_type Current post type.
	 *
	 * @return bool
	 */
	private function row_matches_context( array $row, array $urls, string $post_type ): bool {
		return $this->row_matches_url( $row, $urls ) && $this->row_matches_post_type( $row, $post_type );
	}

	/**
	 * Check whether a raw row is a default starter.
	 *
	 * @param array<string,mixed> $row Row data.
	 *
	 * @return bool
	 */
	private function row_is_default_starter( array $row ): bool {

		$section = sanitize_key( $this->string_value( $row['Section'] ?? '' ) );
		$regex   = $this->normalize_regex( $this->string_value( $row['URL Regex'] ?? '' ) );

		return 'default' === $section && '' === $regex;
	}

	/**
	 * Check whether a raw row matches a URL.
	 *
	 * @param array<string,mixed> $row Row data.
	 * @param string[]            $urls URLs to match.
	 *
	 * @return bool
	 */
	private function row_matches_url( array $row, array $urls ): bool {

		$regex = $this->normalize_regex( $this->string_value( $row['URL Regex'] ?? '' ) );

		if ( '' === $regex ) {
			return false;
		}

		foreach ( $urls as $url ) {
			if ( 1 === preg_match( '~' . str_replace( '~', '\~', $regex ) . '~', $url ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build URL candidates for matching exported regexes.
	 *
	 * @param string $url URL to normalize.
	 *
	 * @return string[]
	 */
	private function build_url_match_candidates( string $url ): array {

		$candidates = array( $url );

		$ordered_url = $this->build_ordered_query_url( $url );
		if ( '' !== $ordered_url ) {
			$candidates[] = $ordered_url;
		}

		$automator_alias = $this->build_automator_admin_alias_url( $url );
		if ( '' !== $automator_alias ) {
			$candidates[] = $automator_alias;

			$ordered_alias = $this->build_ordered_query_url( $automator_alias );
			if ( '' !== $ordered_alias ) {
				$candidates[] = $ordered_alias;
			}
		}

		return array_values( array_unique( $candidates ) );
	}

	/**
	 * Build a URL with the most discriminating query args first.
	 *
	 * @param string $url URL to normalize.
	 *
	 * @return string Normalized path/query URL, or empty string when unchanged.
	 */
	private function build_ordered_query_url( string $url ): string {

		$path = wp_parse_url( $url, PHP_URL_PATH );

		if ( ! is_string( $path ) || '' === $path ) {
			return '';
		}

		$query = $this->parse_query_from_url( $url );

		if ( empty( $query ) ) {
			return '';
		}

		$ordered_query = $this->order_query_args(
			$query,
			array( 'post_type', 'page', 'taxonomy', 'post', 'action', 'tag_ID' )
		);

		return $this->build_path_query_url( $path, $ordered_query, $url );
	}

	/**
	 * Build the edit.php alias used by Automator submenu pages.
	 *
	 * @param string $url URL to inspect.
	 *
	 * @return string Alias URL, or empty string when the URL is not an Automator admin page.
	 */
	private function build_automator_admin_alias_url( string $url ): string {

		$path = wp_parse_url( $url, PHP_URL_PATH );

		if ( ! is_string( $path ) || 'admin.php' !== basename( $path ) ) {
			return '';
		}

		$query = $this->parse_query_from_url( $url );
		$page  = $query['page'] ?? '';

		if ( ! is_scalar( $page ) || ! $this->is_automator_admin_page_slug( (string) $page ) ) {
			return '';
		}

		$query['post_type'] = 'uo-recipe';
		$alias_path         = preg_replace( '/admin\.php$/', 'edit.php', $path );

		if ( ! is_string( $alias_path ) || '' === $alias_path ) {
			return '';
		}

		$ordered_query = $this->order_query_args( $query, array( 'post_type', 'page', 'tab' ) );

		return $this->build_path_query_url( $alias_path, $ordered_query, $url );
	}

	/**
	 * Infer post type from the URL when caller context does not supply one.
	 *
	 * @param string $url URL to inspect.
	 *
	 * @return string
	 */
	private function infer_post_type_from_url( string $url ): string {

		$query = $this->parse_query_from_url( $url );

		if ( isset( $query['post_type'] ) && is_scalar( $query['post_type'] ) ) {
			return $this->normalize_post_type( $query['post_type'] );
		}

		$page = $query['page'] ?? '';
		if ( is_scalar( $page ) && $this->is_automator_admin_page_slug( (string) $page ) ) {
			return 'uo-recipe';
		}

		return '';
	}

	/**
	 * Parse a URL query string.
	 *
	 * @param string $url URL to parse.
	 *
	 * @return array<string,mixed>
	 */
	private function parse_query_from_url( string $url ): array {

		$query        = array();
		$query_string = wp_parse_url( $url, PHP_URL_QUERY );

		if ( is_string( $query_string ) ) {
			wp_parse_str( $query_string, $query );
		}

		return $query;
	}

	/**
	 * Place selected query args first while preserving the remaining args.
	 *
	 * @param array<string,mixed> $query Query args.
	 * @param string[]            $priority_keys Keys to place first.
	 *
	 * @return array<string,mixed>
	 */
	private function order_query_args( array $query, array $priority_keys ): array {

		$ordered = array();

		foreach ( $priority_keys as $key ) {
			if ( array_key_exists( $key, $query ) ) {
				$ordered[ $key ] = $query[ $key ];
			}
		}

		foreach ( $query as $key => $value ) {
			if ( ! array_key_exists( $key, $ordered ) ) {
				$ordered[ $key ] = $value;
			}
		}

		return $ordered;
	}

	/**
	 * Build a path/query URL while preserving fragments.
	 *
	 * @param string              $path Path.
	 * @param array<string,mixed> $query Query args.
	 * @param string              $source_url Source URL for fragment lookup.
	 *
	 * @return string
	 */
	private function build_path_query_url( string $path, array $query, string $source_url ): string {

		$query_string = http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		$fragment     = wp_parse_url( $source_url, PHP_URL_FRAGMENT );
		$url          = $path . ( '' !== $query_string ? '?' . $query_string : '' );

		if ( is_string( $fragment ) && '' !== $fragment ) {
			$url .= '#' . $fragment;
		}

		return $url;
	}

	/**
	 * Check whether an admin page slug belongs to Automator.
	 *
	 * @param string $page Admin page slug.
	 *
	 * @return bool
	 */
	private function is_automator_admin_page_slug( string $page ): bool {

		$page = sanitize_key( $page );

		return 0 === strpos( $page, 'uncanny-automator-' ) || 0 === strpos( $page, 'uo-recipe-' );
	}

	/**
	 * Check whether a raw row matches the current post type.
	 *
	 * Rows without a post type are treated as URL-only starters.
	 *
	 * @param array<string,mixed> $row Row data.
	 * @param string              $post_type Current post type.
	 *
	 * @return bool
	 */
	private function row_matches_post_type( array $row, string $post_type ): bool {

		$row_post_type = $this->normalize_post_type( $row['Post Type'] ?? '' );

		if ( '' === $row_post_type ) {
			return true;
		}

		return '' !== $post_type && $row_post_type === $post_type;
	}

	/**
	 * Normalize JSON-exported regex strings for PCRE.
	 *
	 * @param string $regex Regex string.
	 *
	 * @return string
	 */
	private function normalize_regex( string $regex ): string {
		return str_replace( '\/', '/', trim( $regex ) );
	}

	/**
	 * Get a string value from a raw row field.
	 *
	 * @param mixed $value Raw value.
	 *
	 * @return string
	 */
	private function string_value( $value ): string {

		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return trim( (string) $value );
	}

	/**
	 * Normalize a post type value.
	 *
	 * @param mixed $post_type Raw post type.
	 *
	 * @return string
	 */
	private function normalize_post_type( $post_type ): string {
		return sanitize_key( $this->string_value( $post_type ) );
	}

	/**
	 * Get the versioned transient key.
	 *
	 * @return string
	 */
	private function get_transient_key(): string {

		$version = defined( 'AUTOMATOR_PLUGIN_VERSION' ) ? AUTOMATOR_PLUGIN_VERSION : 'dev';

		return self::TRANSIENT_PREFIX . sanitize_key( (string) $version );
	}

	/**
	 * Get the cache lifetime.
	 *
	 * @return int
	 */
	private function get_cache_ttl(): int {
		return defined( 'WEEK_IN_SECONDS' ) ? WEEK_IN_SECONDS : 604800;
	}

	/**
	 * Get the bundled JSON resource path.
	 *
	 * @return string
	 */
	private function get_resource_path(): string {
		return __DIR__ . '/resources/conversation-starters.json';
	}
}
