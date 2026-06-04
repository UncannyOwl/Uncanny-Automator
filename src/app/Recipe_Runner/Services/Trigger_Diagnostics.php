<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Services;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\Services\Properties;

/**
 * Trigger diagnostic properties — backtrace and engine identifier.
 *
 * Extracted from Trigger_Complete_Stage so the stage focuses on
 * trigger completion logic, not bookkeeping. Legacy facade proxies
 * (class-automator-recipe-process-complete.php) delegate here.
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Services
 * @since   7.3
 */
class Trigger_Diagnostics {

	/**
	 * Record backtrace and engine properties for a trigger run.
	 *
	 * @param array $args Trigger args (needs trigger_id, trigger_log_id, run_number).
	 *
	 * @return void
	 */
	public function record( array $args ): void {
		$this->add_backtrace_property( $args );
		$this->add_engine_property( $args );
	}

	/**
	 * Add backtrace property to trigger log.
	 *
	 * @param array $args Trigger args.
	 *
	 * @return void
	 */
	public function add_backtrace_property( array $args ): void {

		$default    = defined( 'AUTOMATOR_DEBUG_MODE' ) && AUTOMATOR_DEBUG_MODE;
		$is_enabled = Dispatcher::filter( 'automator_log_backtrace_property_enabled', $default, $args );

		if ( false === $is_enabled ) {
			return;
		}

		$stack_trace = explode( ' ', wp_debug_backtrace_summary() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_wp_debug_backtrace_summary

		$properties = new Properties();

		$properties->add_item(
			array(
				'type'       => 'code',
				'label'      => esc_html__( 'Trigger backtrace (for support)', 'uncanny-automator' ),
				'value'      => var_export( $stack_trace, true ), // phpcs:ignore
				'attributes' => array(
					'code_language' => 'PHP',
				),
			)
		);

		$properties->record_trigger_properties( array( 'args' => $args ) );
	}

	/**
	 * Add engine identifier property to trigger log.
	 *
	 * Records which execution engine processed this trigger so support
	 * can distinguish Recipe_Runner (Abstract_Trigger) from legacy facade path.
	 *
	 * @param array $args Trigger args — checks for 'engine' key.
	 *
	 * @return void
	 */
	public function add_engine_property( array $args ): void {

		// Guard: record_trigger_properties requires these keys.
		if ( empty( $args['trigger_id'] ) || empty( $args['trigger_log_id'] ) || ! isset( $args['run_number'] ) ) {
			return;
		}

		$engine = $args['engine'] ?? 'legacy';

		$properties = new Properties();

		$properties->add_item(
			array(
				'type'  => 'text',
				'label' => esc_html__( 'Execution engine', 'uncanny-automator' ),
				'value' => $engine,
			)
		);

		$properties->record_trigger_properties( array( 'args' => $args ) );
	}
}
