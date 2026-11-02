<?php
/**
 * Read native WPBakery element definitions and existing page styling metadata.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Shared\Content\Services;

use Throwable;
use WP_Error;
use WP_Post;
use WPBMap;

/** Provides bounded editor information without rendering or generating CSS. */
final class Wpbakery_Content_Inspector {

	private const ELEMENT_LIMIT    = 200;
	private const PARAMETER_LIMIT  = 128;
	private const STRING_LIMIT     = 2048;
	private const CATALOG_BYTES    = 32768;
	private const DEFINITION_BYTES = 65536;
	private const RESPONSE_BYTES   = 131072;

	/**
	 * Inspect an editable post and optionally one registered element definition.
	 *
	 * Native registry getters may initialize lazy mappings. They do not render
	 * shortcodes. CSS values are read as they stand, without inferring freshness
	 * from an updated flag or running the native CSS loading decision.
	 *
	 * @param WP_Post $post    Post already authorized by the content transport.
	 * @param string  $element Optional native shortcode base, such as vc_row.
	 * @return array|WP_Error
	 */
	public function inspect( WP_Post $post, string $element = '' ) {
		try {
			$allowed = ( new Wpbakery_Content_Lifecycle() )->permissions_check( $post );
			if ( is_wp_error( $allowed ) ) {
				return $allowed;
			}
			if ( strlen( $element ) > 128 || ( '' !== $element && ! preg_match( '/^[^\s<>&\/\[\]=]+$/D', $element ) ) ) {
				return $this->error( 'wpbakery_element_invalid', 'Provide a registered WPBakery shortcode base.', 400 );
			}
			if ( ! is_callable( array( 'WPBMap', 'getShortCodes' ) ) || ! is_callable( array( 'WPBMap', 'getShortCode' ) ) || ! function_exists( 'vc_settings' ) || ! is_callable( array( vc_settings(), 'get' ) ) ) {
				return $this->error( 'wpbakery_inspection_unavailable', 'WPBakery element definitions or settings are unavailable.', 409 );
			}
			$mapped = WPBMap::getShortCodes();
			if ( ! is_array( $mapped ) || ! $mapped ) {
				return $this->error( 'wpbakery_catalog_unavailable', 'WPBakery has not registered its element catalog for this request.', 409 );
			}
			$result = array(
				'name'        => 'wpbakery',
				'version'     => WPB_VC_VERSION,
				'post'        => array(
					'id' => (int) $post->ID,
					'type' => $post->post_type,
					'status' => $post->post_status,
				),
				'permissions' => array(
					'backend_edit_allowed' => true,
					'move_allowed'         => (bool) vc_user_access()->part( 'dragndrop' )->checkStateAny( true, null )->get(),
					'custom_html_allowed'  => (bool) vc_user_access()->part( 'unfiltered_html' )->checkStateAny( true, null )->get(),
				),
				'css'         => $this->css_state( (int) $post->ID ),
				'catalog'     => $this->catalog( $mapped ),
			);
			if ( '' !== $element ) {
				$definition = WPBMap::getShortCode( $element );
				if ( ! is_array( $definition ) ) {
					return $this->error( 'wpbakery_element_not_found', 'This WPBakery element is not registered on the site.', 404 );
				}
				$result['element'] = $this->definition( $element, $definition );
			}
			$encoded = wp_json_encode( $result );
			if ( false === $encoded || strlen( $encoded ) > self::RESPONSE_BYTES ) {
				return $this->error( 'wpbakery_inspection_too_large', 'The WPBakery inspection exceeds its response limit.', 413 );
			}
			return $result;
		} catch ( Throwable $error ) {
			return $this->error( 'wpbakery_inspection_failed', 'WPBakery could not provide its editor information for this request.', 503 );
		}
	}

