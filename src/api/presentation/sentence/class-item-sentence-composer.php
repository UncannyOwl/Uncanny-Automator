<?php
/**
 * Item Sentence Composer.
 *
 * Canonical sentence and sentence HTML composer for core API mutation surfaces.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Presentation\Sentence;

/**
 * Item_Sentence_Composer.
 *
 * Composes:
 * - sentence_human_readable (bracket format)
 * - sentence_human_readable_html (item-title HTML format)
 */
class Item_Sentence_Composer {

	/**
	 * The code for the "Number of Times" field.
	 *
	 * @var string
	 */
	private const NUMBER_OF_TIMES_CODE = 'NUMTIMES';

	/**
	 * Compose sentence outputs from raw config and field labels.
	 *
	 * @param string $sentence_template  Sentence template containing {{decorator:CODE}} tokens.
	 * @param array  $configuration      Field configuration with optional *_readable values.
	 * @param array  $field_labels       Field label map keyed by field code.
	 * @param array  $field_fill_states  Optional explicit fill-state map keyed by field code.
	 *
	 * @return array{brackets: string, html: string}
	 */
	public function compose(
		string $sentence_template,
		array $configuration,
		array $field_labels,
		array $field_fill_states = array()
	): array {
		$fields = $this->normalize_fields( $configuration, $field_labels, $field_fill_states );
		$tokens = $this->parse_tokens( $sentence_template, $fields, $field_labels );

		return array(
			'brackets' => $this->build_bracket_sentence( $sentence_template, $tokens ),
			'html'     => $this->build_html_sentence( $sentence_template, $tokens ),
		);
	}

	/**
	 * Build bracket sentence from parsed tokens.
	 *
	 * @param string $sentence_template Sentence template.
	 * @param array  $tokens            Parsed tokens.
	 *
	 * @return string
	 */
	private function build_bracket_sentence( string $sentence_template, array $tokens ): string {
		$replacements = array();

		foreach ( $tokens as $token ) {
			$raw = $token['raw'];

			if ( null === $token['value'] ) {
				$replacements[ $raw ] = "{{{$token['decorator']}}}";
				continue;
			}

			if ( $this->is_numtimes( $token['code'] ) ) {
				$replacements[ $raw ] = "{{{$token['value']}}}";
				continue;
			}

			$replacements[ $raw ] = "{{{$token['label']}: {$token['value']}}}";
		}

		return strtr( $sentence_template, $replacements );
	}

