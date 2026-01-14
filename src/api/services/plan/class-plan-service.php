<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Plan;

use Uncanny_Automator\Api\Components\Plan\Domain\Plan;
use Uncanny_Automator\Api\Components\Plan\Domain\Plan_Levels;
use Uncanny_Automator\Api\Infrastructure\Plan\Plan_Factory;
use Uncanny_Automator\Api\Infrastructure\Plan\Plan_Implementation;

/**
 * A thin facade providing a simple API for developers to interact with Plans.
 *
 * @package Uncanny_Automator\Api\Services\Plan
 * @since 7.0.0
 */
class Plan_Service {

	private Plan_Factory $factory;

	/**
	 * Constructor.
	 *
	 * @param Plan_Factory|null $factory Plan factory instance.
	 */
	public function __construct( ?Plan_Factory $factory = null ) {
		$this->factory = $factory ?? new Plan_Factory();
	}

	/**
	 * Retrieves the Plan object for the current user.
	 *
	 * @return Plan
	 */
	public function get_current(): Plan {
		return $this->factory->create_from_api();
	}

	/**
	 * Checks if the user has a Lite plan.
	 *
	 * @return bool
	 */
	public function is_lite(): bool {
		return $this->get_current()->is_at_least( new Plan_Implementation( Plan_Levels::LITE ) );
	}

	/**
	 * Checks if the user has any Pro-level plan.
	 *
	 * @return bool
	 */
	public function is_pro(): bool {
		return $this->get_current()->is_at_least( new Plan_Implementation( Plan_Levels::PRO_BASIC ) );
	}

	/**
	 * Checks if the user has a Pro Plus plan.
	 *
	 * @return bool
	 */
	public function is_plus(): bool {
		return $this->get_current()->is_at_least( new Plan_Implementation( Plan_Levels::PRO_PLUS ) );
	}

	/**
	 * Checks if the user has a Pro Elite plan.
	 *
	 * @return bool
	 */
	public function is_elite(): bool {
		return $this->get_current()->is_at_least( new Plan_Implementation( Plan_Levels::PRO_ELITE ) );
	}

	/**
	 * Returns the string ID of the current user's plan (e.g., 'pro-basic').
	 *
	 * @return string
	 */
	public function get_current_plan_id(): string {
		return $this->get_current()->get_id();
	}

	/**
	 * Checks if the current user's plan can access a specific feature.
	 *
	 * @param string $type The type of feature (e.g., 'trigger', 'action').
	 * @param string $feature_id The specific ID of the feature.
	 * @return bool
	 */
	public function user_can_access_feature( string $type, string $feature_id ): bool {
		return $this->get_current()->can_access_feature( $type, $feature_id );
	}

	/**
	 * Checks if the current user's plan is in the given array of allowed plans.
	 * If the array is empty, returns true (available to all plans).
	 *
	 * @param array $allowed_plans Array of plan IDs that can access the feature.
	 * @return bool
	 */
	public function user_can_access( array $allowed_plans ): bool {
		if ( empty( $allowed_plans ) ) {
			return true;
		}
		return in_array( $this->get_current_plan_id(), $allowed_plans, true );
	}

	/**
	 * Checks if the current user's plan meets or exceeds the required tier.
	 *
	 * Uses plan hierarchy: elite > plus > basic > lite.
	 * If user has elite and required tier is plus, returns true.
	 *
	 * @param string $required_tier Required tier ('lite', 'pro-basic', 'pro-plus', 'pro-elite')
	 * @return bool True if user's plan is at least the required tier
	 */
	public function meets_tier_requirement( string $required_tier ): bool {

		if ( ! Plan_Implementation::is_valid( $required_tier ) ) {
			return false;
		}

		$required_plan = new Plan_Implementation( $required_tier );
		$current_plan  = $this->get_current();

		return $current_plan->is_at_least( $required_plan );
	}
}
