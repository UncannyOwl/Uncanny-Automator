<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Integrations\Routes;

use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Field_Registry_Service;
use WP_Error;
use WP_REST_Request;

/**
 * `GET /uap/v2/integrations/{integration_code}/block-modifiers/{block_modifier_type}/{modifier_code}/fields`
 *
 * Lazy field-config lookup for filter_condition / loop_filter modifier
 * rows. Registered by `Integrations_Rest_Controller::register_routes()`.
 * Delegates to `Field_Registry_Service`. Returns raw `Field[]` on success
 * per the FE contract; `WP_Error` for unknown modifiers.
 */
final class Block_Modifier_Fields_Route extends Field_Lookup_Route {

	/**
	 * Request param name for the block-modifier-type discriminator.
	 */
	public static function type_param_name(): string {
		return 'block_modifier_type';
	}

	/**
	 * Request param name for the modifier code.
	 */
	public static function code_param_name(): string {
		return 'modifier_code';
	}

	/**
	 * Validate `block_modifier_type` against `Block_Modifier_Types::is_valid`.
	 *
	 * @param mixed           $value
	 * @param WP_REST_Request $request
	 * @param string          $param
	 * @return bool|WP_Error
	 */
	public static function validate_type( $value, WP_REST_Request $request, string $param ) {
		if ( ! Block_Modifier_Types::is_valid( (string) $value ) ) {
			return new WP_Error(
				'invalid_block_modifier_type',
				sprintf(
					/* translators: %s: list of allowed block modifier types */
					esc_html_x( 'Invalid block_modifier_type. Allowed values: %s', 'Block_Modifier_Fields_Route', 'uncanny-automator' ),
					esc_html( implode( ', ', Block_Modifier_Types::get_all() ) )
				),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Look up the modifier's fields via Field_Registry_Service.
	 *
	 * @param string $integration_code
	 * @param string $type Block-modifier type literal.
	 * @param string $code Modifier code.
	 * @return array|WP_Error
	 */
	public static function fetch_fields( string $integration_code, string $type, string $code ) {
		return Field_Registry_Service::instance()->get_block_modifier_fields( $integration_code, $type, $code );
	}
}
