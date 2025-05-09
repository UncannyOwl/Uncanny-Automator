<?php

namespace Uncanny_Automator\Services\Recipe\Process\Throttle;

use Uncanny_Automator\Services\Recipe\Process\Throttle\Throttle_Strategy_Interface;
use Uncanny_Automator\Services\Recipe\Builder\Settings\Repository\Throttle_Repository_Interface;
use Uncanny_Automator\Services\Recipe\Process\Throttle\Time_Unit_Converter_Trait;
/**
 * Class User_Throttle_Strategy
 *
 * Implements user-level throttling using custom table
 */
class User_Throttle_Strategy implements Throttle_Strategy_Interface {

	use Time_Unit_Converter_Trait;

	/**
	 * @var Recipe_Throttle_Settings_Meta_DTO
	 */
	private $settings;

	/**
	 * @var Throttle_Repository_Interface
	 */
	private $throttle_repository;

	/**
	 * @var int
	 */
	private $recipe_id;

	/**
	 * Constructor
	 *
	 * @param Recipe_Throttle_Settings_Meta_DTO $settings
	 * @param Throttle_Repository_Interface $throttle_repository
	 */
	public function __construct(
		Recipe_Throttle_Settings_Meta_DTO $settings,
		Throttle_Repository_Interface $throttle_repository
	) {

		$this->settings            = $settings;
		$this->throttle_repository = $throttle_repository;
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
	 * @param int $user_id
	 *
	 * @return bool
	 */
	public function can_execute( $user_id = 0 ) {
		if ( $user_id <= 0 ) {
			return true;
		}

		if ( ! $this->settings->is_enabled() ) {
			return true;
		}

		$interval = $this->get_interval();

		if ( false === $interval ) {
			return true;
		}

		return $this->is_interval_elapsed( $interval, $user_id );
	}

	/**
	 * @param array $meta_data
	 *
	 * @return int|false
	 */
	private function get_interval() {

		$duration = (int) $this->settings->get_duration();
		$unit     = strtolower( $this->settings->get_unit() );

		if ( ! isset( $this->unit_to_seconds[ $unit ] ) ) {
			return false;
		}

		return $this->convert_to_seconds( $duration, $unit );
	}

	/**
	 * @param int $recipe_id
	 * @param string $meta_key
	 * @param int $interval
	 * @param int $user_id
	 *
	 * @return bool
	 */
	private function is_interval_elapsed( $interval, $user_id ) {

		if ( empty( $this->recipe_id ) ) {
			throw new \Exception( 'Recipe ID is not set. Use set_recipe_id() to set the recipe ID.' );
		}

		$current_time = time();

		$last_run = $this->throttle_repository->get_last_run( $this->recipe_id, $user_id );

		if ( $current_time - $last_run < $interval ) {
			return false;
		}

		$this->throttle_repository->update_last_run( $this->recipe_id, $user_id, $current_time );

		return true;
	}
}
