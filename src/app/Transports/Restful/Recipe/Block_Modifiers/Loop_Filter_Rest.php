<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe\Block_Modifiers;

use Uncanny_Automator\App\Recipe_Builder\Loop\Services\Filter\Filter_CRUD_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Portability\Recipe_Duplication_Service;
use WP_Error;

/**
 * Loop Filter REST handler.
 *
 * Concrete `Recipe_Block_Modifier_Rest` for the `loop_filter`
 * block-modifier type. `uo-block` (loop) parents store their filters as
 * `uo-block-modifier` rows, managed through `Filter_CRUD_Service`.
 */
final class Loop_Filter_Rest extends Recipe_Block_Modifier_Rest {

	/**
	 * Add a loop filter to its parent loop block.
	 *
	 * @return array|WP_Error
	 */
	protected function do_add_modifier() {
		$service = Filter_CRUD_Service::instance();
		$result  = $service->add_to_loop(
			(int) $this->get_parent_id(),
			$this->get_code(),
			$this->get_integration_code()
		);

		if ( is_wp_error( $result ) ) {
			return $this->failure(
				$result->get_error_message(),
				400,
				'automator_add_loop_filter_failed'
			);
		}

		return array(
			'block_modifier_id' => (int) $result['filter_id'],
			'modifier_data'     => $result['filter'] ?? array(),
		);
	}

	/**
	 * Update loop filter fields.
	 *
	 * Nothing downstream sanitizes: Filter_CRUD_Service array_replaces the
	 * incoming values and the store JSON-encodes them, so the shared
	 * field-prep pipeline is the only place it happens.
	 *
	 * @return array|WP_Error
	 */
	protected function do_update_modifier() {
		$service = Filter_CRUD_Service::instance();
		$result  = $service->update_filter(
			(int) $this->get_post_id(),
			$this->prepare_field_config_for_services()
		);

		if ( is_wp_error( $result ) ) {
			return $this->failure(
				$result->get_error_message(),
				400,
				'automator_update_loop_filter_failed'
			);
		}

		return array(
			'success'       => true,
			'modifier_data' => $result['filter'] ?? array(),
		);
	}

	/**
	 * Delete a loop filter.
	 *
	 * @return bool|WP_Error
	 */
	protected function do_delete_modifier() {
		$service = Filter_CRUD_Service::instance();
		$result  = $service->delete_filter( (int) $this->get_post_id(), true );

		if ( is_wp_error( $result ) ) {
			return $this->failure(
				$result->get_error_message(),
				400,
				'automator_delete_loop_filter_failed'
			);
		}

		return true;
	}

	/**
	 * Duplicate this loop filter within the recipe.
	 *
	 * @return int|WP_Error New block-modifier id.
	 */
	protected function do_duplicate_modifier() {
		$service = new Recipe_Duplication_Service();
		$result  = $service->duplicate_entity( $this->get_recipe_id(), $this->get_post_id() );
		if ( is_wp_error( $result ) ) {
			return $this->failure(
				$result->get_error_message(),
				400,
				'automator_duplicate_loop_filter_failed'
			);
		}

		return (int) $result->get_root_id();
	}
}
