<?php
/**
 * Plan Functions
 *
 * WordPress developer convenience functions for plan operations.
 * Covers plan checking, feature availability, and tier management.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\Api\Functions
 */

declare(strict_types=1);

// Prevent direct access
if ( ! defined( 'ABSPATH' ) && ! defined( 'PHPUNIT_COMPOSER_INSTALL' ) && ! defined( 'WP_TESTS_DIR' ) ) {
	exit;
}

// Import classes
use Uncanny_Automator\Api\Services\Plan\Plan_Service;
use Uncanny_Automator\Api\Components\Plan\Domain\Feature_Type;

// =============================================================================
// PLAN ACCESS
// =============================================================================

/**
 * Get current user's plan.
 *
 * @return \Uncanny_Automator\Api\Components\Plan\Domain\Plan Current plan instance.
 */
function automator_get_current_plan() {
	$plan_service = automator_get_plan_service();
	return $plan_service->get_current();
}

/**
 * Get current plan ID.
 *
 * @return string Plan ID ('lite', 'pro-basic', 'pro-plus', 'pro-elite').
 */
function automator_get_current_plan_id() {
	$plan_service = automator_get_plan_service();
	return $plan_service->get_current_plan_id();
}

/**
 * Get current plan name.
 *
 * @return string Plan display name.
 */
function automator_get_current_plan_name() {
	$plan = automator_get_current_plan();
	return $plan->get_name();
}

/**
 * Get current plan description.
 *
 * @return string Plan description.
 */
function automator_get_current_plan_description() {
	$plan = automator_get_current_plan();
	return $plan->get_description();
}

/**
 * Get current plan level.
 *
 * @return int Plan level (0=lite, 1=pro-basic, 2=pro-plus, 3=pro-elite).
 */
function automator_get_current_plan_level() {
	$plan = automator_get_current_plan();
	return $plan->get_level();
}

// =============================================================================
// PLAN CHECKING
// =============================================================================

/**
 * Check if user has Lite plan.
 *
 * @return bool True if user has Lite plan.
 */
function automator_has_lite_plan() {
	$plan_service = automator_get_plan_service();
	return $plan_service->is_lite();
}

/**
 * Check if user has Pro plan (any tier).
 *
 * @return bool True if user has any Pro plan.
 */
function automator_has_pro_plan() {
	$plan_service = automator_get_plan_service();
	return $plan_service->is_pro();
}

/**
 * Check if user has Pro Basic plan.
 *
 * @return bool True if user has Pro Basic plan.
 */
function automator_has_pro_basic_plan() {
	$plan = automator_get_current_plan();
	return $plan->get_id() === 'pro-basic';
}

/**
 * Check if user has Pro Plus plan.
 *
 * @return bool True if user has Pro Plus plan.
 */
function automator_has_pro_plus_plan() {
	$plan_service = automator_get_plan_service();
	return $plan_service->is_plus();
}

/**
 * Check if user has Pro Elite plan.
 *
 * @return bool True if user has Pro Elite plan.
 */
function automator_has_pro_elite_plan() {
	$plan_service = automator_get_plan_service();
	return $plan_service->is_elite();
}

// =============================================================================
// PLAN COMPARISON
// =============================================================================

/**
 * Check if current plan is at least a specific plan level.
 *
 * @param string $plan_id Plan ID to compare against.
 * @return bool True if current plan is at least the specified plan.
 */
function automator_has_plan_at_least( string $plan_id ) {
	if ( empty( $plan_id ) ) {
		return false;
	}

	try {
		$current_plan = automator_get_current_plan();
		$compare_plan = automator_create_plan( $plan_id );
		return $current_plan->is_at_least( $compare_plan );
	} catch ( \Exception $e ) {
		return false;
	}
}

/**
 * Compare two plan IDs.
 *
 * @param string $plan_a First plan ID.
 * @param string $plan_b Second plan ID.
 * @return int -1 if plan_a < plan_b, 0 if equal, 1 if plan_a > plan_b.
 */
function automator_compare_plans( string $plan_a, string $plan_b ) {
	try {
		$plan_a_obj = automator_create_plan( $plan_a );
		$plan_b_obj = automator_create_plan( $plan_b );
		return $plan_a_obj->get_level() <=> $plan_b_obj->get_level();
	} catch ( \Exception $e ) {
		return 0;
	}
}

// =============================================================================
// FEATURE ACCESS CHECKING
// =============================================================================

/**
 * Check if user can access a specific trigger.
 *
 * @param string $trigger_code Trigger code.
 * @return bool True if user can access the trigger.
 */
function automator_can_access_trigger( string $trigger_code ) {
	if ( empty( $trigger_code ) ) {
		return false;
	}

	$plan_service = automator_get_plan_service();
	return $plan_service->user_can_access_feature( Feature_Type::TRIGGER, $trigger_code );
}

/**
 * Check if user can access a specific action.
 *
 * @param string $action_code Action code.
 * @return bool True if user can access the action.
 */
