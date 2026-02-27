<?php
/**
 * Loop Filter Sentence Composer
 *
 * Replicates the frontend sentence composition algorithm to generate
 * HTML that exactly matches the frontend output structure.
 *
 * Based on: src/assets/src/features/recipe-builder/components/item/sentence/index.js
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Presentation\Loop\Filters;

/**
 * Loop_Filter_Sentence_Composer
 *
 * Composes HTML sentences from templates and field values,
 * matching the exact output of the frontend sentence component.
 *
 * Frontend algorithm:
 * 1. Split sentence by {{ delimiter
 * 2. For each segment, split by }} delimiter
 * 3. Extract pillDefaultText:pillCode:optionsCode
 * 4. Get value from fields (preferring readable over value)
 * 5. Render with exact CSS classes
 * 6. Clean up for logs (remove comments, whitespace, convert buttons to spans)
 *
 * @since 7.0.0
 */
class Loop_Filter_Sentence_Composer {

	/**
	 * Composes a sentence from template and field values.
	 *
	 * Matches frontend output with exact CSS classes:
	 * - sentence (wrapper)
	 * - sentence-plain (plain text)
	 * - sentence-pill (pill wrapper)
	 * - sentence-pill-label (label)
	 * - sentence-pill-value (value wrapper)
	 *
	 * @param string $sentence_template The sentence template with {{pillDefaultText:pillCode}} placeholders.
	 * @param array  $fields            Field values with structure: [ 'code' => ['value' => '', 'readable' => '', 'backup' => ['label' => '']] ].
	 *
	 * @return string HTML sentence matching frontend output.
	 */
	public function compose( string $sentence_template, array $fields ): string {
		// Decode HTML entities (frontend does this with html-entities decode).
		$sentence = html_entity_decode( $sentence_template, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Split by opening delimiter {{ (step 1).
		$segments = explode( '{{', $sentence );

		$html = '<span class="sentence sentence--standard">';

		foreach ( $segments as $index => $segment ) {
			// First segment is always plain text before any pills.
			if ( 0 === $index && ! str_contains( $segment, '}}' ) ) {
				$html .= $this->render_plain_segment( $segment );
				continue;
			}

			// Split by closing delimiter }} (step 2).
			$parts = explode( '}}', $segment, 2 );

			// If no closing delimiter, it's plain text.
			if ( 1 === count( $parts ) ) {
				$html .= $this->render_plain_segment( $parts[0] );
				continue;
			}

			// Extract pill components (step 3).
			$pill_content = $parts[0];
			$after_text   = $parts[1] ?? '';

			$pill_parts = explode( ':', $pill_content, 3 );

			$pill_default_text = $pill_parts[0] ?? '';
			$pill_code         = $pill_parts[1] ?? '';
			$options_code      = $pill_parts[2] ?? $pill_code;

			// Render the pill (step 4-5).
			$html .= $this->render_pill( $pill_default_text, $pill_code, $fields );

			// Render text after the pill.
			$html .= $this->render_plain_segment( $after_text );
		}

		$html .= '</span>';

		// Clean up for logs (step 6) - match frontend outputForLogs.
		return $this->cleanup_for_logs( $html );
	}

	/**
	 * Renders a plain text segment.
	 *
	 * Matches frontend: _templateSentencePlain()
	 *
	 * @param string $text Plain text content.
	 *
	 * @return string HTML span or empty string.
	 */
	private function render_plain_segment( string $text ): string {
		// Empty segments are not rendered (frontend returns undefined).
		if ( '' === trim( $text ) && '' === $text ) {
			return '';
		}

		// Don't render if only whitespace that would be trimmed.
		if ( '' === $text ) {
			return '';
		}

		return sprintf(
			'<span class="sentence-plain">%s</span>',
			esc_html( $text )
		);
	}

	/**
	 * Renders a pill segment with label and value.
	 *
	 * Matches frontend: _templateSentencePill() + _templateSentencePillValue()
	 *
	 * @param string $default_text The default text if no value is available.
	 * @param string $pill_code    The field code to get the value.
	 * @param array  $fields       All field values.
	 *
	 * @return string HTML pill span.
	 */
	private function render_pill( string $default_text, string $pill_code, array $fields ): string {
		// Get pill value or use default text.
		$pill_value = $this->get_pill_value( $pill_code, $fields );

		if ( null === $pill_value ) {
			$pill_value = $default_text;
		}

		// Get label (if configured to show).
		$label_html = $this->get_pill_label( $pill_code, $fields );

		// Frontend renders non-interactive pills as static spans.
		// For logs output, we always use spans (buttons are converted later).
		return sprintf(
			'<span class="sentence-pill">%s%s</span>',
			$label_html,
			esc_html( $pill_value )
		);
	}

	/**
	 * Gets the pill value from field data.
	 *
	 * Matches frontend: _templateSentencePillValue()
	 * Prefers 'readable' over 'value' (e.g., "#general" instead of "C123").
	 *
	 * @param string $pill_code The field code.
	 * @param array  $fields    All field values.
	 *
	 * @return string|null The pill value or null if not available.
	 */
	private function get_pill_value( string $pill_code, array $fields ): ?string {
		// Check if field exists.
		if ( ! isset( $fields[ $pill_code ] ) ) {
			return null;
		}

		$field = $fields[ $pill_code ];

		// Frontend: const printableValue = this.fields[pillCode].readable || this.fields[pillCode].value.
		$printable_value = $field['readable'] ?? $field['value'] ?? '';

		// Empty check - frontend shows "(Empty)" for empty values.
		if ( '' === $printable_value || empty( $printable_value ) ) {
			return '(Empty)';
		}

		// Frontend: this.tokens.renderTextWithTokens() - handles tokens like {{TOKEN}}.
		// For now, return as-is. If token rendering is needed, integrate Token renderer here.
		return (string) $printable_value;
	}

	/**
	 * Gets the pill label HTML if configured to show.
	 *
	 * Matches frontend: _templateSentencePillLabel()
	 *
	 * @param string $pill_code The field code.
	 * @param array  $fields    All field values.
	 *
	 * @return string Label HTML or empty string.
	 */
	private function get_pill_label( string $pill_code, array $fields ): string {
		// Check if field and backup exist.
		if ( ! isset( $fields[ $pill_code ]['backup'] ) ) {
			return '';
		}

		$backup = $fields[ $pill_code ]['backup'];

		// Check if label should be shown (default: true).
		if ( isset( $backup['show_label_in_sentence'] ) && false === $backup['show_label_in_sentence'] ) {
			return '';
		}

		// Check if label exists.
		$label = $backup['label'] ?? '';
		if ( '' === $label || empty( $label ) ) {
			return '';
		}

		return sprintf(
			'<span class="sentence-pill-label">%s: </span>',
			esc_html( $label )
		);
	}

	/**
	 * Cleans up HTML for log storage.
	 *
	 * Matches frontend: outputForLogs getter
	 * - Removes HTML comments
	 * - Removes whitespace between tags
	 * - Converts uo-button to span
	 *
	 * @param string $html The raw HTML.
	 *
	 * @return string Cleaned HTML.
	 */
	private function cleanup_for_logs( string $html ): string {
		// 1. Remove HTML comments.
		// Frontend: html.replace(/<!--[\s\S]*?-->/g, '').
		$html = preg_replace( '/<!--[\s\S]*?-->/', '', $html );

		// 2. Remove spaces between HTML tags.
		// Frontend: html.replace(/(?<=>)\s+(?=<)/g, '').
		$html = preg_replace( '/(?<=>)\s+(?=<)/', '', $html );

		// 3. Replace buttons with spans (frontend compatibility).
		// Frontend: html.replaceAll('uo-button', 'span').
		$html = str_replace( 'uo-button', 'span', $html );

		return $html;
	}

	/**
	 * Composes sentence from simpler label-value pairs.
	 *
	 * Convenience method for cases where you have separate label and value arrays
	 * instead of the full field structure.
	 *
	 * @param string $sentence_template The sentence template.
	 * @param array  $values            Simple value map: ['code' => 'value'].
	 * @param array  $labels            Simple label map: ['code' => 'label'].
	 * @param array  $readable          Optional readable map: ['code' => 'readable'].
	 *
	 * @return string HTML sentence.
	 */
	public function compose_simple( string $sentence_template, array $values, array $labels = array(), array $readable = array() ): string {
		// Convert to full field structure.
		$fields = array();

		foreach ( $values as $code => $value ) {
			$fields[ $code ] = array(
				'value'    => $value,
				'readable' => $readable[ $code ] ?? null,
				'backup'   => array(
					'label'                 => $labels[ $code ] ?? '',
					'show_label_in_sentence' => ! empty( $labels[ $code ] ),
				),
			);
		}

		return $this->compose( $sentence_template, $fields );
	}
}
