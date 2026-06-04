<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Services;

/**
 * Error code constants for structured action errors.
 *
 * Replaces strpos()-based error classification with code-based checks.
 * Every error has a code. Codes are either actionable (should escalate
 * to recipe status) or non-actionable (informational, don't affect recipe).
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Services
 * @since   7.3
 */
class Error_Code {

	// ── Actionable errors — escalate to recipe status ──

	const INTEGRATION_INACTIVE = 'integration_inactive';
	const APP_NOT_CONNECTED    = 'app_not_connected';
	const FUNCTION_NOT_FOUND   = 'function_not_found';
	const API_ERROR            = 'api_error';
	const API_TIMEOUT          = 'api_timeout';
	const AUTH_FAILED          = 'auth_failed';
	const RATE_LIMITED         = 'rate_limited';
	const VALIDATION_FAILED    = 'validation_failed';
	const NOT_FOUND            = 'not_found';
	const PERMISSION_DENIED    = 'permission_denied';
	const DUPLICATE            = 'duplicate';
	const EXECUTION_FAILED     = 'execution_failed';
	const UNKNOWN              = 'unknown';
	const RECIPE_STUCK         = 'recipe_stuck';
	const RECIPE_RECURSION     = 'recipe_recursion';
	const SYSTEM_ERROR         = 'system_error';

	// ── Non-actionable errors — informational, don't escalate ──

	const CONDITION_FAILED     = 'condition_failed';
	const USER_CREATED         = 'user_created';
	const USER_CREATION_FAILED = 'user_creation_failed';
	const USER_FOUND           = 'user_found';
	const USER_NOT_FOUND       = 'user_not_found';
	const ACTION_SKIPPED       = 'action_skipped';

	/**
	 * Non-actionable codes — errors with these codes do not escalate to recipe status.
	 *
	 * @var array<string,bool>
	 */
	private const NON_ACTIONABLE = array(
		self::CONDITION_FAILED     => true,
		self::USER_CREATED         => true,
		self::USER_CREATION_FAILED => true,
		self::USER_FOUND           => true,
		self::USER_NOT_FOUND       => true,
		self::ACTION_SKIPPED       => true,
	);

