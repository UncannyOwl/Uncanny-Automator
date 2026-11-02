<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe\Items;

use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\App\Recipe_Builder\Shared\Enums\Post_Status;
use Uncanny_Automator\App\Transports\Restful\Recipe\Legacy_Rest_Hooks;
use Uncanny_Automator\App\Integration_Catalog\Integration\Value_Objects\Integration_Item;
use Uncanny_Automator\App\Integration_Catalog\Services\Integration_Query_Service;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Field_Registry_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Violation_Service;
use Uncanny_Automator\App\Transports\Restful\Recipe\Traits\Filters\Filter_Field_Values_Before_Save;
use Uncanny_Automator\App\Transports\Restful\Recipe\Traits\Integration_Entity_Properties;
use Uncanny_Automator\App\Transports\Restful\Recipe\Traits\Recipe_Entity;
use Uncanny_Automator\App\Transports\Restful\Recipe\Traits\Recipe_Entity_Properties;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Rest_Responses;

use WP_REST_Response;
use WP_Error;

/**
 * Base class for recipe item REST mutation handlers.
 *
 * Items are triggers and actions (the things that fire and the things
 * that run when triggers fire).
 *
 * Internal storage uses the unified `Recipe_Entity_Properties` +
 * `Integration_Entity_Properties` traits (`post_id`, `type`, `code`,
 * `integration_code`); wire response keys keep the user-facing `item`
 * vocabulary via the per-route response shape.
 */
abstract class Recipe_Item_Rest {

	use Rest_Responses;
	use Recipe_Entity;
	use Recipe_Entity_Properties;
	use Integration_Entity_Properties;
	use Filter_Field_Values_Before_Save;

	/**
	 * The grouping this route serves; the shared field preparation applies
	 * the legacy `automator_field_values_before_save` filter to items only.
	 *
	 * @return string Always `'item'`.
	 */
	protected function event_prefix(): string {
		return 'item';
	}

	/**
	 * Setup — populates `type` from the request's `item_type` URL segment.
	 *
	 * @return void
	 */
	protected function setup(): void {
		$this->set_type(
			(string) $this->get_request()->get_param( 'item_type' )
		);
	}

	/**
	 * Add an item to the recipe.
	 *
	 * Concrete subclasses return `[ 'item_id' => int, 'item_data' => array ]`
	 * or a `WP_Error`.
	 *
	 * @return array|WP_Error
	 */
	abstract protected function do_add_item();

	/**
	 * Update an item.
	 *
	 * Concrete subclasses return `[ 'success' => true, 'item_data' => array, 'config' => array ]`,
	 * where `config` is the flat field config sent to the service, or a `WP_Error`.
	 *
	 * @return array|WP_Error
	 */
	abstract protected function do_update_item();

	/**
	 * Delete an item.
	 *
	 * @return bool|WP_Error
	 */
	abstract protected function do_delete_item();

	/**
	 * Toggle the item's enabled state.
	 *
	 * Concrete subclasses delegate to their CRUD service:
	 * `return {Service}::instance()->set_status( $this->get_post_id(), $this->get_recipe_id(), $is_on );`
	 *
	 * @param bool $is_on true → publish, false → draft.
	 * @return array|WP_Error
	 */
	abstract protected function do_set_status( bool $is_on );

	/**
	 * Duplicate an item (and its subtree, if any) within the recipe.
	 *
	 * Concrete subclasses delegate to `Recipe_Duplication_Service::duplicate_entity()`
	 * and map a `WP_Error` to a failure envelope; on success they return the new
	 * item id as an int.
	 *
	 * @return int|WP_Error New item id, or `WP_Error`.
	 */
	abstract protected function do_duplicate_item();

