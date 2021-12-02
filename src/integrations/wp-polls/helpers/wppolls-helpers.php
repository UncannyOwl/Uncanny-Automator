<?php

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Wppolls_Pro_Helpers;

/**
 * Class Buddypress_Helpers
 *
 * @package Uncanny_Automator
 */
class Wppolls_Helpers {
	/**
	 * @var Wppolls_Helpers
	 */
	public $options;

	/**
	 * @var Wppolls_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Buddypress_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param Wppolls_Pro_Helpers $pro
	 */
	public function setPro( Wppolls_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param Wppolls_Helpers $options
	 */
	public function setOptions( Wppolls_Helpers $options ) {
		$this->options = $options;
	}
}
