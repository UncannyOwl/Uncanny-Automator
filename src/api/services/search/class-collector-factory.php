<?php
/**
 * Collector Factory.
 *
 * Factory for creating search collector instances.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Search;

use Uncanny_Automator\Api\Services\Plan\Plan_Service;
use Uncanny_Automator\Api\Services\Search\Collectors\Action_Collector;
use Uncanny_Automator\Api\Services\Search\Collectors\Condition_Collector;
use Uncanny_Automator\Api\Services\Search\Collectors\Loop_Filter_Collector;
use Uncanny_Automator\Api\Services\Search\Collectors\Loopable_Token_Collector;
use Uncanny_Automator\Api\Services\Search\Collectors\Trigger_Collector;

/**
 * Factory for search collector services.
 */
class Collector_Factory {

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
			new Plan_Service()
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
			\Uncanny_Automator\Api\Services\Trigger\Services\Trigger_Registry_Service::instance(),
			new Plan_Service()
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

	/**
	 * Get loop filter collector service.
	 *
	 * @return Loop_Filter_Collector
	 */
	public function get_loop_filter_collector(): Loop_Filter_Collector {
		if ( ! isset( $this->services['loop_filter_collector'] ) ) {
			$this->services['loop_filter_collector'] = new Loop_Filter_Collector();
		}

		return $this->services['loop_filter_collector'];
	}

	/**
	 * Get loopable token collector service.
	 *
	 * @return Loopable_Token_Collector
	 */
	public function get_loopable_token_collector(): Loopable_Token_Collector {
		if ( ! isset( $this->services['loopable_token_collector'] ) ) {
			$this->services['loopable_token_collector'] = new Loopable_Token_Collector();
		}

		return $this->services['loopable_token_collector'];
	}
}
