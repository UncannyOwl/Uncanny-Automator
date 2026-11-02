<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\User_Preferences\Routes;

use InvalidArgumentException;
use Uncanny_Automator\App\Presentation\Recipe_View\Recipe_View_Preference_Service;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Rest_Responses;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Thin handler for POST /uap/v2/user/preferences/recipe-view/update.
 *
 * Checks the body's shape, hands the change to the service, and answers the
 * whole record as now stored. The service owns the view vocabulary. A body
 * this or the service refuses is a 400, and nothing is stored.
 *
 * @since 8.0.0
 */
final class Update_Recipe_View_Preferences_Route {

	use Rest_Responses;

	/**
	 * The keys the write accepts. `default_view` is settled by the service, never written here.
	 *
	 * @var string[]
	 */
	private const KEYS = array( 'view', 'diagram_intro_dismissed' );

	/**
	 * Store the change and answer `{ preferences }`, or a 400.
	 *
	 * @since 8.0.0
	 *
	 * @param WP_REST_Request $request REST request with a JSON body.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle( WP_REST_Request $request ): WP_REST_Response {

		$body = $request->get_json_params();

		if ( ! self::is_change( $body ) ) {
			return self::refused( esc_html__( 'Send view, diagram_intro_dismissed or both, and nothing else.', 'uncanny-automator' ) );
		}

		try {
			$preferences = Recipe_View_Preference_Service::instance()->update(
				get_current_user_id(),
				$body['view'] ?? null,
				$body['diagram_intro_dismissed'] ?? null
			);
		} catch ( InvalidArgumentException $e ) {
			return self::refused( $e->getMessage() );
		}

		return self::success( array( 'preferences' => $preferences->to_array() ) );
	}

	/**
	 * Whether the body is a non-empty object of the accepted keys: `view` a
	 * string, `diagram_intro_dismissed` a JSON boolean.
	 *
	 * @param mixed $body The decoded JSON body.
	 *
	 * @return bool
	 */
	private static function is_change( $body ): bool {

		if ( ! is_array( $body ) || array() === $body || array() !== array_diff( array_keys( $body ), self::KEYS ) ) {
			return false;
		}

		if ( array_key_exists( 'view', $body ) && ! is_string( $body['view'] ) ) {
			return false;
		}

		return ! array_key_exists( 'diagram_intro_dismissed', $body ) || is_bool( $body['diagram_intro_dismissed'] );
	}

	/**
	 * A 400 in the Automator failure envelope.
	 *
	 * @param string $message Why the body was refused.
	 *
	 * @return WP_REST_Response
	 */
	private static function refused( string $message ): WP_REST_Response {

		return self::error_reply( new WP_Error( 'invalid_recipe_view_preferences', $message, array( 'status' => 400 ) ) );
	}
}