	/**
	 * List native access separately from visibility in the Add Element picker.
	 *
	 * @param array $mapped Native registry entries, including lazy mappings.
	 * @return array
	 */
	private function catalog( array $mapped ): array {
		$elements  = array();
		$bytes     = 0;
		$truncated = false;
		foreach ( array_keys( $mapped ) as $base ) {
			if ( count( $elements ) >= self::ELEMENT_LIMIT ) {
				$truncated = true;
				break;
			}
			$definition = WPBMap::getShortCode( (string) $base );
			if ( ! is_array( $definition ) ) {
				continue;
			}
			$entry   = $this->summary( (string) $base, $definition );
			$encoded = wp_json_encode( $entry );
			if ( false === $encoded || $bytes + strlen( $encoded ) > self::CATALOG_BYTES ) {
				$truncated = true;
				break;
			}
			$bytes     += strlen( $encoded );
			$elements[] = $entry;
		}
		return array(
			'registered_count' => count( $mapped ),
			'returned_count'   => count( $elements ),
			'truncated'        => $truncated,
			'limit'            => self::ELEMENT_LIMIT,
			'elements'         => $elements,
		);
	}

	/**
	 * Keep permission checks identical to the native editor helpers.
	 *
	 * @param string $base       Registered shortcode base.
	 * @param array  $definition Native mapping.
	 * @return array
	 */
	private function summary( string $base, array $definition ): array {
		$budget    = 4096;
		$truncated = false;
		return array(
			'base'            => $this->plain_value( $base, $budget, $truncated ),
			'name'            => $this->plain_value( $definition['name'] ?? $base, $budget, $truncated ),
			'category'        => $this->plain_value( $definition['category'] ?? array(), $budget, $truncated ),
			'edit_allowed'    => (bool) vc_user_access_check_shortcode_edit( $base ),
			'add_allowed'     => (bool) vc_user_access_check_shortcode_all( $base ),
			'requires_custom_html_access' => in_array( $base, wpb_get_elements_with_custom_html(), true ),
			'content_element' => ! isset( $definition['content_element'] ) || true === $definition['content_element'],
			'truncated'       => $truncated,
		);
	}

	/**
	 * Preserve native value/std semantics and declarative dependency information.
	 *
	 * Values over the limits become null and mark the definition incomplete;
	 * partial strings must never look like valid shortcode defaults or choices.
	 * JavaScript callbacks and renderer paths are not included or executed.
	 *
	 * @param string $base       Registered shortcode base.
	 * @param array  $definition Native mapping.
	 * @return array
	 */
	private function definition( string $base, array $definition ): array {
		$budget    = self::DEFINITION_BYTES;
		$truncated = false;
		$result    = $this->summary( $base, $definition );
		if ( 'vc_btn' === $base ) {
			$result['custom_click_requires_custom_html_access'] = true;
		}
		foreach ( array( 'description', 'is_container', 'as_parent', 'as_child', 'default_content', 'show_settings_on_create' ) as $key ) {
			if ( array_key_exists( $key, $definition ) ) {
				$result[ $key ] = $this->plain_value( $definition[ $key ], $budget, $truncated );
			}
		}
		$params = $definition['params'] ?? array();
		if ( ! is_array( $params ) ) {
			$params    = array();
			$truncated = true;
		}
		$result['parameter_count'] = count( $params );
		$result['params']          = array();
		foreach ( array_slice( $params, 0, self::PARAMETER_LIMIT ) as $param ) {
			if ( ! is_array( $param ) ) {
				$truncated = true;
				continue;
			}
			$fields             = array_intersect_key( $param, array_flip( array( 'param_name', 'type', 'heading', 'description', 'group', 'value', 'std', 'dependency', 'settings', 'params', 'save_always', 'admin_label', 'value_type' ) ) );
			$result['params'][] = $this->plain_value( $fields, $budget, $truncated );
		}
		$result['truncated'] = $result['truncated'] || $truncated || count( $params ) > self::PARAMETER_LIMIT;
		$result['limits']    = array(
			'parameters' => self::PARAMETER_LIMIT,
			'string_bytes' => self::STRING_LIMIT,
			'definition_bytes' => self::DEFINITION_BYTES,
		);
		return $result;
	}

