<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Popularity;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;

/**
 * Site-wide usage census for a catalog scope, from 8.0.0 storage.
 *
 * The one place that knows which store counts which scope. Trigger and
 * action scopes count through their store's usage_census_by_code();
 * filter_condition and loop_filter through the block-modifier store, keyed
 * by the parent block type. filter_condition here is the block-modifier rows
 * only: the draining legacy actions_conditions leg is unioned by
 * Entity_Popularity_Calculator, because that is a transition concern, not a
 * storage one.
 *
 * @package Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Popularity
 */
final class Scope_Usage_Counts {

	/**
	 * Census per "INTEGRATION:CODE" for the scope; empty for an unknown scope.
	 *
	 * @since 8.0.0
	 *
	 * @param string $scope Scope constant (Integration_Item_Types or Block_Modifier_Types).
	 *
	 * @return array<string, array{published: int, all: int, last_used: int}>
	 *
	 * @throws \Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception When the store query fails.
	 */
	public static function census( string $scope ): array {
		switch ( $scope ) {
			case Integration_Item_Types::TRIGGER:
				return Database::get_recipe_trigger_store()->usage_census_by_code();
			case Integration_Item_Types::ACTION:
				return Database::get_action_store()->usage_census_by_code();
			case Block_Modifier_Types::FILTER_CONDITION:
				return Database::get_block_modifier_store()->usage_census_by_parent_block_type( Block_Type::FILTER );
			case Block_Modifier_Types::LOOP_FILTER:
				return Database::get_block_modifier_store()->usage_census_by_parent_block_type( Block_Type::LOOP );
		}

		return array();
	}
}
