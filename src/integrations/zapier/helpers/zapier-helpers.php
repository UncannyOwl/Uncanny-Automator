<?php


namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Zapier_Pro_Helpers;
use WP_Error;

/**
 * Class Zapier_Helpers
 *
 * @package Uncanny_Automator
 */
class Zapier_Helpers {

	/**
	 * @var Zapier_Helpers
	 */
	public $options;
	/**
	 * @var Zapier_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Zapier_Pro_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param Zapier_Helpers $options
	 */
	public function setOptions( Zapier_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Zapier_Pro_Helpers $pro
	 */
	public function setPro( Zapier_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}
}
