<?php

namespace Uncanny_Automator\Services\Recipe\Common;

/**
 * A reusable trait for importing common setter and getter class methods.
 *
 * @since 5.0
 * @package Uncanny_Automator\Services\Recipe\Common\Trait_Setter_Getter
 */
trait Trait_Setter_Getter {

	/**
	 * @param $prop
	 *
	 * @return mixed
	 */
	public function get( $prop ) {
		return $this->$prop;
	}

	/**
	 * @param $prop
	 * @param $value
	 *
	 * @return void
	 */
	public function set( $prop, $value ) {
		$this->$prop = $value;
	}
}