	/**
	 * Copy bounded plain data only; never invoke objects, resources or callbacks.
	 *
	 * @param mixed $value     Native mapping value.
	 * @param int   $budget    Remaining encoded scalar/key bytes.
	 * @param bool  $truncated Whether information was omitted.
	 * @param int   $depth     Current nested array depth.
	 * @return mixed
	 */
	private function plain_value( $value, int &$budget, bool &$truncated, int $depth = 0 ) {
		if ( $depth > 6 || $budget <= 0 || is_object( $value ) || is_resource( $value ) || ( is_string( $value ) && strlen( $value ) > self::STRING_LIMIT ) ) {
			$truncated = true;
			return null;
		}
		if ( ! is_array( $value ) ) {
			$encoded = wp_json_encode( $value );
			if ( false === $encoded || strlen( $encoded ) > $budget ) {
				$truncated = true;
				return null;
			}
			$budget -= strlen( $encoded ) + 1;
			return $value;
		}
		$result = array();
		foreach ( $value as $key => $item ) {
			if ( count( $result ) >= 128 || $budget <= 0 ) {
				$truncated = true;
				break;
			}
			if ( is_string( $key ) && ( strlen( $key ) > self::STRING_LIMIT || false !== stripos( $key, 'callback' ) || 0 === strpos( $key, '__vc_' ) ) ) {
				$truncated = true;
				continue;
			}
			$budget        -= strlen( (string) wp_json_encode( $key ) ) + 3;
			$result[ $key ] = $this->plain_value( $item, $budget, $truncated, $depth + 1 );
		}
		return $result;
	}

	/**
	 * Read existing CSS and flags without invoking native CSS loading helpers.
	 *
	 * @param int $post_id Post identity.
	 * @return array
	 */
	private function css_state( int $post_id ): array {
		$result = array();
		foreach ( array( 'default', 'custom' ) as $type ) {
			$key             = '_wpb_shortcodes_' . $type . '_css';
			$value           = get_post_meta( $post_id, $key, true );
			$result[ $type ] = array(
				'exists'    => metadata_exists( 'post', $post_id, $key ),
				'is_string' => is_string( $value ),
				'bytes'     => is_string( $value ) ? strlen( $value ) : null,
				'sha256'    => is_string( $value ) ? hash( 'sha256', $value ) : null,
				'updated'   => $this->flag( $post_id, $key . '_updated' ),
			);
		}
		$strategy                   = vc_settings()->get( 'css_loading' );
		$budget                     = self::STRING_LIMIT;
		$truncated                  = false;
		$result['loading_strategy'] = $this->plain_value( empty( $strategy ) ? 'legacy' : $strategy, $budget, $truncated );
		$result['optimization']     = $this->flag( $post_id, '_wpb_vc_use_optimized_css' );
		$result['truncated']        = $truncated;
		return $result;
	}

	/**
	 * Preserve the stored flag representation instead of treating presence as true.
	 *
	 * @param int    $post_id Post identity.
	 * @param string $key     Native metadata key.
	 * @return array
	 */
	private function flag( int $post_id, string $key ): array {
		$budget    = self::STRING_LIMIT;
		$truncated = false;
		$value     = $this->plain_value( get_post_meta( $post_id, $key, true ), $budget, $truncated );
		return array(
			'exists' => metadata_exists( 'post', $post_id, $key ),
			'value' => $value,
			'truncated' => $truncated,
		);
	}

	/**
	 * Create a transport-compatible failure without exposing plugin internals.
	 *
	 * @param string $code    Stable error code.
	 * @param string $message Actionable message.
	 * @param int    $status  HTTP status.
	 * @return WP_Error
	 */
	private function error( string $code, string $message, int $status ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
