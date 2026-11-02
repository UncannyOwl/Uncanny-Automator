<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe\Routes;

use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Violation_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Settings\Enums\Recipe_Setting_Id;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Settings\Services\Recipe_Settings_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Status;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Services\Trigger_CRUD_Service;
use Uncanny_Automator\App\Transports\Restful\Recipe\Legacy_Rest_Hooks;
use Uncanny_Automator\App\Transports\Restful\Recipe\Traits\Recipe_Entity;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Rest_Responses;
use WP_REST_Request;

/**
 * Recipe settings REST handler.
 *
 * Routes the operations on `POST /uap/v2/recipe/{recipe_id}/settings/{operation}`:
 *   - `update`            — set one editable recipe-level setting (`{ setting_id, fields }`).
 *   - `set-status`        — toggle publish/draft (`{ recipe_status: 'publish' | 'draft' }`).
 *   - `set-type`          — set the recipe's user type (`{ recipe_type: 'user' | 'anonymous' }`).
 *   - `set-trigger-logic` — set the entry operator (`{ trigger_logic: 'all' | 'any' }`).
 *
 * Unlike `Recipe_Item_Rest`/`Recipe_Block_Rest`, the route has no
 * `type` segment (one recipe, not N item types), so this class
 * carries its own small static `dispatch()` instead of extending
 * `Recipe_Entity_Routes`. Operation → method name is a single
 * hyphen→underscore swap.
 *
 * Response shape (both operations): `{ recipe: <ModelRecipe wire shape> }`
 * — the canonical wire recipe via the bridge. No legacy `recipes_object`
 * (verified — every FE mutation response schema marks that key z.any()
 * with "no client code reads it" comments).
 *
 * Each operation that replaces a legacy REST API endpoint fires that endpoint's
 * hook, through `Legacy_Rest_Hooks`, after a successful save. Nothing else fires
 * here; the service fires `automator_event_recipe_changed`.
 */
final class Recipe_Settings_Rest {

	use Rest_Responses;
	use Recipe_Entity;

	/**
	 * URL-form operation slugs accepted by this route. Hyphens stay
	 * readable in the URL; the dispatcher normalises to underscores
	 * at method-invocation time.
	 *
	 * @var array<int,string>
	 */
	private const WHITELISTED_OPERATIONS = array( 'update', 'set-status', 'set-type', 'set-trigger-logic' );

	/**
	 * REST callback. Resolves the operation to a method on a fresh
	 * handler instance and forwards. Referenced from
	 * `Recipe_Rest_Controller::register_routes()` as
	 * `[ Recipe_Settings_Rest::class, 'dispatch' ]`.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function dispatch( WP_REST_Request $request ) {
		$recipe_id = (int) $request['recipe_id'];
		$operation = sanitize_key( (string) $request['operation'] );

		if ( ! self::validate_operation( $operation ) ) {
			return self::failure(
				sprintf(
					// translators: %s is the operation slug that was requested.
					esc_html_x( 'Operation "%s" is not supported.', 'Recipe settings REST error', 'uncanny-automator' ),
					esc_html( $operation )
				),
				400,
				'automator_rest_invalid_operation'
			);
		}

		$method  = str_replace( '-', '_', $operation );
		$handler = new self( $recipe_id, $request );

		return $handler->{$method}();
	}

	/**
	 * Operation-whitelist validator. Referenced from
	 * `Recipe_Rest_Controller` as the route's `operation` arg validator.
	 *
	 * @param mixed $value URL-form operation slug (sanitize_key already applied by the
	 *                     time this is called as a WP_REST_Request arg validator).
	 */
	public static function validate_operation( $value ): bool {
		return in_array( $value, self::WHITELISTED_OPERATIONS, true );
	}

