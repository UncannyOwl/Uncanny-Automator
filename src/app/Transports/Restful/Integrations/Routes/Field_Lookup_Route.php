<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Integrations\Routes;

use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Rest_Responses;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Shared handler body for the lazy field-lookup endpoints.
 *
 * The controller (`Integrations_Rest_Controller`) registers the route
 * patterns inline; this class is the per-request handler that the two
 * concrete subclasses share. Subclasses supply the request-param names
 * and the lookup that hits the matching `Field_Registry_Service` method.
 *
 * Error responses go through {@see Rest_Responses::failure()} — the
 * canonical Automator REST failure helper — so the wire envelope stays
 * consistent with every other endpoint in the surface.
 */
abstract class Field_Lookup_Route {

	use Rest_Responses;

	/**
	 * Request param name carrying the entity-type discriminator —
	 * `'item_type'` or `'block_modifier_type'`.
	 *
	 * @return string
	 */
	abstract public static function type_param_name(): string;

	/**
	 * Request param name carrying the entity code — `'item_code'` or
	 * `'modifier_code'`.
	 *
	 * @return string
	 */
	abstract public static function code_param_name(): string;

	/**
	 * Validate the type-param value against the appropriate enum.
	 * Referenced from the controller as
	 * `[ Item_Fields_Route::class, 'validate_type' ]`.
	 *
	 * Signature matches `validate_callback` semantics:
	 * `(mixed $value, WP_REST_Request $request, string $param): bool|WP_Error`.
	 *
	 * @param mixed           $value
	 * @param WP_REST_Request $request
	 * @param string          $param
	 * @return bool|\WP_Error
	 */
	abstract public static function validate_type( $value, WP_REST_Request $request, string $param );

	/**
	 * Look up the `Field[]` array for the given identifiers via
	 * `Field_Registry_Service`.
	 *
	 * Returns either an array (success) or a `WP_Error` (validation
	 * failure / not-found / materialization error). `handle()` propagates
	 * either shape; the WordPress REST framework auto-serializes
	 * `WP_Error` with the embedded status code.
	 *
	 * @param string $integration_code
	 * @param string $type Sanitised type discriminator.
	 * @param string $code Sanitised entity code.
	 * @return array|WP_Error
	 */
	abstract public static function fetch_fields( string $integration_code, string $type, string $code );

	/**
	 * GET handler — delegates to `fetch_fields()` and propagates the
	 * result.
	 *
	 * Success: array wrapped in a 200 `WP_REST_Response` (no envelope —
	 * raw `Field[]` per the FE contract).
	 *
	 * Failure: any `WP_Error` from the service is re-shaped through
	 * `self::failure()` (the canonical Automator REST failure helper).
	 * The returned `WP_Error` is what the WP REST framework converts to
	 * the standard `{ code, message, data: { status } }` envelope.
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle( WP_REST_Request $request ) {
		$result = static::fetch_fields(
			(string) $request->get_param( 'integration_code' ),
			(string) $request->get_param( static::type_param_name() ),
			(string) $request->get_param( static::code_param_name() )
		);

		if ( is_wp_error( $result ) ) {
			$error_data = $result->get_error_data();
			$status     = is_array( $error_data ) && isset( $error_data['status'] )
				? (int) $error_data['status']
				: 500;

			// Strip `status` from the data payload — Rest_Responses::failure()
			// injects its own. Pass any remaining keys through verbatim.
			if ( is_array( $error_data ) ) {
				unset( $error_data['status'] );
			} else {
				$error_data = array();
			}

			return self::failure(
				$result->get_error_message(),
				$status,
				$result->get_error_code(),
				$error_data
			);
		}

		return new WP_REST_Response( $result, 200 );
	}
}
