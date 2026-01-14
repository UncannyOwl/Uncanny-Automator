<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Restful\Recipe\Items;

use Uncanny_Automator\Api\Transports\Restful\Recipe\Traits\Recipe_Entity;
use Uncanny_Automator\Api\Transports\Restful\Utilities\Traits\Rest_Responses;
use Uncanny_Automator\Api\Transports\Restful\Recipe\Items\Traits\Recipe_Item_Properties;
use Uncanny_Automator\Api\Transports\Restful\Recipe\Items\Traits\Hooks\Hook_Add_Operations;
use Uncanny_Automator\Api\Transports\Restful\Recipe\Items\Traits\Hooks\Hook_Update_Operations;
use Uncanny_Automator\Api\Transports\Restful\Recipe\Items\Traits\Hooks\Hook_Delete_Operations;
use Uncanny_Automator\Api\Transports\Restful\Recipe\Items\Traits\Hooks\Deprecated_Hook_Delete_Operations;
use Uncanny_Automator\Api\Transports\Restful\Recipe\Items\Traits\Legacy_Options_Builder;
use Uncanny_Automator\Api\Services\Integration\Integration_Query_Service;
use Uncanny_Automator\Api\Components\Integration\Value_Objects\Integration_Item;
use Uncanny_Automator\Api\Services\Field\Field_Service;

use WP_REST_Response;
use WP_Error;

/**
 * Base class for all recipe item REST mutation handlers.
 *
 * Concrete child classes MUST implement all required operations.
 */
abstract class Recipe_Item_Rest {

	use Rest_Responses;
	use Recipe_Entity;
	use Recipe_Item_Properties;
	use Hook_Add_Operations;
	use Hook_Update_Operations;
	use Hook_Delete_Operations;
	use Deprecated_Hook_Delete_Operations;
	use Legacy_Options_Builder;

	/**
	 * Setup recipe item specific properties.
	 *
	 * @return void
	 */
	protected function setup(): void {
		$this->set_item_type(
			$this->get_request()->get_param( 'item_type' )
		);
	}

	/**
	 * Add an item to recipe.
	 *
	 * @return array|WP_Error Array with 'item_id' and 'item_data' keys, or WP_Error.
	 */
	abstract protected function do_add_item();

	/**
	 * Update an item in recipe.
	 *
	 * @return array|WP_Error Array with 'success' and 'item_data' keys, or WP_Error.
	 */
	abstract protected function do_update_item();

	/**
	 * Delete an item from recipe.
	 *
	 * @return bool|WP_Error
	 */
	abstract protected function do_delete_item();

	/**
	 * Validate common fields for add operations.
	 *
	 * Helper method for add() implementations that need integration_code and item_code.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function add() {
		$this->dispatch_add_before_hooks();

		// Set class properties for add operations.
		$this->set_item_code( sanitize_text_field( $this->get_request()->get_param( 'item_code' ) ?? '' ) );
		$this->set_integration_code( sanitize_text_field( $this->get_request()->get_param( 'integration_code' ) ?? '' ) );
		$this->set_parent_id( $this->get_request()->get_param( 'parent_id' ) ?? null );

		if ( empty( $this->get_item_code() ) ) {
			return $this->failure( 'Missing item_code.', 400, 'automator_missing_item_code' );
		}

		if ( empty( $this->get_integration_code() ) ) {
			return $this->failure( 'Missing integration_code.', 400, 'automator_missing_integration_code' );
		}

		$result = $this->do_add_item();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Extract from standardized return.
		$item_id   = $result['item_id'];
		$item_data = $result['item_data'] ?? array();

		$this->set_item_id( $item_id );

		$data = array(
			'item_id'        => $item_id,
			'recipes_object' => $this->get_recipe_object_legacy(),
			'recipe'         => $this->get_recipe_object(),
		);

		$this->dispatch_add_complete_hooks( $item_data, $data['recipe'] );

		return $this->success( $data );
	}

	/**
	 * Validate common fields for update operations.
	 *
	 * Helper method for update() implementations that need item_code.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function update() {

		$this->dispatch_update_before_hooks();

		// Validate item ID.
		$validation = $this->validate_item_id();
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$fields = $this->get_request()->get_param( 'fields' ) ?? array();

		// Decode JSON string if necessary.
		if ( is_string( $fields ) ) {
			$fields = json_decode( $fields, true ) ?? array();
		}

		$this->set_fields( $fields );
		if ( empty( $this->get_fields() ) ) {
			return $this->failure( 'Missing fields.', 400, 'automator_missing_fields' );
		}

		$result = $this->do_update_item();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$item_data = $result['item_data'] ?? array();

		$data = array(
			'recipes_object' => $this->get_recipe_object_legacy(),
			'recipe'         => $this->get_recipe_object(),
		);

		$this->dispatch_update_complete_hooks( $item_data, $data['recipe'] );

		return $this->success( $data );
	}

	/**
	 * Validate common fields for delete operations.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete() {
		$this->dispatch_before_delete_hooks();

		// Validate item ID.
		$validation = $this->validate_item_id();
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Delete item.
		$result = $this->do_delete_item();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$data = array(
			'recipes_object' => $this->get_recipe_object_legacy(),
			'recipe'         => $this->get_recipe_object(),
		);

		// New hooks.
		$this->dispatch_delete_complete_hooks( $data['recipe'] );
		// Backwards compatibility.
		$this->dispatch_deprecated_delete_hooks( $data );

		return $this->success( $data );
	}

	/**
	 * Validate item ID.
	 *
	 * @return WP_Error|null Returns WP_Error on validation failure, null on success.
	 */
	private function validate_item_id() {
		$this->set_item_id( $this->get_request()->get_param( 'item_id' ) ?? null );
		if ( empty( $this->get_item_id() ) ) {
			return $this->failure( 'Missing item_id.', 400, 'automator_missing_item_id' );
		}
		return null;
	}

