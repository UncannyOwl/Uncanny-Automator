<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Utilities;

/**
 * Helpers for building common `register_rest_route()` args entries.
 *
 * REST registration is the largest source of boilerplate in the
 * controller files — the same `required` + `type` + `sanitize_callback`
 * triplet repeats for every string and integer param. These static
 * helpers package the common shapes so the controllers stay focused on
 * the per-route differences (pattern, callback, validator wiring).
 *
 * Each helper returns an args partial that drops directly into the
 * `args` entry inside a `register_rest_route()` call:
 *
 *   register_rest_route(
 *       $namespace,
 *       $pattern,
 *       array(
 *           array(
 *               'methods'  => 'GET',
 *               'callback' => array( Some_Route::class, 'handle' ),
 *               'args'     => array(
 *                   'integration_code' => Rest_Arg::string(),
 *                   'item_code'        => Rest_Arg::string(),
 *               ),
 *           ),
 *       )
 *   );
 */
final class Rest_Arg {

	/**
	 * String arg — `sanitize_text_field`, optional validator.
	 *
	 * Most common Integrations-side pattern. Used when the URL pattern
	 * regex isn't strict enough on its own (e.g., codes that mix cases
	 * or contain underscores).
	 *
	 * @param callable|null $validate Optional `validate_callback`.
	 * @param bool          $required Defaults to required; pass `false`
	 *                                for optional request-body fields.
	 * @return array<string, mixed>
	 */
	public static function string( ?callable $validate = null, bool $required = true ): array {
		$arg = array(
			'required'          => $required,
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		);

		if ( null !== $validate ) {
			$arg['validate_callback'] = $validate;
		}

		return $arg;
	}

	/**
	 * Integer arg — `absint`, optional validator.
	 *
	 * `type => 'integer'` alone does NOT reject a non-numeric value: WP
	 * REST only substitutes its validating `rest_parse_request_arg` when
	 * `sanitize_callback` is absent, and this arg always sets one. With an
	 * explicit `sanitize_callback`, `absint` runs unconditionally and
	 * coerces anything non-numeric (e.g. `"abc"`) to `0` rather than
	 * rejecting it with a 400. Pass `$validate` when the caller needs a
	 * real rejection.
	 *
	 * @param callable|null $validate Optional `validate_callback`.
	 * @param bool          $required Defaults to required; pass `false`
	 *                                for optional request-body fields.
	 * @return array<string, mixed>
	 */
	public static function integer( ?callable $validate = null, bool $required = true ): array {
		$arg = array(
			'required'          => $required,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
		);

		if ( null !== $validate ) {
			$arg['validate_callback'] = $validate;
		}

		return $arg;
	}

	/**
	 * Required positive-integer arg — `absint` plus a built-in
	 * `> 0` validator. Saves inlining the same closure at every site
	 * that needs "positive integer or reject".
	 *
	 * @return array<string, mixed>
	 */
	public static function positive_int(): array {
		return self::integer(
			static function ( $value ): bool {
				return is_numeric( $value ) && (int) $value > 0;
			}
		);
	}

	/**
	 * URL-key arg — `sanitize_key`, optional validator.
	 *
	 * Defaults to not-required (the URL regex pattern enforces the
	 * value's shape at the routing layer — Recipe entity-grouping
	 * routes for `item_type` / `block_type` / `block_modifier_type`
	 * and `operation`). Pass `$required = true` for body / query
	 * params where schema validation needs the explicit declaration —
	 * the returned array then also declares `type: 'string'` so WP's
	 * required-param check has a typed value to report on.
	 *
	 * @param callable|null $validate Optional `validate_callback`.
	 * @param bool          $required Set true to mark the arg as
	 *                                required + typed string.
	 * @return array<string, mixed>
	 */
	public static function key( ?callable $validate = null, bool $required = false ): array {
		$arg = array(
			'sanitize_callback' => 'sanitize_key',
		);

		if ( $required ) {
			$arg['required'] = true;
			$arg['type']     = 'string';
		}

		if ( null !== $validate ) {
			$arg['validate_callback'] = $validate;
		}

		return $arg;
	}

	/**
	 * An optional boolean body field.
	 *
	 * Declares `type => boolean` so WordPress rejects a value
	 * `rest_is_boolean()` does not recognise with a 400 rather than
	 * silently coercing it, and sanitises the recognised forms
	 * (`"true"`, `"false"`, `"0"`, `"1"`, `0`, `1`) to a real bool.
	 *
	 * The explicit `validate_callback` is required to make that first
	 * claim true: `sanitize_callback` is also set, and WP REST only runs
	 * its own `rest_is_boolean()` check via the validating
	 * `rest_parse_request_arg` when `sanitize_callback` is absent.
	 * `rest_validate_request_arg` re-applies the `type => boolean` schema
	 * check independently of that substitution.
	 *
	 * @param bool $default_value Value used when the field is absent.
	 *
	 * @return array Argument definition for `register_rest_route()`.
	 */
	public static function boolean( bool $default_value = false ): array {
		return array(
			'required'          => false,
			'default'           => $default_value,
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'validate_callback' => 'rest_validate_request_arg',
		);
	}

	/**
	 * Validator-only arg.
	 *
	 * For params whose format is fully constrained by the URL regex but
	 * that still need a runtime check (e.g., `recipe_id` existence
	 * lookup against the post store).
	 *
	 * @param callable $validate `validate_callback`.
	 * @return array<string, callable>
	 */
	public static function validated( callable $validate ): array {
		return array( 'validate_callback' => $validate );
	}
}
