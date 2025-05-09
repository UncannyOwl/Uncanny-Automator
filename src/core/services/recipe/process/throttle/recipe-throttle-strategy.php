<?php
namespace Uncanny_Automator\Services\Recipe\Process\Throttle;

use Uncanny_Automator\Services\Recipe\Process\Throttle\Throttle_Strategy_Interface;
use Uncanny_Automator\Services\Recipe\Process\Throttle\Time_Unit_Converter_Trait;
/**
 * Class Recipe_Throttle_Strategy
 *
 * Implements recipe-level throttling using post meta
 */
class Recipe_Throttle_Strategy implements Throttle_Strategy_Interface {

	use Time_Unit_Converter_Trait;

	/**
	 * @var Recipe_Throttle_Settings_Meta_DTO
	 */
	private $settings;

	/**
	 * @var string
	 */
	private $meta_key = 'field_recipe_throttle_last_run';

	/**
	 * @var int
	 */
	private $recipe_id;

	/**
	 * Constructor
	 *
	 * @param Recipe_Throttle_Settings_Meta_DTO $settings
	 */
	public function __construct( Recipe_Throttle_Settings_Meta_DTO $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Set the recipe ID
	 *
	 * @param int $recipe_id
	 */
	public function set_recipe_id( int $recipe_id ) {
		$this->recipe_id = $recipe_id;
	}

	/**
	 * @param int $recipe_id
	 * @param string $meta_key
	 * @param int $user_id Not used in recipe strategy
	 *
	 * @return bool
	 */
	public function can_execute( $user_id = 0 ) {

		if ( ! $this->settings->is_enabled() ) {
			return true;
		}

		$interval = $this->convert_to_seconds(
			$this->settings->get_duration(),
			$this->settings->get_unit()
		);

		if ( false === $interval ) {
			return true;
		}

		return $this->is_interval_elapsed( $interval );
	}

	/**
	 * @param array $meta_data
	 *
	 * @return int|false
	 */
	private function get_interval( $meta_data ) {

		$duration = $this->settings->get_duration();
		$unit     = $this->settings->get_unit();

		if ( ! isset( $this->unit_to_seconds[ $unit ] ) ) {
			return false;
		}

		return $this->convert_to_seconds( $duration, $unit );
	}

	/**
	 * @param int $recipe_id
	 * @param string $meta_key
	 * @param int $interval
	 *
	 * @return bool
	 */
	private function is_interval_elapsed( $interval ) {

		if ( empty( $this->recipe_id ) ) {
			throw new \Exception( 'Recipe ID is not set. Use set_recipe_id() to set the recipe ID.' );
		}

		// We're using the recipe post meta to store the last run timestamp for `Per Recipe` throttling.
		$last_run     = (int) get_post_meta( $this->recipe_id, $this->meta_key, true );
		$current_time = time();

		if ( $current_time - $last_run < $interval ) {
			return false;
		}

		// Update the last run timestamp.
		update_post_meta( $this->recipe_id, $this->meta_key, $current_time );
		return true;
	}
}
