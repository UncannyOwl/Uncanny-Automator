<?php


namespace Uncanny_Automator;

use WP_Error;
use Uncanny_Automator_Pro\Integromat_Pro_Helpers;

/**
 * Class Integromat_Helpers
 *
 * @package Uncanny_Automator
 */
class Integromat_Helpers {

	/**
	 * @var Integromat_Helpers
	 */
	public $options;
	/**
	 * @var Integromat_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Integromat_Pro_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param Integromat_Helpers $options
	 */
	public function setOptions( Integromat_Helpers $options ) { // phpcs:ignore
		$this->options = $options;
	}

	/**
	 * @param Integromat_Pro_Helpers $pro
	 */
	public function setPro( Integromat_Pro_Helpers $pro ) { // phpcs:ignore
		$this->pro = $pro;
	}
}
