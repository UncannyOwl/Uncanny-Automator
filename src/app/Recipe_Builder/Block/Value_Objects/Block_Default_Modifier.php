<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects;

use InvalidArgumentException;

/**
 * Block Default Modifier Value Object.
 *
 * Skeleton of a recipe modifier that the backend auto-instantiates inside a block's `modifiers` array when the block is created. Declared on each block's catalog row so the frontend can mirror the same shape inside the optimistic temporary block while waiting for the server-side recipe payload.
 *
 * Shape:
 * - `type` — wire kind of the modifier item (`'filter_condition'`, `'loop_filter'`, …).
 * - `integration_code`, `code` — identify the integration item the modifier instantiates.
 * - `fields` — default field values (FieldsOutput shape).
 *
 * No `id`: defaults are skeletons. The backend assigns IDs at instantiation; the frontend assigns a `temp-*` ID for the optimistic copy.
 *
 * @since 7.x
 */
class Block_Default_Modifier {

	/**
	 * The wire kind of the modifier item.
	 *
	 * @var string
	 */
	private string $type;

	/**
	 * The integration code the modifier item belongs to.
	 *
	 * @var string
	 */
	private string $integration_code;

	/**
	 * The integration-item code.
	 *
	 * @var string
	 */
	private string $code;

	/**
	 * Default field values (FieldsOutput-shaped record).
	 *
	 * @var array
	 */
	private array $fields;

	/**
	 * Constructor.
	 *
	 * @param array $data Default-modifier data.
	 *   @property string $type Modifier kind (`'filter_condition'`, `'loop_filter'`, …).
	 *   @property string $integration_code Owning integration code.
	 *   @property string $code Integration-item code.
	 *   @property array  $fields Default field values keyed by canonical option code.
	 *
	 * @throws InvalidArgumentException If invalid data.
	 */
	public function __construct( array $data ) {
		$this->validate( $data );

		$this->type             = $data['type'];
		$this->integration_code = $data['integration_code'];
		$this->code             = $data['code'];
		$this->fields           = $data['fields'] ?? array();
	}

	/**
	 * Convert to array (REST shape).
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'type'             => $this->type,
			'integration_code' => $this->integration_code,
			'code'             => $this->code,
			'fields'           => $this->fields,
		);
	}

	/**
	 * Validate default-modifier data.
	 *
	 * @param array $data Data to validate.
	 *
	 * @throws InvalidArgumentException If invalid.
	 *
	 * @return void
	 */
	private function validate( array $data ): void {
		if ( empty( $data['type'] ) || ! is_string( $data['type'] ) ) {
			throw new InvalidArgumentException( 'Block default modifier `type` must be a non-empty string' );
		}

		if ( empty( $data['integration_code'] ) || ! is_string( $data['integration_code'] ) ) {
			throw new InvalidArgumentException( 'Block default modifier `integration_code` must be a non-empty string' );
		}

		if ( empty( $data['code'] ) || ! is_string( $data['code'] ) ) {
			throw new InvalidArgumentException( 'Block default modifier `code` must be a non-empty string' );
		}

		if ( isset( $data['fields'] ) && ! is_array( $data['fields'] ) ) {
			throw new InvalidArgumentException( 'Block default modifier `fields` must be an array when provided' );
		}
	}
}
