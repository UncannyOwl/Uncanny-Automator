<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects;

use Uncanny_Automator\App\Recipe_Builder\Field\Services\Utilities\Field_Value_Normalizer;

/**
 * Recipe Throttle Value Object.
 *
 * Validates individual throttle parameters (enabled, duration, unit, scope)
 * but does NOT enforce cross-object business rules. The Recipe domain aggregate
 * enforces type-specific scope requirements:
 *
 * - User recipes: scope required when throttling enabled
 * - Anonymous recipes: scope forbidden even when throttling enabled
 *
 * This value object accepts all valid parameter combinations - the aggregate
 * layer handles recipe-type-specific validation.
 *
 * @since 7.0.0
 */
class Recipe_Throttle {

	/**
	 * @var bool Whether throttling is enabled.
	 */
	private $enabled;

	/**
	 * @var int|null Throttle duration — positive int when enabled, null when disabled.
	 */
	private $duration;

	/**
	 * @var string|null Duration unit (one of VALID_UNITS) when enabled, null when disabled.
	 */
	private $unit;

	/**
	 * @var string|null Throttle scope (one of VALID_SCOPES) when enabled, null when disabled.
	 */
	private $scope;

	/**
	 * Single source of truth for the recipe-throttle duration units.
	 *
	 * The full set the FE offers and released supports. The store coercion and
	 * the MCP tool schema both reference this const — define units here only.
	 * (The runtime Time_Unit_Converter_Trait still maps these to seconds; it
	 * collapses into this source when the throttle enforcement engine migrates.)
	 */
	const VALID_UNITS  = array( 'seconds', 'minutes', 'hours', 'days', 'weeks', 'months', 'years' );
	const VALID_SCOPES = array( 'recipe', 'user' );

	/**
	 * Defaults applied when a value is absent (ctor) or unrecognized (coerce_*).
	 * Defined here so consumers never hardcode their own throttle defaults.
	 */
	const DEFAULT_UNIT = 'hours';

	/**
	 * Throttle the whole recipe rather than each user, unless the author says otherwise.
	 *
	 * @var string
	 */
	const DEFAULT_SCOPE = 'recipe';

	/**
	 * How long the throttle window lasts when the author has not set one.
	 *
	 * @var int
	 */
	const DEFAULT_DURATION = 1;

	/**
	 * Constructor.
	 *
	 * Note: When throttling is disabled, duration/unit/scope defaults are still
	 * stored but should be ignored. Always check is_enabled() before using
	 * throttle configuration values.
	 *
	 * @param mixed  $enabled  Whether throttling is enabled (any boolean-ish value, normalized via Field_Value_Normalizer).
	 * @param int    $duration Throttle duration (positive integer).
	 * @param string $unit     Duration unit (one of Recipe_Throttle::VALID_UNITS).
	 * @param string $scope    Throttle scope (recipe or user).
	 * @throws \InvalidArgumentException If parameters are invalid.
	 */
	public function __construct( $enabled = false, $duration = null, $unit = null, $scope = null ) {
		$this->validate_enabled( $enabled );

		$this->enabled = Field_Value_Normalizer::to_bool( $enabled );

		// Only validate/set other values if throttling is enabled
		// This prevents misleading defaults on disabled throttles
		if ( $this->enabled ) {
			$this->validate_duration( $duration ?? self::DEFAULT_DURATION );
			$this->validate_unit( $unit ?? self::DEFAULT_UNIT );
			$this->validate_scope( $scope ?? self::DEFAULT_SCOPE );

			$this->duration = (int) ( $duration ?? self::DEFAULT_DURATION );
			$this->unit     = $unit ?? self::DEFAULT_UNIT;
			$this->scope    = $scope ?? self::DEFAULT_SCOPE;
		} else {
			// Set null values for disabled throttle to avoid confusion
			$this->duration = null;
			$this->unit     = null;
			$this->scope    = null;
		}
	}

	/**
	 * Create from array.
	 *
	 * @param array $data Throttle data.
	 * @return self
	 * @throws \InvalidArgumentException If data is invalid.
	 */
	public static function from_array( array $data ) {
		return new self(
			$data['enabled'] ?? false,
			$data['duration'] ?? null,
			$data['unit'] ?? null,
			$data['scope'] ?? null
		);
	}

	/**
	 * Get enabled status.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return $this->enabled;
	}

	/**
	 * Get duration.
	 *
	 * Returns null if throttling is disabled to prevent misuse.
	 * Always check is_enabled() before using this value.
	 *
	 * @return int|null Duration or null if disabled.
	 */
	public function get_duration() {
		return $this->duration;
	}

