<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects;

use InvalidArgumentException;

/**
 * Block Sentence Value Object.
 *
 * Unlike items / modifiers — which carry a `{ short, dynamic }` pair because their short variant powers list / palette views — block sentences are only ever rendered in one place (the block header in the flow viewer), so the shape stays minimal: a single unconditional `default` template plus optional conditional `variants`.
 *
 * The frontend's `<uap-d-recipe-sentence>` consumes the chosen template after `pickBlockSentenceTemplate( sentence, fields )` walks `variants` and returns the first matching one (or falls back to `default`).
 *
 * Template syntax: `{{label:FIELD_CODE:groupCode?}}` — same as item / modifier templates. The `label` is the fallback text shown when the field has no value yet; `FIELD_CODE` is the option-code the placeholder substitutes from the block instance's `fields` blob.
 *
 * @since 7.x
 */
class Block_Sentence {

	/**
	 * The unconditional template — rendered when no variant matches.
	 *
	 * @var string
	 */
	private string $default;

	/**
	 * Optional list of conditional overrides.
	 *
	 * @var Block_Sentence_Variant[]
	 */
	private array $variants;

	/**
	 * Constructor.
	 *
	 * @param array $data Sentence data
	 *   @property string $default  Default template (non-empty).
	 *   @property array  $variants Optional array of `{ when, template }` rows
	 *                              (or Block_Sentence_Variant objects).
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid data.
	 */
	public function __construct( array $data ) {
		$this->validate( $data );

		$this->default  = $data['default'];
		$this->variants = $this->normalize_variants( $data['variants'] ?? array() );
	}

	/**
	 * Get the default template.
	 *
	 * @return string
	 */
	public function get_default(): string {
		return $this->default;
	}

	/**
	 * Get the conditional variants.
	 *
	 * @return Block_Sentence_Variant[]
	 */
	public function get_variants(): array {
		return $this->variants;
	}

	/**
	 * Convert to array.
	 *
	 * Omits the `variants` key entirely when no variants are declared so the wire payload matches the optional shape the frontend Zod schema accepts: a backend that hasn't shipped variants yet still parses.
	 *
	 * @return array
	 */
	public function to_array(): array {
		$data = array(
			'default' => $this->default,
		);

		if ( ! empty( $this->variants ) ) {
			$data['variants'] = array_map(
				function ( $variant ) {
					return $variant->to_array();
				},
				$this->variants
			);
		}

		return $data;
	}

	/**
	 * Normalize variants to value objects.
	 *
	 * @param array $variants Array of variant data or Block_Sentence_Variant objects.
	 *
	 * @return Block_Sentence_Variant[]
	 */
	private function normalize_variants( array $variants ): array {
		$normalized = array();

		foreach ( $variants as $variant ) {
			$normalized[] = $variant instanceof Block_Sentence_Variant
				? $variant
				: new Block_Sentence_Variant( $variant );
		}

		return $normalized;
	}

	/**
	 * Validate sentence data.
	 *
	 * @param array $data Sentence data to validate.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid.
	 */
	private function validate( array $data ): void {
		if ( empty( $data['default'] ) || ! is_string( $data['default'] ) ) {
			throw new InvalidArgumentException( 'Block sentence `default` must be a non-empty string' );
		}

		if ( isset( $data['variants'] ) && ! is_array( $data['variants'] ) ) {
			throw new InvalidArgumentException( 'Block sentence `variants` must be an array when provided' );
		}
	}
}
