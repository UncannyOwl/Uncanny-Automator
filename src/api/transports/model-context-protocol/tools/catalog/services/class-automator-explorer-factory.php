<?php
/**
 * Automator Explorer Factory.
 *
 * Factory for creating and providing services needed by the Automator Explorer Tool.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Services;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Services\Trigger_Collector;
use Uncanny_Automator\Api\Services\Plan\Plan_Service;

/**
 * Factory for Automator Explorer services.
 */
class Automator_Explorer_Factory {

	/**
	 * Cached services.
	 *
	 * @var array
	 */
	private array $services = array();
	/**
	 * Create action collector.
	 *
	 * @return Action_Collector
	 */
	public static function create_action_collector(): Action_Collector {
		return new Action_Collector(
			\Uncanny_Automator\Api\Services\Action\Services\Action_Registry_Service::instance(),
			new \Uncanny_Automator\Api\Services\Plan\Plan_Service()
		);
	}
	/**
	 * Get action collector.
	 *
	 * @return Action_Collector
	 */
	public function get_action_collector(): Action_Collector {
		return $this->services['action_collector'] ??= self::create_action_collector();
	}
	/**
	 * Create trigger collector.
	 *
	 * @return Trigger_Collector
	 */
	public static function create_trigger_collector(): Trigger_Collector {
		return new Trigger_Collector(
			\Uncanny_Automator\Api\Services\Trigger\Services\Trigger_Registry_Service::get_instance(),
			new \Uncanny_Automator\Api\Services\Plan\Plan_Service()
		);
	}
	/**
	 * Get trigger collector.
	 *
	 * @return Trigger_Collector
	 */
	public function get_trigger_collector(): Trigger_Collector {
		return $this->services['trigger_collector'] ??= self::create_trigger_collector();
	}

	/**
	 * Get condition collector service.
	 *
	 * @return Condition_Collector
	 */
	public function get_condition_collector(): Condition_Collector {
		if ( ! isset( $this->services['condition_collector'] ) ) {
			$this->services['condition_collector'] = new Condition_Collector();
		}

		return $this->services['condition_collector'];
	}
}
