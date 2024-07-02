<?php

namespace Uncanny_Automator\Services\CLI\Logs;

use Uncanny_Automator\Prune_Logs;
use WP_CLI;

/**
 * Class Prune_Command
 *
 * A WP-CLI command to prune logs based on a specified number of days.
 *
 * @package Uncanny_Automator\Services\CLI\Logs
 */
class Prune_Command {

	/**
	 * @var Prune_Logs
	 */
	protected $logs_prunner;

	/**
	 * Command arguments and descriptions.
	 *
	 * @var array
	 */
	public static $args = array(
		'shortdesc' => 'Log entries older than the number of days specified will be purged.',
		'synopsis'  => array(
			array(
				'type'        => 'assoc',
				'optional'    => false,
				'name'        => 'days',
				'description' => 'Number of days (min 0.5).',
			),
		),
	);

	/**
	 * Option name for storing the last manual prune date.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'automator_last_manual_prune_date';

	/**
	 * Minimum allowed days for pruning.
	 *
	 * @var float
	 */
	const MIN_DAYS = 0.5;

	/**
	 * Constructor.
	 *
	 * @param Prune_Logs $logs_prunner Prune logs instance.
	 */
	public function __construct( Prune_Logs $logs_prunner ) {

		$this->logs_prunner = $logs_prunner;

	}

	/**
	 * Registers the WP-CLI command.
	 *
	 * @return void
	 */
	public function register_command() {
		WP_CLI::add_command( 'automator prune', array( $this, 'execute_prune' ), self::$args );
	}

	/**
	 * Executes the prune command ensuring it's run in WP-CLI context.
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	public function execute_prune( $args, $assoc_args ) {

		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			WP_CLI::error( 'This command can only run in WP-CLI context.' );

			return;
		}

		$this->prune( $args, $assoc_args );

	}

	/**
	 * Prunes logs based on the specified number of days.
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 *
	 * @return void
	 */
	private function prune( $args, $assoc_args ) {

		$days = isset( $assoc_args['days'] ) ? $assoc_args['days'] : null;

		if ( ! $this->is_valid_days( $days ) ) {
			WP_CLI::error( 'Invalid input: --days value must be greater than ' . self::MIN_DAYS . ". Entered --days={$days}", true );
		}

		$this->log_message( "Pruning logs older than {$days} days." );

		// Begin prune.
		try {
			$recipe_logs = $this->get_prune_logs( $days );
			if ( count( $recipe_logs ) > 0 ) {
				$progress = \WP_CLI\Utils\make_progress_bar( 'Pruning logs', count( $recipe_logs ) );

				foreach ( $recipe_logs as $recipe_log ) {
					$this->logs_prunner->purge_logs( $recipe_log['automator_recipe_id'], $recipe_log['ID'], $recipe_log['run_number'] );
					$progress->tick();
				}

				$progress->finish();
				WP_CLI::success( 'Successfully pruned logs.' );
			}
		} catch ( \Exception $e ) {
			WP_CLI::error( $e->getMessage(), true );
		}

		update_option( self::OPTION_NAME, time() );
	}

	/**
	 * @param $days
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function get_prune_logs( $days ) {
		$dt_string   = $this->logs_prunner->get_datetime_string( (float) $days );
		$recipe_logs = $this->logs_prunner->get_recipe_logs_from_date( $dt_string );

		if ( count( $recipe_logs ) > 0 ) {
			WP_CLI::success( 'Found ' . count( $recipe_logs ) . ' logs to prune.' );
		} else {
			WP_CLI::warning( 'No logs found to prune.' );

			return array();
		}

		return $recipe_logs;
	}

	/**
	 * Validates the days parameter.
	 *
	 * @param mixed $days The days value to validate.
	 *
	 * @return bool
	 */
	private function is_valid_days( $days ) {
		return is_numeric( $days ) && (float) $days >= self::MIN_DAYS;
	}

	/**
	 * Logs a message with a timestamp.
	 *
	 * @param string $message The message to log.
	 *
	 * @return void
	 */
	private function log_message( $message ) {
		$timestamp = ( new \DateTime() )->format( 'Y-m-d H:i:s' );
		WP_CLI::log( "[$timestamp] $message" );
	}
}