	/**
	 * Add operation handler.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function add() {
		$this->set_code( sanitize_text_field( (string) ( $this->get_request()->get_param( 'item_code' ) ?? '' ) ) );
		$this->set_integration_code( sanitize_text_field( (string) ( $this->get_request()->get_param( 'integration_code' ) ?? '' ) ) );
		$this->set_parent_id( $this->resolve_parent_id() );

		if ( '' === $this->get_code() ) {
			return $this->failure( 'Missing item_code.', 400, 'automator_missing_item_code' );
		}

		if ( '' === $this->get_integration_code() ) {
			return $this->failure( 'Missing integration_code.', 400, 'automator_missing_integration_code' );
		}

		$result = $this->do_add_item();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$post_id = (int) $result['item_id'];

		$this->set_post_id( $post_id );

		// Before the recipe is read back, as in 7.7.1, so a listener's meta write is in the response.
		$this->fire_legacy_created();

		$data = array(
			'item_id' => $post_id,
			'recipe'  => $this->get_recipe_wire(),
		);

		return $this->success( $data );
	}

	/**
	 * Update operation handler.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function update() {
		Legacy_Rest_Hooks::action( 'automator_recipe_before_options_update', array( $this->get_request() ) );

		$validation = $this->validate_item_id();
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Eagerly load the item's WP_Post so the deprecated hooks we still
		// fire below have access to it. Concrete handlers re-set this via
		// their typed Database stores; this preload is harmless.
		$loaded_post = get_post( $this->get_post_id() );
		if ( $loaded_post instanceof \WP_Post ) {
			$this->set_wp_post( $loaded_post );
			Legacy_Rest_Hooks::action( 'automator_recipe_before_update', array( $loaded_post, $this->get_request() ) );
		}
		$option_updated_heard                   = Legacy_Rest_Hooks::is_heard( 'automator_recipe_option_updated', 'automator_option_updated' );
		list( $meta_key, $before_update_value ) = $option_updated_heard ? $this->legacy_option_snapshot() : array( '', '' );

		$fields = $this->get_request()->get_param( 'fields' ) ?? array();

		$this->set_fields( $fields );
		if ( empty( $this->get_fields() ) ) {
			return $this->failure( 'Missing fields.', 400, 'automator_missing_fields' );
		}

		$result = $this->do_update_item();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$item_data = $result['item_data'] ?? array();

		// Before the recipe is read back, as in 7.7.1, so a listener's meta write is in the response.
		Legacy_Rest_Hooks::action( 'automator_recipe_option_updated_before_cache_is_cleared', array( get_post( $this->get_post_id() ), $this->get_recipe_id() ) );

		$data = array(
			'recipe' => $this->get_recipe_wire(),
		);

		if ( $option_updated_heard ) {
			$this->fire_legacy_update_complete( $item_data, $result['config'], $meta_key, $before_update_value );
		}

		return $this->success( $data );
	}

	/**
	 * Delete operation handler.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete() {
		$validation = $this->validate_item_id();
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$result = $this->do_delete_item();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$data = array(
			'recipe' => $this->get_recipe_wire(),
		);

		Legacy_Rest_Hooks::action( 'automator_recipe_item_deleted', array( $this->get_post_id(), $this->get_recipe_id(), array_merge( array( 'success' => true ), $data ) ) );

		return $this->success( $data );
	}

	/**
	 * Set-status operation handler. Toggles `post_status` between publish
	 * and draft for the underlying item post. Body: `{ item_id, is_item_on }`.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function set_status() {
		// Accept any value WP REST recognises as a boolean (real bool,
		// "true"/"false" strings, 0/1, "0"/"1"). Form-encoded bodies and
		// some client libraries deliver bools as strings; the strict
		// is_bool() check rejected those incorrectly.
		$raw_is_item_on = $this->get_request()->get_param( 'is_item_on' );
		if ( ! rest_is_boolean( $raw_is_item_on ) ) {
			return $this->failure(
				esc_html_x( 'Missing or invalid is_item_on (must be boolean).', 'REST API', 'uncanny-automator' ),
				400,
				'automator_invalid_status'
			);
		}
		$is_item_on = rest_sanitize_boolean( $raw_is_item_on );

		$validation = $this->validate_item_id();
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$result = $this->do_set_status( $is_item_on );
		if ( is_wp_error( $result ) ) {
			if ( Recipe_Violation_Service::REJECTION_CODE === $result->get_error_code() ) {
				return $this->rejection_from_error( $result );
			}
			$error_data = $result->get_error_data();
			$status     = is_array( $error_data ) && isset( $error_data['status'] ) ? (int) $error_data['status'] : 400;
			return $this->failure(
				$result->get_error_message(),
				$status,
				$result->get_error_code(),
				is_array( $error_data ) ? $error_data : array()
			);
		}

		$data = array(
			'recipe' => $this->get_recipe_wire(),
		);

		Legacy_Rest_Hooks::action(
			'automator_recipe_status_updated',
			array(
				$this->get_post_id(),
				$this->get_recipe_id(),
				$is_item_on ? Post_Status::PUBLISH : Post_Status::DRAFT,
				array_merge( array( 'success' => true ), $data ),
			)
		);

		return $this->success( $data );
	}

	/**
	 * Duplicate operation handler. Body: `{ item_id }`.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function duplicate() {
		$validation = $this->validate_item_id();
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$result = $this->do_duplicate_item();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$new_id = (int) $result;
		$this->set_post_id( $new_id );

		$data = array(
			'item_id' => $new_id,
			'recipe'  => $this->get_recipe_wire(),
		);

		// The legacy API duplicated actions only; its `post_ID` was the recipe.
		if ( Integration_Item_Types::ACTION === $this->get_type() ) {
			Legacy_Rest_Hooks::action(
				'automator_recipe_action_duplicated',
				array(
					$this->get_recipe_id(),
					array_merge(
						array(
							'success' => true,
							'post_ID' => $this->get_recipe_id(),
						),
						$data
					),
				)
			);
		}

		return $this->success( $data );
	}

	/**
	 * Validate the wire-shape `item_id` request param.
	 * Internal storage normalises this to `post_id` via the universal
	 * `Recipe_Entity_Properties` trait.
	 *
	 * @return WP_Error|null `WP_Error` on failure, `null` on success.
	 */
	private function validate_item_id() {
		$raw_item_id = $this->get_request()->get_param( 'item_id' );
		$this->set_post_id( ! empty( $raw_item_id ) ? (int) $raw_item_id : null );

		if ( empty( $this->get_post_id() ) ) {
			return $this->failure( 'Missing item_id.', 400, 'automator_missing_item_id' );
		}

		return null;
	}

