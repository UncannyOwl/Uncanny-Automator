<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects;

use InvalidArgumentException;

/**
 * Block Sentence Variant Value Object.
 *
 * Represents one conditional override for a block's rendered sentence.
 *
 * The frontend renderer walks `Block_Sentence::get_variants()` in catalog order and returns the first variant whose every `when` row matches the block instance's `fields` blob. Each row matches stringwise, so radios, numbers, and booleans all work without per-type branching.
 *
 * @since 7.x
 */
class Block_Sentence_Variant {

	/**
	 * Conditions, AND-combined. At least one row required.
	 *
	 * Each row has shape: [ 'field' => string, 'equals' => string|int|float|bool ].
	 *
	 * @var array<int, array{field:string,equals:mixed}>
	 */
	private array $when;

	/**
	 * Sentence template to render when this variant wins.
	 *
	 * Same `{{label:FIELD_CODE:groupCode?}}` syntax as
	 * Block_Sentence::get_default().
	 *
	 * @var string
	 */
	private string $template;

	/**
	 * Constructor.
	 *
	 * @param array $data Variant data
	 *   @property array  $when     Non-empty array of `{ field, equals }` rows.
	 *   @property string $template Sentence template (non-empty).
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid data.
	 */
	public function __construct( array $data ) {
		$this->validate( $data );

		$this->when     = $this->normalize_when( $data['when'] );
		$this->template = $data['template'];
	}

	/**
	 * Get the AND-combined conditions.
	 *
	 * @return array<int, array{field:string,equals:mixed}>
	 */
	public function get_when(): array {
		return $this->when;
	}

	/**
	 * Get the variant template.
	 *
	 * @return string
	 */
	public function get_template(): string {
		return $this->template;
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'when'     => $this->when,
			'template' => $this->template,
		);
	}

	/**
	 * Normalize the `when` rows so equals values are passed through verbatim (string|int|float|bool) without any coercion. The frontend compares stringwise; preserving the original PHP type means the catalog payload stays self-describing.
	 *
	 * @param array $when Raw rows.
	 *
	 * @return array<int, array{field:string,equals:mixed}>
	 */
	private function normalize_when( array $when ): array {
		$normalized = array();

		foreach ( $when as $row ) {
			$normalized[] = array(
				'field'  => $row['field'],
				'equals' => $row['equals'],
			);
		}

		return $normalized;
	}

	/**
	 * Validate variant data.
	 *
	 * @param array $data Variant data to validate.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid.
	 */
	private function validate( array $data ): void {
		if ( ! isset( $data['when'] ) || ! is_array( $data['when'] ) || empty( $data['when'] ) ) {
			throw new InvalidArgumentException( 'Block sentence variant `when` must be a non-empty array' );
		}

		foreach ( $data['when'] as $index => $row ) {
			if ( ! is_array( $row ) ) {
				throw new InvalidArgumentException( sprintf( 'Block sentence variant `when[%d]` must be an array', (int) $index ) );
			}

			if ( ! isset( $row['field'] ) || ! is_string( $row['field'] ) || '' === $row['field'] ) {
				throw new InvalidArgumentException( sprintf( 'Block sentence variant `when[%d].field` must be a non-empty string', (int) $index ) );
			}

			if ( ! array_key_exists( 'equals', $row ) ) {
				throw new InvalidArgumentException( sprintf( 'Block sentence variant `when[%d].equals` is required', (int) $index ) );
			}

			if ( ! is_string( $row['equals'] ) && ! is_int( $row['equals'] ) && ! is_float( $row['equals'] ) && ! is_bool( $row['equals'] ) ) {
				throw new InvalidArgumentException( sprintf( 'Block sentence variant `when[%d].equals` must be string, int, float, or bool', (int) $index ) );
			}
		}

		if ( empty( $data['template'] ) || ! is_string( $data['template'] ) ) {
			throw new InvalidArgumentException( 'Block sentence variant `template` must be a non-empty string' );
		}
	}
}
