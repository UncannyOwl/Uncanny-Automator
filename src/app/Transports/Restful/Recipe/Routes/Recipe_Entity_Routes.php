<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe\Routes;

use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Rest_Responses;
use WP_REST_Request;

/**
 * Shared dispatch for one kind of recipe entity (item / block / block-modifier).
 *
 * Naming aligns with the runner / pipeline vocabulary (`Schema_Node`,
 * `*_Schema_Node`) — every concrete subclass owns the per-request dispatch
 * for one kind of recipe entity.
 *
 * Registration of the matching `register_rest_route()` call lives in
 * `Recipe_Rest_Controller::register_routes()`; this class is the
 * per-request dispatcher. `handle()` resolves a per-type handler from
 * `handler_classes()` and calls the matching operation method on a fresh
 * instance constructed with `(int $recipe_id, WP_REST_Request $request)`.
 */
abstract class Recipe_Entity_Routes {

	use Rest_Responses;

	/**
	 * Request param name carrying the entity-type discriminator. e.g.
	 * `'item_type'`, `'block_type'`, `'block_modifier_type'`.
	 *
	 * @return string
	 */
	abstract public static function type_param_name(): string;

	/**
	 * Map of type literal → fully-qualified handler class name. Each handler
	 * must accept `(int $recipe_id, WP_REST_Request $request)` in its
	 * constructor and expose the operations listed in `whitelisted_operations()`.
	 *
	 * @return array<string, class-string>
	 */
	abstract public static function handler_classes(): array;

	/**
	 * Operations this entity group accepts. e.g. `[ 'add', 'update', 'delete' ]`.
	 * Items will later add `'set-status'`.
	 *
	 * @return array<int, string>
	 */
	abstract public static function whitelisted_operations(): array;

	/**
	 * Human-readable label used in error messages. e.g. `'item'`, `'block'`,
	 * `'block-modifier'`. Hyphens stay readable in messages but are normalised
	 * to underscores in error codes (see `error_code_for`).
	 *
	 * @return string
	 */
	abstract public static function entity_label(): string;

	/**
	 * REST callback. Resolves a per-type handler and dispatches the
	 * requested operation. Referenced from `Recipe_Rest_Controller` as
	 * `[ Items_Routes::class, 'dispatch' ]` (or the matching subclass).
	 *
	 * Named `dispatch` (not `handle`) because entity-grouping routes don't
	 * directly handle the request body — they look up a per-type handler
	 * instance and forward to it. The simple, non-entity-grouping routes
	 * (`Get_Recipe_Route`, etc.) keep the `handle()` name.
	 *
	 * @param WP_REST_Request $request
	 * @return mixed Whatever the handler's operation method returns —
	 *               typically `WP_REST_Response|WP_Error`.
	 */
	public static function dispatch( WP_REST_Request $request ) {
		$recipe_id = (int) $request['recipe_id'];
		$type      = sanitize_key( (string) $request[ static::type_param_name() ] );
		$operation = sanitize_key( (string) $request['operation'] );
		$method    = str_replace( '-', '_', $operation );

		$handler = static::resolve_handler( $type, $recipe_id, $request );
		if ( is_wp_error( $handler ) ) {
			return $handler;
		}

		if ( ! method_exists( $handler, $method ) ) {
			return self::failure(
				sprintf(
					// translators: 1: operation name, 2: entity label, 3: entity type.
					esc_html_x( 'Operation "%1$s" is not supported for %2$s type "%3$s".', 'Recipe entity REST error', 'uncanny-automator' ),
					esc_html( $operation ),
					esc_html( static::entity_label() ),
					esc_html( $type )
				),
				400,
				'automator_rest_invalid_operation'
			);
		}

		return $handler->{$method}();
	}

	/**
	 * Validate the operation segment against `whitelisted_operations()`.
	 * Referenced from `Recipe_Rest_Controller` as
	 * `[ Items_Routes::class, 'validate_operation' ]`.
	 *
	 * @param mixed $value
	 * @return bool
	 */
	public static function validate_operation( $value ): bool {
		return in_array( $value, static::whitelisted_operations(), true );
	}

	/**
	 * Resolve a handler instance for the given type, or return a 400 error
	 * if no class is mapped.
	 *
	 * @param string          $type      The per-type discriminator (e.g. `action`, `filter`).
	 * @param int             $recipe_id Recipe id parsed from the URL.
	 * @param WP_REST_Request $request
	 * @return object|WP_Error
	 */
	private static function resolve_handler( string $type, int $recipe_id, WP_REST_Request $request ) {
		$class = static::handler_classes()[ $type ] ?? null;

		if ( null === $class || ! class_exists( $class ) ) {
			return self::failure(
				sprintf(
					// translators: 1: entity label, 2: entity type.
					esc_html_x( 'Unknown %1$s type "%2$s".', 'Recipe entity REST error', 'uncanny-automator' ),
					esc_html( static::entity_label() ),
					esc_html( $type )
				),
				400,
				static::error_code_for( 'unknown', 'type' )
			);
		}

		return new $class( $recipe_id, $request );
	}

	/**
	 * Build a snake_case error code from `entity_label()` (which may contain
	 * hyphens) so callers see `automator_rest_unknown_block_modifier_type`,
	 * not `automator_rest_unknown_block-modifier_type`.
	 *
	 * @param string $verb   e.g. `unknown`.
	 * @param string $suffix e.g. `type`.
	 * @return string
	 */
	private static function error_code_for( string $verb, string $suffix ): string {
		$normalised_label = str_replace( '-', '_', static::entity_label() );

		return sprintf( 'automator_rest_%s_%s_%s', $verb, $normalised_label, $suffix );
	}
}
