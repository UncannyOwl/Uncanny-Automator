<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Dependency;

use Uncanny_Automator\Api\Components\Dependency\Value_Objects\Dependency_Cta;
use Uncanny_Automator\Api\Components\Dependency\Value_Objects\Dependency_Tag;

/**
 * Dependency Configuration.
 *
 * Data transfer object for dependency configuration with fluent interface.
 * Contains no validation logic - serves as a bridge between services
 * and validated domain objects.
 *
 * @since 7.0.0
 */
class Dependency_Config {

	/**
	 * Dependency ID.
	 *
	 * @var string
	 */
	private string $id;

	/**
	 * Dependency name.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * Dependency description.
	 *
	 * @var string
	 */
	private string $description;

	/**
	 * Is met.
	 *
	 * @var bool
	 */
	private bool $is_met;

	/**
	 * Is disabled.
	 *
	 * @var bool
	 */
	private bool $is_disabled = false;

	/**
	 * Dependencies.
	 *
	 * @var array
	 */
	private array $dependencies = array();

	/**
	 * CTA.
	 *
	 * @var Dependency_Cta
	 */
	private Dependency_Cta $cta;

	/**
	 * Type.
	 *
	 * @var string
	 */
	private string $type;

	/**
	 * Scenario ID.
	 *
	 * @var string|null
	 */
	private ?string $scenario_id = null;

	/**
	 * Icon.
	 *
	 * @var string|null
	 */
	private ?string $icon = null;

	/**
	 * Developer.
	 *
	 * @var array|null
	 */
	private ?array $developer = null;

	/**
	 * Tags.
	 *
	 * @var Dependency_Tag[]
	 */
	private array $tags = array();

	/**
	 * Private constructor. Use create() or from_array() to instantiate.
	 *
	 * @return void
	 */
	private function __construct() {
	}

	/**
	 * Create new config instance.
	 *
	 * @return self
	 */
	public static function create(): self {
		return new self();
	}

	/**
	 * Create new config instance from array.
	 *
	 * Provides a simpler way to create a Dependency_Config from an array
	 * instead of using the fluent interface.
	 *
	 * @param array $config Configuration array with keys:
	 *   - type (string, required): Dependency type
	 *   - id (string, required): Unique dependency ID
	 *   - name (string, required): Display name
	 *   - description (string, required): Description text
	 *   - is_met (bool, required): Whether dependency is met
	 *   - cta (Dependency_Cta, required): Call-to-action object
	 *   - scenario_id (string, required): Scenario identifier
	 *   - is_disabled (bool, optional): Whether dependency is disabled
	 *   - dependencies (array, optional): Sub-dependency IDs
	 *   - icon (string, optional): Icon URL
	 *   - developer (array, optional): Developer details
	 *
	 * @return self
	 */
	public static function from_array( array $config ): self {
		$instance = new self();

		// Set required properties
		$instance->type( $config['type'] );
		$instance->id( $config['id'] );
		$instance->name( $config['name'] );
		$instance->description( $config['description'] );
		$instance->is_met( $config['is_met'] );
		$instance->cta( $config['cta'] );
		$instance->scenario_id( $config['scenario_id'] );

		// Set optional properties with defaults
		$instance->is_disabled( $config['is_disabled'] ?? false );
		$instance->dependencies( $config['dependencies'] ?? array() );

		// Set optional properties only if provided
		if ( isset( $config['icon'] ) ) {
			$instance->icon( $config['icon'] );
		}

		if ( isset( $config['developer'] ) ) {
			$instance->developer( $config['developer'] );
		}

		if ( isset( $config['tags'] ) ) {
			$instance->tags( $config['tags'] );
		}

		return $instance;
	}

	/**
	 * Set dependency ID.
	 *
	 * @param string $id Dependency ID.
	 *
	 * @return self
	 */
	public function id( string $id ): self {
		$this->id = $id;
		return $this;
	}

	/**
	 * Set dependency name.
	 *
	 * @param string $name Dependency name.
	 *
	 * @return self
	 */
	public function name( string $name ): self {
		$this->name = $name;
		return $this;
	}

	/**
	 * Set dependency description.
	 *
	 * @param string $description Dependency description.
	 *
	 * @return self
	 */
	public function description( string $description ): self {
		$this->description = $description;
		return $this;
	}

	/**
	 * Set is_met status.
	 *
	 * @param bool $is_met Whether the dependency is met.
	 *
	 * @return self
	 */
	public function is_met( bool $is_met ): self {
		$this->is_met = $is_met;
		return $this;
	}

	/**
	 * Set is_disabled status.
	 *
	 * @param bool $is_disabled Whether the dependency is disabled.
	 *
	 * @return self
	 */
	public function is_disabled( bool $is_disabled ): self {
		$this->is_disabled = $is_disabled;
		return $this;
	}

	/**
	 * Set dependency IDs.
	 *
	 * @param array $dependencies Array of dependency IDs.
	 *
	 * @return self
	 */
	public function dependencies( array $dependencies ): self {
		$this->dependencies = $dependencies;
		return $this;
	}

	/**
	 * Set CTA.
	 *
	 * @param Dependency_Cta $cta Call to action object.
	 *
	 * @return self
	 */
	public function cta( Dependency_Cta $cta ): self {
		$this->cta = $cta;
		return $this;
	}

	/**
	 * Set dependency type.
	 *
	 * @param string $type Dependency type ('license', 'installable', 'account').
	 *
	 * @return self
	 */
	public function type( string $type ): self {
		$this->type = $type;
		return $this;
	}

	/**
	 * Set scenario ID.
	 *
	 * @param string $scenario_id Scenario ID.
	 *
	 * @return self
	 */
	public function scenario_id( string $scenario_id ): self {
		$this->scenario_id = $scenario_id;
		return $this;
	}

	/**
	 * Set icon.
	 *
	 * @param string $icon Icon URL.
	 *
	 * @return self
	 */
	public function icon( string $icon ): self {
		$this->icon = $icon;
		return $this;
	}

	/**
	 * Set developer info.
	 *
	 * @param array $developer Developer info array.
	 *
	 * @return self
	 */
	public function developer( array $developer ): self {
		$this->developer = $developer;
		return $this;
	}

	/**
	 * Set tags.
	 *
	 * Accepts either Dependency_Tag objects or raw arrays.
	 * Raw arrays are automatically converted to Dependency_Tag objects.
	 *
	 * @param array $tags Array of Dependency_Tag objects or raw tag arrays.
	 *
	 * @return self
	 */
	public function tags( array $tags ): self {
		$this->tags = array_map(
			function ( $tag ) {
				return $tag instanceof Dependency_Tag ? $tag : new Dependency_Tag( $tag );
			},
			$tags
		);
		return $this;
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
	 * Get is_met status.
	 *
	 * @return bool
	 */
	public function get_is_met(): bool {
		return $this->is_met;
	}

	/**
	 * Get is_disabled status.
	 *
	 * @return bool
	 */
	public function get_is_disabled(): bool {
		return $this->is_disabled;
	}

	/**
	 * Get dependencies.
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
}
