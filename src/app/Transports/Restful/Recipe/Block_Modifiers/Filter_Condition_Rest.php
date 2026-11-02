<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe\Block_Modifiers;

use Uncanny_Automator\App\Recipe_Builder\Block\Services\Filter_Condition_Crud_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Portability\Recipe_Duplication_Service;
use WP_Error;

/**
 * Filter Condition REST handler.
 *
 * Concrete `Recipe_Block_Modifier_Rest` for the `filter_condition`
 * block-modifier type. Filter conditions are stored as
 * `uo-block-modifier` posts under a parent `uo-block` (filter) post.
 */
final class Filter_Condition_Rest extends Recipe_Block_Modifier_Rest {

	/**
	 * Add a filter condition to its parent filter block.
	 *
	 * Filter conditions require a `parent_id` (the filter block) — the
	 * abstract validates `modifier_code` and `integration_code`; this
	 * method validates the parent linkage.
	 *
	 * @return array|WP_Error Array with `block_modifier_id` and `modifier_data`,
	 *                        or `WP_Error` on failure.
	 */
	protected function do_add_modifier() {
		$block_id = $this->get_parent_id();
		if ( empty( $block_id ) ) {
			return $this->failure(
				'Missing parent_id. Filter conditions must be added to an existing filter block.',
				400,
				'automator_missing_parent_id'
			);
		}

		$service = Filter_Condition_Crud_Service::instance();
		$result  = $service->add_condition(
			$block_id,
			$this->get_integration_code(),
			$this->get_code()
		);

		if ( is_wp_error( $result ) ) {
			return $this->failure(
				$result->get_error_message(),
				400,
				'automator_add_filter_condition_failed'
			);
		}

		return array(
			'block_modifier_id' => (int) $result['id'],
			'modifier_data'     => $result,
		);
	}

	/**
	 * Update filter condition fields.
	 *
	 * @return array|WP_Error
	 */
	protected function do_update_modifier() {
		$config  = $this->prepare_field_config_for_services();
		$service = Filter_Condition_Crud_Service::instance();
		$result  = $service->update_condition(
			(int) $this->get_post_id(),
			array( 'fields' => $config )
		);

		if ( is_wp_error( $result ) ) {
			return $this->failure(
				$result->get_error_message(),
				400,
				'automator_update_filter_condition_failed'
			);
		}

		return array(
			'success'       => true,
			'modifier_data' => $result,
		);
	}

	/**
	 * Delete a filter condition.
	 *
	 * @return bool|WP_Error
	 */
	protected function do_delete_modifier() {
		$service = Filter_Condition_Crud_Service::instance();
		$result  = $service->delete_condition( (int) $this->get_post_id() );

		if ( is_wp_error( $result ) ) {
			return $this->failure(
				$result->get_error_message(),
				400,
				'automator_delete_filter_condition_failed'
			);
		}

		return true;
	}

	/**
	 * Duplicate this filter condition within the recipe.
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
				'automator_duplicate_filter_condition_failed'
			);
		}

		return (int) $result->get_root_id();
	}
}
