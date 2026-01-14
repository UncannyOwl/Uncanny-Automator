<?php
/**
 * Sentence Human Readable Service.
 *
 * Builds human-readable sentences from templates and field collections.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Sentence_Html;

use Uncanny_Automator\Api\Components\Shared\Sentence_Html\Value_Objects\Sentence_Template;
use Uncanny_Automator\Api\Components\Shared\Sentence_Html\Collections\Sentence_Field_Value_Text_Collection;
use Uncanny_Automator\Api\Components\Shared\Sentence_Html\Collections\Sentence_Field_Label_Collection;

/**
 * Class Sentence_Human_Readable_Service
 *
 * Service for building human-readable sentences from templates and field collections.
 */
class Sentence_Human_Readable_Service {

	/**
	 * The code for the "Number of Times" field.
	 *
	 * @var string
	 */
	const NUMBER_OF_TIMES_CODE = 'NUMTIMES';

	/**
	 * Builds the Human Readable sentence from a template + field collections.
	 *
	 * Output format: "Text {{Label: value}} more text"
	 *
	 * @param Sentence_Template                    $sentence_template Template with {{decorator:CODE}} tokens.
	 * @param Sentence_Field_Value_Text_Collection $field_values      Selected values (code → [value, text]).
	 * @param Sentence_Field_Label_Collection      $field_labels      Labels (code → label).
	 *
	 * @return string Human readable sentence.
	 */
	public function build(
		Sentence_Template $sentence_template,
		Sentence_Field_Value_Text_Collection $field_values,
		Sentence_Field_Label_Collection $field_labels
	): string {

		$sentence  = $sentence_template->get_value();
		$fields    = $field_values->to_fields_array();
		$label_map = $field_labels->to_label_map();

		$tokens       = $this->parse_tokens( $sentence, $fields, $label_map );
		$replacements = array();

		foreach ( $tokens as $token ) {

			$raw = $token['raw'];

			// Unknown code - use decorator only.
			if ( null === $token['value'] ) {
				$replacements[ $raw ] = "{{{$token['decorator']}}}";
				continue;
			}

			// NUMTIMES special case: value only, no label.
			if ( $this->is_numtimes( $token['code'] ) ) {
				$replacements[ $raw ] = "{{{$token['value']}}}";
				continue;
			}

			// Standard: {{Label: value}}.
			$replacements[ $raw ] = "{{{$token['label']}: {$token['value']}}}";
		}

		return strtr( $sentence, $replacements );
	}

