<?php

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Flsupport_Pro_Helpers;
/**
 * Class Flsupport_Helpers
 *
 * @package Uncanny_Automator
 */
class Flsupport_Helpers {
	/**
	 * @var Flsupport_Helpers
	 */
	public $options;

	/**
	 * @var Fsupport_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Flsupport_Helpers constructor.
	 */
	public function __construct() {
		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param Flsupport_Helpers $options
	 */
	public function setOptions( Flsupport_Helpers $options ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->options = $options;
	}

	/**
	 * @param Fsupport_Pro_Helpers $pro
	 */
	public function setPro( Flsupport_Pro_Helpers $pro ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->pro = $pro;
	}

	public function get_person_by_id( $id ) {
		return \FluentSupport\App\Models\Person::where( 'id', $id )->first();
	}

	public function get_ticket_by_id( $ticket_id ) {
		return \FluentSupport\App\Models\Ticket::find( $ticket_id );
	}
}
