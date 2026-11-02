<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe\Items;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Recipe_Builder\Action\Services\Action_CRUD_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Portability\Recipe_Duplication_Service;
use WP_Error;

/**
 * Action REST handler.
 */
class Action_Rest extends Recipe_Item_Rest {

	/**
	 * Read one of the item's post meta values through its store.
	 *
	 * @param string $key Meta key.
	 *
	 * @return mixed The stored value, or '' when there is none.
	 */
	protected function item_meta( string $key ) {
		return Database::get_action_store()->get_meta( $this->get_post_id(), $key );
	}

	/**
	 * Add an action to recipe.
	 *
	 * @return array|WP_Error
	 */
	protected function do_add_item() {
		$recipe_id = $this->get_recipe_id();
		$parent_id = $this->get_parent_id() ?? $recipe_id;
		$path_code = (string) ( $this->get_request()->get_param( 'path_code' ) ?? '' );

		$service = Action_CRUD_Service::instance();

		if ( $parent_id !== $recipe_id && '' !== $path_code ) {
			$result = $service->add_to_block_path( $recipe_id, $this->get_code(), array(), $parent_id, $path_code );
		} else {
			$result = $service->add_to_recipe( $recipe_id, $this->get_code(), array(), $parent_id );
		}

		if ( is_wp_error( $result ) ) {
			$error_code = $result->get_error_code();
			return $this->failure( $result->get_error_message(), 400, '' !== $error_code ? $error_code : 'automator_add_action_to_recipe_failed' );
		}

		$action_id = (int) $result['action_id'];
		$this->set_post_id( $action_id );

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
		$store     = Database::get_action_store();
		$item_post = $store->get_wp_post( $this->get_post_id() );
		if ( ! $item_post ) {
			return $this->failure( 'Action not found.', 404, 'automator_action_not_found' );
		}

		$this->set_wp_post( $item_post );
		$this->set_code( (string) $store->get_meta( $this->get_post_id(), 'code' ) );
		$this->set_integration_code( (string) $store->get_meta( $this->get_post_id(), 'integration' ) );

		$action_definition = $this->get_item_definition();
		if ( null === $action_definition ) {
			return $this->failure( 'Action not found.', 404, 'automator_action_not_found' );
		}

		$config  = $this->prepare_field_config_for_services();
		$service = Action_CRUD_Service::instance();
		$result  = $service->update_action( $this->get_post_id(), $config, null, $this->resolve_parent_id(), null, $this->get_path_code(), $this->get_recipe_id() );

		if ( is_wp_error( $result ) ) {
			return $this->failure( $result->get_error_message(), 400, 'automator_update_action_failed' );
		}

		return array(
			'success'   => true,
			'item_data' => $result['action'] ?? array(),
			'config'    => $config,
		);
	}

	/**
	 * Delete action from recipe.
	 *
	 * @return bool|WP_Error
	 */
	protected function do_delete_item() {
		// Capture code from meta before deletion (needed for hook payload).
		$this->set_code( (string) Database::get_action_store()->get_meta( $this->get_post_id(), 'code' ) );

		$service = Action_CRUD_Service::instance();
		$result  = $service->delete_action( $this->get_post_id(), true, $this->get_recipe_id() );

		if ( is_wp_error( $result ) ) {
			return $this->failure( $result->get_error_message(), 400, 'automator_delete_action_failed' );
		}

		return true;
	}

	/**
	 * Toggle the action's post_status.
	 *
	 * @param bool $is_on true → publish, false → draft.
	 * @return array|WP_Error
	 */
	protected function do_set_status( bool $is_on ) {
		return Action_CRUD_Service::instance()->set_status(
			$this->get_post_id(),
			$this->get_recipe_id(),
			$is_on
		);
	}

	/**
	 * Duplicate this action (and any subtree) within the recipe.
	 *
	 * @return int|WP_Error New action id.
	 */
	protected function do_duplicate_item() {
		$service = new Recipe_Duplication_Service();
		$result  = $service->duplicate_entity( $this->get_recipe_id(), $this->get_post_id() );
		if ( is_wp_error( $result ) ) {
			return $this->failure( $result->get_error_message(), 400, 'automator_duplicate_action_failed' );
		}

		return (int) $result->get_root_id();
	}
}
