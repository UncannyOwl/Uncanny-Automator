<?php
/**
 * Sentence Output Builder.
 *
 * Backward-compatible adapter that delegates sentence generation to presentation.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Sentence_Html;

use Uncanny_Automator\Api\Components\Shared\Sentence_Html\Collections\Sentence_Field_Label_Collection;
use Uncanny_Automator\Api\Components\Shared\Sentence_Html\Collections\Sentence_Field_Value_Text_Collection;
use Uncanny_Automator\Api\Components\Shared\Sentence_Html\Value_Objects\Sentence_Field_Label;
use Uncanny_Automator\Api\Components\Shared\Sentence_Html\Value_Objects\Sentence_Field_Value_Text;
use Uncanny_Automator\Api\Components\Shared\Sentence_Html\Value_Objects\Sentence_Template;
use Uncanny_Automator\Api\Presentation\Sentence\Item_Sentence_Composer;

/**
 * Class Sentence_Output_Builder
 *
 * @deprecated 7.0.0 Use Uncanny_Automator\Api\Presentation\Sentence\Item_Sentence_Composer directly.
 *
 * Thin compatibility wrapper kept for existing callers and tests.
 */
class Sentence_Output_Builder {

	/**
	 * Sentence composer owned by presentation layer.
	 *
	 * @var Item_Sentence_Composer
	 */
	private Item_Sentence_Composer $composer;

	/**
	 * Legacy sentence service override for backward compatibility.
	 *
	 * @var Sentence_Human_Readable_Service|null
	 */
	private ?Sentence_Human_Readable_Service $legacy_service = null;

	/**
	 * Constructor.
	 *
	 * @param Sentence_Human_Readable_Service|null $service Optional legacy service instance for compatibility tests.
	 */
	public function __construct( ?Sentence_Human_Readable_Service $service = null ) {
		$this->legacy_service = $service;
		$this->composer       = new Item_Sentence_Composer();
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
		if ( $this->legacy_service instanceof Sentence_Human_Readable_Service ) {
			return $this->build_with_legacy_service( $sentence_template, $configuration, $field_labels );
		}

		return $this->composer->compose( $sentence_template, $configuration, $field_labels );
	}

	/**
	 * Build sentence outputs with explicitly injected legacy service.
	 *
	 * @param string $sentence_template Sentence template.
	 * @param array  $configuration     Raw config values.
	 * @param array  $field_labels      Field labels.
	 *
	 * @return array{brackets: string, html: string}
	 */
	private function build_with_legacy_service( string $sentence_template, array $configuration, array $field_labels ): array {
		$template = new Sentence_Template( $sentence_template );
		$values   = new Sentence_Field_Value_Text_Collection();
		$labels   = new Sentence_Field_Label_Collection();

		foreach ( $field_labels as $code => $label ) {
			$labels->add( new Sentence_Field_Label( $code, $label ) );

			if ( ! isset( $configuration[ $code ] ) ) {
				continue;
			}

			$raw_value = $configuration[ $code ];
			$text      = $configuration[ $code . '_readable' ] ?? (string) $raw_value;
			$is_filled = ! empty( $text ) && '-1' !== (string) $raw_value && -1 !== $raw_value;

			$values->add( new Sentence_Field_Value_Text( $code, $raw_value, (string) $text, $is_filled ) );
		}

		return array(
			'brackets' => $this->legacy_service->build( $template, $values, $labels ),
			'html'     => $this->legacy_service->build_html( $template, $values, $labels ),
		);
	}
}