	/**
	 * Get unit.
	 *
	 * Returns null if throttling is disabled to prevent misuse.
	 * Always check is_enabled() before using this value.
	 *
	 * @return string|null Unit or null if disabled.
	 */
	public function get_unit() {
		return $this->unit;
	}

	/**
	 * Get scope.
	 *
	 * Returns null if throttling is disabled to prevent misuse.
	 * Always check is_enabled() before using this value.
	 *
	 * Note: Cross-object scope validation is enforced by Recipe aggregate:
	 * - User recipes require scope when enabled
	 * - Anonymous recipes forbid scope even when enabled
	 *
	 * @return string|null Scope or null if disabled.
	 */
	public function get_scope() {
		return $this->scope;
	}

	/**
	 * Convert to array.
	 *
	 * Returns array representation with null values for disabled throttle
	 * to prevent misinterpretation of throttle settings.
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'enabled'  => $this->enabled,
			'duration' => $this->duration,
			'unit'     => $this->unit,
			'scope'    => $this->scope,
		);
	}

	/**
	 * Coerce a unit to a valid value, defaulting to DEFAULT_UNIT.
	 *
	 * Lenient counterpart to the strict ctor validation — for callers (e.g. the
	 * store) that normalize possibly-stale input rather than reject it.
	 *
	 * @param mixed $unit Candidate unit.
	 * @return string A value in VALID_UNITS.
	 */
	public static function coerce_unit( $unit ): string {
		return in_array( $unit, self::VALID_UNITS, true ) ? (string) $unit : self::DEFAULT_UNIT;
	}

	/**
	 * Coerce a scope to a valid value, defaulting to DEFAULT_SCOPE.
	 *
	 * @param mixed $scope Candidate scope.
	 * @return string A value in VALID_SCOPES.
	 */
	public static function coerce_scope( $scope ): string {
		return in_array( $scope, self::VALID_SCOPES, true ) ? (string) $scope : self::DEFAULT_SCOPE;
	}

	/**
	 * The canonical "no throttle" domain shape (a disabled throttle's array form).
	 *
	 * Single source for the disabled shape so readers (e.g. Recipe_Settings_Service)
	 * don't re-declare the keys.
	 *
	 * @return array{enabled: bool, duration: int|null, unit: string|null, scope: string|null}
	 */
	public static function disabled_shape(): array {
		return ( new self( false ) )->to_array();
	}

	/**
	 * Validate enabled parameter.
	 *
	 * Accepts boolean values or boolean-ish strings/numbers.
	 * Rejects ambiguous values that could lead to unexpected behavior.
	 *
	 * @param mixed $enabled Enabled value.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate_enabled( $enabled ) {
		// Bool semantics live in Field_Value_Normalizer; the VO only enforces
		// that the input is boolean-ish (rejecting 2, -1, 'bananas', arrays...).
		if ( ! Field_Value_Normalizer::is_boolish( $enabled ) ) {
			throw new \InvalidArgumentException(
				'Enabled must be a boolean-ish value (bool, 0/1, true/false, yes/no, on/off, or empty)'
			);
		}
	}

	/**
	 * Validate duration parameter.
	 *
	 * @param mixed $duration Duration value.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate_duration( $duration ) {
		if ( ! is_numeric( $duration ) || $duration < 1 ) {
			throw new \InvalidArgumentException( 'Duration must be a positive integer' );
		}
	}

	/**
	 * Validate unit parameter.
	 *
	 * @param mixed $unit Unit value.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate_unit( $unit ) {
		if ( ! is_string( $unit ) || ! in_array( $unit, self::VALID_UNITS, true ) ) {
			throw new \InvalidArgumentException(
				'Unit must be one of: ' . esc_html( implode( ', ', self::VALID_UNITS ) )
			);
		}
	}

	/**
	 * Validate scope parameter.
	 *
	 * @param mixed $scope Scope value.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate_scope( $scope ) {
		if ( ! is_string( $scope ) || ! in_array( $scope, self::VALID_SCOPES, true ) ) {
			throw new \InvalidArgumentException(
				'Scope must be one of: ' . esc_html( implode( ', ', self::VALID_SCOPES ) )
			);
		}
	}

	/**
	 * Prevent external mutation via reflection or serialization.
	 *
	 * @throws \BadMethodCallException Always.
	 */
	public function __wakeup() {
		throw new \BadMethodCallException( 'Recipe_Throttle cannot be unserialized' );
	}

	/**
	 * Prevent cloning to maintain immutability.
	 *
	 * @throws \BadMethodCallException Always.
	 */
	public function __clone() {
		throw new \BadMethodCallException( 'Recipe_Throttle cannot be cloned' );
	}
}
