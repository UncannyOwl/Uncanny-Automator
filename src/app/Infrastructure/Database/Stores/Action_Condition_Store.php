<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Infrastructure\Database\Stores;

use Uncanny_Automator\App\Recipe_Builder\Recipe\Recipe;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Action_Conditions;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Store;
use WP_Error;

class Action_Condition_Store {

	private Recipe_Store $recipe_store;

	/**
	 * @param Recipe_Store $recipe_store
	 */
	public function __construct( Recipe_Store $recipe_store ) {
		$this->recipe_store = $recipe_store;
	}

	/**
	 * @param int $recipe_id
	 */
	public function get_recipe( int $recipe_id ) {
		return $this->recipe_store->get( $recipe_id );
	}

	/**
	 * @param Recipe $recipe
	 */
	public function save_recipe( Recipe $recipe ): void {
		$this->recipe_store->save( $recipe );
	}

	/**
	 * @param Recipe                   $recipe
	 * @param Recipe_Action_Conditions $conditions
	 *
	 * @return Recipe|WP_Error
	 */
	public function update_conditions( Recipe $recipe, Recipe_Action_Conditions $conditions ) {
		try {
			$config = $recipe->get_config();
			$config->action_conditions( $conditions->to_array() );

			$updated_recipe = new Recipe( $config );
			$this->recipe_store->save( $updated_recipe );

			return $updated_recipe;

		} catch ( \Throwable $exception ) {
			return new WP_Error( 'DOMAIN_SERVICE_EXCEPTION', $exception->getMessage() );
		}
	}
}
