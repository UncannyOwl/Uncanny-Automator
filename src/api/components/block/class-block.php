<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Block;

use InvalidArgumentException;
use Uncanny_Automator\Api\Components\Block\Value_Objects\Block_Details;
use Uncanny_Automator\Api\Services\Dependency\Dependency_Evaluatable;
use Uncanny_Automator\Api\Services\Scope_Tag\Scope_Tag_Evaluatable;
use Uncanny_Automator\Api\Components\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\Api\Services\Plan\Plan_Levels_Helper;
use Uncanny_Automator\Api\Components\Block\Enums\Block_Type;

/**
 * Block Aggregate.
 *
 * Represents a recipe block that can contain other recipe items.
 *
 * @since 7.0.0
 */
class Block implements Dependency_Evaluatable, Scope_Tag_Evaluatable {

	/**
	 * The block type.
	 *
	 * @var string
	 */
	private string $type;

	/**
	 * The block name.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * The supported scopes.
	 *
	 * @var array
	 */
	private array $supported_scopes;

	/**
	 * The required tier.
	 *
	 * @var string
	 */
	private string $required_tier;

	/**
	 * The unsupported entities.
	 *
	 * @var array
	 */
	private array $unsupported_entities;

	/**
	 * The block details.
	 *
	 * @var Block_Details
	 */
	private Block_Details $details;

	/**
	 * The fields.
	 *
	 * @var array
	 */
	private array $fields;

	/**
	 * The paths.
	 *
	 * @var array
	 */
	private array $paths;

	/**
	 * The dependency description.
	 *
	 * @var string
	 */
	private string $dependency_description;

	/**
	 * Constructor.
	 *
	 * @param Block_Config $config Block configuration.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid configuration.
	 */
	public function __construct( Block_Config $config ) {
		$this->validate( $config );

		$this->type                   = $config->get_type();
		$this->name                   = $config->get_name();
		$this->supported_scopes       = $config->get_supported_scopes();
		$this->required_tier          = $config->get_required_tier();
		$this->unsupported_entities   = $config->get_unsupported_entities();
		$this->details                = $config->get_details();
		$this->fields                 = $config->get_fields();
		$this->paths                  = $config->get_paths();
		$this->dependency_description = $config->get_dependency_description();
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
	 * Get dependency description.
	 *
	 * @return string
	 */
	public function get_dependency_description(): string {
		return $this->dependency_description;
	}

	/**
	 * Get entity code ( type passed to interface implementation).
	 *
	 * Implements Dependency_Evaluatable and Scope_Tag_Evaluatable.
	 *
	 * @return string Block code.
	 */
	public function get_entity_code(): string {
		return $this->get_type();
	}

	/**
	 * Get entity name (interface implementation).
	 *
	 * Implements Dependency_Evaluatable and Scope_Tag_Evaluatable.
	 *
	 * @return string Block name.
	 */
	public function get_entity_name(): string {
		return $this->name;
	}

	/**
	 * Get entity required tier (interface implementation).
	 *
	 * Implements Dependency_Evaluatable and Scope_Tag_Evaluatable.
	 *
	 * @return string Required tier.
	 */
	public function get_entity_required_tier(): string {
		return $this->required_tier;
	}

	/**
	 * Get entity type (interface implementation).
	 *
	 * Implements Dependency_Evaluatable and Scope_Tag_Evaluatable.
	 *
	 * @return string Entity type ('block').
	 */
	public function get_entity_type(): string {
		return 'block';
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		$data = array(
			'type'                 => $this->type,
			'name'                 => $this->name,
			'supported_scopes'     => $this->supported_scopes,
			'required_tier'        => $this->required_tier,
			'unsupported_entities' => array_map(
				function ( $entity ) {
					return $entity->to_array();
				},
				$this->unsupported_entities
			),
			'details'              => $this->details->to_array(),
			'fields'               => $this->fields,
			'paths'                => array_map(
				function ( $path ) {
					return $path->to_array();
				},
				$this->paths
			),
		);

		return $data;
	}

	/**
	 * Validate block configuration.
	 *
	 * @param Block_Config $config Configuration to validate.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid.
	 */
	private function validate( Block_Config $config ): void {
		if ( empty( $config->get_type() ) ) {
			throw new InvalidArgumentException( 'Block type must be a non-empty string' );
		}

		if ( ! Block_Type::is_valid( $config->get_type() ) ) {
			throw new InvalidArgumentException( 'Block type must be one of: ' . implode( ', ', Block_Type::get_all() ) );
		}

		if ( empty( $config->get_name() ) ) {
			throw new InvalidArgumentException( 'Block name must be a non-empty string' );
		}

		$supported_scopes = $config->get_supported_scopes();
		if ( empty( $supported_scopes ) || ! is_array( $supported_scopes ) ) {
			throw new InvalidArgumentException( 'Block supported_scopes must be a non-empty array' );
		}

		foreach ( $supported_scopes as $scope ) {
			if ( ! Integration_Item_Types::is_valid( $scope ) ) {
				throw new InvalidArgumentException( 'Block supported_scopes must contain only valid scopes: ' . implode( ', ', Integration_Item_Types::get_all() ) );
			}
		}

		$required_tier = $config->get_required_tier();
		if ( empty( $required_tier ) || ! Plan_Levels_Helper::is_valid( $required_tier ) ) {
			throw new InvalidArgumentException( 'Block required_tier must be one of: ' . implode( ', ', Plan_Levels_Helper::get_all() ) );
		}

		if ( empty( $config->get_unsupported_entities() ) ) {
			$config->unsupported_entities( array() );
		}

		if ( empty( $config->get_paths() ) ) {
			$config->paths( array() );
		}

		$this->validate_path_codes( $config->get_paths() );
	}

	/**
	 * Validate path codes are unique and uppercase.
	 *
	 * @param array $paths Array of Block_Path objects
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid
	 */
	private function validate_path_codes( array $paths ): void {
		$path_codes = array();

		foreach ( $paths as $path ) {
			$path_code = $path->get_path_code();

			if ( isset( $path_codes[ $path_code ] ) ) {
				throw new InvalidArgumentException( sprintf( 'Duplicate path code found: %s. Path codes must be unique within a block.', $path_code ) );
			}

			$path_codes[ $path_code ] = true;
		}
	}
}
