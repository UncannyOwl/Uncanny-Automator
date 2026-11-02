<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe\Routes;

use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;
use Uncanny_Automator\App\Transports\Restful\Recipe\Block_Modifiers\Filter_Condition_Rest;
use Uncanny_Automator\App\Transports\Restful\Recipe\Block_Modifiers\Loop_Filter_Rest;

/**
 * Block-modifiers dispatcher — `/recipe/{id}/block-modifier/{block_modifier_type}/{op}`.
 *
 * Block-modifiers (filter conditions, loop filters) are conceptually
 * separate from items — they live under a parent block, not a recipe
 * directly. Their routes mount under `/block-modifier/` to mirror the
 * frontend contract; the legacy `/item/loop_filter/...` and
 * `/item/filter_condition/...` paths are gone.
 *
 * Registration of this route lives in `Recipe_Rest_Controller::register_routes()`.
 */
final class Block_Modifiers_Routes extends Recipe_Entity_Routes {

	/**
	 * Request param name carrying the block-modifier-type discriminator.
	 *
	 * @return string Always `'block_modifier_type'`.
	 */
	public static function type_param_name(): string {
		return 'block_modifier_type';
	}

	/**
	 * Human-readable label used in error messages.
	 *
	 * @return string Always `'block-modifier'`.
	 */
	public static function entity_label(): string {
		return 'block-modifier';
	}

	/**
	 * Operations the block-modifiers route accepts.
	 *
	 * @return array<int, string>
	 */
	public static function whitelisted_operations(): array {
		return array( 'add', 'update', 'delete', 'duplicate' );
	}

	/**
	 * Map of block-modifier type → handler class.
	 *
	 * @return array<string, class-string>
	 */
	public static function handler_classes(): array {
		return array(
			Block_Modifier_Types::FILTER_CONDITION => Filter_Condition_Rest::class,
			Block_Modifier_Types::LOOP_FILTER      => Loop_Filter_Rest::class,
		);
	}
}
