<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Restful\Recipe\Items;

use WP_Error;
use Uncanny_Automator\Api\Services\Action\Services\Action_CRUD_Service;
use Uncanny_Automator\Api\Database\Database;
use Uncanny_Automator\Api\Transports\Restful\Recipe\Items\Traits\Filters\Filter_Before_Item_Created;
use Uncanny_Automator\Api\Transports\Restful\Recipe\Items\Traits\Filters\Filter_Field_Values_Before_Save;
use Uncanny_Automator\Api\Transports\Restful\Recipe\Items\Traits\Hooks\Deprecated_Hook_Add_Operations;
use Uncanny_Automator\Api\Transports\Restful\Recipe\Items\Traits\Hooks\Deprecated_Hook_Update_Operations;

/**
 * Action REST handler.
 *
 * @package Uncanny_Automator\Api\Transports\Restful\Recipe\Items
 */
class Action_Rest extends Recipe_Item_Rest {

	use Filter_Before_Item_Created;
	use Filter_Field_Values_Before_Save;
	use Deprecated_Hook_Add_Operations;
	use Deprecated_Hook_Update_Operations;

	/**
	 * Add an action to recipe.
	 *
	 * @return array|WP_Error
	 */
	protected function do_add_item() {
		$post_type     = Database::get_action_store()->get_post_type();
		$filter_result = $this->apply_creation_filter( $post_type, 'create_action' );
		if ( true !== $filter_result ) {
			return $filter_result;
		}

		$recipe_id = $this->get_recipe_id();
		$parent_id = $this->get_parent_id() ?? $recipe_id;

		$service = Action_CRUD_Service::instance();
		$result  = $service->add_to_recipe(
			$recipe_id,
			$this->get_item_code(),
			array(),
			array(),
			(int) $parent_id
		);

		if ( is_wp_error( $result ) ) {
			return $this->failure(
				$result->get_error_message(),
				400,
				'automator_add_action_to_recipe_failed'
			);
		}

		$action_id = (int) $result['action_id'];

		// Deprecated hooks (must set item_id first).
		$this->set_item_id( $action_id );
		$this->dispatch_deprecated_add_hooks();

		return array(
			'item_id'   => $action_id,
			'item_data' => $result['action'] ?? array(),
		);
	}

	/**
	 * Update action config.
	 *
	 * @return array|WP_Error
	 */
	protected function do_update_item() {
		$item_post = Database::get_action_store()->get_wp_post( (int) $this->get_item_id() );
		if ( ! $item_post ) {
			return $this->failure( 'Action not found.', 404, 'automator_action_not_found' );
		}

		$this->set_item_post( $item_post );
		$this->set_item_code_from_meta();
		$this->set_integration_code_from_meta();

		$action_definition = $this->get_item_definition();
		if ( null === $action_definition ) {
			return $this->failure( 'Action not found.', 404, 'automator_action_not_found' );
		}

		// Deprecated hooks.
		$this->dispatch_deprecated_before_update_hooks( $action_definition->to_array()['meta_code'] );

		$config  = $this->prepare_field_config_for_services();
		$service = Action_CRUD_Service::instance();
		$result  = $service->update_action( (int) $this->get_item_id(), $config, array() );

		if ( is_wp_error( $result ) ) {
			return $this->failure( $result->get_error_message(), 400, 'automator_update_action_failed' );
		}

		// Deprecated hooks.
		$this->dispatch_deprecated_option_updated_hooks( $config );

		return array(
			'success'   => true,
			'item_data' => $result['action'] ?? array(),
		);
	}

	/**
	 * Delete action from recipe.
	 *
	 * @return bool|WP_Error
	 */
	protected function do_delete_item() {
		// Capture item_code from meta before deletion (needed for hooks).
		$this->set_item_code_from_meta();

		$service = Action_CRUD_Service::instance();
		$result  = $service->delete_action( (int) $this->get_item_id(), true );

		if ( is_wp_error( $result ) ) {
			return $this->failure( $result->get_error_message(), 400, 'automator_delete_action_failed' );
		}

		$this->set_item_post_type( Database::get_action_store()->get_post_type() );

		return true;
	}
}
