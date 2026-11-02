<?php
/**
 * Bounded native design facts for WordPress content authors.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Site;

/** Reads public design values, never theme options, CSS, or template content. */
final class Site_Design_Context {

	public const MAX_PRESETS      = 32;
	public const MAX_TEMPLATES    = 64;
	public const MAX_DESIGN_BYTES = 16384;
	private const MAX_POST_TYPES  = 16;

	/** @var bool Whether a response limit omitted otherwise available values. */
	private $truncated = false;

	/**
	 * Read current native settings for the authenticated executor.
	 *
	 * @param int $executor WordPress user ID checked by the calling MCP tool.
	 * @return array
	 */
	public function read( int $executor ): array {
		$this->truncated = false;
		$theme           = wp_get_theme();
		$available       = function_exists( 'wp_get_global_settings' );
		$settings        = $available ? wp_get_global_settings() : array();
		$settings        = is_array( $settings ) ? $settings : array();

		$data              = array(
			'theme'                     => array(
				'name'           => $this->text( $theme->get( 'Name' ) ) ?? '',
				'stylesheet'     => $this->text( $theme->get_stylesheet() ) ?? '',
				'version'        => $this->text( $theme->get( 'Version' ) ) ?? '',
				'is_block_theme' => function_exists( 'wp_is_block_theme' ) && wp_is_block_theme(),
			),
			'source'                    => 'wp_get_global_settings',
			'global_settings_available' => $available,
			'limitation'                => 'Native global presets only. Classic theme CSS and Customizer overrides may differ; inspect the rendered page before matching its design.',
			'presets'                   => array(
				'colors'        => $this->presets( $settings['color']['palette'] ?? array(), 'color' ),
				'font_sizes'    => $this->presets( $settings['typography']['fontSizes'] ?? array(), 'size' ),
				'font_families' => $this->presets( $settings['typography']['fontFamilies'] ?? array(), 'fontFamily' ),
				'spacing_sizes' => $this->presets( $settings['spacing']['spacingSizes'] ?? array(), 'size' ),
			),
			'layout'                    => array(
				'content_size' => $this->text( $settings['layout']['contentSize'] ?? null ),
				'wide_size'    => $this->text( $settings['layout']['wideSize'] ?? null ),
			),
			'templates'                 => $this->templates( $theme, $executor ),
		);
		$data['truncated'] = $this->truncated;
		return $this->bound( $data );
	}

	/**
	 * Preserve native preset slugs and custom > theme > default precedence.
	 *
	 * @param mixed  $origins Native origin-keyed preset arrays.
	 * @param string $value_key Native value field.
	 * @return array
	 */
	private function presets( $origins, string $value_key ): array {
		if ( ! is_array( $origins ) || array() === $origins ) {
			return array();
		}
		// Available since core 5.8. Omit presets if the native helper cannot
		// be used rather than guessing WordPress's CSS identifier rules.
		if ( ! function_exists( '_wp_to_kebab_case' ) ) {
			$this->truncated = true;
			return array();
		}
		$values = array();
		$seen   = array();
		foreach ( array( 'custom', 'theme', 'default' ) as $origin ) {
			if ( ! isset( $origins[ $origin ] ) || ! is_array( $origins[ $origin ] ) ) {
				continue;
			}
			// Native CSS uses the last duplicate within each origin. Resolve
			// effective values before applying this response's size limits.
			$resolved = array();
			foreach ( $origins[ $origin ] as $preset ) {
				if ( ! is_array( $preset ) ) {
					continue;
				}
				$slug = $preset['slug'] ?? null;
				if ( is_string( $slug ) || is_int( $slug ) || is_float( $slug ) ) {
					// Core CSS treats brandColor and brand-color as one preset.
					// Resolve that identity before applying projection limits.
					$resolved[ _wp_to_kebab_case( (string) $slug ) ] = $preset;
				}
			}
			foreach ( $resolved as $key => $preset ) {
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = true;
				$slug         = $this->text( $preset['slug'] );
				$value        = $this->text( $preset[ $value_key ] ?? null );
				if ( null === $slug || '' === $slug || null === $value || '' === $value ) {
					continue;
				}
				if ( count( $values ) >= self::MAX_PRESETS ) {
					$this->truncated = true;
					return array_values( $values );
				}
				$values[ $key ] = array(
					'slug'   => $slug,
					'name'   => $this->text( $preset['name'] ?? null ) ?? $slug,
					'value'  => $value,
					'origin' => $origin,
				);
			}
		}
		return array_values( $values );
	}

	/**
	 * Return only templates selectable for REST content the executor may edit.
	 *
	 * @param \WP_Theme $theme Active theme.
	 * @param int       $executor WordPress user ID.
	 * @return array
	 */
	private function templates( \WP_Theme $theme, int $executor ): array {
		$templates = array();
		$count     = 0;
		foreach ( get_post_types(
			array(
				'show_in_rest' => true,
				'public' => true,
			),
			'objects'
		) as $post_type ) {
			if ( ! post_type_supports( $post_type->name, 'editor' ) || ! user_can( $executor, $post_type->cap->edit_posts ) ) {
				continue;
			}
			if ( ++$count > self::MAX_POST_TYPES ) {
				$this->truncated = true;
				break;
			}
			foreach ( $theme->get_page_templates( null, $post_type->name ) as $slug => $name ) {
				$slug = $this->text( $slug );
				$name = $this->text( $name );
				if ( null === $slug || null === $name ) {
					continue;
				}
				if ( count( $templates ) >= self::MAX_TEMPLATES ) {
					$this->truncated = true;
					return $templates;
				}
				$templates[] = array(
					'post_type' => $post_type->name,
					'slug'      => $slug,
					'name'      => $name,
				);
			}
		}
		return $templates;
	}


	/**
	 * Keep even unusually large native scalar values within a small payload.
	 * Earlier entries retain custom/theme precedence when a list is shortened.
	 *
	 * @param array $data The projected design facts.
	 * @return array
	 */
	private function bound( array $data ): array {
		$length = strlen( (string) wp_json_encode( $data ) );
		while ( $length > self::MAX_DESIGN_BYTES ) {
			$largest = 'templates';
			$bytes   = strlen( (string) wp_json_encode( $data['templates'] ) );
			foreach ( $data['presets'] as $kind => $values ) {
				$size = strlen( (string) wp_json_encode( $values ) );
				if ( $size > $bytes ) {
					$largest = $kind;
					$bytes   = $size;
				}
			}
			if ( 'templates' === $largest ) {
				array_pop( $data['templates'] );
			} else {
				array_pop( $data['presets'][ $largest ] );
			}
			$data['truncated'] = true;
			$length            = strlen( (string) wp_json_encode( $data ) );
		}
		return $data;
	}

	/**
	 * Accept native scalar values without rewriting identifiers or CSS values.
	 *
	 * @param mixed $value Native field.
	 * @return string|null
	 */
	private function text( $value ): ?string {
		if ( ! is_string( $value ) && ! is_int( $value ) && ! is_float( $value ) ) {
			return null;
		}
		$value = (string) $value;
		if ( strlen( $value ) > 512 ) {
			$this->truncated = true;
			return null;
		}
		return $value;
	}
}