	/**
	 * Build HTML sentence from parsed tokens.
	 *
	 * @param string $sentence_template Sentence template.
	 * @param array  $tokens            Parsed tokens.
	 *
	 * @return string
	 */
	private function build_html_sentence( string $sentence_template, array $tokens ): string {
		$segments = $this->parse_segments( $sentence_template, $tokens );
		$html     = '<div>';

		foreach ( $segments as $segment ) {
			if ( 'text' === $segment['type'] ) {
				$html .= $this->render_text_span( $segment['content'] );
				continue;
			}

			$html .= $this->render_token_span( $segment['token'] );
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Parse all tokens from the sentence template.
	 *
	 * @param string $sentence_template Sentence template.
	 * @param array  $fields            Field value map keyed by code.
	 * @param array  $label_map         Label map keyed by code.
	 *
	 * @return array
	 */
	private function parse_tokens( string $sentence_template, array $fields, array $label_map ): array {
		preg_match_all( '/\{\{([^}]+)\}\}/', $sentence_template, $matches, PREG_OFFSET_CAPTURE );

		$tokens = array();

		foreach ( $matches[1] as $match ) {
			$inner  = $match[0];
			$offset = $match[1] - 2; // adjust for opening braces
			$parts  = explode( ':', $inner, 2 );

			if ( 2 !== count( $parts ) ) {
				continue;
			}

			$decorator = trim( $parts[0] );
			$code      = trim( $parts[1] );
			$raw       = '{{' . $inner . '}}';

			if ( ! isset( $fields[ $code ], $label_map[ $code ] ) ) {
				$tokens[] = array(
					'raw'       => $raw,
					'offset'    => $offset,
					'decorator' => $decorator,
					'code'      => $code,
					'label'     => null,
					'value'     => null,
					'is_filled' => false,
				);
				continue;
			}

			$tokens[] = array(
				'raw'       => $raw,
				'offset'    => $offset,
				'decorator' => $decorator,
				'code'      => $code,
				'label'     => $label_map[ $code ],
				'value'     => $fields[ $code ]['text'],
				'is_filled' => $fields[ $code ]['is_filled'],
			);
		}

		return $tokens;
	}

	/**
	 * Parse template into text and token segments.
	 *
	 * @param string $sentence_template Sentence template.
	 * @param array  $tokens            Parsed token list.
	 *
	 * @return array
	 */
	private function parse_segments( string $sentence_template, array $tokens ): array {
		$segments = array();
		$cursor   = 0;

		foreach ( $tokens as $token ) {
			$offset = $token['offset'];

			if ( $offset > $cursor ) {
				$text = substr( $sentence_template, $cursor, $offset - $cursor );
				if ( '' !== $text ) {
					$segments[] = array(
						'type'    => 'text',
						'content' => $text,
					);
				}
			}

			$segments[] = array(
				'type'  => 'token',
				'token' => $token,
			);

			$cursor = $offset + strlen( $token['raw'] );
		}

		if ( $cursor < strlen( $sentence_template ) ) {
			$text = substr( $sentence_template, $cursor );
			if ( '' !== $text ) {
				$segments[] = array(
					'type'    => 'text',
					'content' => $text,
				);
			}
		}

		return $segments;
	}

	/**
	 * Render a text segment.
	 *
	 * @param string $text Text content.
	 *
	 * @return string
	 */
	private function render_text_span( string $text ): string {
		return sprintf(
			'<span class="item-title__normal">%s</span>',
			esc_html( $text )
		);
	}

	/**
	 * Render a token segment.
	 *
	 * @param array $token Token data.
	 *
	 * @return string
	 */
	private function render_token_span( array $token ): string {
		$code = esc_attr( $token['code'] );

		if ( null === $token['value'] ) {
			return sprintf(
				'<span class="item-title__token" data-token-id="%s" data-options-id="%s">%s</span>',
				$code,
				$code,
				esc_html( $token['decorator'] )
			);
		}

		if ( $this->is_numtimes( $token['code'] ) ) {
			return sprintf(
				'<span class="item-title__token item-title__token--filled" data-token-id="%s" data-options-id="%s">%s</span>',
				$code,
				$code,
				esc_html( $token['value'] )
			);
		}

		$filled_class = $token['is_filled'] ? ' item-title__token--filled' : '';

		return sprintf(
			'<span class="item-title__token%s" data-token-id="%s" data-options-id="%s"><span class="item-title__token-label">%s:</span> %s</span>',
			$filled_class,
			$code,
			$code,
			esc_html( $token['label'] ),
			esc_html( $token['value'] )
		);
	}

	/**
	 * Normalize raw configuration to field map used during token rendering.
	 *
	 * @param array $configuration     Field configuration.
	 * @param array $field_labels      Field labels map.
	 * @param array $field_fill_states Optional fill-state overrides.
	 *
	 * @return array
	 */
	private function normalize_fields( array $configuration, array $field_labels, array $field_fill_states ): array {
		$fields = array();

		foreach ( $field_labels as $code => $label ) {
			if ( ! array_key_exists( $code, $configuration ) ) {
				continue;
			}

			$raw_value = $this->normalize_raw_value( $configuration[ $code ] );
			$text      = $this->normalize_text_value( $configuration[ $code . '_readable' ] ?? $raw_value );

			if ( array_key_exists( $code, $field_fill_states ) ) {
				$is_filled = (bool) $field_fill_states[ $code ];
			} else {
				$is_filled = ! empty( $text ) && '-1' !== (string) $raw_value && -1 !== $raw_value;
			}

			$fields[ $code ] = array(
				'value'     => $raw_value,
				'text'      => $text,
				'is_filled' => $is_filled,
			);
		}

		return $fields;
	}

	/**
	 * Normalize a raw field value.
	 *
	 * @param mixed $value Raw field value.
	 *
	 * @return mixed
	 */
	private function normalize_raw_value( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( null === $value ) {
			return '';
		}

		if ( is_array( $value ) || ( is_object( $value ) && ! method_exists( $value, '__toString' ) ) ) {
			return wp_json_encode( $value );
		}

		return $value;
	}

	/**
	 * Normalize a readable field value to string.
	 *
	 * @param mixed $value Readable field value.
	 *
	 * @return string
	 */
	private function normalize_text_value( $value ): string {
		if ( null === $value ) {
			return '';
		}

		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( is_array( $value ) || ( is_object( $value ) && ! method_exists( $value, '__toString' ) ) ) {
			return (string) wp_json_encode( $value );
		}

		if ( ! is_string( $value ) ) {
			return (string) $value;
		}

		return $value;
	}

	/**
	 * Check if a field code is the NUMTIMES special case.
	 *
	 * @param string $code Field code.
	 *
	 * @return bool
	 */
	private function is_numtimes( string $code ): bool {
		return strtolower( $code ) === strtolower( self::NUMBER_OF_TIMES_CODE );
	}
}
