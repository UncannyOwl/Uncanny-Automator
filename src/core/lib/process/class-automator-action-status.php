<?php
namespace Uncanny_Automator;

/**
 * Class Automator_Status
 *
 * @package Uncanny_Automator
 */
class Automator_Status {

	const NOT_COMPLETED         = 0;
	const COMPLETED             = 1;
	const COMPLETED_WITH_ERRORS = 2;
	const IN_PROGRESS           = 5;
	const CANCELLED             = 7;
	const SKIPPED               = 8;
	const DID_NOTHING           = 9;
	const COMPLETED_AWAITING    = 10;
	const COMPLETED_WITH_NOTICE = 11;

	/**
	 * Action status name
	 *
	 * @param  int $status
	 * @return string
	 */
	public static function name( $status ) {
		$output = $status;
		switch ( $status ) {
			case self::NOT_COMPLETED:
				$output = esc_attr__( 'Not completed', 'uncanny-automator' );
				break;
			case self::COMPLETED:
				$output = esc_attr__( 'Completed', 'uncanny-automator' );
				break;
			case self::COMPLETED_WITH_ERRORS:
				$output = esc_attr__( 'Completed with errors', 'uncanny-automator' );
				break;
			case self::IN_PROGRESS:
				$output = esc_attr__( 'In progress', 'uncanny-automator' );
				break;
			case self::CANCELLED:
				$output = esc_attr__( 'Cancelled', 'uncanny-automator' );
				break;
			case self::SKIPPED:
				$output = esc_attr__( 'Skipped', 'uncanny-automator' );
				break;
			case self::DID_NOTHING:
				$output = esc_attr__( 'Completed, did nothing', 'uncanny-automator' );
				break;
			case self::COMPLETED_AWAITING:
				$output = esc_attr__( 'Completed, awaiting', 'uncanny-automator' );
				break;
			case self::COMPLETED_WITH_NOTICE:
				$output = esc_attr__( 'Completed with notice', 'uncanny-automator' );
				break;
			default:
				$output = $status;
				break;
		}

		return apply_filters( 'automator_status', $output, $status );
	}

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
			),
			$status
		);

		return ! isset( $label[ $status ] ) ? $status : $label[ $status ];

	}

	public static function finished( $status ) {
		return in_array( $status, self::get_finished_statuses(), true );
	}

}
