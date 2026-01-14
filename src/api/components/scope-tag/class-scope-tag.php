<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Scope_Tag;

use InvalidArgumentException;

/**
 * Scope Tag Aggregate.
 *
 * Pure domain object representing a scope tag.
 * Contains zero WordPress dependencies - pure PHP business logic only.
 *
 * Represents a single scope tag ( e.g. License, Availability, Dependency ).
 *
 * @since 7.0.0
 */
class Scope_Tag {

	/**
	 * The tag type.
	 *
	 * @var string
	 */
	private string $type;

	/**
	 * The tag scenario ID.
	 *
	 * @var string
	 */
	private string $scenario_id;

	/**
	 * The tag label.
	 *
	 * @var string
	 */
	private string $label;

	/**
	 * The tag icon.
	 *
	 * @var string
	 */
	private string $icon;

	/**
	 * The tag color.
	 *
	 * @var string
	 */
	private string $color;

	/**
	 * The tag helper text.
	 *
	 * @var string
	 */
	private string $helper;

	/**
	 * Scenario map for scope tag types and scenario IDs.
	 *
	 * @var array<string, array<string>>
	 */
	private array $scenario_map = array(
		'license'      => array(
			'license-pro-basic',
			'license-pro-plus',
			'license-pro-elite',
		),
		'dependency'   => array(
			'dependency-not-connected',
			'dependency-not-installed',
		),
		'availability' => array(
			'availability-locked',
			'availability-unsupported',
		),
		'third-party'  => array(
			'third-party',
		),
	);

	/**
	 * Allowed colors for scope tag colors.
	 *
	 * @var array<string>
	 */
	private array $allowed_colors = array(
		'neutral',
		'info',
		'warning',
		'error',
		'success',
	);

	/**
	 * Constructor.
	 *
	 * @param Scope_Tag_Config $config Scope tag configuration object.
	 *  @property string $type Tag type.
	 *  @property string $scenario_id Tag scenario ID.
	 *  @property string $label Tag label.
	 *  @property string $icon Tag icon.
	 *  @property string $color Tag color.
	 *  @property string $helper Tag helper text.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid configuration.
	 */
	public function __construct( Scope_Tag_Config $config ) {
		$this->validate( $config );

		$this->type        = $config->get_type();
		$this->scenario_id = $config->get_scenario_id();
		$this->label       = $config->get_label();
		$this->icon        = $config->get_icon() ?? '';
		$this->color       = $config->get_color() ?? '';
		$this->helper      = $config->get_helper() ?? '';
	}

	/**
	 * Get tag type.
	 *
	 * @return string Tag type.
	 */
	public function get_type(): string {
		return $this->type;
	}

	/**
	 * Get tag scenario ID.
	 *
	 * @return string Tag scenario ID.
	 */
	public function get_scenario_id(): string {
		return $this->scenario_id;
	}

	/**
	 * Get tag label.
	 *
	 * @return string Tag label.
	 */
	public function get_label(): string {
		return $this->label;
	}

	/**
	 * Get tag icon.
	 *
	 * @return string Tag icon.
	 */
	public function get_icon(): string {
		return $this->icon;
	}

	/**
	 * Get tag color.
	 *
	 * @return string Tag color.
	 */
	public function get_color(): string {
		return $this->color;
	}

	/**
	 * Get tag helper text.
	 *
	 * @return string Tag helper text.
	 */
	public function get_helper(): string {
		return $this->helper;
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		$data = array(
			'type'        => $this->type,
			'scenario_id' => $this->scenario_id,
			'label'       => $this->label,
		);

		if ( ! empty( $this->icon ) ) {
			$data['icon'] = $this->icon;
		}

		if ( ! empty( $this->color ) ) {
			$data['color'] = $this->color;
		}

		if ( ! empty( $this->helper ) ) {
			$data['helper'] = $this->helper;
		}

		return $data;
	}

	/**
	 * Validate tag data.
	 *
	 * @param Scope_Tag_Config $config Tag config to validate.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid.
	 */
	private function validate( Scope_Tag_Config $config ): void {
		// Validate required fields.
		$this->validate_type( $config );
		$this->validate_scenario_id( $config );
		$this->validate_label( $config );

		// Validate optional fields.
		$this->validate_icon( $config );
		$this->validate_color( $config );
		$this->validate_helper( $config );
	}

	/**
	 * Validate type field.
	 *
	 * @param Scope_Tag_Config $config Tag config.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid.
	 */
	private function validate_type( Scope_Tag_Config $config ): void {
		$type = $config->get_type();
		if ( empty( $type ) ) {
			throw new InvalidArgumentException( "Scope tag must have a 'type' field" );
		}

		if ( ! isset( $this->scenario_map[ $type ] ) ) {
			throw new InvalidArgumentException(
				"Scope tag has invalid type '{$type}'. Allowed: " . implode( ', ', array_keys( $this->scenario_map ) )
			);
		}
	}

	/**
	 * Validate scenario_id field based on type (discriminated union).
	 *
	 * @param Scope_Tag_Config $config Tag config.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid.
	 */
	private function validate_scenario_id( Scope_Tag_Config $config ): void {
		$scenario_id = $config->get_scenario_id();
		if ( empty( $scenario_id ) ) {
			throw new InvalidArgumentException( "Scope tag must have a 'scenario_id' field" );
		}

		$type              = $config->get_type();
		$allowed_scenarios = $this->scenario_map[ $type ];
		if ( ! in_array( $scenario_id, $allowed_scenarios, true ) ) {
			throw new InvalidArgumentException(
				"Scope tag has invalid scenario_id '{$scenario_id}' for type '{$type}'. Allowed: " . implode( ', ', $allowed_scenarios )
			);
		}
	}

	/**
	 * Validate label field.
	 *
	 * @param Scope_Tag_Config $config Tag config.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid.
	 */
	private function validate_label( Scope_Tag_Config $config ): void {
		$label = $config->get_label();
		if ( empty( $label ) ) {
			throw new InvalidArgumentException( "Scope tag must have a 'label' field" );
		}
	}

	/**
	 * Validate icon field (optional).
	 *
	 * Note: Type validation is enforced by Scope_Tag_Config::get_icon() return type.
	 *
	 * @param Scope_Tag_Config $config Tag config.
	 *
	 * @return void
	 */
	private function validate_icon( Scope_Tag_Config $config ): void {
		// Icon is optional - no validation needed beyond type enforcement by config getter.
		// Keeping method for consistency and potential future validation rules.
	}

	/**
	 * Validate color field (optional).
	 *
	 * @param Scope_Tag_Config $config Tag config.
	 *
	 * @return void
	 * @throws InvalidArgumentException If invalid.
	 */
	private function validate_color( Scope_Tag_Config $config ): void {
		$color = $config->get_color();
		if ( null === $color ) {
			return;
		}

		if ( ! in_array( $color, $this->allowed_colors, true ) ) {
			throw new InvalidArgumentException(
				"Scope tag has invalid color '{$color}'. Allowed: " . implode( ', ', $this->allowed_colors )
			);
		}
	}

	/**
	 * Validate helper field (optional).
	 *
	 * Note: Type validation is enforced by Scope_Tag_Config::get_helper() return type.
	 *
	 * @param Scope_Tag_Config $config Tag config.
	 *
	 * @return void
	 */
	private function validate_helper( Scope_Tag_Config $config ): void {
		// Helper is optional - no validation needed beyond type enforcement by config getter.
		// Keeping method for consistency and potential future validation rules.
	}
}
