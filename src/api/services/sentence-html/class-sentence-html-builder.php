<?php
/**
 * Sentence Human Readable Service.
 *
 * Backward-compatible adapter for legacy callers.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Sentence_Html;

use Uncanny_Automator\Api\Components\Shared\Sentence_Html\Collections\Sentence_Field_Label_Collection;
use Uncanny_Automator\Api\Components\Shared\Sentence_Html\Collections\Sentence_Field_Value_Text_Collection;
use Uncanny_Automator\Api\Components\Shared\Sentence_Html\Value_Objects\Sentence_Template;
use Uncanny_Automator\Api\Presentation\Sentence\Item_Sentence_Composer;

/**
 * Class Sentence_Human_Readable_Service
 *
 * @deprecated 7.0.0 Use Uncanny_Automator\Api\Presentation\Sentence\Item_Sentence_Composer directly.
 */
class Sentence_Human_Readable_Service {

	/**
	 * Sentence composer owned by presentation layer.
	 *
	 * @var Item_Sentence_Composer
	 */
	private Item_Sentence_Composer $composer;

	/**
	 * Constructor.
	 *
	 * @param Item_Sentence_Composer|null $composer Optional composer instance.
	 */
	public function __construct( ?Item_Sentence_Composer $composer = null ) {
		$this->composer = $composer ?? new Item_Sentence_Composer();
	}

	/**
	 * Builds bracket sentence from legacy value objects.
	 *
	 * @param Sentence_Template                    $sentence_template Template with {{decorator:CODE}} tokens.
	 * @param Sentence_Field_Value_Text_Collection $field_values      Selected values.
	 * @param Sentence_Field_Label_Collection      $field_labels      Labels.
	 *
	 * @return string
	 */
	public function build(
		Sentence_Template $sentence_template,
		Sentence_Field_Value_Text_Collection $field_values,
		Sentence_Field_Label_Collection $field_labels
	): string {
		$result = $this->composer->compose(
			$sentence_template->get_value(),
			$this->to_configuration( $field_values ),
			$field_labels->to_label_map(),
			$this->to_fill_states( $field_values )
		);

		return $result['brackets'];
	}

	/**
	 * Builds HTML sentence from legacy value objects.
	 *
	 * @param Sentence_Template                    $sentence_template Template with {{decorator:CODE}} tokens.
	 * @param Sentence_Field_Value_Text_Collection $field_values      Selected values.
	 * @param Sentence_Field_Label_Collection      $field_labels      Labels.
	 *
	 * @return string
	 */
	public function build_html(
		Sentence_Template $sentence_template,
		Sentence_Field_Value_Text_Collection $field_values,
		Sentence_Field_Label_Collection $field_labels
	): string {
		$result = $this->composer->compose(
			$sentence_template->get_value(),
			$this->to_configuration( $field_values ),
			$field_labels->to_label_map(),
			$this->to_fill_states( $field_values )
		);

		return $result['html'];
	}

	/**
	 * Convert legacy field-value collection to composer configuration array.
	 *
	 * @param Sentence_Field_Value_Text_Collection $field_values Field values.
	 *
	 * @return array
	 */
	private function to_configuration( Sentence_Field_Value_Text_Collection $field_values ): array {
		$configuration = array();
		$fields        = $field_values->to_fields_array();

		foreach ( $fields as $code => $field ) {
			$configuration[ $code ]                 = $field['value'] ?? '';
			$configuration[ "{$code}_readable" ]    = $field['text'] ?? '';
		}

		return $configuration;
	}

	/**
	 * Convert legacy field-value collection to explicit fill-state map.
	 *
	 * @param Sentence_Field_Value_Text_Collection $field_values Field values.
	 *
	 * @return array
	 */
	private function to_fill_states( Sentence_Field_Value_Text_Collection $field_values ): array {
		$fill_states = array();
		$fields      = $field_values->to_fields_array();

		foreach ( $fields as $code => $field ) {
			if ( array_key_exists( 'is_filled', $field ) ) {
				$fill_states[ $code ] = (bool) $field['is_filled'];
			}
		}

		return $fill_states;
	}
}
