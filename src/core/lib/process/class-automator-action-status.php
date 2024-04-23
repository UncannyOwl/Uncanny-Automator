<?php

namespace Uncanny_Automator;

/**
 * Class Automator_Status
 *
 * @package Uncanny_Automator
 */
class Automator_Status {

	/**
	 * @var int
	 */
	const NOT_COMPLETED = 0;

	/**
	 * @var int
	 */
	const COMPLETED = 1;

	/**
	 * @var int
	 */
	const COMPLETED_WITH_ERRORS = 2;

	/**
	 * @var int
	 */
	const IN_PROGRESS = 5;

	/**
	 * @var int
	 */
	const CANCELLED = 7;

	/**
	 * @var int
	 */
	const SKIPPED = 8;

	/**
	 * @var int
	 */
	const DID_NOTHING = 9;

	/**
	 * @var int
	 */
	const COMPLETED_AWAITING = 10;

	/**
	 * @var int
	 */
	const COMPLETED_WITH_NOTICE = 11;

	/**
	 * @var int
	 */
	const QUEUED = 12;

	/**
	 *
	 */
	const IN_PROGRESS_WITH_ERROR = 13;

	/**
	 * The status for recipes that are stuck in progress.
	 *
	 * @var int
	 */
	const FAILED = 14;

	/**
	 * Action status name
	 *
	 * @param int $status
	 *
	 * @return string
	 */
	public static function name( $status ) {

		$status_names = self::get_all_statuses();

		$output = isset( $status_names[ $status ] ) ? $status_names[ $status ] : $status;

		return apply_filters( 'automator_status', $output, $status );
	}

	/**
	 * @return array
	 */
	public static function get_all_statuses() {
		return array(
			self::NOT_COMPLETED          => esc_attr_x( 'Not completed', 'Recipe log status', 'uncanny-automator' ),
			self::COMPLETED              => esc_attr_x( 'Completed', 'Recipe log status', 'uncanny-automator' ),
			self::COMPLETED_WITH_ERRORS  => esc_attr_x( 'Completed with errors', 'Recipe log status', 'uncanny-automator' ),
			self::IN_PROGRESS            => esc_attr_x( 'In progress', 'Recipe log status', 'uncanny-automator' ),
			self::CANCELLED              => esc_attr_x( 'Cancelled', 'Recipe log status', 'uncanny-automator' ),
			self::QUEUED                 => esc_attr_x( 'Queued', 'Recipe log status', 'uncanny-automator' ),
			self::SKIPPED                => esc_attr_x( 'Skipped', 'Recipe log status', 'uncanny-automator' ),
			self::DID_NOTHING            => esc_attr_x( 'Completed, did nothing', 'Recipe log status', 'uncanny-automator' ),
			self::COMPLETED_AWAITING     => esc_attr_x( 'Completed, awaiting', 'Recipe log status', 'uncanny-automator' ),
			self::COMPLETED_WITH_NOTICE  => esc_attr_x( 'Completed with notice', 'Recipe log status', 'uncanny-automator' ),
			self::FAILED                 => esc_attr_x( 'Failed', 'Recipe log status', 'uncanny-automator' ),
			self::IN_PROGRESS_WITH_ERROR => esc_attr_x( 'In progress with errors', 'Recipe log status', 'uncanny-automator' ),
		);
	}

	/**
	 * @return int[]
	 */
	public static function get_finished_statuses() {

		$finished_statuses = array(
			self::COMPLETED,
			self::COMPLETED_WITH_ERRORS,
			self::CANCELLED,
			self::SKIPPED,
			self::DID_NOTHING,
			self::COMPLETED_WITH_NOTICE,
			self::FAILED,
		);

		return apply_filters( 'automator_status_finished', $finished_statuses );
	}

	/**
	 * @return int[]
	 */
	public static function get_removable_statuses() {

		$removable_statuses = array(
			self::COMPLETED,
			self::CANCELLED,
			self::SKIPPED,
			self::DID_NOTHING,
			self::COMPLETED_WITH_NOTICE,
			self::FAILED,
		);

		return apply_filters( 'automator_status_removable', $removable_statuses );

	}

	/**
	 * @param $status
	 *
	 * @return mixed
	 */
	public static function get_class_name( $status ) {

		$label = apply_filters(
			'automator_status_get_class_name',
			array(
				self::NOT_COMPLETED          => 'not-completed',
				self::COMPLETED              => 'completed',
				self::COMPLETED_WITH_ERRORS  => 'completed-with-errors',
				self::IN_PROGRESS            => 'in-progress',
				self::SKIPPED                => 'skipped',
				self::DID_NOTHING            => 'completed-do-nothing',
				self::COMPLETED_AWAITING     => 'completed-awaiting',
				self::COMPLETED_WITH_NOTICE  => 'completed-with-notice',
				self::CANCELLED              => 'cancelled',
				self::QUEUED                 => 'queued',
				self::FAILED                 => 'failed',
				self::IN_PROGRESS_WITH_ERROR => 'in-progress-with-errors',
			),
			$status
		);

		return ! isset( $label[ $status ] ) ? $status : $label[ $status ];

	}

	/**
	 * @param $status
	 *
	 * @return bool
	 */
	public static function finished( $status ) {
		return in_array( $status, self::get_finished_statuses(), true );
	}
}
