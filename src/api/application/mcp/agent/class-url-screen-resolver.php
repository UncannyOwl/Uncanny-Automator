<?php
/**
 * URL Screen Resolver — maps an admin URL to structured screen data.
 *
 * Pure utility: no side-effects, no global state.
 *
 * @since 7.1.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Application\Mcp\Agent;

/**
 * Resolves a WordPress admin URL into screen identifiers.
 *
 * @since 7.1.0
 */
class Url_Screen_Resolver {

	/**
	 * Default resolved data.
	 *
	 * @var array{screen_id: string, post_id: int, post_type: string, taxonomy: string, tag_id: int, page_slug: string}
	 */
	private const DEFAULTS = array(
		'screen_id' => '',
		'post_id'   => 0,
		'post_type' => '',
		'taxonomy'  => '',
		'tag_id'    => 0,
		'page_slug' => '',
	);

	/**
	 * Resolve a URL into structured screen data.
	 *
	 * @param string $url Absolute or relative admin URL.
	 *
	 * @return array{screen_id: string, post_id: int, post_type: string, taxonomy: string, tag_id: int, page_slug: string}
	 */
	public function resolve( string $url ): array {

		$path = $this->extract_admin_path( $url );

		if ( '' === $path ) {
			return self::DEFAULTS;
		}

		$basename = basename( strtok( $path, '?' ) );

		$query        = array();
		$query_string = wp_parse_url( $url, PHP_URL_QUERY );

		if ( is_string( $query_string ) ) {
			wp_parse_str( $query_string, $query );
		}

		return $this->map_screen( $basename, $query );
	}

	// ------------------------------------------------------------------
	// Internal helpers
	// ------------------------------------------------------------------

	/**
	 * Extract the path portion after /wp-admin/.
	 *
	 * @param string $url Full or relative URL.
	 *
	 * @return string Path segment after /wp-admin/, or empty string.
	 */
	private function extract_admin_path( string $url ): string {

		$path = wp_parse_url( $url, PHP_URL_PATH );

		if ( ! is_string( $path ) ) {
			return '';
		}

		$admin_pos = strpos( $path, '/wp-admin/' );

		if ( false === $admin_pos ) {
			return '';
		}

		return substr( $path, $admin_pos + strlen( '/wp-admin/' ) );
	}

	/**
	 * Map a basename + query string to screen data.
	 *
	 * @param string $basename File basename (e.g. "post.php").
	 * @param array  $query    Parsed query parameters.
	 *
	 * @return array{screen_id: string, post_id: int, post_type: string, taxonomy: string, tag_id: int, page_slug: string}
	 */
	private function map_screen( string $basename, array $query ): array {

		switch ( $basename ) {
			case 'post.php':
				return $this->resolve_post_edit( $query );

			case 'post-new.php':
				return $this->resolve_post_new( $query );

			case 'edit.php':
				return $this->resolve_edit_list( $query );

			case 'edit-tags.php':
				return $this->resolve_edit_tags( $query );

			case 'admin.php':
				return $this->resolve_admin_page( $query );

			case 'index.php':
				$result              = self::DEFAULTS;
				$result['screen_id'] = 'dashboard';
				return $result;

			default:
				return self::DEFAULTS;
		}
	}

	/**
	 * Resolve post.php?post=N&action=edit.
	 *
	 * @param array $query Parsed query parameters.
	 *
	 * @return array{screen_id: string, post_id: int, post_type: string, taxonomy: string, tag_id: int, page_slug: string}
	 */
	private function resolve_post_edit( array $query ): array {

		$post_id = isset( $query['post'] ) ? absint( $query['post'] ) : 0;

		if ( 0 === $post_id ) {
			return self::DEFAULTS;
		}

		$post_type = get_post_type( $post_id );

		if ( false === $post_type ) {
			return self::DEFAULTS;
		}

		$result              = self::DEFAULTS;
		$result['screen_id'] = sanitize_key( $post_type );
		$result['post_id']   = $post_id;
		$result['post_type'] = sanitize_key( $post_type );

		return $result;
	}

	/**
	 * Resolve post-new.php?post_type=X.
	 *
	 * @param array $query Parsed query parameters.
	 *
	 * @return array{screen_id: string, post_id: int, post_type: string, taxonomy: string, tag_id: int, page_slug: string}
	 */
	private function resolve_post_new( array $query ): array {

		$post_type = isset( $query['post_type'] ) ? sanitize_key( $query['post_type'] ) : 'post';

		$result              = self::DEFAULTS;
		$result['screen_id'] = $post_type;
		$result['post_type'] = $post_type;

		return $result;
	}

	/**
	 * Resolve edit.php?post_type=X.
	 *
	 * @param array $query Parsed query parameters.
	 *
	 * @return array{screen_id: string, post_id: int, post_type: string, taxonomy: string, tag_id: int, page_slug: string}
	 */
	private function resolve_edit_list( array $query ): array {

		$post_type = isset( $query['post_type'] ) ? sanitize_key( $query['post_type'] ) : 'post';

		$result              = self::DEFAULTS;
		$result['screen_id'] = 'edit-' . $post_type;
		$result['post_type'] = $post_type;

		return $result;
	}

	/**
	 * Resolve edit-tags.php?taxonomy=T&tag_ID=N.
	 *
	 * @param array $query Parsed query parameters.
	 *
	 * @return array{screen_id: string, post_id: int, post_type: string, taxonomy: string, tag_id: int, page_slug: string}
	 */
	private function resolve_edit_tags( array $query ): array {

		$taxonomy = isset( $query['taxonomy'] ) ? sanitize_key( $query['taxonomy'] ) : '';

		if ( '' === $taxonomy ) {
			return self::DEFAULTS;
		}

		$result              = self::DEFAULTS;
		$result['screen_id'] = 'edit-' . $taxonomy;
		$result['taxonomy']  = $taxonomy;
		$result['tag_id']    = isset( $query['tag_ID'] ) ? absint( $query['tag_ID'] ) : 0;

		return $result;
	}

	/**
	 * Resolve admin.php?page=uncanny-automator-*.
	 *
	 * @param array $query Parsed query parameters.
	 *
	 * @return array{screen_id: string, post_id: int, post_type: string, taxonomy: string, tag_id: int, page_slug: string}
	 */
	private function resolve_admin_page( array $query ): array {

		$page = isset( $query['page'] ) ? sanitize_key( $query['page'] ) : '';

		if ( '' === $page ) {
			return self::DEFAULTS;
		}

		$result              = self::DEFAULTS;
		$result['screen_id'] = 'uo-recipe_page_' . $page;
		$result['page_slug'] = $page;

		return $result;
	}
}
