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
}