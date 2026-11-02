<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Integrations\Routes;

use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Field_Registry_Service;
use WP_Error;
use WP_REST_Request;

/**
 * `GET /uap/v2/integrations/{integration_code}/items/{item_type}/{item_code}/fields`
 *
 * Lazy field-config lookup for trigger / action rows.
 * Registered by `Integrations_Rest_Controller::register_routes()`.
 * Delegates to `Field_Registry_Service`. Returns raw `Field[]` on success
 * (no envelope) per the FE contract; `WP_Error` for unknown items /
 * materialization failures (the FE's TanStack Query layer surfaces these
 * via `isError`).
 */
final class Item_Fields_Route extends Field_Lookup_Route {

	/**
	 * Request param name for the item-type discriminator.
	 */
	public static function type_param_name(): string {
		return 'item_type';
	}

	/**
	 * Request param name for the item code.
	 */
	public static function code_param_name(): string {
		return 'item_code';
	}

	/**
	 * Validate `item_type` against `Integration_Item_Types::is_valid`.
	 *
	 * @param mixed           $value
	 * @param WP_REST_Request $request
	 * @param string          $param
	 * @return bool|WP_Error
	 */
	public static function validate_type( $value, WP_REST_Request $request, string $param ) {
		if ( ! Integration_Item_Types::is_valid( (string) $value ) ) {
			return new WP_Error(
				'invalid_item_type',
				sprintf(
					/* translators: %s: list of allowed item types */
					esc_html_x( 'Invalid item_type. Allowed values: %s', 'Item_Fields_Route', 'uncanny-automator' ),
					esc_html( implode( ', ', Integration_Item_Types::get_all() ) )
				),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Look up the item's fields via Field_Registry_Service.
	 *
	 * @param string $integration_code
	 * @param string $type Item type literal.
	 * @param string $code Item code.
	 * @return array|WP_Error
	 */
	public static function fetch_fields( string $integration_code, string $type, string $code ) {
		return Field_Registry_Service::instance()->get_item_fields( $integration_code, $type, $code );
	}
}
