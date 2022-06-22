<?php

namespace Uncanny_Automator\Migrations;

/**
 * Class Migrate_Schedules.
 *
 * @package Uncanny_Automator
 */
class Migrate_Schedules extends Migration {

	const NEW_DATE_FORMAT = 'Y-m-d';

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct( '411_schedules_migration' );

		$this->add_filters_for_pro();
	}

	/**
	 * conditions_met
	 *
	 * Only run this migration if Automator Pro is older than 4.2
	 *
	 * @return void
	 */
	public function conditions_met() {
		return automator_pro_older_than( '4.2' );
	}

	/**
	 * migrate
	 *
	 * @return void
	 */
	public function migrate() {

		$recipes = Automator()->get_recipes_data( false );

		$this->check_recipes( $recipes );

		$this->complete();
	}

	/**
	 * add_filters_for_pro
	 *
	 * We add a filter for get_option( 'date_format' ) call that Automator Pro is doing
	 *
	 * @return void
	 */
	private function add_filters_for_pro() {
		// If Automator pro is enabled and older than 4.1, we need to intercept
		if ( automator_pro_older_than( '4.2' ) ) {
			add_filter( 'pre_option_date_format', array( $this, 'intercept_date_format_option_calls' ), 10, 3 );
		}
	}

	/**
	 * check_recipes
	 *
	 * @param  mixed $recipes
	 * @return void
	 */
	private function check_recipes( $recipes ) {

		if ( empty( $recipes ) ) {
			return;
		}

		foreach ( $recipes as $recipe ) {

			if ( empty( $recipe['actions'] ) ) {
				continue;
			}

			$this->check_actions( $recipe['actions'] );
		}

	}

	/**
	 * check_actions
	 *
	 * @param  mixed $actions
	 * @return void
	 */
	private function check_actions( $actions ) {

		foreach ( $actions as $action ) {

			if ( ! $this->has_schedule( $action ) ) {
				continue;
			}

			$this->convert_schedule( $action );
		}

	}

	/**
	 * has_schedule
	 *
	 * @param  mixed $action
	 * @return bool
	 */
	private function has_schedule( $action ) {

		if ( ! empty( $action['meta']['async_mode'] ) && 'schedule' === $action['meta']['async_mode'] ) {
			return true;
		}

		return false;
	}

	/**
	 * convert_schedule
	 *
	 * @param  mixed $action
	 * @return void
	 */
	private function convert_schedule( $action ) {

		$old_date = $action['meta']['async_schedule_date'];

		try {

			automator_log( 'Updating schedule for action: ' . $action['ID'], $this->name, true );
			automator_log( 'Old date: ' . $old_date, $this->name, true );

			$new_date = $this->convert_date( $old_date );

			automator_log( 'New date: ' . $new_date, $this->name, true );

			$this->update_schedule( $action['ID'], $new_date );

		} catch ( \Exception $e ) {
			automator_log( $e->getMessage(), $this->name, true );
		}

	}

	/**
	 * convert_date
	 *
	 * @param  string $old_date
	 * @return string
	 */
	public function convert_date( $old_date ) {

		$wp_date_format = get_option( 'date_format' );

		automator_log( 'WP date format: ' . $wp_date_format, $this->name, true );

		$date_time = \DateTime::createFromFormat( $wp_date_format, $old_date, wp_timezone() );

		if ( ! $date_time ) {
			throw new \Exception( 'Error extracting a timestamp from ' . $old_date . ' using format ' . $wp_date_format );
		}

		return $date_time->format( self::NEW_DATE_FORMAT );

	}

	/**
	 * update_schedule
	 *
	 * @param  mixed $post_id
	 * @param  mixed $date
	 * @return void
	 */
	private function update_schedule( $post_id, $date ) {
		update_post_meta( $post_id, 'async_schedule_date', $date );
	}

	/**
	 * intercept_date_format_option_calls
	 *
	 * @param  mixed $intercept
	 * @param  string $option
	 * @param  mixed $default
	 * @return mixed
	 */
	public function intercept_date_format_option_calls( $intercept, $option, $default ) {

		$backtrace = debug_backtrace( 2, 10 ); //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace

		foreach ( $backtrace as $function ) {
			if ( $this->called_by_automator_pro( $function ) ) {
				return self::NEW_DATE_FORMAT;
			}
		}

		return $intercept;
	}

	/**
	 * called_by_automator_pro
	 *
	 * Make sure that the option was requested by Automator Pro.
	 *
	 * @param  mixed $function
	 * @return bool
	 */
	public function called_by_automator_pro( $function ) {

		if ( 'get_schedule_seconds' !== $function['function'] ) {
			return false;
		}

		if ( false === strpos( $function['class'], 'Uncanny_Automator_Pro' ) ) {
			return false;
		}

		if ( false === strpos( $function['class'], 'Async_Actions' ) ) {
			return false;
		}

		return true;
	}

}

new Migrate_Schedules();
