<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe\Blocks;

use Uncanny_Automator\App\Transports\Restful\Recipe\Traits\Filters\Filter_Field_Values_Before_Save;
use Uncanny_Automator\App\Transports\Restful\Recipe\Traits\Recipe_Entity;
use Uncanny_Automator\App\Transports\Restful\Recipe\Traits\Recipe_Entity_Properties;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Rest_Responses;
use Uncanny_Automator\App\Recipe_Builder\Block\Services\Block_Crud_Service;

use WP_REST_Response;
use WP_Error;

/**
 * Base class for recipe block REST mutation handlers.
 *
 * Blocks are the structural recipe entities that wrap other content
 * (filter, loop, delay_schedule). Unlike items and block-modifiers,
 * blocks are NOT integration-registered — Filter / Loop / Delay are
 * framework-owned, so this abstract does NOT mix in
 * `Integration_Entity_Properties`. The shared hook payload's `code`
 * argument is empty for block events (the hook traits' `method_exists`
 * fallback handles that).
 */
abstract class Recipe_Block_Rest {

	use Rest_Responses;
	use Recipe_Entity;
	use Recipe_Entity_Properties;
	use Filter_Field_Values_Before_Save;

	/**
	 * Whether to delete child items when removing a block.
	 *
	 * When true, all child items are deleted. When false, child items
	 * are moved up to the block's parent. Set from the request body's
	 * `delete_content` param at the start of `delete()`.
	 *
	 * Block-only — items + modifiers cascade-delete unconditionally.
	 *
	 * @var bool
	 */
	private bool $delete_content = false;

	/**
	 * The grouping this route serves; the shared field preparation applies
	 * the legacy `automator_field_values_before_save` filter to items only.
	 *
	 * @return string Always `'block'`.
	 */
	protected function event_prefix(): string {
		return 'block';
	}

	/**
	 * Setup — populates `type` from the request's `block_type` URL segment.
	 *
	 * @return void
	 */
	protected function setup(): void {
		$this->set_type(
			(string) $this->get_request()->get_param( 'block_type' )
		);
	}

	/**
	 * Add a block to recipe.
	 *
	 * Concrete subclasses return the block post id (int) on success or
	 * `WP_Error` on failure.
	 *
	 * @return int|WP_Error
	 */
	abstract protected function do_add_block();

	/**
	 * Update a block's fields.
	 *
	 * @return bool|WP_Error
	 */
	abstract protected function do_update_block();

	/**
	 * Delete a block.
	 *
	 * @return bool|WP_Error
	 */
	abstract protected function do_delete_block();

	/**
	 * Duplicate a block (and its whole subtree) within the recipe.
	 *
	 * Concrete subclasses delegate to `Recipe_Duplication_Service::duplicate_entity()`
	 * and map a `WP_Error` to a failure envelope; on success they return the new
	 * ROOT block id as an int.
	 *
	 * @return int|WP_Error New root block id, or `WP_Error`.
	 */
	abstract protected function do_duplicate_block();

	/**
	 * Add operation handler.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function add() {
		$raw_parent_id = $this->get_request()->get_param( 'parent_id' );
		$this->set_parent_id( ! empty( $raw_parent_id ) ? (int) $raw_parent_id : null );

		if ( empty( $this->get_parent_id() ) ) {
			return $this->failure( 'Missing parent_id.', 400, 'automator_missing_parent_id' );
		}

		$block_id = $this->do_add_block();
		if ( is_wp_error( $block_id ) ) {
			return $block_id;
		}

		$this->set_post_id( (int) $block_id );

		$data = array(
			'block_id' => $block_id,
			'recipe'   => $this->get_recipe_wire(),
		);

		return $this->success( $data );
	}

	/**
	 * Update operation handler.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function update() {
		$validation = $this->validate_block_id();
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$this->set_fields( $this->get_request()->get_param( 'fields' ) ?? array() );
		if ( empty( $this->get_fields() ) ) {
			return $this->failure( 'Missing fields.', 400, 'automator_missing_fields' );
		}

		$result = $this->do_update_block();
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
		$validation = $this->validate_block_id();
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// rest_sanitize_boolean() rather than a truthiness read: the string
		// "false" is a caller asking to keep the steps. The route declares the
		// field too, but a handler built directly never sees that coercion.
		$this->set_delete_content(
			rest_sanitize_boolean( $this->get_request()->get_param( 'delete_content' ) )
		);

		$result = $this->do_delete_block();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$data = array(
			'recipe' => $this->get_recipe_wire(),
		);

		return $this->success( $data );
	}

	/**
	 * Duplicate operation handler. Body: `{ block_id }`. Reports only the
	 * ROOT new block id; the duplicated subtree is reflected in the recipe wire.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function duplicate() {
		$validation = $this->validate_block_id();
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$result = $this->do_duplicate_block();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$new_id = (int) $result;
		$this->set_post_id( $new_id );

		$data = array(
			'block_id' => $new_id,
			'recipe'   => $this->get_recipe_wire(),
		);

		return $this->success( $data );
	}

	/**
	 * Validate the wire-shape `block_id` request param and its recipe.
	 *
	 * Internal storage normalises the id to `post_id` via
	 * `Recipe_Entity_Properties`. The recipe check runs here so it precedes
	 * every write in `update()`, `delete()` and `duplicate()`.
	 *
	 * @return WP_Error|null
	 */
	private function validate_block_id() {
		$raw_block_id = $this->get_request()->get_param( 'block_id' );
		$this->set_post_id( ! empty( $raw_block_id ) ? (int) $raw_block_id : null );

		if ( empty( $this->get_post_id() ) ) {
			return $this->failure( 'Missing block_id.', 400, 'automator_missing_block_id' );
		}

		return $this->validate_block_recipe();
	}

	/**
	 * Reject a block that does not belong to the recipe in the route.
	 *
	 * A missing block passes through so the per-operation handler reports it
	 * with its own status code.
	 *
	 * @return WP_Error|null
	 */
	private function validate_block_recipe() {
		$owned = Block_Crud_Service::instance()->verify_block_recipe( (int) $this->get_post_id(), (int) $this->get_recipe_id() );

		if ( is_wp_error( $owned ) && 'block_recipe_mismatch' === $owned->get_error_code() ) {
			return $this->failure( 'Block does not belong to this recipe.', 404, 'automator_block_recipe_mismatch' );
		}

		return null;
	}

	/**
	 * Set the delete-content flag from the request body.
	 *
	 * @param bool $delete_content
	 * @return void
	 */
	protected function set_delete_content( bool $delete_content ): void {
		$this->delete_content = $delete_content;
	}

	/**
	 * Get the delete-content flag.
	 *
	 * @return bool
	 */
	protected function get_delete_content(): bool {
		return $this->delete_content;
	}
}
