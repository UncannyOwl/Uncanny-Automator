<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe\Block_Modifiers;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Recipe_Builder\Block\Services\Block_Crud_Service;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Field_Registry_Service;
use Uncanny_Automator\App\Transports\Restful\Recipe\Traits\Filters\Filter_Field_Values_Before_Save;
use Uncanny_Automator\App\Transports\Restful\Recipe\Traits\Integration_Entity_Properties;
use Uncanny_Automator\App\Transports\Restful\Recipe\Traits\Recipe_Entity;
use Uncanny_Automator\App\Transports\Restful\Recipe\Traits\Recipe_Entity_Properties;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Rest_Responses;

use WP_REST_Response;
use WP_Error;

/**
 * Base class for recipe block-modifier REST mutation handlers.
 *
 * Block-modifiers (filter conditions, loop filters) are conceptually
 * their own grouping per the FE contract — parallel sibling of
 * `Recipe_Item_Rest`, NOT a subclass.
 *
 * Internal storage uses the unified `Recipe_Entity_Properties` +
 * `Integration_Entity_Properties` traits (`post_id`, `type`, `code`,
 * `integration_code`); wire response keys keep the user-facing
 * `block_modifier` vocabulary via the per-route response shape.
 */
abstract class Recipe_Block_Modifier_Rest {

	use Rest_Responses;
	use Recipe_Entity;
	use Recipe_Entity_Properties;
	use Integration_Entity_Properties;
	use Filter_Field_Values_Before_Save;

	/**
	 * The grouping this route serves; the shared field preparation applies
	 * the legacy `automator_field_values_before_save` filter to items only.
	 *
	 * @return string Always `'block_modifier'`.
	 */
	protected function event_prefix(): string {
		return 'block_modifier';
	}

