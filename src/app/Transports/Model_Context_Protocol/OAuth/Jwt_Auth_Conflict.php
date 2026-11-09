<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\Mcp_Auth_Log;
use WP_Error;
use WP_Hook;
use WP_REST_Request;

/**
 * Keeps JWT Authentication for WP-API from refusing requests Automator authorizes.
 *
 * That plugin (wordpress.org slug jwt-authentication-for-wp-rest-api) decodes
 * every `Authorization: Bearer` header as a JWT on determine_current_user. When
 * decoding fails, it keeps the error, and its rest_pre_dispatch callback
 * (Jwt_Auth_Public::rest_pre_dispatch(), priority 10) returns that error for
 * every REST request in the PHP request, nested ones included. It replaces
 * whatever it receives, including an earlier refusal from another plugin or
 * from Automator's own guards. An Automator token is not a JWT, so the
 * connection and every ability an MCP tool runs answered 403
 * jwt_auth_invalid_token (or jwt_auth_bad_config).
 *
 * The plugin's callback is wrapped where it stands. When the plugin refuses a
 * request that Automator authorizes with its own valid token, the wrapper
 * returns what the callback received, as if the plugin were not installed:
 * earlier refusals and answers stand, and Automator's own checks run next. For
 * every other request, it returns the plugin's answer. Clearing the error
 * further down the filter instead would also clear the earlier result the
 * plugin replaced.
 *
 * @since 8.0.3
 */
final class Jwt_Auth_Conflict {

	/**
	 * The plugin class whose rest_pre_dispatch callback refuses foreign tokens.
	 *
	 * @var string
	 */
	private const PLUGIN_CLASS = 'Jwt_Auth_Public';

	/**
	 * The prefix of every error code that callback returns.
	 *
	 * @var string
	 */
	private const ERROR_CODE_PREFIX = 'jwt_auth_';

	/**
	 * The authentication log reference for an ignored refusal.
	 *
	 * @var string
	 */
	private const LOG_REF = 'UA-MCP-208';

	/**
	 * Seconds one log entry covers (an hour).
	 *
	 * Every MCP request on an affected site meets the conflict, so one entry per
	 * request would cycle the whole support log.
	 *
	 * @var int
	 */
	private const LOG_INTERVAL = 3600;

	/**
	 * The plugin's own callback.
	 *
	 * @var callable
	 */
	private $callback;

	/**
	 * The number of arguments the plugin registered its callback with.
	 *
	 * @var int
	 */
	private int $accepted_args;

	/**
	 * Whether Automator authorizes a request with its own valid token.
	 *
	 * @var callable(WP_REST_Request):bool
	 */
	private $authorizes;

	/**
	 * Constructor.
	 *
	 * @param callable $callback      The plugin's own callback.
	 * @param int      $accepted_args The number of arguments it was registered with.
	 * @param callable $authorizes    Whether Automator authorizes a request with its own valid token.
	 */
	private function __construct( callable $callback, int $accepted_args, callable $authorizes ) {
		$this->callback      = $callback;
		$this->accepted_args = $accepted_args;
		$this->authorizes    = $authorizes;
	}

	/**
	 * Wrap the plugin's rest_pre_dispatch callback in its own place.
	 *
	 * The entry is replaced under its own priority and key, so the plugin keeps
	 * its position among the other callbacks and remove_filter() with its
	 * original callable still removes it. Removing and adding it again would
	 * move it behind every callback registered later at its priority. A
	 * callback that is already wrapped is left alone.
	 *
	 * @since 8.0.3
	 *
	 * @param callable $authorizes Whether Automator authorizes a request with its own valid token.
	 *
	 * @return void
	 */
	public static function contain( callable $authorizes ): void {
		$hook = $GLOBALS['wp_filter']['rest_pre_dispatch'] ?? null;
		if ( ! $hook instanceof WP_Hook ) {
			return;
		}

		foreach ( $hook->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $id => $callback ) {
				if ( ! self::is_plugin_callback( $callback['function'] ?? null ) ) {
					continue;
				}

				$hook->callbacks[ $priority ][ $id ] = array(
					'function'      => new self( $callback['function'], (int) ( $callback['accepted_args'] ?? 1 ), $authorizes ),
					'accepted_args' => 3,
				);
			}
		}
	}

	/**
	 * Run the plugin's callback, and drop its refusal of a request Automator authorizes.
	 *
	 * @since 8.0.3
	 *
	 * @param mixed $result  Pre-dispatch result the callback receives.
	 * @param mixed $server  REST server instance.
	 * @param mixed $request REST request object.
	 *
	 * @return mixed
	 */
	public function __invoke( $result, $server = null, $request = null ) {
		$answer = call_user_func_array( $this->callback, array_slice( array( $result, $server, $request ), 0, $this->accepted_args ) );

		if (
			$answer === $result
			|| ! $answer instanceof WP_Error
			|| 0 !== strpos( (string) $answer->get_error_code(), self::ERROR_CODE_PREFIX )
			|| ! $request instanceof WP_REST_Request
			|| ! call_user_func( $this->authorizes, $request )
		) {
			return $answer;
		}

		self::record_ignored_refusal( $answer, $request );

		return $result;
	}

	/**
	 * Whether a registered callback is the plugin's rest_pre_dispatch method.
	 *
	 * @param mixed $callback Registered callback.
	 *
	 * @return bool
	 */
	private static function is_plugin_callback( $callback ): bool {
		return is_array( $callback )
			&& isset( $callback[0], $callback[1] )
			&& is_object( $callback[0] )
			&& is_a( $callback[0], self::PLUGIN_CLASS )
			&& 'rest_pre_dispatch' === $callback[1];
	}

	/**
	 * Record, at most once an hour, that a refusal was ignored.
	 *
	 * The log itself is the gate: clearing it lets the next conflict be
	 * recorded at once. Two requests at the same moment may both record one.
	 *
	 * @param WP_Error        $refusal The plugin's refusal.
	 * @param WP_REST_Request $request REST request object.
	 *
	 * @return void
	 */
	private static function record_ignored_refusal( WP_Error $refusal, WP_REST_Request $request ): void {
		$since = time() - self::LOG_INTERVAL;
		foreach ( Mcp_Auth_Log::all() as $entry ) {
			if ( self::LOG_REF === ( $entry['ref'] ?? '' ) && (int) ( $entry['t'] ?? 0 ) > $since ) {
				return;
			}
		}

		Mcp_Auth_Log::record(
			'bearer_auth',
			'jwt_auth_conflict',
			array(
				'ref'     => self::LOG_REF,
				// The detail field keeps 160 characters, so the route goes in the subject.
				'subject' => strtolower( '/' . trim( (string) $request->get_route(), '/' ) ),
				'detail'  => sprintf(
					'Ignored JWT Authentication for WP-API error %s for an Automator-authenticated request on this route.',
					(string) $refusal->get_error_code()
				),
			)
		);
	}
}