	/**
	 * Resolve the parent ID for this item — request body first, falling
	 * back to the loaded item post's `post_parent` for updates that
	 * don't carry `parent_id` explicitly.
	 *
	 * @return int|null
	 */
	protected function resolve_parent_id(): ?int {
		$raw_parent_id = $this->get_request()->get_param( 'parent_id' );
		if ( ! empty( $raw_parent_id ) ) {
			return (int) $raw_parent_id;
		}

		$post = $this->get_wp_post();
		if ( null !== $post ) {
			return (int) $post->post_parent;
		}

		return null;
	}

	/**
	 * Look up the integration-catalog row for this item via
	 * `Integration_Query_Service`. Requires `code`, `integration_code`,
	 * and `type` to be set.
	 *
	 * Items-specific because it returns `Integration_Item`. The modifier
	 * abstract has no parallel; concrete modifier handlers don't need
	 * the catalog lookup today.
	 *
	 * @return Integration_Item|null
	 */
	protected function get_item_definition(): ?Integration_Item {
		$integration_code = $this->get_integration_code();
		$code             = $this->get_code();
		$type             = $this->get_type();

		if ( '' === $integration_code || '' === $code || '' === $type ) {
			return null;
		}

		return Integration_Query_Service::get_instance()->get_item(
			$integration_code,
			$code,
			$type
		);
	}

