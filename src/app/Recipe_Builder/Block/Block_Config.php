<?php

namespace Uncanny_Automator\App\Recipe_Builder\Block;

use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Layout;
use Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects\Block_Default_Modifier;
use Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects\Block_Details;
use Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects\Block_Modifier_Type;
use Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects\Block_Path;
use Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects\Block_Sentence;
use Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects\Block_Unsupported_Entity;

/**
 * Block Configuration.
 *
 * Data transfer object for block configuration with fluent interface.
 * Contains no validation logic - serves as a bridge between services
 * and validated domain objects.
 *
 * @since 7.0.0
 */
class Block_Config {

	/**
	 * Block type.
	 *
	 * @var string
	 */
	private string $type;

	/**
	 * Block name.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * Supported scopes.
	 *
	 * @var array
	 */
	private array $supported_scopes;

	/**
	 * Required tier.
	 *
	 * @var string
	 */
	private string $required_tier;

	/**
	 * Unsupported entities.
	 *
	 * @var array
	 */
	private array $unsupported_entities = array();

	/**
	 * Block details.
	 *
	 * @var Block_Details
	 */
	private Block_Details $details;

	/**
	 * Fields.
	 *
	 * @var array
	 */
	private array $fields = array();

	/**
	 * Paths.
	 *
	 * @var array
	 */
	private array $paths = array();

	/**
	 * How the block encloses the items placed inside it.
	 *
	 * One of {@see Block_Layout}. Declared by the block definition, never inferred from the shape of the rest of this config.
	 *
	 * @var string
	 */
	private string $layout = Block_Layout::DEFAULT_LAYOUT;

	/**
	 * Accepted modifier types as value objects. Each entry carries the wire `id` (`block_modifier_type` code) and a contextual display `name`.
	 *
	 * @var Block_Modifier_Type[]
	 */
	private array $modifier_types = array();

	/**
	 * Default modifiers auto-attached when the block is created.
	 *
	 * @var Block_Default_Modifier[]
	 */
	private array $default_modifiers = array();

	/**
	 * Dependency description.
	 *
	 * @var string
	 */
	private string $dependency_description = '';

	/**
	 * Block sentence (optional).
	 *
	 * @var Block_Sentence|null
	 */
	private ?Block_Sentence $sentence = null;

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
	 * Provides a simpler way to create a Block_Config from an array
	 * instead of using the fluent interface.
	 *
	 * @param array $config Configuration array with keys:
	 *   - type (string, required): Unique block identifier
	 *   - name (string, required): Display name
	 *   - supported_scopes (array, required): Supported scopes
	 *   - required_tier (string, required): Required tier
	 *   - details (Block_Details|array, required): Block details
	 *   - unsupported_entities (array, optional): Unsupported entities
	 *   - fields (array, optional): Block fields
	 *   - paths (array, optional): Block paths
	 *   - layout (string, optional): One of {@see Block_Layout}; defaults to {@see Block_Layout::DEFAULT_LAYOUT}
	 *   - modifier_types (array, optional): Accepted modifier-type entries as `['id' => ..., 'name' => ...]` arrays or {@link Block_Modifier_Type} value objects
	 *   - default_modifiers (array, optional): Default-modifier skeletons
	 *   - dependency_description (string, optional): Dependency description
	 *
	 * @return self
	 */
	public static function from_array( array $config ): self {
		$instance = new self();

		$instance->type( $config['type'] );
		$instance->name( $config['name'] );
		$instance->supported_scopes( $config['supported_scopes'] );
		$instance->required_tier( $config['required_tier'] );
		$instance->details( $config['details'] );

		$instance->unsupported_entities( $config['unsupported_entities'] ?? array() );
		$instance->fields( $config['fields'] ?? array() );
		$instance->paths( $config['paths'] ?? array() );
		$instance->layout( $config['layout'] ?? Block_Layout::DEFAULT_LAYOUT );
		$instance->modifier_types( $config['modifier_types'] ?? array() );
		$instance->default_modifiers( $config['default_modifiers'] ?? array() );
		$instance->dependency_description( $config['dependency_description'] ?? '' );

		if ( array_key_exists( 'sentence', $config ) ) {
			$instance->sentence( $config['sentence'] );
		}

		return $instance;
	}

	/**
	 * Set block type.
	 *
	 * @param string $type Block type.
	 *
	 * @return self
	 */
	public function type( string $type ): self {
		$this->type = $type;
		return $this;
	}

	/**
	 * Set block name.
	 *
	 * @param string $name Block name.
	 *
	 * @return self
	 */
	public function name( string $name ): self {
		$this->name = $name;
		return $this;
	}

	/**
	 * Set supported scopes.
	 *
	 * @param array $supported_scopes Supported scopes.
	 *
	 * @return self
	 */
	public function supported_scopes( array $supported_scopes ): self {
		$this->supported_scopes = $supported_scopes;
		return $this;
	}

	/**
	 * Set required tier.
	 *
	 * @param string $required_tier Required tier.
	 *
	 * @return self
	 */
	public function required_tier( string $required_tier ): self {
		$this->required_tier = $required_tier;
		return $this;
	}

