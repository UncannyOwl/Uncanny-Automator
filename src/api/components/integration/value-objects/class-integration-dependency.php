<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Integration\Value_Objects;

use InvalidArgumentException;

/**
 * Integration Dependency Value Object.
 *
 * Discriminated union by type:
 * - "license": Relates to Automator license levels or API credits
 * - "installable": Relates to installing/activating a required plugin
 * - "account": Relates to connecting an external app account
 *
 * @since 7.0.0
 */
class Integration_Dependency {

	/**
	 * The dependency id.
	 *
	 * @var string
	 */
	private string $id;

	/**
	 * The dependency name.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * The dependency description.
	 *
	 * @var string
	 */
	private string $description;

	/**
	 * The dependency is met.
	 *
	 * @var bool
	 */
	private bool $is_met;

	/**
	 * The dependency is disabled.
	 *
	 * @var bool
	 */
	private bool $is_disabled;

	/**
	 * The dependency dependencies.
	 *
	 * @var array
	 */
	private array $dependencies;

	/**
	 * The dependency CTA.
	 *
	 * @var Integration_Dependency_Cta
	 */
	private Integration_Dependency_Cta $cta;

	/**
	 * The dependency type.
	 *
	 * @var string
	 */
	private string $type;

	/**
	 * The dependency scenario id.
	 *
	 * @var string|null
	 */
	private ?string $scenario_id;

	/**
	 * The dependency icon.
	 *
	 * @var string|null
	 */
	private ?string $icon;

	/**
	 * The dependency developer.
	 *
	 * @var array|null
	 */
	private ?array $developer;

	/**
	 * Constructor.
	 *
	 * @param array $data Dependency data
	 *  @property string $id Dependency id.
	 *  @property string $name Dependency name.
	 *  @property string $description Dependency description.
	 *  @property bool $is_met Dependency is met.
	 *  @property bool $is_disabled Dependency is disabled.
	 *  @property array $dependencies Dependency dependencies.
	 *  @property array $cta Dependency CTA.
	 *  @property string $type Dependency type.
	 *  @property string|null $scenario_id Dependency scenario id.
	 *  @property string|null $icon Dependency icon.
	 *  @property array|null $developer Dependency developer.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid data
	 */
	public function __construct( array $data ) {
		$this->validate( $data );

		// Common properties
		$this->id           = $data['id'];
		$this->name         = $data['name'];
		$this->description  = $data['description'];
		$this->is_met       = $data['is_met'];
		$this->is_disabled  = $data['is_disabled'];
		$this->dependencies = $data['dependencies'] ?? array();
		$this->cta          = new Integration_Dependency_Cta( $data['cta'] );
		$this->type         = $data['type'];

		// Type-specific properties
		$this->scenario_id = $data['scenario_id'] ?? null;
		$this->icon        = $data['icon'] ?? null;
		$this->developer   = $data['developer'] ?? null;
	}

	/**
	 * Get ID.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return $this->id;
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
	 * Get description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return $this->description;
	}

	/**
	 * Check if dependency is met.
	 *
	 * @return bool
	 */
	public function is_met(): bool {
		return $this->is_met;
	}

	/**
	 * Check if dependency is disabled.
	 *
	 * @return bool
	 */
	public function is_disabled(): bool {
		return $this->is_disabled;
	}

	/**
	 * Get dependency IDs.
	 *
	 * @return array
	 */
	public function get_dependencies(): array {
		return $this->dependencies;
	}

	/**
	 * Get CTA.
	 *
	 * @return Integration_Dependency_Cta
	 */
	public function get_cta(): Integration_Dependency_Cta {
		return $this->cta;
	}

	/**
	 * Get type.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return $this->type;
	}

	/**
	 * Get scenario ID.
	 *
	 * @return string|null
	 */
	public function get_scenario_id(): ?string {
		return $this->scenario_id;
	}

	/**
	 * Get icon.
	 *
	 * @return string|null
	 */
	public function get_icon(): ?string {
		return $this->icon;
	}

