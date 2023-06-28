<?php
namespace Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Queries;

/**
 * The fat repository class for logs endpoint.
 *
 * @since 4.12
 */
class Recipe_Logs_Queries {

	/**
	 * @var \wpdb $db
	 */
	protected $db = null;

	/**
	 * @param \wpdb $db
	 */
	public function __construct( \wpdb $db ) {
		$this->db = $db;
	}

	/**
	 * @param int[] $params Accepts ['recipe_log_id','recipe_id', 'run_number'].
	 *
	 * @return mixed[]|object|void|null
	 */
	public function recipe_log_query( $params ) {

		if ( automator_db_view_exists( 'recipe' ) ) {
			return $this->db->get_row(
				$this->db->prepare(
					"SELECT * FROM {$this->db->prefix}uap_recipe_logs_view 
					WHERE automator_recipe_id = %d 
					AND run_number = %d
					AND recipe_log_id = %d
					ORDER BY recipe_date_time DESC LIMIT 0,100",
					$params['recipe_id'],
					$params['run_number'],
					$params['recipe_log_id']
				),
				ARRAY_A
			);
		}

		$recipe_query_result = (array) require __DIR__ . '/view-queries/recipe.php';

		if ( empty( $recipe_query_result ) ) {
			return array();
		}

		return $recipe_query_result;

	}

}
