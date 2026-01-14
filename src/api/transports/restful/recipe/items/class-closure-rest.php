<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Restful\Recipe\Items;

use WP_Error;
use Uncanny_Automator\Api\Services\Closure\Services\Closure_Service;
use Uncanny_Automator\Api\Database\Database;
use Uncanny_Automator\Api\Transports\Restful\Recipe\Items\Traits\Filters\Filter_Before_Item_Created;
use Uncanny_Automator\Api\Transports\Restful\Recipe\Items\Traits\Filters\Filter_Field_Values_Before_Save;
use Uncanny_Automator\Api\Transports\Restful\Recipe\Items\Traits\Hooks\Deprecated_Hook_Add_Operations;
use Uncanny_Automator\Api\Transports\Restful\Recipe\Items\Traits\Hooks\Deprecated_Hook_Update_Operations;

/**
 * Closure REST handler.
 *
 * @package Uncanny_Automator\Api\Transports\Restful\Recipe\Items
 */
class Closure_Rest extends Recipe_Item_Rest {

	use Filter_Before_Item_Created;
	use Filter_Field_Values_Before_Save;
	use Deprecated_Hook_Add_Operations;
	use Deprecated_Hook_Update_Operations;

	/**
	 * The primary meta key for closures.
	 *
	 * @var string
	 */
	const CLOSURE_META_KEY = 'REDIRECTURL';

	/**
	 * Add a closure to recipe.
	 *
	 * @return array|WP_Error
	 */
	protected function do_add_item() {
		$post_type     = Database::get_closure_store()->get_post_type();
		$filter_result = $this->apply_creation_filter( $post_type, 'create_closure' );
		if ( true !== $filter_result ) {
			return $filter_result;
		}

		$service = Closure_Service::instance();
		$result  = $service->add_empty_to_recipe( $this->get_recipe_id() );

		if ( is_wp_error( $result ) ) {
			return $this->failure( $result->get_error_message(), 400, 'automator_add_closure_to_recipe_failed' );
		}

		$closure_id = (int) $result['closure_id'];

		// Deprecated hooks (must set item_id first).
		$this->set_item_id( $closure_id );
		$this->dispatch_deprecated_add_hooks();

		return array(
			'item_id'   => $closure_id,
			'item_data' => $result['closure'] ?? array(),
		);
	}

	/**
	 * Update closure config.
	 *
	 * @return array|WP_Error
	 */
	protected function do_update_item() {
		$item_post = Database::get_closure_store()->get_wp_post( (int) $this->get_item_id() );
		if ( ! $item_post ) {
			return $this->failure( 'Closure not found.', 404, 'automator_closure_not_found' );
		}

		// Set item properties for hooks.
		$this->set_item_post( $item_post );
		$this->set_item_code( self::CLOSURE_META_KEY );

		// Deprecated hooks.
		$this->dispatch_deprecated_before_update_hooks( self::CLOSURE_META_KEY );

		$config  = $this->prepare_field_config_for_services();
		$service = Closure_Service::instance();
		$result  = $service->add_closure( $this->get_recipe_id(), $config[ self::CLOSURE_META_KEY ] ?? '' );

		if ( is_wp_error( $result ) ) {
			return $this->failure( $result->get_error_message(), 400, 'automator_update_closure_failed' );
		}

		// Deprecated hooks.
		$this->dispatch_deprecated_option_updated_hooks( $config );

		return array(
			'success'   => true,
			'item_data' => $result['closure'] ?? array(),
		);
	}

	/**
	 * Delete closure from recipe.
	 *
	 * @return bool|WP_Error
	 */
	protected function do_delete_item() {
		// Set item_code for hooks.
		$this->set_item_code( self::CLOSURE_META_KEY );

		$service = Closure_Service::instance();
		$result  = $service->delete_recipe_closures( $this->get_recipe_id() );

		if ( is_wp_error( $result ) ) {
			return $this->failure( $result->get_error_message(), 400, 'automator_delete_closure_failed' );
		}

		$this->set_item_post_type( Database::get_closure_store()->get_post_type() );

		return true;
	}
}
