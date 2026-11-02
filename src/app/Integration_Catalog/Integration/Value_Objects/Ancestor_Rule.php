<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Integration_Catalog\Integration\Value_Objects;

use InvalidArgumentException;

/**
 * Ancestor Rule value object.
 *
 * One field-aware ancestor rule on a block modifier: an ancestor block of
 * `entity_code` whose fields satisfy every `when` row. Mirrors the frontend
 * ModelBlockAncestorRuleSchema. Validated in the constructor, then frozen.
 */
class Ancestor_Rule {

	/**
	 * The ancestor entity kind. The literal 'block' in current usage.
	 *
	 * @var string
	 */
	private string $entity_type;

	/**
	 * The block type code the ancestor must have (e.g. 'loop').
	 *
	 * @var string
	 */
	private string $entity_code;

	/**
	 * AND-ed equality rows the ancestor's fields must satisfy. Each row is
	 * `[ 'field' => <option code>, 'equals' => <string> ]`. Empty means a
	 * structural match (ancestor of that type, any field values).
	 *
	 * @var array<int,array{field:string,equals:string}>
	 */
	private array $when;

	/**
	 * Optional human-readable reason, shown disabled-with-reason in the picker.
	 * Empty string when not provided.
	 *
	 * @var string
	 */
	private string $reason;

	/**
	 * Constructor.
	 *
	 * @param array $rule Raw rule data: entity_type, entity_code, optional when[], optional reason.
	 *
	 * @throws InvalidArgumentException When a required field is missing or any field is malformed.
	 */
	public function __construct( array $rule ) {
		$this->validate( $rule );

		$this->entity_type = $rule['entity_type'];
		$this->entity_code = $rule['entity_code'];
		$this->when        = $this->normalize_when( $rule['when'] ?? array() );
		$this->reason      = isset( $rule['reason'] ) ? (string) $rule['reason'] : '';
	}

	/**
	 * Emit the rule in the frontend wire shape. `when` and `reason` are omitted
	 * when empty.
	 *
	 * @return array
	 */
	public function to_array(): array {
		$out = array(
			'entity_type' => $this->entity_type,
			'entity_code' => $this->entity_code,
		);
		if ( ! empty( $this->when ) ) {
			$out['when'] = $this->when;
		}
		if ( '' !== $this->reason ) {
			$out['reason'] = $this->reason;
		}
		return $out;
	}

	/**
	 * Validate the top-level rule shape.
	 *
	 * @param array $rule Raw rule input.
	 *
	 * @return void
	 * @throws InvalidArgumentException When entity_type/entity_code are not non-empty strings,
	 *                                  or when/reason are present but the wrong type.
	 */
	private function validate( array $rule ): void {
		foreach ( array( 'entity_type', 'entity_code' ) as $field ) {
			if ( ! isset( $rule[ $field ] ) || ! is_string( $rule[ $field ] ) || '' === trim( $rule[ $field ] ) ) {
				throw new InvalidArgumentException( sprintf( 'Ancestor rule %s must be a non-empty string', esc_html( $field ) ) );
			}
		}
		if ( isset( $rule['when'] ) && ! is_array( $rule['when'] ) ) {
			throw new InvalidArgumentException( 'Ancestor rule when must be an array' );
		}
		if ( isset( $rule['reason'] ) && ! is_string( $rule['reason'] ) ) {
			throw new InvalidArgumentException( 'Ancestor rule reason must be a string' );
		}
	}

	/**
	 * Normalize and validate the `when` rows, casting each `equals` to string.
	 *
	 * @param array $when Raw when rows.
	 *
	 * @return array<int,array{field:string,equals:string}> Normalized rows.
	 * @throws InvalidArgumentException When a row lacks a non-empty string field, or its equals
	 *                                  is not a string or int.
	 */
	private function normalize_when( array $when ): array {
		$rows = array();
		foreach ( $when as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['field'] ) || ! is_string( $row['field'] ) || '' === trim( $row['field'] ) ) {
				throw new InvalidArgumentException( 'Ancestor rule when row must have a non-empty string field' );
			}
			if ( ! isset( $row['equals'] ) || ( ! is_string( $row['equals'] ) && ! is_int( $row['equals'] ) ) ) {
				throw new InvalidArgumentException( 'Ancestor rule when row equals must be a string or int' );
			}
			$rows[] = array(
				'field'  => $row['field'],
				'equals' => (string) $row['equals'],
			);
		}
		return $rows;
	}
}
