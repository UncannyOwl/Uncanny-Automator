<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Dependency;

use InvalidArgumentException;
use Uncanny_Automator\Api\Components\Dependency\Value_Objects\Dependency_Cta;
use Uncanny_Automator\Api\Components\Dependency\Value_Objects\Dependency_Tag;

/**
 * Dependency Aggregate.
 *
 * Discriminated union by type:
 * - "license": Relates to Automator license levels or API credits
 * - "installable": Relates to installing/activating a required plugin
 * - "account": Relates to connecting an external app account
 *
 * @since 7.0.0
 */
class Dependency {

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
	 * @var Dependency_Cta
	 */
	private Dependency_Cta $cta;

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
	 * The dependency tags.
	 *
	 * @var Dependency_Tag[]
	 */
	private array $tags;

	/**
	 * Constructor.
	 *
	 * @param Dependency_Config $config Dependency configuration.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid configuration.
	 */
	public function __construct( Dependency_Config $config ) {
		$this->validate( $config );

		$this->id           = $config->get_id();
		$this->name         = $config->get_name();
		$this->description  = $config->get_description();
		$this->is_met       = $config->get_is_met();
		$this->is_disabled  = $config->get_is_disabled();
		$this->dependencies = $config->get_dependencies();
		$this->cta          = $config->get_cta();
		$this->type         = $config->get_type();
		$this->scenario_id  = $config->get_scenario_id();
		$this->icon         = $config->get_icon();
		$this->developer    = $config->get_developer();
		$this->tags         = $config->get_tags();
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
	 * @return Dependency_Cta
	 */
	public function get_cta(): Dependency_Cta {
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
	 * Get tags.
	 *
	 * @return Dependency_Tag[]
	 */
	public function get_tags(): array {
		return $this->tags;
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

		if ( ! empty( $this->tags ) ) {
			$data['tags'] = array_map(
				function ( Dependency_Tag $tag ) {
					return $tag->to_array();
				},
				$this->tags
			);
		}

		return $data;
	}

	/**
	 * Validate dependency configuration.
	 *
	 * @param Dependency_Config $config Configuration to validate.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid.
	 */
	private function validate( Dependency_Config $config ): void {
		// Validate required fields are set.
		if ( empty( $config->get_id() ) ) {
			throw new InvalidArgumentException( 'Dependency id must be a non-empty string' );
		}

		if ( empty( $config->get_name() ) ) {
			throw new InvalidArgumentException( 'Dependency name must be a non-empty string' );
		}

		if ( empty( $config->get_description() ) ) {
			throw new InvalidArgumentException( 'Dependency description must be a non-empty string' );
		}

		if ( empty( $config->get_type() ) ) {
			throw new InvalidArgumentException( 'Dependency type is required' );
		}

		// Validate type enum.
		$valid_types = array( 'license', 'installable', 'account' );
		if ( ! in_array( $config->get_type(), $valid_types, true ) ) {
			throw new InvalidArgumentException( 'Dependency type must be one of: ' . implode( ', ', $valid_types ) );
		}

		// Type-specific validation.
		$this->validate_type_specific( $config );
	}

	/**
	 * Validate type-specific fields.
	 *
	 * @param Dependency_Config $config Configuration to validate.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid.
	 */
	private function validate_type_specific( Dependency_Config $config ): void {
		$type = $config->get_type();

		// All types require scenario_id.
		if ( empty( $config->get_scenario_id() ) ) {
			throw new InvalidArgumentException( "Dependency scenario_id is required for type: {$type}" );
		}

		// If not installable, return early.
		if ( 'installable' !== $type ) {
			return;
		}

		// Validate installable type specific fields.
		if ( empty( $config->get_icon() ) ) {
			throw new InvalidArgumentException( 'Installable dependency requires icon' );
		}

		$developer = $config->get_developer();
		if ( empty( $developer ) || ! is_array( $developer ) ) {
			throw new InvalidArgumentException( 'Installable dependency requires developer object' );
		}

		if ( empty( $developer['name'] ) || ! is_string( $developer['name'] ) ) {
			throw new InvalidArgumentException( 'Installable dependency developer must have name' );
		}

		if ( ! isset( $developer['site'] ) || ! is_string( $developer['site'] ) ) {
			throw new InvalidArgumentException( 'Installable dependency developer must have site (string)' );
		}
	}
}
