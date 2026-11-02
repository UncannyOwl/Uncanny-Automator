<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Block\Registry\Blocks;

use Uncanny_Automator\App\Recipe_Builder\Block\Block_Config;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Layout;
use Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects\Block_Default_Modifier;
use Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects\Block_Details;
use Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects\Block_Modifier_Type;
use Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects\Block_Path;
use Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects\Block_Sentence;
use Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects\Block_Taxonomies;
use Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects\Block_Unsupported_Entity;

/**
 * Abstract Block
 *
 * Base class for block registration definitions.
 * All block definitions must extend this class and implement setup_block().
 *
 * @package Uncanny_Automator\App\Recipe_Builder\Block\Registry\Blocks
 * @since 7.0.0
 */
abstract class Abstract_Block {

	/**
	 * Block type.
	 *
	 * @var string
	 */
	protected $block_type;

	/**
	 * Block name.
	 *
	 * @var string
	 */
	protected $block_name;

	/**
	 * Supported scopes.
	 *
	 * @var array
	 */
	protected $supported_scopes = array();

	/**
	 * Required tier.
	 *
	 * @var string
	 */
	protected $required_tier;

	/**
	 * Unsupported entities (as arrays).
	 *
	 * @var array
	 */
	protected $unsupported_entities = array();

	/**
	 * Icon.
	 *
	 * @var string
	 */
	protected $icon;

	/**
	 * Primary color.
	 *
	 * @var string
	 */
	protected $primary_color;

	/**
	 * Description.
	 *
	 * @var string
	 */
	protected $description;

	/**
	 * Short description.
	 *
	 * @var string
	 */
	protected $short_description;

	/**
	 * External URL.
	 *
	 * @var string
	 */
	protected $external_url;

	/**
	 * Taxonomy categories.
	 *
	 * @var array
	 */
	protected $taxonomy_categories = array();

	/**
	 * Taxonomy collections.
	 *
	 * @var array
	 */
	protected $taxonomy_collections = array();

	/**
	 * Taxonomy tags.
	 *
	 * @var array
	 */
	protected $taxonomy_tags = array();

	/**
	 * Fields.
	 *
	 * @var array
	 */
	protected $fields = array();

	/**
	 * Paths (as arrays).
	 *
	 * @var array
	 */
	protected $paths = array();

	/**
	 * How the block ENCLOSES the items placed inside it: a container the flow passes through ({@see Block_Layout::FRAME}), a set of branches drawn side by side ({@see Block_Layout::FAN_OUT}), or a body that runs later and is drawn apart from the flow ({@see Block_Layout::DEFERRED}).
	 *
	 * Declared, never inferred. A surface laying the recipe out reads this rather than counting paths or checking whether the block happens to accept modifiers - that inference is how Delay ended up enclosing nothing, from a `modifier_types()` call nobody had ever made.
	 *
	 * @var string
	 */
	protected string $layout = Block_Layout::DEFAULT_LAYOUT;

	/**
	 * Accepted modifier types as value objects.
	 *
	 * Each entry carries the wire `id` (e.g. `'filter_condition'`) and a display `name` contextual to the block (e.g. `"Condition"` inside Filter). Empty array means the block rejects modifiers (e.g. Delay). Each `id` must match a `block_modifier_type` registered by an integration item; the modifier catalog is assembled at REST time from the integrations registry.
	 *
	 * @var Block_Modifier_Type[]
	 */
	protected array $modifier_types = array();

	/**
	 * Skeletons of modifiers the backend auto-attaches when the block is created.
	 *
	 * Each entry conforms to {@link Block_Default_Modifier}. The frontend mirrors this list inside the optimistic temporary block so the placeholder render matches the eventual server response.
	 *
	 * @var Block_Default_Modifier[]
	 */
	protected array $default_modifiers = array();

	/**
	 * Dependency description.
	 *
	 * @var string
	 */
	protected $dependency_description = '';

