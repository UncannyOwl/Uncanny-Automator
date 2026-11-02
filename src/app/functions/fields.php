<?php
/**
 * Field helper functions.
 *
 * Public API for field-value utilities: the boolean normalizer that the FE
 * isCheckboxValueChecked util mirrors, and the item-sentence composer that
 * renders field values into an item's bracket and HTML sentences. Outside-src/app
 * callers use these; src/app internals call the classes directly.
 *
 * @package Uncanny_Automator\App\Functions
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Uncanny_Automator\App\Recipe_Builder\Field\Services\Utilities\Field_Value_Normalizer;
use Uncanny_Automator\App\Presentation\Recipe_Builder\Sentence\Item_Sentence_Composer;

if ( ! function_exists( 'automator_normalize_bool' ) ) {
	/**
	 * Normalize any stored/wire checkbox value to a strict bool.
	 *
	 * Mirrors the FE isCheckboxValueChecked util (FILTER_VALIDATE_BOOLEAN):
	 * true/1/'1'/'true'/'on'/'yes' are true; everything else is false.
	 *
	 * @param mixed $value Stored or wire value.
	 * @return bool
	 */
	function automator_normalize_bool( $value ): bool {
		return Field_Value_Normalizer::to_bool( $value );
	}
}

if ( ! function_exists( 'automator_compose_item_sentence' ) ) {
	/**
	 * Render an item's sentence template with field values, the way the builder stores it: the bracket form
	 * (`{{Label: value}}`) and the HTML form. Never throws; on failure both forms are the template itself.
	 *
	 * @since 8.0.0
	 *
	 * @param string               $sentence_template The template, with `{{decorator:CODE}}` tokens.
	 * @param array<string, mixed> $configuration     Field values keyed by field code.
	 * @param array<string, string> $field_labels     Field labels keyed by field code.
	 *
	 * @return array{brackets: string, html: string}
	 */
	function automator_compose_item_sentence( string $sentence_template, array $configuration, array $field_labels ): array {
		try {
			return ( new Item_Sentence_Composer() )->compose( $sentence_template, $configuration, $field_labels );
		} catch ( \Throwable $e ) {
			return array(
				'brackets' => $sentence_template,
				'html'     => esc_html( $sentence_template ),
			);
		}
	}
}
