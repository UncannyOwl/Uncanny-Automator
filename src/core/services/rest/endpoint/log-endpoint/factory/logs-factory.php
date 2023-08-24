<?php
namespace Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Factory;

use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Resources\Action_Logs_Resources;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Resources\Loop_Logs_Resources;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Resources\Recipe_Logs_Resources;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Resources\Trigger_Logs_Resources;

/**
 * The cluster for recipe, trigger, action repositories.
 *
 * @since 4.12
 */
class Logs_Factory {

	/**
	 * @var Recipe_Logs_Resources
	 */
	protected $recipe_logs_resources = null;

	/**
	 * @var Trigger_Logs_Resources
	 */
	protected $trigger_logs_resources = null;

	/**
	 * @var Action_Logs_Resources
	 */
	protected $action_logs_resources = null;

	/**
	 * @var Loop_Logs_Resources
	 */
	protected $loop_logs_resources = null;

	public function __construct(
		Recipe_Logs_Resources $recipe_logs_resources,
		Trigger_Logs_Resources $trigger_logs_resources,
		Action_Logs_Resources $action_logs_resources,
		Loop_Logs_Resources $loop_logs_resources ) {

		$this->recipe_logs_resources  = $recipe_logs_resources;
		$this->trigger_logs_resources = $trigger_logs_resources;
		$this->action_logs_resources  = $action_logs_resources;
		$this->loop_logs_resources    = $loop_logs_resources;

	}

	/**
	 * @return Recipe_Logs_Resources
	 */
	public function recipe() {
		return $this->recipe_logs_resources;
	}

	/**
	 * @return Trigger_Logs_Resources
	 */
	public function trigger() {
		return $this->trigger_logs_resources;
	}

	/**
	 * @return Action_Logs_Resources
	 */
	public function action() {
		return $this->action_logs_resources;
	}

	public function loop() {
		return $this->loop_logs_resources;
	}
}
