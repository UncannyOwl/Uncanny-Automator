<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Block\Value_Objects;

use InvalidArgumentException;
use Uncanny_Automator\Api\Components\Block\Block_Colors;

/**
 * Block Path Value Object.
 *
 * Represents a path within a block that recipe items can follow.
 *
 * @since 7.0.0
 */
class Block_Path {

	/**
	 * Path code.
	 *
	 * @var string
	 */
	private string $path_code;

	/**
	 * Path name.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * Direction.
	 *
	 * @var string
	 */
	private string $direction;

	/**
	 * Primary color.
	 *
	 * @var string
	 */
	private string $primary_color;

	/**
	 * Unsupported entities.
	 *
	 * @var array
	 */
	private array $unsupported_entities;

	/**
	 * Valid directions.
	 *
	 * @var array
	 */
	private array $valid_directions = array( 'left', 'right', 'bottom' );


	/**
	 * Constructor.
	 *
	 * @param array $data Path data
	 *   @property string $path_code Unique path identifier (uppercase).
	 *   @property string $name Display name.
	 *   @property string $direction Visual direction ('left', 'right', 'bottom').
	 *   @property string $primary_color Path color.
	 *   @property array $unsupported_entities Entities that cannot be added to this path.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid data
	 */
	public function __construct( array $data ) {
		$this->validate( $data );

		$this->path_code            = $data['path_code'];
		$this->name                 = $data['name'];
		$this->direction            = $data['direction'];
		$this->primary_color        = $data['primary_color'];
		$this->unsupported_entities = $this->normalize_unsupported_entities( $data['unsupported_entities'] ?? array() );
	}

	/**
	 * Get path code.
	 *
	 * @return string
	 */
	public function get_path_code(): string {
		return $this->path_code;
	}

	/**
	 * Get name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Get direction.
	 *
	 * @return string
	 */
	public function get_direction(): string {
		return $this->direction;
	}

	/**
	 * Get primary color.
	 *
	 * @return string
	 */
	public function get_primary_color(): string {
		return $this->primary_color;
	}

	/**
	 * Get unsupported entities.
	 *
	 * @return array Array of Block_Unsupported_Entity objects
	 */
	public function get_unsupported_entities(): array {
		return $this->unsupported_entities;
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		$data = array(
			'path_code'            => $this->path_code,
			'name'                 => $this->name,
			'direction'            => $this->direction,
			'primary_color'        => $this->primary_color,
			'unsupported_entities' => array_map(
				function ( $entity ) {
					return $entity->to_array();
				},
				$this->unsupported_entities
			),
		);

		return $data;
	}

	/**
	 * Normalize unsupported entities to value objects.
	 *
	 * @param array $entities Array of entity data or Block_Unsupported_Entity objects
	 *
	 * @return array Array of Block_Unsupported_Entity objects
	 */
	private function normalize_unsupported_entities( array $entities ): array {
		$normalized = array();

		foreach ( $entities as $entity ) {
			$normalized[] = $entity instanceof Block_Unsupported_Entity
				? $entity
				: new Block_Unsupported_Entity( $entity );
		}

		return $normalized;
	}

	/**
	 * Validate path data.
	 *
	 * @param array $data Path data to validate
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid
	 */
	private function validate( array $data ): void {
		if ( empty( $data['path_code'] ) || ! is_string( $data['path_code'] ) ) {
			throw new InvalidArgumentException( 'Path code must be a non-empty string' );
		}

		if ( strtoupper( $data['path_code'] ) !== $data['path_code'] ) {
			throw new InvalidArgumentException( 'Path code must be uppercase' );
		}

		if ( empty( $data['name'] ) || ! is_string( $data['name'] ) ) {
			throw new InvalidArgumentException( 'Path name must be a non-empty string' );
		}

		if ( empty( $data['direction'] ) || ! in_array( $data['direction'], $this->valid_directions, true ) ) {
			throw new InvalidArgumentException( 'Direction must be one of: ' . implode( ', ', $this->valid_directions ) );
		}

		if ( empty( $data['primary_color'] ) || ! Block_Colors::is_valid( $data['primary_color'] ) ) {
			throw new InvalidArgumentException( 'Primary color must be one of: ' . implode( ', ', Block_Colors::get_all() ) );
		}
	}
}
