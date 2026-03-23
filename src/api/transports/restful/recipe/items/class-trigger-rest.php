<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Restful\Recipe\Items;

use WP_Error;
use Uncanny_Automator\Api\Services\Trigger\Services\Trigger_CRUD_Service;
use Uncanny_Automator\Api\Services\Trigger\Services\Trigger_Registry_Service;
use Uncanny_Automator\Api\Database\Database;
use Uncanny_Automator\Api\Transports\Restful\Recipe\Items\Traits\Filters\Filter_Before_Item_Created;
use Uncanny_Automator\Api\Transports\Restful\Recipe\Items\Traits\Filters\Filter_Field_Values_Before_Save;
use Uncanny_Automator\Api\Transports\Restful\Recipe\Items\Traits\Hooks\Deprecated_Hook_Add_Operations;
use Uncanny_Automator\Api\Transports\Restful\Recipe\Items\Traits\Hooks\Deprecated_Hook_Update_Operations;

/**
 * Trigger REST handler.
 *
 * @package Uncanny_Automator\Api\Transports\Restful\Recipe\Items
 */
class Trigger_Rest extends Recipe_Item_Rest {

	use Filter_Before_Item_Created;
	use Filter_Field_Values_Before_Save;
	use Deprecated_Hook_Add_Operations;
	use Deprecated_Hook_Update_Operations;

	/**
	 * Add a trigger to recipe.
	 *
	 * @return array|WP_Error
	 */
	protected function do_add_item() {
		$post_type     = Database::get_recipe_trigger_store()->get_post_type();
		$filter_result = $this->apply_creation_filter( $post_type, 'create_trigger' );
		if ( true !== $filter_result ) {
			return $filter_result;
		}

		$config = $this->build_trigger_config();
		if ( null === $config ) {
			return $this->failure( 'Trigger not found in registry.', 404, 'automator_trigger_not_found' );
		}

		$service = Trigger_CRUD_Service::instance();
		$result  = $service->add_to_recipe(
			$this->get_recipe_id(),
			$this->get_item_code(),
			$config
		);

		if ( is_wp_error( $result ) ) {
			return $this->failure( $result->get_error_message(), 400, 'automator_add_trigger_to_recipe_failed' );
		}

		$trigger_id = (int) $result['trigger_id'];

		// Deprecated hooks (must set item_id first).
		$this->set_item_id( $trigger_id );
		$this->dispatch_deprecated_add_hooks();

		return array(
			'item_id'   => $trigger_id,
			'item_data' => $result['trigger'] ?? array(),
		);
	}

	/**
	 * Update trigger config.
	 *
	 * @return array|WP_Error
	 */
	protected function do_update_item() {
		$item_post = Database::get_recipe_trigger_store()->get_wp_post( (int) $this->get_item_id() );
		if ( ! $item_post ) {
			return $this->failure( 'Trigger not found.', 404, 'automator_trigger_not_found' );
		}

		$this->set_item_post( $item_post );
		$this->set_item_code_from_meta();
		$this->set_integration_code_from_meta();

		// Deprecated hooks.
		$this->dispatch_deprecated_before_update_hooks( $this->resolve_meta_code() );

		$config = $this->prepare_field_config_for_services();

		$service = Trigger_CRUD_Service::instance();
		$result  = $service->update_trigger( (int) $this->get_item_id(), $config );

		if ( is_wp_error( $result ) ) {
			return $this->failure( $result->get_error_message(), 400, 'automator_update_trigger_failed' );
		}

		// Deprecated hooks.
		$this->dispatch_deprecated_option_updated_hooks( $config );

		return array(
			'success'   => true,
			'item_data' => $result['trigger'] ?? array(),
		);
	}

	/**
	 * Delete trigger from recipe.
	 *
	 * @return bool|WP_Error
	 */
	protected function do_delete_item() {
		// Capture item_code from meta before deletion (needed for hooks).
		$this->set_item_code_from_meta();

		$service = Trigger_CRUD_Service::instance();
		$result  = $service->remove_from_recipe( $this->get_recipe_id(), (int) $this->get_item_id() );

		if ( is_wp_error( $result ) ) {
			return $this->failure( $result->get_error_message(), 400, 'automator_delete_trigger_failed' );
		}

		$this->set_item_post_type( Database::get_recipe_trigger_store()->get_post_type() );

		return true;
	}

	/**
	 * Resolve meta_code from Integration_Query_Service.
	 *
	 * @return string
	 */
	private function resolve_meta_code(): string {
		$definition = $this->get_item_definition();
		if ( null === $definition ) {
			return '';
		}

		return $definition->to_array()['meta_code'] ?? '';
	}

	/**
	 * Build trigger configuration for CRUD service.
	 *
	 * @return array|null
	 */
	private function build_trigger_config(): ?array {
		$registry = Trigger_Registry_Service::get_instance()->get_trigger_by_code( $this->get_item_code() );
		if ( empty( $registry ) ) {
			return null;
		}

		return array(
			'integration'             => $this->get_integration_code(),
			'meta_code'               => $this->resolve_meta_code(),
			'sentence'                => $registry['sentence'] ?? '',
			'sentence_human_readable' => $registry['select_option_name'] ?? '',
			'type'                    => $registry['type'] ?? 'anonymous',
			'hook'                    => $registry['action'] ?? '',
		);
	}
}
