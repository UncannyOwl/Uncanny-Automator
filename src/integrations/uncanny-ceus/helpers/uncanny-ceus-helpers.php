<?php


namespace Uncanny_Automator;

/**
 * Class Uncanny_Ceus_Helpers
 *
 * @package Uncanny_Automator
 */
class Uncanny_Ceus_Helpers {

	/**
	 * @var Uncanny_Ceus_Helpers
	 */
	public $options;

	/**
	 * @var bool
	 */
	public $load_options;


	/**
	 * @var Uncanny_Ceus_Pro_Helpers
	 */
	public $pro;

	/**
	 * Uoa_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param Uncanny_Ceus_Helpers $options
	 */
	public function setOptions( Uncanny_Ceus_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Uncanny_Ceus_Pro_Helpers $pro
	 */
	public function setPro( Uncanny_Ceus_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}
}