	/**
	 * Get item definition from Integration_Query_Service.
	 *
	 * Requires item_code and integration_code to be set before calling.
	 * Use set_item_code_from_meta() and set_integration_code_from_meta()
	 * to populate these values from post meta.
	 *
	 * @return Integration_Item|null The Integration_Item or null if not found.
	 */
	protected function get_item_definition(): ?Integration_Item {
		$integration_code = $this->get_integration_code();
		$item_code        = $this->get_item_code();

		if ( empty( $integration_code ) || empty( $item_code ) ) {
			return null;
		}

		return Integration_Query_Service::get_instance()->get_item(
			$integration_code,
			$item_code,
			$this->get_item_type()
		);
	}

	/**
	 * Get field service instance.
	 *
	 * @return Field_Service
	 */
	protected function get_field_service(): Field_Service {
		return Field_Service::instance();
	}

	/**
	 * Flatten fields from structured format to flat config.
	 *
	 * When item context is available, transforms field types (textarea → markdown/html)
	 * based on integration definitions and applies the deprecated legacy filters.
	 * The new automator_field_type filter is applied in Field_Sanitizer for all transports.
	 *
	 * @param array<string, array> $fields Structured fields from Zod schema.
	 * @return array<string, mixed> Flat config array for CRUD services.
	 */
	protected function flatten_fields( array $fields ): array {
		// Ensure item_code and integration_code are populated from meta if not already set.
		if ( empty( $this->get_item_code() ) && ! empty( $this->get_item_id() ) ) {
			$this->set_item_code_from_meta();
		}
		if ( empty( $this->get_integration_code() ) && ! empty( $this->get_item_id() ) ) {
			$this->set_integration_code_from_meta();
		}

		$item_code      = $this->get_item_code();
		$item_type      = $this->get_item_type();
		$legacy_options = array();

		// Transform field types at REST layer if we have item context.
		if ( ! empty( $item_code ) && ! empty( $item_type ) ) {
			$group_code = $this->get_item_meta_code();

			// Build legacy options.
			$legacy_options = $this->build_legacy_options_from_fields(
				$fields,
				$item_type,
				$item_code,
				$group_code
			);

			// Transform field types based on legacy options.
			// This also applies the deprecated legacy type filter.
			$this->transform_field_types( $fields, $legacy_options );
		}

		// Use simple flatten - types are already correct.
		$flat_config = $this->get_field_service()->flatten_fields( $fields, 'rest' );

		// Apply deprecated automator_sanitized_data filter for backwards compatibility.
		if ( ! empty( $legacy_options ) ) {
			$flat_config = $this->apply_deprecated_sanitized_data_to_config(
				$flat_config,
				$fields,
				$legacy_options
			);
		}

		return $flat_config;
	}

	/**
	 * Get the item meta code (primary meta key) from the item definition.
	 *
	 * This is the groupCode used in legacy options (e.g., 'LOGGING_DATA', 'URL_CONDITION').
	 *
	 * @return string The meta code, or empty string if not available.
	 */
	protected function get_item_meta_code(): string {
		$definition = $this->get_item_definition();
		if ( null === $definition ) {
			return '';
		}

		return $definition->to_array()['meta_code'] ?? '';
	}
}
