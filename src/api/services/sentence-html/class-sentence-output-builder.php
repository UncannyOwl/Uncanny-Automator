<?php
/**
 * Sentence Output Builder.
 *
 * Provides a convenient helper for building sentence outputs from raw configuration data.
 * This class bridges the gap between raw configuration arrays and the domain objects
 * used by Sentence_Human_Readable_Service.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Sentence_Html;

use Uncanny_Automator\Api\Components\Shared\Sentence_Html\Value_Objects\Sentence_Template;
use Uncanny_Automator\Api\Components\Shared\Sentence_Html\Value_Objects\Sentence_Field_Label;
use Uncanny_Automator\Api\Components\Shared\Sentence_Html\Value_Objects\Sentence_Field_Value_Text;
use Uncanny_Automator\Api\Components\Shared\Sentence_Html\Collections\Sentence_Field_Value_Text_Collection;
use Uncanny_Automator\Api\Components\Shared\Sentence_Html\Collections\Sentence_Field_Label_Collection;

/**
 * Class Sentence_Output_Builder
 *
 * Helper class that converts raw configuration arrays into domain objects
 * and generates both bracket-wrapped and HTML sentence formats.
 */
class Sentence_Output_Builder {

	/**
	 * The sentence service instance.
	 *
	 * @var Sentence_Human_Readable_Service
	 */
	private Sentence_Human_Readable_Service $service;

	/**
	 * Constructor.
	 *
	 * @param Sentence_Human_Readable_Service|null $service Optional service instance for testing.
	 */
	public function __construct( ?Sentence_Human_Readable_Service $service = null ) {
		$this->service = $service ?? new Sentence_Human_Readable_Service();
	}

	/**
	 * Builds sentence outputs from raw configuration data.
	 *
	 * Converts raw configuration arrays into domain objects and generates
	 * both bracket-wrapped and HTML sentence formats.
	 *
	 * @param string $sentence_template The sentence template with {{decorator:CODE}} tokens.
	 * @param array  $configuration     Field values including _readable suffixes.
	 * @param array  $field_labels      Map of field codes to labels.
	 *
	 * @return array{brackets: string, html: string} Sentence outputs.
	 */
	public function build( string $sentence_template, array $configuration, array $field_labels ): array {

		$template = new Sentence_Template( $sentence_template );

		// Build field value collection from configuration.
		$field_value_collection = $this->build_field_value_collection( $configuration, $field_labels );

		// Build field label collection.
		$field_label_collection = $this->build_field_label_collection( $field_labels );

		// Generate outputs using the service.
		$brackets = $this->service->build( $template, $field_value_collection, $field_label_collection );
		$html     = $this->service->build_html( $template, $field_value_collection, $field_label_collection );

		return array(
			'brackets' => $brackets,
			'html'     => $html,
		);
	}

	/**
	 * Builds a field value collection from configuration array.
	 *
	 * @param array $configuration Field values including _readable suffixes.
	 * @param array $field_labels  Map of field codes to labels.
	 *
	 * @return Sentence_Field_Value_Text_Collection The field value collection.
	 */
	private function build_field_value_collection( array $configuration, array $field_labels ): Sentence_Field_Value_Text_Collection {

		$collection = new Sentence_Field_Value_Text_Collection();

		foreach ( $field_labels as $code => $label ) {
			// Skip if no value exists for this code.
			if ( ! isset( $configuration[ $code ] ) ) {
				continue;
			}

			$raw_value = $configuration[ $code ];
			$text      = $configuration[ $code . '_readable' ] ?? (string) $raw_value;

			// Determine is_filled: has value and it's not empty/placeholder.
			// -1 typically means "Any X" selection → not filled.
			$is_filled = ! empty( $text ) && '-1' !== (string) $raw_value && -1 !== $raw_value;

			// Convert boolean raw values to 'true'/'false' strings.
			if ( is_bool( $raw_value ) ) {
				$raw_value = true === $raw_value ? 'true' : 'false';
			}

			$collection->add(
				new Sentence_Field_Value_Text( $code, $raw_value, $text, $is_filled )
			);
		}

		return $collection;
	}

	/**
	 * Builds a field label collection from labels array.
	 *
	 * @param array $field_labels Map of field codes to labels.
	 *
	 * @return Sentence_Field_Label_Collection The field label collection.
	 */
	private function build_field_label_collection( array $field_labels ): Sentence_Field_Label_Collection {

		$collection = new Sentence_Field_Label_Collection();

		foreach ( $field_labels as $code => $label ) {
			$collection->add(
				new Sentence_Field_Label( $code, $label )
			);
		}

		return $collection;
	}
}