	/**
	 * Authoritative field definitions (option_code => definition) for this
	 * item, sourced from the registry. Feeds internal field-type resolution
	 * (textarea -> html/markdown for supports_tinymce/supports_markdown) on
	 * save so the sanitizer preserves rich-text content. Registry-sourced,
	 * never request-derived. Returns an empty map when the item's fields
	 * cannot be resolved (the resolution then no-ops).
	 *
	 * @return array<string, array>
	 */
	protected function authoritative_field_definitions(): array {
		$fields = Field_Registry_Service::instance()->get_item_fields(
			$this->get_integration_code(),
			$this->get_type(),
			$this->get_code()
		);

		if ( is_wp_error( $fields ) ) {
			return array();
		}

		$by_code = array();
		foreach ( $fields as $definition ) {
			if ( is_array( $definition ) && isset( $definition['option_code'] ) ) {
				$by_code[ (string) $definition['option_code'] ] = $definition;
			}
		}

		return $by_code;
	}

	/**
	 * Read one of the item's post meta values through its store.
	 *
	 * @param string $key Meta key.
	 *
	 * @return mixed The stored value, or '' when there is none.
	 */
	abstract protected function item_meta( string $key );

	/**
	 * The stored value of each key a save sent, keyed as it was sent.
	 *
	 * @since 8.0.0
	 *
	 * @param string[] $keys Keys the save sent.
	 *
	 * @return array<string, mixed>
	 */
	protected function saved_field_values( array $keys ): array {
		$values = array();
		foreach ( $keys as $key ) {
			$values[ $key ] = $this->item_meta( $key );
		}

		return $values;
	}

	/**
	 * Fire the legacy `automator_recipe_{trigger|action}_created` notice after an add.
	 *
	 * @return void
	 */
	private function fire_legacy_created(): void {
		$type = $this->get_type();
		if ( ! Integration_Item_Types::is_valid( $type ) ) {
			return;
		}

		Legacy_Rest_Hooks::action( "automator_recipe_{$type}_created", array( $this->get_post_id(), $this->get_code(), $this->get_request() ) );
	}

	/**
	 * The item's main option code and its stored value, read before the update writes.
	 *
	 * The legacy `automator_recipe_option_updated` passed an option code and that
	 * option's value before the save. Its code was whichever saved key came last;
	 * the item's main option code is the one a listener can rely on.
	 *
	 * @return array{0: string, 1: mixed} Option code ('' when the definition has none) and its value before the save.
	 */
	private function legacy_option_snapshot(): array {
		$this->set_code( (string) $this->item_meta( 'code' ) );
		$this->set_integration_code( (string) $this->item_meta( 'integration' ) );

		$definition = $this->get_item_definition();
		$meta_key   = null === $definition ? '' : (string) ( $definition->to_array()['meta_code'] ?? '' );

		return array( $meta_key, '' === $meta_key ? '' : $this->item_meta( $meta_key ) );
	}

	/**
	 * Fire the legacy notices that followed the cache clear of an option save, with the values the legacy API passed.
	 *
	 * `$meta_value` holds every key this save stored, its `_readable` and `_custom`
	 * companions included, read back from the store, so a value an event listener
	 * masked stays masked. `$item` is the post as saved: an update can publish or
	 * move it. The filter's return is not used: the response is built from the
	 * saved recipe.
	 *
	 * @param array  $item_data           The saved item, as the service returned it.
	 * @param array  $config              The flat field config sent to the service.
	 * @param string $meta_key            The item's main option code.
	 * @param mixed  $before_update_value That option's value before the save.
	 *
	 * @return void
	 */
	private function fire_legacy_update_complete( array $item_data, array $config, string $meta_key, $before_update_value ): void {
		$meta_value = $this->saved_field_values( array_map( 'strval', array_keys( $config ) ) );

		$item = get_post( $this->get_post_id() );
		Legacy_Rest_Hooks::action( 'automator_recipe_option_updated', array( $item, $meta_key, $meta_value, $before_update_value, $this->get_recipe_id(), array_merge( array( 'success' => true ), $item_data ) ) );
		Legacy_Rest_Hooks::filter( 'automator_option_updated', array( array( 'success' => true ), $item, $meta_key, $meta_value ) );
	}
}