	/**
	 * Authoritative field definitions for this modifier, keyed by option_code.
	 *
	 * Without this the trait's empty default makes internal type resolution a
	 * no-op, so a `supports_tinymce` textarea reaches the sanitizer as a plain
	 * textarea and loses its HTML.
	 *
	 * @return array<string, array>
	 */
	protected function authoritative_field_definitions(): array {
		$fields = Field_Registry_Service::instance()->get_block_modifier_fields(
			$this->get_integration_code(),
			$this->get_type(),
			$this->get_code()
		);

		if ( is_wp_error( $fields ) || ! is_array( $fields ) ) {
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
	 * Setup — populates `type` from the request's `block_modifier_type`
	 * URL segment.
	 *
	 * @return void
	 */
	protected function setup(): void {
		$this->set_type(
			(string) $this->get_request()->get_param( 'block_modifier_type' )
		);
	}

	/**
	 * Add a block-modifier to its parent block.
	 *
	 * Concrete subclasses return `[ 'block_modifier_id' => int, 'modifier_data' => array ]`
	 * or a `WP_Error`.
	 *
	 * @return array|WP_Error
	 */
	abstract protected function do_add_modifier();

	/**
	 * Update a block-modifier's fields.
	 *
	 * Concrete subclasses return `[ 'success' => true, 'modifier_data' => array ]`
	 * or a `WP_Error`.
	 *
	 * @return array|WP_Error
	 */
	abstract protected function do_update_modifier();

	/**
	 * Delete a block-modifier.
	 *
	 * @return bool|WP_Error
	 */
	abstract protected function do_delete_modifier();

	/**
	 * Duplicate a block-modifier within the recipe.
	 *
	 * Concrete subclasses delegate to `Recipe_Duplication_Service::duplicate_entity()`
	 * and map a `WP_Error` to a failure envelope; on success they return the
	 * new block-modifier id as an int.
	 *
	 * @return int|WP_Error New block-modifier id, or `WP_Error`.
	 */
	abstract protected function do_duplicate_modifier();

	/**
	 * Add operation handler.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function add() {
		$this->set_code( sanitize_text_field( (string) ( $this->get_request()->get_param( 'modifier_code' ) ?? '' ) ) );
		$this->set_integration_code( sanitize_text_field( (string) ( $this->get_request()->get_param( 'integration_code' ) ?? '' ) ) );
		$this->set_parent_id( $this->resolve_parent_id() );

		if ( '' === $this->get_code() ) {
			return $this->failure( 'Missing modifier_code.', 400, 'automator_missing_modifier_code' );
		}

		if ( '' === $this->get_integration_code() ) {
			return $this->failure( 'Missing integration_code.', 400, 'automator_missing_integration_code' );
		}

		$parent_error = $this->validate_parent_block_recipe();
		if ( is_wp_error( $parent_error ) ) {
			return $parent_error;
		}

		$result = $this->do_add_modifier();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$post_id = (int) $result['block_modifier_id'];

		$this->set_post_id( $post_id );

		$data = array(
			'block_modifier_id' => $post_id,
			'recipe'            => $this->get_recipe_wire(),
		);

		return $this->success( $data );
	}

	/**
	 * Update operation handler.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function update() {
		$validation = $this->validate_block_modifier_id();
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$this->hydrate_identity_from_store();

		$fields = $this->get_request()->get_param( 'fields' ) ?? array();
		$this->set_fields( $fields );
		if ( empty( $this->get_fields() ) ) {
			return $this->failure( 'Missing fields.', 400, 'automator_missing_fields' );
		}

		$result = $this->do_update_modifier();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$data = array(
			'recipe' => $this->get_recipe_wire(),
		);

		return $this->success( $data );
	}

	/**
	 * Delete operation handler.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete() {
		$validation = $this->validate_block_modifier_id();
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$result = $this->do_delete_modifier();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$data = array(
			'recipe' => $this->get_recipe_wire(),
		);

		return $this->success( $data );
	}

	/**
	 * Duplicate operation handler. Body: `{ block_modifier_id }`.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function duplicate() {
		$validation = $this->validate_block_modifier_id();
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$result = $this->do_duplicate_modifier();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$new_id = (int) $result;
		$this->set_post_id( $new_id );

		$data = array(
			'block_modifier_id' => $new_id,
			'recipe'            => $this->get_recipe_wire(),
		);

		return $this->success( $data );
	}

	/**
	 * Populate `code` + `integration_code` from the stored modifier.
	 *
	 * `add()` takes both from the request; `update()` carries only the id, so
	 * without this the field-prep pipeline resolves definitions against empty
	 * codes and silently falls back to no definitions.
	 *
	 * @return void
	 */
	private function hydrate_identity_from_store(): void {
		$modifier = Database::get_block_modifier_store()->get( (int) $this->get_post_id() );

		if ( ! is_array( $modifier ) ) {
			return;
		}

		$this->set_code( (string) ( $modifier['code'] ?? '' ) );
		$this->set_integration_code( (string) ( $modifier['integration_code'] ?? '' ) );
	}

	/**
	 * Validate the wire-shape `block_modifier_id` request param and its recipe.
	 * Internal storage normalises this to `post_id` via the universal
	 * `Recipe_Entity_Properties` trait. The recipe check runs here so it
	 * precedes every write in `update()`, `delete()` and `duplicate()`.
	 *
	 * @since 8.0.0 Refuses a modifier of another recipe.
	 *
	 * @return WP_Error|null `WP_Error` on failure, `null` on success.
	 */
	private function validate_block_modifier_id() {
		$raw = $this->get_request()->get_param( 'block_modifier_id' );
		$this->set_post_id( ! empty( $raw ) ? (int) $raw : null );

		if ( empty( $this->get_post_id() ) ) {
			return $this->failure( 'Missing block_modifier_id.', 400, 'automator_missing_block_modifier_id' );
		}

		return $this->validate_block_modifier_recipe();
	}

	/**
	 * Reject a modifier that does not belong to the recipe in the route.
	 *
	 * A missing modifier passes through so the per-operation handler reports
	 * it with its own status code, as `Recipe_Block_Rest` does for blocks.
	 *
	 * @since 8.0.0
	 *
	 * @return WP_Error|null
	 */
	private function validate_block_modifier_recipe() {
		$owned = Block_Crud_Service::instance()->verify_block_modifier_recipe( (int) $this->get_post_id(), (int) $this->get_recipe_id() );

		if ( is_wp_error( $owned ) && 'block_modifier_recipe_mismatch' === $owned->get_error_code() ) {
			return $this->failure( $owned->get_error_message(), 404, 'automator_block_modifier_recipe_mismatch' );
		}

		return null;
	}

	/**
	 * Reject an add whose parent block does not belong to the recipe in the route.
	 *
	 * A missing parent, or one that is no block, passes through so the
	 * per-type handler reports it with its own error; nothing is added either way.
	 *
	 * @since 8.0.0
	 *
	 * @return WP_Error|null
	 */
	private function validate_parent_block_recipe() {
		$parent_id = (int) $this->get_parent_id();
		if ( $parent_id <= 0 ) {
			return null;
		}

		$owned = Block_Crud_Service::instance()->verify_block_recipe( $parent_id, (int) $this->get_recipe_id() );

		if ( is_wp_error( $owned ) && 'block_recipe_mismatch' === $owned->get_error_code() ) {
			return $this->failure(
				sprintf(
					/* translators: %1$d Block ID, %2$d Recipe ID. */
					esc_html_x( 'Block %1$d does not belong to recipe %2$d.', 'REST API', 'uncanny-automator' ),
					$parent_id,
					(int) $this->get_recipe_id()
				),
				404,
				'automator_block_modifier_recipe_mismatch'
			);
		}

		return null;
	}

	/**
	 * Resolve the parent ID from the request body.
	 *
	 * Only reached from `add()`, before a post exists, so there is nothing to
	 * fall back to; re-parenting has its own endpoint (`Reorder_Route`).
	 *
	 * @return int|null
	 */
	protected function resolve_parent_id(): ?int {
		$raw_parent_id = $this->get_request()->get_param( 'parent_id' );

		return ! empty( $raw_parent_id ) ? (int) $raw_parent_id : null;
	}
}
