<?php

namespace Uncanny_Automator;

/**
 * Class Cron_Exceptions
 * @package Uncanny_Automator
 * @deprecated 3.0
 */
class A_Cron_Exceptions {
	/**
	 * Cron_Exceptions constructor.
	 */
	public function __construct() {
		// add_filter( 'uap_cron_action_exception', [ $this, 'add_cron_exception' ] );
	}

	/**
	 * @param $exceptions
	 *
	 * @return mixed
	 */
	public function add_cron_exception( $exceptions ) {
//		if ( ! in_array( 'pmpro_cron_expire_memberships', $exceptions ) ) {
//			$exceptions[] = 'pmpro_cron_expire_memberships';
//		}
//
//		return $exceptions;
	}
}