function automator_can_access_action( string $action_code ) {
	if ( empty( $action_code ) ) {
		return false;
	}

	$plan_service = automator_get_plan_service();
	return $plan_service->user_can_access_feature( Feature_Type::ACTION, $action_code );
}

/**
 * Check if user can access a specific action condition.
 *
 * @param string $condition_code Condition code.
 * @return bool True if user can access the action condition.
 */
function automator_can_access_action_condition( string $condition_code ) {
	if ( empty( $condition_code ) ) {
		return false;
	}

	$plan_service = automator_get_plan_service();
	return $plan_service->user_can_access_feature( Feature_Type::ACTION_CONDITION, $condition_code );
}

/**
 * Check if user can access loops.
 *
 * @param string $loop_type Loop type identifier.
 * @return bool True if user can access loops.
 */
function automator_can_access_loops( string $loop_type = '' ) {
	$plan_service = automator_get_plan_service();
	return $plan_service->user_can_access_feature( Feature_Type::LOOP, $loop_type ? $loop_type : 'basic' );
}

/**
 * Check if user can access a specific feature.
 *
 * @param string $feature_type Feature type ('trigger', 'action', 'action_condition', 'loop').
 * @param string $feature_id   Feature identifier.
 * @return bool True if user can access the feature.
 */
function automator_can_access_feature( string $feature_type, string $feature_id ) {
	if ( empty( $feature_type ) || empty( $feature_id ) ) {
		return false;
	}

	$valid_types = array(
		Feature_Type::TRIGGER,
		Feature_Type::ACTION,
		Feature_Type::ACTION_CONDITION,
		Feature_Type::LOOP,
	);

	if ( ! in_array( $feature_type, $valid_types, true ) ) {
		return false;
	}

	$plan_service = automator_get_plan_service();
	return $plan_service->user_can_access_feature( $feature_type, $feature_id );
}

// =============================================================================
// PLAN UTILITIES
// =============================================================================

/**
 * Create a plan instance.
 *
 * @param string $plan_id Plan ID.
 * @return \Uncanny_Automator\Api\Components\Plan\Domain\Plan|WP_Error Plan instance or error.
 */
function automator_create_plan( string $plan_id ) {
	if ( empty( $plan_id ) ) {
		return new WP_Error(
			'missing_plan_id',
			esc_html_x( 'Plan ID is required.', 'Plan helper error', 'uncanny-automator' )
		);
	}

	try {
		return new \Uncanny_Automator\Api\Infrastructure\Plan\Plan_Implementation( $plan_id );
	} catch ( \InvalidArgumentException $e ) {
		return new WP_Error(
			'invalid_plan_id',
			sprintf(
				/* translators: %s Error message. */
				esc_html_x( 'Invalid plan ID: %s', 'Plan helper error', 'uncanny-automator' ),
				$e->getMessage()
			)
		);
	}
}

/**
 * Check if plan ID is valid.
 *
 * @param string $plan_id Plan ID to validate.
 * @return bool True if plan ID is valid.
 */
function automator_is_valid_plan_id( string $plan_id ) {
	if ( empty( $plan_id ) ) {
		return false;
	}

	return \Uncanny_Automator\Api\Infrastructure\Plan\Plan_Implementation::is_valid( $plan_id );
}

/**
 * Get all available plan IDs.
 *
 * @return array Array of valid plan IDs.
 */
function automator_get_available_plan_ids() {
	return array_keys( \Uncanny_Automator\Api\Infrastructure\Plan\Plan_Implementation::HIERARCHY );
}

/**
 * Get plan display name.
 *
 * @param string $plan_id Plan ID.
 * @return string Plan display name or 'Unknown Plan' if invalid.
 */
function automator_get_plan_display_name( string $plan_id ) {
	$plan = automator_create_plan( $plan_id );

	if ( is_wp_error( $plan ) ) {
		return esc_html_x( 'Unknown Plan', 'Plan helper label', 'uncanny-automator' );
	}

	return $plan->get_name();
}

/**
 * Get plan hierarchy level.
 *
 * @param string $plan_id Plan ID.
 * @return int Plan level or 0 if invalid.
 */
function automator_get_plan_hierarchy_level( string $plan_id ) {
	$plan = automator_create_plan( $plan_id );

	if ( is_wp_error( $plan ) ) {
		return 0;
	}

	return $plan->get_level();
}

// =============================================================================
// LEGACY COMPATIBILITY (TEMPORARY)
// =============================================================================

/**
 * Check if user can access feature (legacy function).
 *
 * @deprecated Use automator_can_access_feature() instead.
 * @param array $allowed_plans Array of plan IDs that can access the feature.
 * @return bool True if user can access.
 */
function automator_user_can_access( array $allowed_plans ) {
	$plan_service = automator_get_plan_service();
	return $plan_service->user_can_access( $allowed_plans );
}

/**
 * Get user plan (legacy function).
 *
 * @deprecated Use automator_get_current_plan_id() instead.
 * @return string Plan ID.
 */
function automator_get_user_plan() {
	return automator_get_current_plan_id();
}
