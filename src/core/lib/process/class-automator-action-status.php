<?php
namespace Uncanny_Automator;

/**
 * Class Automator_Status
 *
 * @package Uncanny_Automator
 */
class Automator_Status {

	/**
	 *
	 */
	const NOT_COMPLETED         = 0;
	/**
	 *
	 */
	const COMPLETED             = 1;
	/**
	 *
	 */
	const COMPLETED_WITH_ERRORS = 2;
	/**
	 *
	 */
	const IN_PROGRESS           = 5;
	/**
	 *
	 */
	const CANCELLED             = 7;
	/**
	 *
	 */
	const SKIPPED               = 8;
	/**
	 *
	 */
	const DID_NOTHING           = 9;
	/**
	 *
	 */
	const COMPLETED_AWAITING    = 10;
	/**
	 *
	 */
	const COMPLETED_WITH_NOTICE = 11;
	/**
	 *
	 */
	const QUEUED                = 12;

	/**
	 * Action status name
	 *
	 * @param int $status
	 *
	 * @return string
	 */
	public static function name( $status ) {

		$status_names = array(
			self::NOT_COMPLETED         => esc_attr_x( 'Not completed', 'Recipe log status', 'uncanny-automator' ),
			self::COMPLETED             => esc_attr_x( 'Completed', 'Recipe log status', 'uncanny-automator' ),
			self::COMPLETED_WITH_ERRORS => esc_attr_x( 'Completed with errors', 'Recipe log status', 'uncanny-automator' ),
			self::IN_PROGRESS           => esc_attr_x( 'In progress', 'Recipe log status', 'uncanny-automator' ),
			self::CANCELLED             => esc_attr_x( 'Cancelled', 'Recipe log status', 'uncanny-automator' ),
			self::QUEUED                => esc_attr_x( 'Queued', 'Recipe log status', 'uncanny-automator' ),
			self::SKIPPED               => esc_attr_x( 'Skipped', 'Recipe log status', 'uncanny-automator' ),
			self::DID_NOTHING           => esc_attr_x( 'Completed, did nothing', 'Recipe log status', 'uncanny-automator' ),
			self::COMPLETED_AWAITING    => esc_attr_x( 'Completed, awaiting', 'Recipe log status', 'uncanny-automator' ),
			self::COMPLETED_WITH_NOTICE => esc_attr_x( 'Completed with notice', 'Recipe log status', 'uncanny-automator' ),
		);

		$output = isset( $status_names[ $status ] ) ? $status_names[ $status ] : $status;

		return apply_filters( 'automator_status', $output, $status );

	}

	/**
	 * @return mixed|null
	 */
	public static function get_finished_statuses() {

		$finished_statuses = array(
			self::COMPLETED,
			self::COMPLETED_WITH_ERRORS,
			self::CANCELLED,
			self::SKIPPED,
			self::DID_NOTHING,
			self::COMPLETED_WITH_NOTICE,
		);

		return apply_filters( 'automator_status_finished', $finished_statuses );

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
				self::NOT_COMPLETED         => 'not-completed',
				self::COMPLETED             => 'completed',
				self::COMPLETED_WITH_ERRORS => 'completed-with-errors',
				self::IN_PROGRESS           => 'in-progress',
				self::SKIPPED               => 'skipped',
				self::DID_NOTHING           => 'completed-do-nothing',
				self::COMPLETED_AWAITING    => 'completed-awaiting',
				self::COMPLETED_WITH_NOTICE => 'completed-with-notice',
				self::CANCELLED             => 'cancelled',
				self::QUEUED                => 'queued',
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
