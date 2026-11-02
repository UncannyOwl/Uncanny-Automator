<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe\Routes;

use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Transports\Restful\Recipe\Blocks\Delay_Block_Rest;
use Uncanny_Automator\App\Transports\Restful\Recipe\Blocks\Filter_Block_Rest;
use Uncanny_Automator\App\Transports\Restful\Recipe\Blocks\Loop_Block_Rest;

/**
 * Blocks dispatcher — `/recipe/{id}/block/{block_type}/{op}`.
 *
 * Blocks are the structural recipe entities that wrap other content
 * (filter, loop, delay_schedule). Block-MODIFIERS (filter conditions,
 * loop filters) are a separate grouping — see `Block_Modifiers_Routes`.
 *
 * Registration of this route lives in `Recipe_Rest_Controller::register_routes()`.
 */
final class Blocks_Routes extends Recipe_Entity_Routes {

	/**
	 * Request param name carrying the block-type discriminator.
	 *
	 * @return string Always `'block_type'`.
	 */
	public static function type_param_name(): string {
		return 'block_type';
	}

	/**
	 * Human-readable label used in error messages.
	 *
	 * @return string Always `'block'`.
	 */
	public static function entity_label(): string {
		return 'block';
	}

	/**
	 * Operations the blocks route accepts.
	 *
	 * @return array<int, string>
	 */
	public static function whitelisted_operations(): array {
		return array( 'add', 'update', 'delete', 'duplicate' );
	}

	/**
	 * Map of block type → handler class.
	 *
	 * @return array<string, class-string>
	 */
	public static function handler_classes(): array {
		return array(
			Block_Type::FILTER         => Filter_Block_Rest::class,
			Block_Type::LOOP           => Loop_Block_Rest::class,
			Block_Type::DELAY_SCHEDULE => Delay_Block_Rest::class,
		);
	}
}
