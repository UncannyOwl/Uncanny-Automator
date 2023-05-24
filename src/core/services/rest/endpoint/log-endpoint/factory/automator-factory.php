<?php
namespace Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Factory;

use Uncanny_Automator\Automator_Functions;
use Uncanny_Automator\Automator_Status;

/**
 * Class Automator_Factory
 *
 * This class is responsible for providing access to various Automator components, such as the Automator_Functions and Automator_Status objects.
 */
class Automator_Factory {

	/**
	 * An instance of the Automator_Functions class
	 *
	 * @var Automator_Functions Defaults to null.
	 */
	protected $automator_functions = null;

	/**
	 * @var Automator_Status Defaults to null.
	 */
	protected $logs_status = null;

	/**
	 * Automator_Factory constructor.
	 *
	 * @param Automator_Functions $automator_functions An instance of the Automator_Functions class
	 * @param Automator_Status $logs_status An instance of the Automator_Status class
	 */
	public function __construct(
		Automator_Functions $automator_functions,
		Automator_Status $logs_status
	) {
		$this->automator_functions = $automator_functions;
		$this->logs_status         = $logs_status;
	}

	/**
	 * Returns an instance of the Automator_Functions class
	 *
	 * @return Automator_Functions An instance of the Automator_Functions class
	 */
	public function root() {
		return $this->automator_functions;
	}

	/**
	 * Returns an instance of the Automator_Functions class, accessing the db property
	 *
	 * @return \Uncanny_Automator\Automator_DB_Handler The db property of the Automator_Functions class
	 */
	public function db() {
		return $this->root()->db;
	}

	/**
	 * Returns an instance of the Automator_Functions class, accessing the db->api property
	 *
	 * @return \Uncanny_Automator\Automator_DB_Handler_Api The db->api property of the Automator_Functions class
	 */
	public function db_api() {
		return $this->db()->api;
	}

	/**
	 * Returns an instance of the Automator_Status class
	 *
	 * @return Automator_Status An instance of the Automator_Status class
	 */
	public function status() {
		return $this->logs_status;
	}
}
