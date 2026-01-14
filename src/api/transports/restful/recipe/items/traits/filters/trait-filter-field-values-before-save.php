<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Restful\Recipe\Items\Traits\Filters;

use Uncanny_Automator\Api\Components\Integration\Enums\Integration_Item_Types;

/**
 * Trait for preparing field config before saving recipe items.
 *
 * Provides common functionality for transforming and filtering config
 * before saving recipe items via CRUD services.
 *
 * @since 7.0
 */
trait Filter_Field_Values_Before_Save {

	/**
	 * Prepare field config for CRUD services.
	 *
	 * Transforms structured fields to flat config and applies filters.
	 *
	 * @return array The config array ready for services.
	 */
	protected function prepare_field_config_for_services(): array {
		$fields = $this->get_fields();
		$config = $this->flatten_fields( $fields );

		// New hooks: all item types.
		$config = $this->apply_fields_update_before_filters( $config );

		// Legacy filter: only for trigger, action, closure.
		$config = $this->apply_deprecated_field_values_filter( $config );

		return $config;
	}

	/**
	 * Apply new fields_before_save filters.
	 *
	 * Provides consistent parameters for all item types.
	 *
	 * @param array $config The flattened config.
	 * @return array Filtered config.
	 */
	private function apply_fields_update_before_filters( array $config ): array {
		$item_type = $this->get_item_type();

		/**
		 * Filters field config before saving any recipe item.
		 *
		 * @since 7.0
		 *
		 * @param array      $config    Flattened field config.
		 * @param int        $recipe_id Recipe ID.
		 * @param int|string $item_id   Item ID (int for posts, string for conditions).
		 * @param string     $item_type Item type (trigger, action, closure, filter_condition).
		 */
		$config = apply_filters(
			'automator_recipe_item_fields_update_before',
			$config,
			$this->get_recipe_id(),
			$this->get_item_id(),
			$item_type
		);

		/**
		 * Filters field config before saving specific item type.
		 *
		 * Dynamic hook: automator_recipe_{type}_fields_before_save
		 *
		 * @since 7.0
		 *
		 * @param array      $config    Flattened field config.
		 * @param int        $recipe_id Recipe ID.
		 * @param int|string $item_id   Item ID.
		 */
		$config = apply_filters(
			"automator_recipe_{$item_type}_fields_update_before",
			$config,
			$this->get_recipe_id(),
			$this->get_item_id()
		);

		return $config;
	}

	/**
	 * Apply deprecated filter for backwards compatibility.
	 *
	 * Only applies for trigger, action, and closure types (not loop_filter or filter_condition).
	 * These are the only types that historically used this filter.
	 *
	 * @param array $config The config array.
	 * @return array Filtered config.
	 */
	private function apply_deprecated_field_values_filter( array $config ): array {
		$item_type = $this->get_item_type();
		$item_post = $this->get_item_post();

		// Only apply for trigger, action, closure - the types that historically used this filter.
		$legacy_types = array(
			Integration_Item_Types::TRIGGER,
			Integration_Item_Types::ACTION,
			Integration_Item_Types::CLOSURE,
		);
		if ( ! in_array( $item_type, $legacy_types, true ) || null === $item_post ) {
			return $config;
		}

		/**
		 * Filters field values before they are saved.
		 *
		 * @since 4.x
		 * @deprecated 7.0 Use automator_recipe_item_fields_before_save instead.
		 *
		 * @param array    $config    The config array being saved.
		 * @param \WP_Post $item_post The item post object.
		 */
		return apply_filters_deprecated(
			'automator_field_values_before_save',
			array( $config, $item_post ),
			'7.0',
			'automator_recipe_item_fields_before_save',
			esc_html_x( 'The automator_field_values_before_save filter is deprecated. Use automator_recipe_item_fields_before_save or automator_recipe_{type}_fields_before_save instead.', 'REST API', 'uncanny-automator' )
		);
	}
}
