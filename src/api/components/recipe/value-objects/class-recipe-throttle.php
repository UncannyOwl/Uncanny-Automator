<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Recipe\Value_Objects;

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

	private $enabled;
	private $duration;
	private $unit;
	private $scope;

	const VALID_UNITS  = array( 'minutes', 'hours', 'days' );
	const VALID_SCOPES = array( 'recipe', 'user' );

	/**
	 * Constructor.
	 *
	 * Note: When throttling is disabled, duration/unit/scope defaults are still
	 * stored but should be ignored. Always check is_enabled() before using
	 * throttle configuration values.
	 *
	 * @param mixed  $enabled  Whether throttling is enabled (bool, 0/1, '0'/'1').
	 * @param int    $duration Throttle duration (positive integer).
	 * @param string $unit     Duration unit (minutes, hours, days).
	 * @param string $scope    Throttle scope (recipe or user).
	 * @throws \InvalidArgumentException If parameters are invalid.
	 */
	public function __construct( $enabled = false, $duration = null, $unit = null, $scope = null ) {
		$this->validate_enabled( $enabled );

		$this->enabled = (bool) $enabled;

		// Only validate/set other values if throttling is enabled
		// This prevents misleading defaults on disabled throttles
		if ( $this->enabled ) {
			$this->validate_duration( $duration ?? 1 );
			$this->validate_unit( $unit ?? 'hours' );
			$this->validate_scope( $scope ?? 'recipe' );

			$this->duration = (int) ( $duration ?? 1 );
			$this->unit     = $unit ?? 'hours';
			$this->scope    = $scope ?? 'recipe';
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
	 * Validate enabled parameter.
	 *
	 * Accepts boolean values or boolean-ish strings/numbers.
	 * Rejects ambiguous values that could lead to unexpected behavior.
	 *
	 * @param mixed $enabled Enabled value.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate_enabled( $enabled ) {
		// Accept explicit booleans
		if ( is_bool( $enabled ) ) {
			return;
		}

		// Accept numeric 0/1
		if ( is_numeric( $enabled ) && in_array( (int) $enabled, array( 0, 1 ), true ) ) {
			return;
		}

		// Accept string '0'/'1'
		if ( is_string( $enabled ) && in_array( $enabled, array( '0', '1' ), true ) ) {
			return;
		}

		// Reject ambiguous values like 'bananas', 2, -1, etc.
		throw new \InvalidArgumentException(
			'Enabled must be boolean, numeric 0/1, or string "0"/"1"'
		);
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
				'Unit must be one of: ' . implode( ', ', self::VALID_UNITS )
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
				'Scope must be one of: ' . implode( ', ', self::VALID_SCOPES )
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
