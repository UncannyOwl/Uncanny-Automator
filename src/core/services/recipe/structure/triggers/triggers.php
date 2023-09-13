<?php
namespace Uncanny_Automator\Services\Recipe\Structure\Triggers;

use Uncanny_Automator\Services\Recipe\Common;

/**
 * This class represents the collection of Triggers in the recipe object.
 *
 * @package Uncanny_Automator\Services\Recipe\Structure\Triggers
 *
 * @since 5.0
 */
final class Triggers implements \JsonSerializable {

	use Common\Trait_JSON_Serializer;
	use Common\Trait_Setter_Getter;

	protected $logic = 'all';
	protected $items = array();

	/**
	 * @var \Uncanny_Automator\Services\Recipe\Structure
	 */
	private static $recipe = null;

	public function __construct( \Uncanny_Automator\Services\Recipe\Structure $recipe ) {

		self::$recipe = $recipe;

		$this->hydrate_properties();

	}

	/**
	 * Hydrates the object properties.
	 *
	 * @return void
	 */
	private function hydrate_properties() {

		$meta = self::$recipe->meta();

		$trigger_items = array();

		// Trigger logic.
		$this->logic = isset( $meta['automator_trigger_logic'] )
			? $meta['automator_trigger_logic'] : 'all';

		$triggers = Automator()->get_recipe_data( 'uo-trigger', self::$recipe->get_recipe_id() );

		foreach ( $triggers as $trigger_item ) {

			$trigger = new Trigger\Trigger( self::$recipe );
			$trigger->hydrate_from( $trigger_item );

			$trigger_items[] = $trigger;

		}

		// Trigger items.
		$this->items = $trigger_items;

	}
}
