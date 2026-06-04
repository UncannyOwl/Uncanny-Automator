<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Services;

use Uncanny_Automator\Automator_Status;
use Uncanny_Automator\App\Infrastructure\Database\Database;

/**
 * Classifies action errors as actionable or non-actionable.
 *
 * Extracted from Recipe_Complete_Stage — pure stateless utility with no
 * instance dependencies. Determines whether an error should escalate
 * to the recipe status or be silently absorbed.
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Services
 * @since   7.3
 */
final class Action_Error_Classifier {

	/**
	 * Non-actionable completion statuses — errors with these don't escalate to the recipe.
	 */
	private const NON_ACTIONABLE_STATUSES = array(
		Automator_Status::DID_NOTHING,
		Automator_Status::SKIPPED,
		Automator_Status::COMPLETED_WITH_NOTICE,
	);

	/**
	 * Substrings that indicate a non-actionable error message.
	 */
	private const NON_ACTIONABLE_PATTERNS = array(
		'condition' => array( 'failed condition', 'failed conditions' ),
		'creation'  => array( 'new user created', 'new user was created', 'creating a new user failed' ),
		'matching'  => array( 'existing user was found', 'user was not found', 'user found matching' ),
	);

	/**
	 * Find the first actionable error from all actions in a recipe.
	 *
	 * @param int $recipe_log_id The recipe log ID.
	 *
	 * @return object|null
	 */
	public static function find_actionable_error( $recipe_log_id ): ?object {

		$all_action_errors = ( Database::get_execution_log_store() )->get_action_error_messages( $recipe_log_id );

		if ( empty( $all_action_errors ) ) {
			return null;
		}

		foreach ( $all_action_errors as $action_error ) {
			if ( self::is_actionable_error( $action_error ) ) {
				return $action_error;
			}
		}

		return null;
	}

	/**
	 * Determine whether an action error should update the recipe status.
	 *
	 * @param object $action_error Object with error_message and completed properties.
	 *
	 * @return bool
	 */
	public static function is_actionable_error( $action_error ): bool {

		$message  = $action_error->error_message ?? '';
		$complete = isset( $action_error->completed ) ? (int) $action_error->completed : 0;

		if ( self::is_non_actionable_message( $message ) ) {
			return false;
		}

		return ! in_array( $complete, self::NON_ACTIONABLE_STATUSES, true );
	}

	/**
	 * Check if error message is from failed condition block.
	 *
	 * @param string|null $error_message The error message.
	 *
	 * @return bool
	 */
	public static function is_condition_block_failed_message( ?string $error_message ): bool {
		return self::message_matches_patterns( $error_message, self::NON_ACTIONABLE_PATTERNS['condition'] );
	}

	/**
	 * Check if error message is from user selector user creation.
	 *
	 * @param string|null $error_message The error message.
	 *
	 * @return bool
	 */
	public static function is_user_selector_user_creation_message( ?string $error_message ): bool {
		return self::message_matches_patterns( $error_message, self::NON_ACTIONABLE_PATTERNS['creation'] );
	}

	/**
	 * Check if error message is from user selector matching.
	 *
	 * @param string|null $error_message The error message.
	 *
	 * @return bool
	 */
	public static function is_user_selector_matching_message( ?string $error_message ): bool {
		return self::message_matches_patterns( $error_message, self::NON_ACTIONABLE_PATTERNS['matching'] );
	}

	/**
	 * Get the non-actionable status list.
	 *
	 * @return int[]
	 */
	public static function get_non_actionable_statuses(): array {
		return self::NON_ACTIONABLE_STATUSES;
	}

	// ── Private helpers ──

	/**
	 * Check if a message matches any non-actionable pattern across all categories.
	 *
	 * @param string $message The error message.
	 *
	 * @return bool
	 */
	private static function is_non_actionable_message( string $message ): bool {

		$lower = strtolower( $message );

		foreach ( self::NON_ACTIONABLE_PATTERNS as $patterns ) {
			if ( self::substring_exists( $patterns, $lower ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a message matches specific substring patterns.
	 *
	 * @param string|null $message  The error message.
	 * @param string[]    $patterns Substrings to search for.
	 *
	 * @return bool
	 */
	private static function message_matches_patterns( ?string $message, array $patterns ): bool {
		if ( null === $message || '' === $message ) {
			return false;
		}
		return self::substring_exists( $patterns, strtolower( $message ) );
	}

	/**
	 * Check if any substring exists in a string.
	 *
	 * @param string[] $substrings Substrings to search for.
	 * @param string   $_string    The string to search in.
	 *
	 * @return bool
	 */
	private static function substring_exists( array $substrings, string $_string ): bool {

		foreach ( $substrings as $substring ) {
			if ( false !== strpos( $_string, $substring ) ) {
				return true;
			}
		}

		return false;
	}
}