	/**
	 * Legacy message patterns mapped to error codes.
	 *
	 * Used by infer_from_message() to bridge old-style string errors
	 * into structured codes. Checked in order — first match wins.
	 *
	 * Order matters: more specific patterns before generic ones.
	 * e.g. "user was not found" before "not found" to avoid NOT_FOUND matching user messages.
	 *
	 * @var array<string,string>
	 */
	private const MESSAGE_PATTERNS = array(

		// ── Non-actionable: conditions & user selector ──
		'failed condition'              => self::CONDITION_FAILED,
		'failed conditions'             => self::CONDITION_FAILED,
		'new user created'              => self::USER_CREATED,
		'new user was created'          => self::USER_CREATED,
		'creating a new user failed'    => self::USER_CREATION_FAILED,
		'existing user was found'       => self::USER_FOUND,
		'user found matching'           => self::USER_FOUND,
		'user was not found'            => self::USER_NOT_FOUND,
		'no user found matching'        => self::USER_NOT_FOUND,
		'no user matching'              => self::USER_NOT_FOUND,

		// ── Auth / credentials ──
		'invalid credentials'           => self::AUTH_FAILED,
		'token refresh failed'          => self::AUTH_FAILED,
		'failed to refresh access token' => self::AUTH_FAILED,
		'is not connected'              => self::AUTH_FAILED,
		'is currently disconnected'     => self::AUTH_FAILED,
		'please reconnect'              => self::AUTH_FAILED,
		're-authenticate'               => self::AUTH_FAILED,
		'authorization error'           => self::AUTH_FAILED,
		'missing access token'          => self::AUTH_FAILED,
		'server credentials are invalid' => self::AUTH_FAILED,
		'double-check your api key'     => self::AUTH_FAILED,

		// ── Rate limiting ──
		'rate limit'                    => self::RATE_LIMITED,
		'too many requests'             => self::RATE_LIMITED,
		'throttle'                      => self::RATE_LIMITED,

		// ── Permission ──
		'do not have permission'        => self::PERMISSION_DENIED,
		'insufficient permissions'      => self::PERMISSION_DENIED,
		'cannot be applied to administrators' => self::PERMISSION_DENIED,
		'for security'                  => self::PERMISSION_DENIED,
		'author can not be removed'     => self::PERMISSION_DENIED,
		'you cannot ban'                => self::PERMISSION_DENIED,

		// ── Duplicate ──
		'already exists'                => self::DUPLICATE,
		'already enrolled'              => self::DUPLICATE,
		'already applied'               => self::DUPLICATE,
		'already set to'                => self::DUPLICATE,

		// ── Validation ──
		'is required'                   => self::VALIDATION_FAILED,
		'is empty'                      => self::VALIDATION_FAILED,
		'please enter a valid'          => self::VALIDATION_FAILED,
		'please select'                 => self::VALIDATION_FAILED,
		'invalid email'                 => self::VALIDATION_FAILED,
		'must be a valid number'        => self::VALIDATION_FAILED,
		'must be between'               => self::VALIDATION_FAILED,
		'must be no longer than'        => self::VALIDATION_FAILED,
		'invalid post id'               => self::VALIDATION_FAILED,
		'invalid form'                  => self::VALIDATION_FAILED,
		'invalid download id'           => self::VALIDATION_FAILED,
		'missing required data'         => self::VALIDATION_FAILED,
		'no products selected'          => self::VALIDATION_FAILED,
		'no valid products'             => self::VALIDATION_FAILED,
		'invalid channel type'          => self::VALIDATION_FAILED,
		'invalid tag selected'          => self::VALIDATION_FAILED,
		'invalid campaign selected'     => self::VALIDATION_FAILED,
		'invalid operator'              => self::VALIDATION_FAILED,

		// ── Function / class not found ──
		'class is not found'            => self::FUNCTION_NOT_FOUND,
		'class not found'               => self::FUNCTION_NOT_FOUND,
		'function does not exist'       => self::FUNCTION_NOT_FOUND,
		'function not found'            => self::FUNCTION_NOT_FOUND,
		'method not found'              => self::FUNCTION_NOT_FOUND,
		'dose not exists'               => self::FUNCTION_NOT_FOUND, // sic — typo in codebase
		'not installed or activated'     => self::INTEGRATION_INACTIVE,
		'plugin must be installed'      => self::INTEGRATION_INACTIVE,
		'plugin is not active'          => self::INTEGRATION_INACTIVE,

		// ── Not found (entities) — must come AFTER user patterns ──
		'not found'                     => self::NOT_FOUND,
		'doesn\'t exist'                => self::NOT_FOUND,
		'does not exist'                => self::NOT_FOUND,

		// ── API errors ──
		'api error'                     => self::API_ERROR,
		'api has returned an empty response' => self::API_ERROR,
		'no data returned'              => self::API_ERROR,
		'empty response'                => self::API_ERROR,
		'returned an error'             => self::API_ERROR,
		'wp_error'                      => self::API_ERROR,

		// ── Timeout ──
		'timed out'                     => self::API_TIMEOUT,
		'timeout'                       => self::API_TIMEOUT,
		'no response was received'      => self::API_TIMEOUT,

		// ── Execution failures ──
		'failed to create'              => self::EXECUTION_FAILED,
		'failed to update'              => self::EXECUTION_FAILED,
		'failed to add'                 => self::EXECUTION_FAILED,
		'failed to remove'              => self::EXECUTION_FAILED,
		'failed to mark'                => self::EXECUTION_FAILED,
		'failed to generate'            => self::EXECUTION_FAILED,
		'unable to create'              => self::EXECUTION_FAILED,
		'unable to fetch'               => self::EXECUTION_FAILED,
		'could not create'              => self::EXECUTION_FAILED,
		'error adding'                  => self::EXECUTION_FAILED,
		'error removing'                => self::EXECUTION_FAILED,
		'error updating'                => self::EXECUTION_FAILED,
		'error sending'                 => self::EXECUTION_FAILED,
	);

	/**
	 * Check if an error code is actionable (should escalate to recipe status).
	 *
	 * @param string $code Error code constant.
	 *
	 * @return bool
	 */
	public static function is_actionable( string $code ): bool {
		return ! isset( self::NON_ACTIONABLE[ $code ] );
	}

	/**
	 * Infer an error code from a legacy error message string.
	 *
	 * Scans the message for known patterns and returns the matching code.
	 * Falls back to UNKNOWN if no pattern matches.
	 *
	 * @param string $message The legacy error message.
	 *
	 * @return string Error code constant.
	 */
	public static function infer_from_message( string $message ): string {

		if ( '' === $message ) {
			return self::UNKNOWN;
		}

		$lower = strtolower( $message );

		foreach ( self::MESSAGE_PATTERNS as $pattern => $code ) {
			if ( false !== strpos( $lower, $pattern ) ) {
				return $code;
			}
		}

		return self::UNKNOWN;
	}

	/**
	 * Check if a legacy error message is actionable.
	 *
	 * Convenience wrapper: infers code from message, then checks actionability.
	 *
	 * @param string $message The legacy error message.
	 *
	 * @return bool
	 */
	public static function is_actionable_by_message( string $message ): bool {
		return self::is_actionable( self::infer_from_message( $message ) );
	}
}
