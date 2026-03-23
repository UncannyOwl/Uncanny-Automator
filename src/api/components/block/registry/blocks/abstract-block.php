<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Block\Registry\Blocks;

use Uncanny_Automator\Api\Components\Block\Block_Config;
use Uncanny_Automator\Api\Components\Block\Value_Objects\Block_Details;
use Uncanny_Automator\Api\Components\Block\Value_Objects\Block_Path;
use Uncanny_Automator\Api\Components\Block\Value_Objects\Block_Taxonomies;
use Uncanny_Automator\Api\Components\Block\Value_Objects\Block_Unsupported_Entity;

/**
 * Abstract Block
 *
 * Base class for block registration definitions.
 * All block definitions must extend this class and implement setup_block().
 *
 * @package Uncanny_Automator\Api\Components\Block\Registry\Blocks
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
	 * Dependency description.
	 *
	 * @var string
	 */
	protected $dependency_description = '';

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
			->dependency_description( $this->get_dependency_description() );
	}
}
