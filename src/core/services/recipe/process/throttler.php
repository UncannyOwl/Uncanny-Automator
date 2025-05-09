<?php

namespace Uncanny_Automator\Services\Recipe\Process;

use Uncanny_Automator\Services\Recipe\Process\Throttle\Recipe_Throttle_Strategy;
use Uncanny_Automator\Services\Recipe\Process\Throttle\User_Throttle_Strategy;
use Uncanny_Automator\Services\Recipe\Builder\Settings\Repository\Throttle_Repository;
use Uncanny_Automator\Services\Recipe\Process\Throttle\Recipe_Throttle_Settings_Meta_DTO;
/**
 * Class Throttler.
 *
 * The main class that manages the throttling strategies for recipes.
 *
 * Consists of two strategies:
 * 1. Recipe_Throttle_Strategy: Manages recipe-level throttling
 * 2. User_Throttle_Strategy: Manages user-level throttling
 *
 * Consider this class as the glue code that ties the two strategies together.
 *
 * Manages throttling strategies for recipes
 */
class Throttler {

	/**
	 * @var Recipe_Throttle_Strategy
	 */
	private $recipe_strategy;

	/**
	 * @var User_Throttle_Strategy
	 */
	private $user_strategy;

	/**
	 * @var Recipe_Throttle_Settings_Meta_DTO
	 */
	private $settings;

	/**
	 * @var int
	 */
	private $recipe_id;

	/**
	 * Constructor
	 */
	public function __construct( int $recipe_id, array $meta ) {

		$this->recipe_id = $recipe_id;

		$this->settings = Recipe_Throttle_Settings_Meta_DTO::from_meta( $meta );

		if ( ! $this->settings ) {
			throw new \Exception( 'Invalid settings' );
		}

		$this->recipe_strategy = new Recipe_Throttle_Strategy(
			$this->settings
		);

		$this->recipe_strategy->set_recipe_id( $this->recipe_id );

		$this->user_strategy = new User_Throttle_Strategy(
			$this->settings,
			new Throttle_Repository( $GLOBALS['wpdb'] )
		);

		$this->user_strategy->set_recipe_id( $this->recipe_id );
	}

	/**
	 * Checks if execution is allowed based on throttling rules
	 *
	 * @param int $recipe_id
	 * @param string $meta_key
	 * @param int $user_id
	 *
	 * @return bool
	 */
	public function can_execute( $user_id = 0 ) {

		$mode = $this->settings->get_scope();

		if ( 'user' === $mode && $user_id > 0 ) {
			return $this->user_strategy->can_execute( $user_id );
		}

		return $this->recipe_strategy->can_execute();
	}
}