	/**
	 * Builds the HTML representation of the human-readable sentence.
	 *
	 * Output is wrapped in a <div> and uses styled spans:
	 * - Normal text:   <span class="item-title__normal">text</span>
	 * - Known field:   <span class="item-title__token [item-title__token--filled]" data-token-id="CODE" data-options-id="CODE">
	 *                    <span class="item-title__token-label">Label:</span> value
	 *                  </span>
	 * - NUMTIMES:      <span class="item-title__token item-title__token--filled" data-token-id="NUMTIMES" data-options-id="NUMTIMES">value</span>
	 * - Unknown code:  <span class="item-title__token" data-token-id="CODE" data-options-id="CODE">decorator</span>
	 *
	 * The --filled modifier is applied when:
	 * - NUMTIMES: Always applied
	 * - Known fields: Applied when is_filled=true in the field value object
	 * - Unknown codes: Never applied
	 *
	 * @since 7.0.0
	 *
	 * @param Sentence_Template                    $sentence_template Template with {{decorator:CODE}} tokens.
	 * @param Sentence_Field_Value_Text_Collection $field_values      Selected values (code → [value, text, is_filled]).
	 * @param Sentence_Field_Label_Collection      $field_labels      Labels (code → label).
	 *
	 * @return string HTML formatted sentence wrapped in a div.
	 */
	// phpcs:ignore Uncanny_Automator.Commenting.FunctionCommentAutoFix.MissingFunctionComment -- Comprehensive docblock is present above.
	public function build_html( Sentence_Template $sentence_template, Sentence_Field_Value_Text_Collection $field_values, Sentence_Field_Label_Collection $field_labels ): string {

		$sentence  = $sentence_template->get_value();
		$fields    = $field_values->to_fields_array();
		$label_map = $field_labels->to_label_map();

		$tokens   = $this->parse_tokens( $sentence, $fields, $label_map );
		$segments = $this->parse_segments( $sentence, $tokens );

		$html = '<div>';

		foreach ( $segments as $segment ) {
			if ( 'text' === $segment['type'] ) {
				$html .= $this->render_text_span( $segment['content'] );
			} else {
				$html .= $this->render_token_span( $segment['token'] );
			}
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Parse all {{decorator:CODE}} tokens from the sentence.
	 *
	 * Returns an array of token data with resolved labels and values.
	 * This method is shared by both build() and build_html().
	 *
	 * @param string $sentence  The raw sentence template.
	 * @param array  $fields    Field values keyed by code.
	 * @param array  $label_map Labels keyed by code.
	 *
	 * @return array[] Array of token data arrays with keys: raw, decorator, code, label, value, is_filled.
	 */
	private function parse_tokens( string $sentence, array $fields, array $label_map ): array {

		preg_match_all( '/\{\{([^}]+)\}\}/', $sentence, $matches, PREG_OFFSET_CAPTURE );

		$tokens = array();

		foreach ( $matches[1] as $match ) {
			$inner  = $match[0];
			$offset = $match[1] - 2; // Adjust for opening braces.
			$x      = explode( ':', $inner, 2 );

			if ( count( $x ) !== 2 ) {
				continue;
			}

			$decorator = trim( $x[0] );
			$code      = trim( $x[1] );
			$raw       = '{{' . $inner . '}}';

			// Unknown code - fallback to decorator only.
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
				'is_filled' => $fields[ $code ]['is_filled'] ?? true,
			);
		}

		return $tokens;
	}

	/**
	 * Parse sentence into segments of text and tokens.
	 *
	 * Splits the sentence at token boundaries to create alternating
	 * text and token segments for HTML rendering.
	 *
	 * @param string  $sentence The raw sentence template.
	 * @param array[] $tokens   Parsed token data from parse_tokens().
	 *
	 * @return array[] Array of segments with 'type' (text|token) and 'content' or 'token'.
	 */
	private function parse_segments( string $sentence, array $tokens ): array {

		$segments = array();
		$cursor   = 0;

		foreach ( $tokens as $token ) {
			$offset = $token['offset'];

			// Add text segment before this token (if any).
			if ( $offset > $cursor ) {
				$text = substr( $sentence, $cursor, $offset - $cursor );
				if ( '' !== $text ) {
					$segments[] = array(
						'type'    => 'text',
						'content' => $text,
					);
				}
			}

			// Add token segment.
			$segments[] = array(
				'type'  => 'token',
				'token' => $token,
			);

			$cursor = $offset + strlen( $token['raw'] );
		}

		// Add trailing text after last token (if any).
		if ( $cursor < strlen( $sentence ) ) {
			$text = substr( $sentence, $cursor );
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
	 * Render a normal text span.
	 *
	 * @param string $text The text content.
	 *
	 * @return string HTML span element.
	 */
	private function render_text_span( string $text ): string {
		return sprintf(
			'<span class="item-title__normal">%s</span>',
			esc_html( $text )
		);
	}

	/**
	 * Render a token span with appropriate styling.
	 *
	 * Handles three cases:
	 * - Unknown code: No --filled class, decorator text only
	 * - NUMTIMES: Has --filled class, value only (no label)
	 * - Known field: --filled class based on is_filled flag, label + value
	 *
	 * @param array $token Token data from parse_tokens().
	 *
	 * @return string HTML span element.
	 */
	private function render_token_span( array $token ): string {

		$code = esc_attr( $token['code'] );

		// Unknown code - no --filled, decorator only.
		if ( null === $token['value'] ) {
			return sprintf(
				'<span class="item-title__token" data-token-id="%s" data-options-id="%s">%s</span>',
				$code,
				$code,
				esc_html( $token['decorator'] )
			);
		}

		// NUMTIMES - always has --filled, value only (no label).
		if ( $this->is_numtimes( $token['code'] ) ) {
			return sprintf(
				'<span class="item-title__token item-title__token--filled" data-token-id="%s" data-options-id="%s">%s</span>',
				$code,
				$code,
				esc_html( $token['value'] )
			);
		}

		// Known field - --filled based on is_filled flag, label + value.
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
	 * Check if a field code is the NUMTIMES special case.
	 *
	 * @param string $code The field code to check.
	 *
	 * @return bool True if this is the NUMTIMES field.
	 */
	private function is_numtimes( string $code ): bool {
		return strtolower( $code ) === strtolower( self::NUMBER_OF_TIMES_CODE );
	}
}
