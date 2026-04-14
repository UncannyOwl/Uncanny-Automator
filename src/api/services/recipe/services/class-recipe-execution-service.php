<?php
/**
 * Recipe Execution Service
 *
 * Validates and executes recipes with manual triggers.
 * Absorbs DB access and infrastructure concerns from the transport layer.
 *
 * @since 7.1.0
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Recipe\Services;

use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Id;
use Uncanny_Automator\Api\Database\Stores\WP_Recipe_Trigger_Store;
use WP_Error;

/**
 * Recipe_Execution_Service Class
 *
 * Handles recipe execution: validates the recipe exists, has a manual trigger,
 * and fires the execution action.
 */
class Recipe_Execution_Service {

	/**
	 * Singleton instance.
	 *
	 * @var Recipe_Execution_Service|null
	 */
	private static $instance = null;

	/**
	 * Trigger store.
	 *
	 * @var WP_Recipe_Trigger_Store
	 */
	private $trigger_store;

	/**
	 * Constructor.
	 *
	 * Allows dependency injection for testing. Production code can use instance()
	 * for backward compatibility with singleton pattern.
	 *
	 * @param WP_Recipe_Trigger_Store|null $trigger_store Optional trigger store instance.
	 */
	public function __construct( ?WP_Recipe_Trigger_Store $trigger_store = null ) {
		global $wpdb;
		$this->trigger_store = $trigger_store ?? new WP_Recipe_Trigger_Store( $wpdb );
	}

	/**
	 * Get singleton instance.
	 *
	 * @return Recipe_Execution_Service
	 */
	public static function instance(): Recipe_Execution_Service {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Execute a recipe by its ID.
	 *
	 * Validates the recipe exists, has a manual trigger, and fires the execution action.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return array|WP_Error Result array with recipe_id, recipe_title, recipe_status on success.
	 */
	public function execute_recipe( int $recipe_id ) {

		// Validate recipe exists.
		$recipe_post = get_post( $recipe_id );
		if ( ! $recipe_post || AUTOMATOR_POST_TYPE_RECIPE !== $recipe_post->post_type ) {
			return new WP_Error( 'recipe_not_found', 'Recipe not found or invalid type.' );
		}

		// Check for manual trigger.
		$recipe_id_vo = new Recipe_Id( $recipe_id );

		if ( ! $this->trigger_store->recipe_has_manual_trigger( $recipe_id_vo ) ) {
			return new WP_Error(
				'no_manual_trigger',
				'Recipe lacks a manual trigger. Add one with save_trigger using trigger_code "RECIPE_MANUAL_TRIGGER_ANON".'
			);
		}

		do_action( 'automator_pro_run_now_recipe', $recipe_id );

		return array(
			'recipe_id'     => $recipe_id,
			'recipe_title'  => get_the_title( $recipe_id ),
			'recipe_status' => 'initiated',
		);
	}
}
