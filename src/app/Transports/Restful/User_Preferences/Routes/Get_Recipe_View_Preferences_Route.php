<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\User_Preferences\Routes;

use Uncanny_Automator\App\Presentation\Recipe_View\Recipe_View_Preference_Service;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Rest_Responses;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Thin handler for GET /uap/v2/user/preferences/recipe-view: the current
 * user's recipe view preference in the `{ success, data, message }` envelope.
 *
 * @since 8.0.0
 */
final class Get_Recipe_View_Preferences_Route {

	use Rest_Responses;

	/**
	 * Answer `{ preferences }` for the current user.
	 *
	 * @since 8.0.0
	 *
	 * @param WP_REST_Request $request REST request.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle( WP_REST_Request $request ): WP_REST_Response {

		$preferences = Recipe_View_Preference_Service::instance()->get( get_current_user_id() );

		return self::success( array( 'preferences' => $preferences->to_array() ) );
	}
}