	/**
	 * Sentence definition (short + dynamic + optional variants).
	 *
	 * Optional. Blocks that don't declare a sentence simply render no sentence row in the flow viewer.
	 *
	 * @var Block_Sentence|null
	 */
	protected ?Block_Sentence $sentence = null;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->setup_block();
	}

	/**
	 * Setup block definition.
	 *
	 * Each concrete block class must implement this method to set up
	 * the block's properties using the setter methods.
	 *
	 * @return void
	 */
	abstract protected function setup_block(): void;

	/**
	 * Set block type.
	 *
	 * @param string $type Block type.
	 *
	 * @return void
	 */
	protected function set_block_type( string $type ): void {
		$this->block_type = $type;
	}

	/**
	 * Get block type.
	 *
	 * @return string
	 */
	public function get_block_type(): string {
		return $this->block_type;
	}

	/**
	 * Set block name.
	 *
	 * @param string $name Block name.
	 *
	 * @return void
	 */
	protected function set_block_name( string $name ): void {
		$this->block_name = $name;
	}

	/**
	 * Get block name.
	 *
	 * @return string
	 */
	public function get_block_name(): string {
		return $this->block_name;
	}

	/**
	 * Set supported scopes.
	 *
	 * @param array $scopes Supported scopes.
	 *
	 * @return void
	 */
	protected function set_supported_scopes( array $scopes ): void {
		$this->supported_scopes = $scopes;
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
	 * Set required tier.
	 *
	 * @param string $tier Required tier.
	 *
	 * @return void
	 */
	protected function set_required_tier( string $tier ): void {
		$this->required_tier = $tier;
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
	 * Set unsupported entities (as arrays).
	 *
	 * @param array $entities Array of entity arrays.
	 *
	 * @return void
	 */
	protected function set_unsupported_entities( array $entities ): void {
		$this->unsupported_entities = $entities;
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
	 * Set icon.
	 *
	 * @param string $icon Icon.
	 *
	 * @return void
	 */
	protected function set_icon( string $icon ): void {
		$this->icon = $icon;
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
	 * Set primary color.
	 *
	 * @param string $color Primary color.
	 *
	 * @return void
	 */
	protected function set_primary_color( string $color ): void {
		$this->primary_color = $color;
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
	 * Set description.
	 *
	 * @param string $description Description.
	 *
	 * @return void
	 */
	protected function set_description( string $description ): void {
		$this->description = $description;
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
	 * Set short description.
	 *
	 * @param string $short_description Short description.
	 *
	 * @return void
	 */
	protected function set_short_description( string $short_description ): void {
		$this->short_description = $short_description;
	}

	/**
	 * Get short description.
	 *
	 * @return string
	 */
	public function get_short_description(): string {
		return $this->short_description;
	}

	/**
	 * Set external URL.
	 *
	 * @param string $external_url External URL.
	 *
	 * @return void
	 */
	protected function set_external_url( string $external_url ): void {
		$this->external_url = $external_url;
	}

	/**
	 * Get external URL.
	 *
	 * @return string
	 */
	public function get_external_url(): string {
		return $this->external_url;
	}

	/**
	 * Set taxonomy categories.
	 *
	 * @param array $categories Taxonomy categories.
	 *
	 * @return void
	 */
	protected function set_taxonomy_categories( array $categories ): void {
		$this->taxonomy_categories = $categories;
	}

	/**
	 * Get taxonomy categories.
	 *
	 * @return array
	 */
	public function get_taxonomy_categories(): array {
		return $this->taxonomy_categories;
	}

	/**
	 * Set taxonomy collections.
	 *
	 * @param array $collections Taxonomy collections.
	 *
	 * @return void
	 */
	protected function set_taxonomy_collections( array $collections ): void {
		$this->taxonomy_collections = $collections;
	}

	/**
	 * Get taxonomy collections.
	 *
	 * @return array
	 */
	public function get_taxonomy_collections(): array {
		return $this->taxonomy_collections;
	}

	/**
	 * Set taxonomy tags.
	 *
	 * @param array $tags Taxonomy tags.
	 *
	 * @return void
	 */
	protected function set_taxonomy_tags( array $tags ): void {
		$this->taxonomy_tags = $tags;
	}

	/**
	 * Get taxonomy tags.
	 *
	 * @return array
	 */
	public function get_taxonomy_tags(): array {
		return $this->taxonomy_tags;
	}

	/**
	 * Set fields.
	 *
	 * @param array $fields Block fields.
	 *
	 * @return void
	 */
	protected function set_fields( array $fields ): void {
		$this->fields = $fields;
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
	 * Set paths (as arrays).
	 *
	 * @param array $paths Array of path arrays.
	 *
	 * @return void
	 */
	protected function set_paths( array $paths ): void {
		$this->paths = $paths;
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
	 * Set how the block encloses the items placed inside it.
	 *
	 * @param string $layout One of {@see Block_Layout}.
	 *
	 * @return void
	 */
	protected function set_layout( string $layout ): void {
		$this->layout = $layout;
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
	 * Set the modifier types the block accepts.
	 *
	 * Each entry can be a {@link Block_Modifier_Type} value object or a data array `['id' => ..., 'name' => ...]`; data arrays are converted via the value object's constructor (which validates the shape).
	 *
	 * @param array $types List of modifier-type data arrays or value objects.
	 *
	 * @return void
	 */
	protected function set_modifier_types( array $types ): void {
		$this->modifier_types = array_map(
			function ( $entry ) {
				return $entry instanceof Block_Modifier_Type
					? $entry
					: new Block_Modifier_Type( $entry );
			},
			$types
		);
	}

	/**
	 * Get the accepted modifier types as value objects.
	 *
	 * @return Block_Modifier_Type[]
	 */
	public function get_modifier_types(): array {
		return $this->modifier_types;
	}

	/**
	 * Set the default modifiers auto-attached when the block is created.
	 *
	 * Each entry can be a {@link Block_Default_Modifier} or a data array; the value object validates the shape.
	 *
	 * @param array $defaults List of skeleton data or value objects.
	 *
	 * @return void
	 */
	protected function set_default_modifiers( array $defaults ): void {
		$this->default_modifiers = array_map(
			function ( $entry ) {
				return $entry instanceof Block_Default_Modifier
					? $entry
					: new Block_Default_Modifier( $entry );
			},
			$defaults
		);
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
	 * Set dependency description.
	 *
	 * @param string $dependency_description Dependency description.
	 *
	 * @return void
	 */
	protected function set_dependency_description( string $dependency_description ): void {
		$this->dependency_description = $dependency_description;
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
	 * Set the block sentence (short + dynamic + optional variants).
	 *
	 * Concrete blocks call this from `setup_block()` with an array; the value object validates the shape. Pass `null` to clear.
	 *
	 * @param array|Block_Sentence|null $sentence Sentence data, value
	 *                                            object, or null.
	 *
	 * @return void
	 */
	protected function set_sentence( $sentence ): void {
		if ( null === $sentence ) {
			$this->sentence = null;
			return;
		}

		$this->sentence = $sentence instanceof Block_Sentence
			? $sentence
			: new Block_Sentence( $sentence );
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
	 * Get block configuration.
	 *
	 * Builds and returns a Block_Config instance from the block's properties.
	 * Creates all value objects internally.
	 *
	 * @return Block_Config
	 */
	public function get_config(): Block_Config {
		$taxonomies = new Block_Taxonomies(
			array(
				'categories'  => $this->get_taxonomy_categories(),
				'collections' => $this->get_taxonomy_collections(),
				'tags'        => $this->get_taxonomy_tags(),
			)
		);

		$details = new Block_Details(
			array(
				'icon'              => $this->get_icon(),
				'primary_color'     => $this->get_primary_color(),
				'description'       => $this->get_description(),
				'short_description' => $this->get_short_description(),
				'taxonomies'        => $taxonomies,
				'external_url'      => $this->get_external_url(),
			)
		);

		$unsupported_entities = array();
		foreach ( $this->get_unsupported_entities() as $entity ) {
			$unsupported_entities[] = new Block_Unsupported_Entity( $entity );
		}

		$paths = array();
		foreach ( $this->get_paths() as $path ) {
			$paths[] = new Block_Path( $path );
		}

		return Block_Config::create()
			->type( $this->get_block_type() )
			->name( $this->get_block_name() )
			->supported_scopes( $this->get_supported_scopes() )
			->required_tier( $this->get_required_tier() )
			->unsupported_entities( $unsupported_entities )
			->details( $details )
			->fields( $this->get_fields() )
			->paths( $paths )
			->layout( $this->get_layout() )
			->modifier_types( $this->get_modifier_types() )
			->default_modifiers( $this->get_default_modifiers() )
			->dependency_description( $this->get_dependency_description() )
			->sentence( $this->get_sentence() );
	}
}
