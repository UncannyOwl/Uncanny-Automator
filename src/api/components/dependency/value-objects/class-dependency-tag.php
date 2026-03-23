<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Dependency\Value_Objects;

use InvalidArgumentException;

/**
 * Dependency Tag Value Object.
 *
 * Represents a tag associated with a dependency, containing
 * scenario identification, display label, and icon.
 *
 * @since 7.0
 */
class Dependency_Tag {

	/**
	 * Scenario ID.
	 *
	 * @var string
	 */
	private string $scenario_id;

	/**
	 * Tag label.
	 *
	 * @var string
	 */
	private string $label;

	/**
	 * Tag icon.
	 *
	 * @var string
	 */
	private string $icon;

	/**
	 * Constructor.
	 *
	 * @param array $data Tag data.
	 *  @property string $scenario_id Scenario ID.
	 *  @property string $label Tag label.
	 *  @property string $icon Tag icon.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid data.
	 */
	public function __construct( array $data ) {
		$this->validate( $data );

		$this->scenario_id = $data['scenario_id'];
		$this->label       = $data['label'];
		$this->icon        = $data['icon'];
	}

	/**
	 * Get scenario ID.
	 *
	 * @return string
	 */
	public function get_scenario_id(): string {
		return $this->scenario_id;
	}

	/**
	 * Get label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return $this->label;
	}

	/**
	 * Get icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return $this->icon;
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'scenario_id' => $this->scenario_id,
			'label'       => $this->label,
			'icon'        => $this->icon,
		);
	}

	/**
	 * Validate tag data.
	 *
	 * @param array $data Tag data to validate.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid.
	 */
	private function validate( array $data ): void {
		if ( ! isset( $data['scenario_id'] ) || ! is_string( $data['scenario_id'] ) || empty( $data['scenario_id'] ) ) {
			throw new InvalidArgumentException( 'Tag scenario_id is required and must be a non-empty string' );
		}

		if ( ! isset( $data['label'] ) || ! is_string( $data['label'] ) || empty( $data['label'] ) ) {
			throw new InvalidArgumentException( 'Tag label is required and must be a non-empty string' );
		}

		if ( ! isset( $data['icon'] ) || ! is_string( $data['icon'] ) || empty( $data['icon'] ) ) {
			throw new InvalidArgumentException( 'Tag icon is required and must be a non-empty string' );
		}
	}
}