	/**
	 * Set unsupported entities.
	 *
	 * @param array $unsupported_entities Array of Block_Unsupported_Entity objects or arrays.
	 *
	 * @return self
	 */
	public function unsupported_entities( array $unsupported_entities ): self {
		$this->unsupported_entities = $this->normalize_unsupported_entities( $unsupported_entities );
		return $this;
	}

	/**
	 * Set block details.
	 *
	 * @param Block_Details|array $details Block details object or array.
	 *
	 * @return self
	 */
	public function details( $details ): self {
		$this->details = $details instanceof Block_Details
			? $details
			: new Block_Details( $details );

		return $this;
	}

	/**
	 * Set fields.
	 *
	 * @param array $fields Block fields.
	 *
	 * @return self
	 */
	public function fields( array $fields ): self {
		$this->fields = $fields;
		return $this;
	}

	/**
	 * Set paths.
	 *
	 * @param array $paths Array of Block_Path objects or arrays.
	 *
	 * @return self
	 */
	public function paths( array $paths ): self {
		$this->paths = $this->normalize_paths( $paths );
		return $this;
	}

	/**
	 * Set how the block encloses the items placed inside it.
	 *
	 * @param string $layout One of {@see Block_Layout}.
	 *
	 * @return self
	 */
	public function layout( string $layout ): self {
		$this->layout = $layout;
		return $this;
	}

	/**
	 * Set the modifier types the block accepts.
	 *
	 * Each entry can be a {@link Block_Modifier_Type} value object or a data array `['id' => ..., 'name' => ...]`; data arrays are converted via the value object's constructor (which validates the shape).
	 *
	 * @param array $types List of modifier-type data arrays or value objects.
	 *
	 * @return self
	 */
	public function modifier_types( array $types ): self {
		$this->modifier_types = array_map(
			function ( $entry ) {
				return $entry instanceof Block_Modifier_Type
					? $entry
					: new Block_Modifier_Type( $entry );
			},
			$types
		);
		return $this;
	}

	/**
	 * Set the default modifiers auto-attached on block creation.
	 *
	 * @param array $defaults List of `Block_Default_Modifier` instances or data arrays.
	 *
	 * @return self
	 */
	public function default_modifiers( array $defaults ): self {
		$this->default_modifiers = array_map(
			function ( $entry ) {
				return $entry instanceof Block_Default_Modifier
					? $entry
					: new Block_Default_Modifier( $entry );
			},
			$defaults
		);

		return $this;
	}

	/**
	 * Set dependency description.
	 *
	 * @param string $dependency_description Dependency description.
	 *
	 * @return self
	 */
	public function dependency_description( string $dependency_description ): self {
		$this->dependency_description = $dependency_description;
		return $this;
	}

	/**
	 * Set the block sentence (or clear it with `null`).
	 *
	 * @param Block_Sentence|array|null $sentence Sentence value object,
	 *                                            data array, or null.
	 *
	 * @return self
	 */
	public function sentence( $sentence ): self {
		if ( null === $sentence ) {
			$this->sentence = null;
			return $this;
		}

		$this->sentence = $sentence instanceof Block_Sentence
			? $sentence
			: new Block_Sentence( $sentence );

		return $this;
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
	 * Get name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Get supported scopes.
	 *
	 * @return array
	 */
	public function get_supported_scopes(): array {
		return $this->supported_scopes;
	}

	/**
	 * Get required tier.
	 *
	 * @return string
	 */
	public function get_required_tier(): string {
		return $this->required_tier;
	}

	/**
	 * Get unsupported entities.
	 *
	 * @return array
	 */
	public function get_unsupported_entities(): array {
		return $this->unsupported_entities;
	}

	/**
	 * Get details.
	 *
	 * @return Block_Details
	 */
	public function get_details(): Block_Details {
		return $this->details;
	}

	/**
	 * Get fields.
	 *
	 * @return array
	 */
	public function get_fields(): array {
		return $this->fields;
	}

	/**
	 * Get paths.
	 *
	 * @return array
	 */
	public function get_paths(): array {
		return $this->paths;
	}

	/**
	 * Get how the block encloses the items placed inside it.
	 *
	 * @return string One of {@see Block_Layout}.
	 */
	public function get_layout(): string {
		return $this->layout;
	}

	/**
	 * Get the accepted modifier types.
	 *
	 * @return string[]
	 */
	public function get_modifier_types(): array {
		return $this->modifier_types;
	}

	/**
	 * Get the default modifiers as value objects.
	 *
	 * @return Block_Default_Modifier[]
	 */
	public function get_default_modifiers(): array {
		return $this->default_modifiers;
	}

	/**
	 * Get dependency description.
	 *
	 * @return string
	 */
	public function get_dependency_description(): string {
		return $this->dependency_description;
	}

	/**
	 * Get the block sentence.
	 *
	 * @return Block_Sentence|null
	 */
	public function get_sentence(): ?Block_Sentence {
		return $this->sentence;
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
	 * Normalize paths to value objects.
	 *
	 * @param array $paths Array of path data or Block_Path objects
	 *
	 * @return array Array of Block_Path objects
	 */
	private function normalize_paths( array $paths ): array {
		$normalized = array();
		foreach ( $paths as $path ) {
			$normalized[] = $path instanceof Block_Path
				? $path
				: new Block_Path( $path );
		}

		return $normalized;
	}
}
