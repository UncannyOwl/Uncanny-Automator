<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Infrastructure\Plan;

use Uncanny_Automator\Api\Components\Plan\Domain\Plan;
use Uncanny_Automator\Api\Components\Plan\Domain\Plan_Levels;
use Uncanny_Automator\Api\Services\Plan\Plan_Levels_Helper;

/**
 * Concrete implementation of the Plan interface.
 *
 * This class represents the "Adapter" in a Hexagonal Architecture, containing
 * all the business logic for plan hierarchy, validation, and feature access.
 *
 * @package Uncanny_Automator\Api\Infrastructure\Plan
 * @since 7.0.0
 */
class Plan_Implementation implements Plan {

	private string $id;

	const HIERARCHY = array(
		Plan_Levels::LITE      => 0,
		Plan_Levels::PRO_BASIC => 1,
		Plan_Levels::PRO_PLUS  => 2,
		Plan_Levels::PRO_ELITE => 3,
	);

	const NAMES = array(
		Plan_Levels::LITE      => 'Lite',
		Plan_Levels::PRO_BASIC => 'Pro',
		Plan_Levels::PRO_PLUS  => 'Pro Plus',
		Plan_Levels::PRO_ELITE => 'Elite',
	);

	const DESCRIPTIONS = array(
		Plan_Levels::LITE      => 'Free plan with limited features.',
		Plan_Levels::PRO_BASIC => 'Pro plan with access to premium features.',
		Plan_Levels::PRO_PLUS  => 'Pro Plus plan with advanced features.',
		Plan_Levels::PRO_ELITE => 'Elite plan with all features and premium support.',
	);

	/**
	 * Constructor.
	 *
	 * @param string $id Plan ID.
	 */
	public function __construct( string $id ) {
		if ( ! self::is_valid( $id ) ) {
			throw new \InvalidArgumentException( sprintf( 'Invalid plan ID provided: %s', esc_html( $id ) ) );
		}
		$this->id = $id;
	}
	/**
	 * Is valid.
	 *
	 * @param string $id The ID.
	 * @return bool
	 */
	public static function is_valid( string $id ): bool {
		return Plan_Levels_Helper::is_valid( $id );
	}
	/**
	 * Get id.
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
		return self::NAMES[ $this->id ] ?? 'Unknown Plan';
	}
	/**
	 * Get description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return self::DESCRIPTIONS[ $this->id ] ?? 'No description available.';
	}
	/**
	 * Get level.
	 *
	 * @return int
	 */
	public function get_level(): int {
		return self::HIERARCHY[ $this->id ] ?? 0;
	}
	/**
	 * Is at least.
	 *
	 * @param Plan $other The other.
	 * @return bool
	 */
	public function is_at_least( Plan $other ): bool {
		return $this->get_level() >= $other->get_level();
	}
	/**
	 * Can access feature.
	 *
	 * @param string $type The type.
	 * @param string $feature_id The ID.
	 * @return bool
	 */
	public function can_access_feature( string $type, string $feature_id ): bool {
		// For now, simple logic: Pro Basic and above can access everything.
		// This can be expanded into a full feature management system in the future.
		return $this->is_at_least( new self( Plan_Levels::PRO_BASIC ) );
	}
}
