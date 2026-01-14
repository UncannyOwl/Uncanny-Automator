<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Block\Value_Objects;

use InvalidArgumentException;

/**
 * Block Unsupported Entity Value Object.
 *
 * Represents an entity that cannot be added as a child of a block.
 *
 * @since 7.0.0
 */
class Block_Unsupported_Entity {

	/**
	 * Entity type.
	 *
	 * @var string
	 */
	private string $entity_type;

	/**
	 * Entity code.
	 *
	 * @var string
	 */
	private string $entity_code;

	/**
	 * Reason for restriction.
	 *
	 * @var string
	 */
	private string $reason;

	/**
	 * Valid entity types.
	 *
	 * @var array
	 */
	private array $valid_entity_types = array(
		'integration',
		'item',
		'block',
	);

	/**
	 * Constructor.
	 *
	 * @param array $data Entity data
	 *   @property string $entity_type Entity type ('integration', 'item', 'block').
	 *   @property string $entity_code Entity code/ID.
	 *   @property string $reason Human-readable reason for restriction.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid data
	 */
	public function __construct( array $data ) {
		$this->validate( $data );

		$this->entity_type = $data['entity_type'];
		$this->entity_code = $data['entity_code'];
		$this->reason      = $data['reason'];
	}

	/**
	 * Get entity type.
	 *
	 * @return string
	 */
	public function get_entity_type(): string {
		return $this->entity_type;
	}

	/**
	 * Get entity code.
	 *
	 * @return string
	 */
	public function get_entity_code(): string {
		return $this->entity_code;
	}

	/**
	 * Get reason.
	 *
	 * @return string
	 */
	public function get_reason(): string {
		return $this->reason;
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'entity_type' => $this->entity_type,
			'entity_code' => $this->entity_code,
			'reason'      => $this->reason,
		);
	}

	/**
	 * Validate entity data.
	 *
	 * @param array $data Entity data to validate
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid
	 */
	private function validate( array $data ): void {
		if ( ! isset( $data['entity_type'] ) || ! in_array( $data['entity_type'], $this->valid_entity_types, true ) ) {
			throw new InvalidArgumentException( 'Entity type must be one of: ' . implode( ', ', $this->valid_entity_types ) );
		}

		if ( empty( $data['entity_code'] ) || ! is_string( $data['entity_code'] ) ) {
			throw new InvalidArgumentException( 'Entity code must be a non-empty string' );
		}

		if ( empty( $data['reason'] ) || ! is_string( $data['reason'] ) ) {
			throw new InvalidArgumentException( 'Reason must be a non-empty string' );
		}
	}
}
