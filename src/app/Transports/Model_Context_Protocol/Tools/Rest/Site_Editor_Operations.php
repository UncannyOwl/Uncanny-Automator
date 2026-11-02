<?php
/**
 * Reviewed native WordPress site-editor operations.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Rest;

/** Keep site editing on native controllers and bounded site settings. */
final class Site_Editor_Operations {

	/** Options whose change breaks the site, hands over password recovery or lets strangers register as administrators. */
	private const LOCKOUT_OPTIONS = array( 'siteurl', 'home', 'admin_email', 'users_can_register', 'default_role' );

	/** Whether this route belongs to the reviewed site-editor surface. */
	public static function owns( string $route ): bool {
		return 1 === preg_match( '#^/wp/v2/(?:templates|template-parts|global-styles|themes|settings)(?:/|$)#i', $route );
	}

	/** Reject replacement controllers, unknown methods and changed native permissions. */
	public static function describe( string $route, string $method, array $handler ): ?array {
		$callback = $handler['callback'] ?? null;
		if ( ! is_array( $callback ) || ! isset( $callback[0], $callback[1] ) || ! is_object( $callback[0] ) ) {
			return null;
		}
		$family  = explode( '/', $route )[3] ?? '';
		$classes = array(
			'templates'      => 'WP_REST_Templates_Controller',
			'template-parts' => 'WP_REST_Templates_Controller',
			'global-styles'  => 'WP_REST_Global_Styles_Controller',
			'themes'         => 'WP_REST_Themes_Controller',
			'settings'       => 'WP_REST_Settings_Controller',
		);
		if ( ( $classes[ $family ] ?? null ) !== get_class( $callback[0] ) ) {
			return null;
		}
		$reads      = array(
			'templates'      => array( 'get_items', 'get_item', 'get_template_fallback' ),
			'template-parts' => array( 'get_items', 'get_item', 'get_template_fallback' ),
			'global-styles'  => array( 'get_item', 'get_theme_item', 'get_theme_items' ),
			'themes'         => array( 'get_items', 'get_item' ),
			'settings'       => array( 'get_item' ),
		);
		$read       = in_array( $method, array( 'GET', 'HEAD' ), true ) && in_array( $callback[1], $reads[ $family ], true );
		$edit       = 'themes' !== $family && 'update_item' === $callback[1] && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true );
		$template   = in_array( $family, array( 'templates', 'template-parts' ), true );
		$create     = $template && 'POST' === $method && 'create_item' === $callback[1];
		$delete     = $template && 'DELETE' === $method && 'delete_item' === $callback[1];
		$permission = $callback[1] . '_permissions_check';
		if ( 'get_template_fallback' === $callback[1] || 'settings' === $family ) {
			$permission = 'get_item_permissions_check';
		}
		if ( ! ( $read || $edit || $create || $delete ) || array( $callback[0], $permission ) !== ( $handler['permission_callback'] ?? null ) ) {
			return null;
		}
		// Core's theme GET can initialize the active theme's global-styles post.
		$read_only = $read && 'themes' !== $family;
		$policy    = array(
			'read_only'       => $read_only,
			'tools'           => $read_only ? array( 'get_posts' ) : array( 'get_posts', 'site_manage' ),
			'max_input_bytes' => Rest_Operation_Policy::DEFAULT_MAX_INPUT_BYTES,
		);
		if ( $template && ( $create || $edit ) ) {
			// Core forces publish on template saves, even when another status is sent.
			$status                   = array(
				'type'        => 'string',
				'enum'        => array( 'publish' ),
				'description' => 'Native template saves publish immediately. Draft and scheduled template saves are not supported.',
			);
			$policy['request_schema'] = array(
				'type'       => 'object',
				'properties' => array(
					'query' => array(
						'type' => 'object',
						'properties' => array( 'status' => $status ),
					),
					'body'  => array(
						'type' => 'object',
						'properties' => array( 'status' => $status ),
					),
				),
			);
		}
		if ( 'settings' === $family ) {
			$properties                = self::settings_fields( $callback[0]->get_item_schema()['properties'] );
			$policy['response_fields'] = array_keys( $properties );
			$policy['request_schema']  = array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'query' => array(
						'type' => 'object',
						'additionalProperties' => false,
						'properties' => array( '_fields' => array( 'type' => 'string' ) ),
					),
					'body'  => array(
						'type' => 'object',
						'additionalProperties' => false,
						'properties' => $read ? array() : $properties,
					),
				),
			);
		}
		return $policy;
	}

	/** Every registered setting except lockout ones under any REST name, including settings registered during dispatch. */
	public static function settings_fields( array $fields ): array {
		$properties = $fields;
		foreach ( get_registered_settings() as $option => $setting ) {
			if ( empty( $setting['show_in_rest'] ) || ! in_array( $option, self::LOCKOUT_OPTIONS, true ) ) {
				continue;
			}
			$rest = is_array( $setting['show_in_rest'] ) ? $setting['show_in_rest'] : array();
			unset( $properties[ ! empty( $rest['name'] ) ? $rest['name'] : $option ] );
		}
		return $properties;
	}

	/** Permit native theme/template identifiers only on their specific REST paths. */
	public static function valid_path( string $path ): bool {
		// Encoded separators and double decoding never identify a site-editor target.
		if ( preg_match( '/%(?:2f|5c|25)/i', $path ) ) {
			return false;
		}
		$decoded = rawurldecode( $path );
		foreach ( explode( '/', $decoded ) as $segment ) {
			if ( '.' === $segment || '..' === $segment ) {
				return false;
			}
		}
		$theme = '[\p{L}\p{N}_@().\[\] -]+(?:/[\p{L}\p{N}_@().\[\] -]+)?';
		return 1 === preg_match(
			'#^/wp/v2/(?:'
			. '(?:templates|template-parts)/' . $theme . '/{1,2}[\p{L}\p{N}_-]+'
			. '|themes/' . $theme
			. '|global-styles/themes/' . $theme . '(?:/variations)?'
			. ')$#uD',
			$decoded
		);
	}

	/** Match only WordPress's known identifier normalization, never an arbitrary sanitizer. */
	public static function canonical_parameter( array $handler, string $name, string $value ): string {
		$callback = $handler['callback'] ?? null;
		if ( ! is_array( $callback ) || ! isset( $callback[0] ) || ! is_object( $callback[0] ) ) {
			return $value;
		}
		$class = get_class( $callback[0] );
		if ( 'id' === $name && 'WP_REST_Templates_Controller' === $class && is_callable( array( $callback[0], '_sanitize_template_id' ) ) ) {
			return $callback[0]->_sanitize_template_id( $value );
		}
		if ( 'stylesheet' === $name && in_array( $class, array( 'WP_REST_Themes_Controller', 'WP_REST_Global_Styles_Controller' ), true ) ) {
			return urldecode( $value );
		}
		return $value;
	}
}
