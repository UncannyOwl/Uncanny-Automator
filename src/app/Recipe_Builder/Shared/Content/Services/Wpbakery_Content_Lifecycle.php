<?php
/** Native WPBakery permissions and derived styling for a guarded content edit. */

declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Shared\Content\Services;

use Uncanny_Automator\App\Events\Wpbakery_Css_Observer;

final class Wpbakery_Content_Lifecycle {

	/** Keep a changed vendor lifecycle closed until its source has been qualified. */
	public function permissions_check( \WP_Post $post ) {
		if ( ! defined( 'WPB_VC_VERSION' ) || '9.0.1' !== WPB_VC_VERSION || ! function_exists( 'wpbakery' ) || ! function_exists( 'vc_user_access' ) || ! function_exists( 'vc_post_param' ) || ! function_exists( 'wpb_get_elements_with_custom_html' ) || ! function_exists( 'vc_check_post_type' ) || ! function_exists( 'vc_user_access_check_shortcode_edit' ) || ! function_exists( 'vc_user_access_check_shortcode_all' ) || ! class_exists( 'WPBMap' ) || ! is_callable( array( wpbakery(), 'buildShortcodesCss' ) ) ) {
			return $this->error( 'wpbakery_version_unsupported', 'This editing workflow requires the reviewed WPBakery Page Builder 9.0.1 lifecycle.', 409 );
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) || ! vc_check_post_type( $post->post_type ) || ! vc_user_access()->canEdit( $post->ID )->get() || ! vc_user_access()->part( 'backend_editor' )->can()->get() ) {
			return $this->error( 'wpbakery_edit_forbidden', 'WPBakery does not allow this user to edit this post in its backend editor.', 403 );
		}
		if ( 'dopreview' === vc_post_param( 'wp-preview' ) ) {
			return $this->error( 'wpbakery_preview_context', 'Use an ordinary content request. A native preview request can target a revision instead of this post.', 409 );
		}
		return true;
	}

	/** Check the resulting tree and the native access needed for the change. */
	public function validate( \WP_Post $post, string $before, string $after, array $edits ) {
		$allowed = $this->permissions_check( $post );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}
		foreach ( $edits as $edit ) {
			if ( in_array( $edit['action'] ?? '', array( 'copy_range', 'move_range' ), true ) ) {
				return $this->error( 'wpbakery_block_operation_unsupported', 'Block copy and move offsets describe Gutenberg blocks. Use checked literal or range replacements for WPBakery elements.', 400 );
			}
		}
		\WPBMap::addAllMappedShortcodes();
		$old = $this->nodes( $before );
		$new = $this->nodes( $after );
		if ( is_wp_error( $old ) || is_wp_error( $new ) ) {
			return is_wp_error( $old ) ? $old : $new;
		}
		if ( ! $new ) {
			return $this->error( 'wpbakery_layout_required', 'Keep an editable WPBakery layout. This operation does not convert or erase the whole page.', 400 );
		}
		if ( ! vc_user_access()->part( 'unfiltered_html' )->checkStateAny( true, null )->get() ) {
			$raw_types = wpb_get_elements_with_custom_html();
			foreach ( $new as $index => $node ) {
				if ( ( $old[ $index ] ?? null ) !== $node && ( in_array( $node['tag'], $raw_types, true ) || ( 'vc_btn' === $node['tag'] && ( isset( $node['attributes']['custom_onclick'] ) || isset( $node['attributes']['custom_onclick_code'] ) ) ) ) ) {
					return $this->error( 'wpbakery_custom_html_forbidden', 'WPBakery does not allow this user to add or change custom HTML, JavaScript or custom button clicks.', 403 );
				}
			}
		}
		$structure = static function ( array $nodes ): array {
			return array_map(
				static function ( array $node ): array {
					return array( $node['tag'], $node['depth'] );
				},
				$nodes
			);
		};
		if ( $structure( $old ) !== $structure( $new ) ) {
			// Strip unchanged edges. A pure insertion/deletion needs full access
			// only to those elements, not to unrelated existing page content.
			while ( $old && $new && $old[0] === $new[0] ) {
				array_shift( $old );
				array_shift( $new );
			}
			while ( $old && $new && end( $old ) === end( $new ) ) {
				array_pop( $old );
				array_pop( $new );
			}
			if ( $old && $new && ! vc_user_access()->part( 'dragndrop' )->checkStateAny( true, null )->get() ) {
				return $this->error( 'wpbakery_structure_forbidden', 'WPBakery does not allow this user to rearrange elements.', 403 );
			}
			foreach ( array_merge( $old, $new ) as $node ) {
				if ( ! vc_user_access_check_shortcode_all( $node['tag'] ) ) {
					return $this->error( 'wpbakery_structure_forbidden', 'Changing the layout requires full WPBakery access to its affected element types.', 403 );
				}
			}
		} else {
			foreach ( $new as $index => $node ) {
				if ( $old[ $index ]['own_source'] !== $node['own_source'] && ! vc_user_access_check_shortcode_edit( $node['tag'] ) ) {
					return $this->error( 'wpbakery_element_forbidden', 'WPBakery does not allow this user to edit an affected element.', 403 );
				}
			}
		}
		return true;
	}

	/** Parse registered native elements without rendering them. */
	private function nodes( string $source, int $depth = 0, ?string &$own = null, bool $container = true ) {
		if ( $depth > 32 || strlen( $source ) > 262144 ) {
			return $this->error( 'wpbakery_layout_too_large', 'The WPBakery layout exceeds the supported size or nesting limit.', 413 );
		}
		$tags = array_keys( \WPBMap::getShortCodes() );
		if ( false === preg_match_all( '/' . get_shortcode_regex( $tags ) . '/s', $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return $this->error( 'wpbakery_layout_invalid', 'The WPBakery source could not be parsed.', 400 );
		}
		$nodes   = array();
		$own     = '';
		$literal = '';
		$cursor  = 0;
		foreach ( $matches as $match ) {
			$gap      = substr( $source, $cursor, $match[0][1] - $cursor );
			$own     .= $gap;
			$literal .= $gap;
			$cursor   = $match[0][1] + strlen( $match[0][0] );
			if ( '[' === $match[1][0] && ']' === $match[6][0] ) {
				// Escaped shortcodes render as literal text owned by their parent.
				$own .= $match[0][0];
				continue;
			}
			$tag         = $match[2][0];
			$definition  = \WPBMap::getShortCode( $tag );
			$inner       = $match[5][0];
			$closing     = '[/' . $tag . ']';
			$has_content = ! empty( $definition['is_container'] ) || in_array( 'content', array_column( $definition['params'] ?? array(), 'param_name' ), true );
			if ( $has_content && substr( $match[0][0], -strlen( $closing ) ) !== $closing ) {
				return $this->error( 'wpbakery_layout_invalid', 'A WPBakery content element is missing its closing shortcode.', 400 );
			}
			if ( ! $this->valid_attributes( $match[3][0] ) ) {
				return $this->error( 'wpbakery_layout_invalid', 'A WPBakery element has malformed or duplicate attributes.', 400 );
			}
			$child_own = '';
			$children  = $this->nodes( $inner, $depth + 1, $child_own, ! empty( $definition['is_container'] ) );
			if ( is_wp_error( $children ) ) {
				return $children;
			}
			$nodes[] = array(
				'tag'        => $tag,
				'depth'      => $depth,
				'attributes' => shortcode_parse_atts( $match[3][0] ),
				'own_source' => $match[3][0] . "\0" . $child_own,
			);
			$nodes   = array_merge( $nodes, $children );
			if ( count( $nodes ) > 2000 ) {
				return $this->error( 'wpbakery_layout_too_large', 'The WPBakery layout contains too many elements.', 413 );
			}
		}
		$tail     = substr( $source, $cursor );
		$own     .= $tail;
		$literal .= $tail;
		if ( ( $container && '' !== trim( $own ) ) || preg_match( '/\[\/?(?:' . implode( '|', array_map( 'preg_quote', $tags ) ) . ')(?=[\s\]\/])/', $literal ) ) {
			return $this->error( 'wpbakery_layout_invalid', 'The layout contains unmatched shortcodes or content outside an editable element. Repair it in the native editor first.', 400 );
		}
		return $nodes;
	}

	/** Native mapped attributes are named values; reject ambiguous tokenization. */
	private function valid_attributes( string $attributes ): bool {
		$assignment = '\\s+([a-zA-Z_][a-zA-Z0-9_-]*)\\s*=\\s*(?:"[^"\\[\\]]*"|\'[^\'\\[\\]]*\'|[^\\s"\'=<>`\\[\\]]+)';
		if ( 1 !== preg_match( '/\\A(?:' . $assignment . ')*\\s*\\z/s', $attributes ) ) {
			return false;
		}
		preg_match_all( '/' . $assignment . '/s', $attributes, $matches );
		$names = array_map( 'strtolower', $matches[1] );
		return count( $names ) === count( array_unique( $names ) );
	}

	/** Must run inside the existing post lock, before its transaction commits. */
	public function rebuild( int $post_id ) {
		$observer = new Wpbakery_Css_Observer( $post_id );
		$observer->register();
		try {
			foreach ( array( 'default', 'custom' ) as $type ) {
				wpbakery()->buildShortcodesCss( $post_id, $type );
			}
		} catch ( \Throwable $error ) {
			return $this->error( 'wpbakery_css_failed', 'WPBakery could not regenerate this page styling. Read the page before retrying.', 503 );
		} finally {
			$observer->unregister();
		}
		wp_cache_delete( $post_id, 'post_meta' );
		$values = $observer->values();
		$hashes = array();
		foreach ( array( 'default', 'custom' ) as $type ) {
			$key = '_wpb_shortcodes_' . $type . '_css';
			if ( ! array_key_exists( $type, $values ) || ! is_string( $values[ $type ] ) || ( '' === $values[ $type ] ? metadata_exists( 'post', $post_id, $key ) : ( get_post_meta( $post_id, $key, true ) !== $values[ $type ] || '1' !== get_post_meta( $post_id, $key . '_updated', true ) ) ) ) {
				return $this->error( 'wpbakery_css_unverified', 'WPBakery styling did not match its native generated output. Read the page before retrying.', 503 );
			}
			$hashes[ $type ] = hash( 'sha256', $values[ $type ] );
		}
		return array(
			'name'             => 'wpbakery',
			'version'          => WPB_VC_VERSION,
			'css_verification' => 'exact',
			'css_hashes'       => $hashes,
		);
	}
	/**
	 * Error.
	 *
	 * @param string $code The code.
	 * @param string $message The message.
	 * @param int $status The status.
	 * @return \
	 */
	private function error( string $code, string $message, int $status ): \WP_Error {
		return new \WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
