<?php


namespace Uncanny_Automator;

/**
 * Class H5p_Helpers
 * @package Uncanny_Automator
 */
class H5p_Helpers {
	/**
	 * @var H5p_Helpers
	 */
	public $options;

	/**
	 * @var \Uncanny_Automator_Pro\H5p_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * @param H5p_Helpers $options
	 */
	public function setOptions( H5p_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param \Uncanny_Automator_Pro\H5p_Pro_Helpers $pro
	 */
	public function setPro( \Uncanny_Automator_Pro\H5p_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/*
	 * 	if ( ! $this->load_options ) {
			global $uncanny_automator;

			return $uncanny_automator->helpers->recipe->build_default_options_array( $label, $option_code );
		}
	 */
	/**
	 * H5p_Helpers constructor.
	 */
	public function __construct() {
		global $uncanny_automator;
		$this->load_options = $uncanny_automator->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}
}