	/**
	 * `update` operation — set one editable recipe-level setting.
	 *
	 * Body: `{ setting_id: string, fields: array | json-string }`
	 * (the FE currently sends `fields` JSON-stringified to match its
	 * legacy `set-setting` shape; we accept both forms).
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update() {
		$setting_id = sanitize_key( (string) ( $this->get_request()->get_param( 'setting_id' ) ?? '' ) );

		if ( '' === $setting_id ) {
			return $this->failure( esc_html_x( 'Missing setting_id.', 'Recipe settings REST error', 'uncanny-automator' ), 400, 'automator_missing_setting_id' );
		}
		if ( ! Recipe_Setting_Id::is_editable( $setting_id ) ) {
			return $this->failure(
				sprintf(
					// translators: %s is the recipe-setting ID that was rejected.
					esc_html_x( 'Setting "%s" is not editable.', 'Recipe settings REST error', 'uncanny-automator' ),
					esc_html( $setting_id )
				),
				400,
				'automator_setting_not_editable'
			);
		}

		$fields_raw = $this->get_request()->get_param( 'fields' );
		$fields     = is_string( $fields_raw ) ? json_decode( $fields_raw, true ) : $fields_raw;
		if ( ! is_array( $fields ) || empty( $fields ) ) {
			return $this->failure( esc_html_x( 'Missing or invalid fields.', 'Recipe settings REST error', 'uncanny-automator' ), 400, 'automator_missing_fields' );
		}

		$result = Recipe_Settings_Service::instance()->set_setting( $this->get_recipe_id(), $setting_id, $fields );
		if ( is_wp_error( $result ) ) {
			return $this->failure( $result->get_error_message(), 400, $result->get_error_code() );
		}

		$data = array( 'recipe' => $this->get_recipe_wire() );
		$this->fire_legacy_setting_updated( $setting_id, $data );

		return $this->success( $data );
	}

	/**
	 * `set-status` operation — toggle publish/draft.
	 *
	 * Body: `{ recipe_status: 'publish' | 'draft' }` — matches the wire
	 * `recipe_status` field returned by `Recipe_Wire_Serializer`.
	 *
	 * A publish transition that fails validation returns HTTP 422 with the
	 * FE violations envelope (`data` = exactly `{ violations, recipe }`,
	 * strict-parsed) via `$this->rejection_from_error()`; drafting never validates.
	 * Generic failures keep the WP_Error 400 shape.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_status() {
		$recipe_status = sanitize_key( (string) ( $this->get_request()->get_param( 'recipe_status' ) ?? '' ) );
		if ( ! in_array( $recipe_status, array( Recipe_Status::PUBLISH, Recipe_Status::DRAFT ), true ) ) {
			return $this->failure(
				esc_html_x( 'Missing or invalid recipe_status (must be publish or draft).', 'Recipe settings REST error', 'uncanny-automator' ),
				400,
				'automator_invalid_status'
			);
		}
		$is_recipe_on = Recipe_Status::is_recipe_on( $recipe_status );

		$result = Recipe_Settings_Service::instance()->set_status( $this->get_recipe_id(), $is_recipe_on );
		if ( is_wp_error( $result ) ) {
			if ( Recipe_Violation_Service::REJECTION_CODE === $result->get_error_code() ) {
				return $this->rejection_from_error( $result );
			}
			return $this->failure( $result->get_error_message(), 400, $result->get_error_code() );
		}

		$data = array( 'recipe' => $this->get_recipe_wire() );
		Legacy_Rest_Hooks::action(
			'automator_recipe_status_updated',
			array(
				$this->get_recipe_id(),
				$this->get_recipe_id(),
				$is_recipe_on ? Recipe_Status::PUBLISH : Recipe_Status::DRAFT,
				array_merge( array( 'success' => true ), $data ),
			)
		);

		return $this->success( $data );
	}

	/**
	 * `set-type` operation — set the recipe's user type.
	 *
	 * Body: `{ recipe_type: 'user' | 'anonymous' }`. The new `src/app` home for
	 * the recipe-type write; supersedes the legacy `change_post_recipe_type`
	 * endpoint (src/core), pending the FE repoint.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_type() {
		$recipe_type = sanitize_key( (string) ( $this->get_request()->get_param( 'recipe_type' ) ?? '' ) );
		$settings    = Recipe_Settings_Service::instance();
		$before      = $settings->get_recipe_type_or_null( $this->get_recipe_id() );

		$result = $settings->set_recipe_type( $this->get_recipe_id(), $recipe_type );
		if ( is_wp_error( $result ) ) {
			return $this->failure( $result->get_error_message(), 400, $result->get_error_code() );
		}

		$data = array( 'recipe' => $this->get_recipe_wire() );
		// As in 7.7.1, the hook fires only when the type changed.
		if ( true === $result && $before !== $recipe_type ) {
			Legacy_Rest_Hooks::action( 'automator_recipe_type_updated', array( $this->get_recipe_id(), $recipe_type, array_merge( array( 'success' => true ), $data ) ) );
		}

		return $this->success( $data );
	}

	/**
	 * `set-trigger-logic` operation — set the entry operator (all/any).
	 *
	 * Body: `{ trigger_logic: 'all' | 'any' }` (case-insensitive; anything else
	 * is rejected — stricter than the legacy endpoint, which persisted any
	 * string unvalidated). The new `src/app` home for the trigger-logic write;
	 * supersedes the legacy `triggers_change_logic` endpoint (src/core),
	 * pending the FE repoint. Persistence + validation live in
	 * `Trigger_CRUD_Service::set_trigger_logic()`; the store owns the write.
	 * It fires no legacy hook, because the endpoint it supersedes fired none.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_trigger_logic() {
		$trigger_logic = sanitize_text_field( (string) ( $this->get_request()->get_param( 'trigger_logic' ) ?? '' ) );

		$result = Trigger_CRUD_Service::instance()->set_trigger_logic( $this->get_recipe_id(), $trigger_logic );
		if ( is_wp_error( $result ) ) {
			return $this->failure( $result->get_error_message(), 400, $result->get_error_code() );
		}

		return $this->success( array( 'recipe' => $this->get_recipe_wire() ) );
	}

	/**
	 * Fire the hook of the legacy endpoint that saved this setting.
	 *
	 * The legacy API saved the per-user and the total run limits through their own
	 * endpoints and had none for the throttle, so a throttle save fires nothing.
	 *
	 * @param string $setting_id The saved setting.
	 * @param array  $data       The route's response data.
	 *
	 * @return void
	 */
	private function fire_legacy_setting_updated( string $setting_id, array $data ): void {
		$settings = Recipe_Settings_Service::instance();

		switch ( $setting_id ) {
			case Recipe_Setting_Id::RECIPE_TIMES_PER_USER:
				$hook  = 'automator_recipe_completions_allowed_updated';
				$value = $settings->get_times_per_user( $this->get_recipe_id() );
				break;
			case Recipe_Setting_Id::RECIPE_TOTAL_TIMES:
				$hook  = 'automator_recipe_max_completions_allowed_updated';
				$value = $settings->get_total_times( $this->get_recipe_id() );
				break;
			default:
				return;
		}

		Legacy_Rest_Hooks::action( $hook, array( $this->get_recipe_id(), $value, array_merge( array( 'success' => true ), $data ) ) );
	}
}
