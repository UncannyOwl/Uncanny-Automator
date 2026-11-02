<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

use Uncanny_Automator\App\Events\Dispatcher;

/**
 * Backwards-compatibility shims for the Api_Client → Automator_Platform rename.
 *
 * Registered once at bootstrap so:
 *  - Third-party extensions referencing the old class names via FQCN keep
 *    resolving (through {@see class_alias}).
 *  - Internal `src/core/` callers that still use the legacy `Api_Caller`
 *    base class continue to function.
 *  - Listeners on the deprecated `automator_api_client_response` action
 *    keep firing when the new `automator_platform_response` action is
 *    dispatched.
 *
 * See `docs/planning/automator-platform-alignment-plan.md` Task 1 for the
 * full rename map.
 *
 * @since 8.0.0
 */
final class BackCompat {

	/**
	 * Register class aliases and hook forwarders.
	 *
	 * Idempotent via the sentinel below — {@see class_alias} does NOT
	 * silently ignore duplicates; re-aliasing an existing name emits a
	 * "Cannot redeclare class" warning. All aliases register together,
	 * so one alias existing means the whole set (and the hook forwarder)
	 * is already in place.
	 *
	 * @return void
	 */
	public static function register(): void {

		if ( class_exists( 'Uncanny_Automator\\App\\Infrastructure\\Api_Client\\Api_Client', false ) ) {
			return;
		}

		// Class aliases: old FQCN → new class.
		class_alias( Client::class, 'Uncanny_Automator\\App\\Infrastructure\\Api_Client\\Api_Client' );
		class_alias( Request::class, 'Uncanny_Automator\\App\\Infrastructure\\Api_Client\\Api_Request' );
		class_alias( Response::class, 'Uncanny_Automator\\App\\Infrastructure\\Api_Client\\Api_Response' );
		class_alias( Client_Interface::class, 'Uncanny_Automator\\App\\Infrastructure\\Api_Client\\Api_Client_Interface' );
		class_alias( License_Header_Injector::class, 'Uncanny_Automator\\App\\Infrastructure\\Api_Client\\License_Header_Injector' );

		// Abstract caller class alias (src/core extenders still use old FQCN).
		class_alias(
			'Uncanny_Automator\\App_Integrations\\Automator_Platform\\Caller',
			'Uncanny_Automator\\App_Integrations\\Api_Caller'
		);

		// Hook forwarder — rebroadcast new action under the deprecated name.
		// Signature preserved (params order identical) so listeners migrate without diffs.
		add_action( 'automator_platform_response', array( self::class, 'forward_response_hook' ), 10, 2 );
	}

	/**
	 * Forward the new `automator_platform_response` action to the deprecated
	 * `automator_api_client_response` hook so existing subscribers keep firing.
	 *
	 * @param mixed $response The parsed response (Response VO) or null during handshake.
	 * @param mixed $request  The original Request VO or null during handshake.
	 *
	 * @return void
	 */
	public static function forward_response_hook( $response, $request ): void {
		/**
		 * Fires after an API response has been received.
		 *
		 * @deprecated 7.6.0 Use automator_platform_response instead.
		 */
		Dispatcher::action( 'automator_api_client_response', $response, $request );
	}
}
