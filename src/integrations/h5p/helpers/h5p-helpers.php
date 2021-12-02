<?php


namespace Uncanny_Automator;

use Uncanny_Automator_Pro\H5p_Pro_Helpers;

/**
 * Class H5p_Helpers
 *
 * @package Uncanny_Automator
 */
class H5p_Helpers {
	/**
	 * @var H5p_Helpers
	 */
	public $options;

	/**
	 * @var H5p_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * H5p_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param H5p_Helpers $options
	 */
	public function setOptions( H5p_Helpers $options ) {
		$this->options = $options;
	}

	/*
	 * 	if ( ! $this->load_options ) {


			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}
	 */

	/**
	 * @param H5p_Pro_Helpers $pro
	 */
	public function setPro( H5p_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}
}
