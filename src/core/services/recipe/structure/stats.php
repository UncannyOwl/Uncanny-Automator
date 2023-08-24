<?php
namespace Uncanny_Automator\Services\Recipe\Structure;

use Uncanny_Automator\Services\Recipe\Common;

/**
 * Represents the stats in the recipe object
 *
 * @package Uncanny_Automator\Services\Recipe\Structure
 * @since 5.0
 */
final class Stats implements \JsonSerializable {

	use Common\Trait_JSON_Serializer;
	use Common\Trait_Setter_Getter;

	protected $total_runs = 0;

	/**
	 * @var \Uncanny_Automator\Services\Recipe\Structure
	 */
	private static $recipe = null;

	public function __construct( \Uncanny_Automator\Services\Recipe\Structure $recipe ) {
		self::$recipe     = $recipe;
		$this->total_runs = $this->get_recipe_total_runs();
	}

	/**
	 * Retrieves the total recipe runs of all status.
	 *
	 * @return int
	 */
	private function get_recipe_total_runs() {

		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT count(*) as record_count 
				FROM {$wpdb->prefix}uap_recipe_log
					WHERE automator_recipe_id = %d",
				self::$recipe->get_recipe_id()
			)
		);

		return absint( $count );

	}
}
