<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe\Routes;

use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\App\Transports\Restful\Recipe\Items\Action_Rest;
use Uncanny_Automator\App\Transports\Restful\Recipe\Items\Trigger_Rest;

/**
 * Items dispatcher — `/recipe/{id}/item/{item_type}/{op}`.
 *
 * Items are triggers and actions (the things that fire and the things
 * that run when triggers fire). Block-modifiers (filter
 * conditions, loop filters) are NOT items — they dispatch through
 * `Block_Modifiers_Routes` per the frontend contract.
 *
 * Registration of this route lives in `Recipe_Rest_Controller::register_routes()`.
 */
final class Items_Routes extends Recipe_Entity_Routes {

	/**
	 * Request param name carrying the item-type discriminator.
	 *
	 * @return string Always `'item_type'`.
	 */
	public static function type_param_name(): string {
		return 'item_type';
	}

	/**
	 * Human-readable label used in error messages.
	 *
	 * @return string Always `'item'`.
	 */
	public static function entity_label(): string {
		return 'item';
	}

	/**
	 * Operations the items route accepts.
	 *
	 * `set-status` is URL-form (with a hyphen); the dispatcher normalizes
	 * to the PHP method name `set_status` at invocation time.
	 *
	 * @return array<int, string>
	 */
	public static function whitelisted_operations(): array {
		return array( 'add', 'update', 'delete', 'set-status', 'duplicate' );
	}

	/**
	 * Map of item type → handler class.
	 *
	 * @return array<string, class-string>
	 */
	public static function handler_classes(): array {
		return array(
			Integration_Item_Types::TRIGGER => Trigger_Rest::class,
			Integration_Item_Types::ACTION  => Action_Rest::class,
		);
	}
}
