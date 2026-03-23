<?php
/**
 * Condition Title Composer.
 *
 * Canonical condition backup title HTML generator for core API condition flows.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Presentation\Condition;

/**
 * Condition_Title_Composer.
 */
class Condition_Title_Composer {

	/**
	 * Compose backup title HTML from a dynamic condition name.
	 *
	 * @param string $dynamic_name Dynamic sentence/title.
	 * @param array  $fields       Condition field values.
	 *
	 * @return string
	 */
	public function compose( string $dynamic_name, array $fields = array() ): string {
		$dynamic_name = trim( $this->interpolate_dynamic_tokens( $dynamic_name, $fields ) );

		if ( '' !== $dynamic_name ) {
			return sprintf(
				'<span class="uap-dynamic-sentence" dir="auto"><span class="uap-dynamic-sentence-plain">%s</span></span>',
				esc_html( $dynamic_name )
			);
		}

		return $this->compose_from_fields( $fields );
	}

	/**
	 * Replace {{label:FIELD}} tokens in a dynamic sentence with readable values.
	 *
	 * @param string $dynamic_name Dynamic sentence template.
	 * @param array  $fields       Condition field values.
	 *
	 * @return string
	 */
	private function interpolate_dynamic_tokens( string $dynamic_name, array $fields ): string {
		return (string) preg_replace_callback(
			'/\{\{\s*([^:}]+)\s*:\s*([^}]+)\s*\}\}/',
			function ( array $matches ) use ( $fields ): string {
				$label = trim( (string) ( $matches[1] ?? '' ) );
				$code  = trim( (string) ( $matches[2] ?? '' ) );

				$value = $this->normalize_value(
					$fields[ $code . '_readable' ] ?? ( $fields[ $code ] ?? '' )
				);

				if ( '' === $value ) {
					return $label;
				}

				if ( '' === $label ) {
					return $value;
				}

				return sprintf( '%s: %s', $label, $value );
			},
			$dynamic_name
		);
	}

	/**
	 * Build fallback HTML title from available saved fields.
	 *
	 * @param array $fields Condition field values.
	 *
	 * @return string
	 */
	private function compose_from_fields( array $fields ): string {
		$pills = array();

		foreach ( $this->extract_field_codes( $fields ) as $field_code ) {
			$value = $this->normalize_value(
				$fields[ $field_code . '_readable' ] ?? ( $fields[ $field_code ] ?? '' )
			);
			if ( '' === $value ) {
				continue;
			}

			$label = $this->normalize_value( $fields[ $field_code . '_label' ] ?? $field_code );

			$pills[] = sprintf(
				'<span class="uap-dynamic-sentence-pill uap-dynamic-sentence-pill--filled" data-field="%1$s"><span class="uap-dynamic-sentence-pill-label">%2$s: </span><span class="uap-text-with-tokens">%3$s</span></span>',
				esc_attr( $field_code ),
				esc_html( $label ),
				esc_html( $value )
			);
		}

		if ( empty( $pills ) ) {
			return sprintf(
				'<span class="uap-dynamic-sentence" dir="auto"><span class="uap-dynamic-sentence-plain">%s</span></span>',
				esc_html_x( 'Condition', 'Condition fallback title', 'uncanny-automator' )
			);
		}

		return sprintf(
			'<span class="uap-dynamic-sentence" dir="auto">%s</span>',
			implode( '<span class="uap-dynamic-sentence-plain"> </span>', $pills )
		);
	}

	/**
	 * Extract unique field codes from condition fields.
	 *
	 * @param array $fields Condition field values.
	 *
	 * @return string[]
	 */
	private function extract_field_codes( array $fields ): array {
		$field_codes = array();

		foreach ( array_keys( $fields ) as $key ) {
			if ( ! is_string( $key ) ) {
				continue;
			}

			if ( str_ends_with( $key, '_label' ) ) {
				$field_codes[] = substr( $key, 0, -6 );
				continue;
			}

			if ( str_ends_with( $key, '_readable' ) ) {
				$field_codes[] = substr( $key, 0, -9 );
				continue;
			}

			$field_codes[] = $key;
		}

		$field_codes = array_filter(
			array_unique( $field_codes ),
			static function ( $field_code ): bool {
				return '' !== trim( (string) $field_code );
			}
		);

		return array_values( $field_codes );
	}

	/**
	 * Normalize mixed field values to plain strings.
	 *
	 * @param mixed $value Raw field value.
	 *
	 * @return string
	 */
	private function normalize_value( $value ): string {
		if ( is_array( $value ) || ( is_object( $value ) && ! method_exists( $value, '__toString' ) ) ) {
			return '';
		}

		return trim( (string) $value );
	}
}
