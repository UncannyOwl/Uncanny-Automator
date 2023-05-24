<?php
namespace Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Resources;

use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Factory\Automator_Factory;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Queries\Recipe_Logs_Queries;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Utils\Formatters_Utils;

class Recipe_Logs_Resources {

	/**
	 * @var Recipe_Logs_Queries
	 */
	protected $recipe_logs_queries = null;

	/**
	 * @var Automator_Factory
	 */
	protected $automator_factory = null;

	/**
	 * @var Formatters_Utils
	 */
	protected $utils = null;

	public function __construct(
		Recipe_Logs_Queries $recipe_logs_queries,
		Formatters_Utils $utils,
		Automator_Factory $automator_factory
		) {

		$this->utils               = $utils;
		$this->recipe_logs_queries = $recipe_logs_queries;
		$this->automator_factory   = $automator_factory;

	}

	/**
	 * @param int[] $params
	 *
	 * @return mixed[]|object|void|null
	 */
	public function get_log( $params ) {
		return $this->recipe_logs_queries->recipe_log_query( $params );
	}

}
