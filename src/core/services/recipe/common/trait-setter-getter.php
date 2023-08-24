<?php
namespace Uncanny_Automator\Services\Recipe\Common;

/**
 * A reusable trait for importing common setter and getter class methods.
 *
 * @package Uncanny_Automator\Services\Recipe\Common\Trait_Setter_Getter
 * @since 5.0
 */
trait Trait_Setter_Getter {
	public function get( $prop ) {
		return $this->$prop;
	}
	public function set( $prop, $value ) {
		$this->$prop = $value;
	}
}
