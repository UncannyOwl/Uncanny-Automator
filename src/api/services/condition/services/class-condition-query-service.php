<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Condition\Services;

use Uncanny_Automator\Api\Database\Stores\Action_Condition_Store;
use Uncanny_Automator\Api\Services\Traits\Service_Response_Formatter;

/**
 * Condition Query Service - Handles condition data retrieval.
 *
 * Provides read-only operations for accessing condition data
 * from recipes, ensuring clean separation of concerns.
 *
 * @since 7.0.0
 */
class Condition_Query_Service {

	use Service_Response_Formatter;

	private Action_Condition_Store $repository;

	/**
	 * Constructor.
	 *
	 * @param Action_Condition_Store $repository Action condition store.
	 */
	public function __construct( Action_Condition_Store $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Get condition groups for recipe.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return array|\WP_Error Condition groups or error.
	 */
	public function get_recipe_conditions( int $recipe_id ) {
		try {
			$recipe = $this->repository->get_recipe( $recipe_id );
			if ( ! $recipe ) {
				return $this->error_response( 'condition_recipe_not_found', 'Recipe not found' );
			}

			$conditions = $recipe->get_recipe_action_conditions();
			if ( ! $conditions ) {
				return array(
					'recipe_id'        => $recipe_id,
					'condition_groups' => array(),
					'total_groups'     => 0,
				);
			}

			return array(
				'recipe_id'        => $recipe_id,
				'condition_groups' => $conditions->to_array(),
				'total_groups'     => $conditions->count_groups(),
				'total_conditions' => $conditions->count_conditions(),
			);

		} catch ( \Exception $e ) {
			return $this->error_response( 'condition_retrieval_failed', $e->getMessage() );
		}
	}
}