	/**
	 * Get developer info.
	 *
	 * @return array|null
	 */
	public function get_developer(): ?array {
		return $this->developer;
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		$data = array(
			'id'           => $this->id,
			'name'         => $this->name,
			'description'  => $this->description,
			'is_met'       => $this->is_met,
			'is_disabled'  => $this->is_disabled,
			'dependencies' => $this->dependencies,
			'cta'          => $this->cta->to_array(),
			'type'         => $this->type,
		);

		if ( null !== $this->scenario_id ) {
			$data['scenario_id'] = $this->scenario_id;
		}

		if ( null !== $this->icon ) {
			$data['icon'] = $this->icon;
		}

		if ( null !== $this->developer ) {
			$data['developer'] = $this->developer;
		}

		return $data;
	}

	/**
	 * Validate dependency data.
	 *
	 * @param array $data Dependency data to validate
	 * @throws InvalidArgumentException If invalid
	 */
	private function validate( array $data ): void {
		// Validate common required fields
		$required = array( 'id', 'name', 'description', 'is_met', 'is_disabled', 'cta', 'type' );
		foreach ( $required as $field ) {
			if ( ! isset( $data[ $field ] ) ) {
				throw new InvalidArgumentException( "Dependency missing required field: {$field}" );
			}
		}

		// Validate types
		if ( ! is_string( $data['id'] ) || empty( $data['id'] ) ) {
			throw new InvalidArgumentException( 'Dependency id must be a non-empty string' );
		}

		if ( ! is_string( $data['name'] ) || empty( $data['name'] ) ) {
			throw new InvalidArgumentException( 'Dependency name must be a non-empty string' );
		}

		if ( ! is_string( $data['description'] ) || empty( $data['description'] ) ) {
			throw new InvalidArgumentException( 'Dependency description must be a non-empty string' );
		}

		if ( ! is_bool( $data['is_met'] ) ) {
			throw new InvalidArgumentException( 'Dependency is_met must be a boolean' );
		}

		if ( ! is_bool( $data['is_disabled'] ) ) {
			throw new InvalidArgumentException( 'Dependency is_disabled must be a boolean' );
		}

		if ( isset( $data['dependencies'] ) && ! is_array( $data['dependencies'] ) ) {
			throw new InvalidArgumentException( 'Dependency dependencies must be an array' );
		}

		if ( ! is_array( $data['cta'] ) ) {
			throw new InvalidArgumentException( 'Dependency cta must be an array' );
		}

		// Validate type enum
		$valid_types = array( 'license', 'installable', 'account' );
		if ( ! in_array( $data['type'], $valid_types, true ) ) {
			throw new InvalidArgumentException( 'Dependency type must be one of: ' . implode( ', ', $valid_types ) );
		}

		// Type-specific validation
		$this->validate_type_specific( $data );
	}

	/**
	 * Validate type-specific fields.
	 *
	 * @param array $data Dependency data
	 * @throws InvalidArgumentException If invalid
	 */
	private function validate_type_specific( array $data ): void {
		$type = $data['type'];

		// All types require scenario_id
		if ( ! isset( $data['scenario_id'] ) || ! is_string( $data['scenario_id'] ) || empty( $data['scenario_id'] ) ) {
			throw new InvalidArgumentException( "Dependency scenario_id is required for type: {$type}" );
		}

		// Installable type requires icon and developer
		if ( 'installable' === $type ) {
			if ( ! isset( $data['icon'] ) || ! is_string( $data['icon'] ) || empty( $data['icon'] ) ) {
				throw new InvalidArgumentException( 'Installable dependency requires icon' );
			}

			if ( ! isset( $data['developer'] ) || ! is_array( $data['developer'] ) ) {
				throw new InvalidArgumentException( 'Installable dependency requires developer object' );
			}

			if ( ! isset( $data['developer']['name'] ) || ! is_string( $data['developer']['name'] ) ) {
				throw new InvalidArgumentException( 'Installable dependency developer must have name' );
			}

			if ( ! isset( $data['developer']['site'] ) || ! is_string( $data['developer']['site'] ) ) {
				throw new InvalidArgumentException( 'Installable dependency developer must have site' );
			}
		}
	}
}
