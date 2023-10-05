<?php
namespace Uncanny_Automator\Recipe;

/**
 * Trait Log_Properties
 *
 * This serves as an easy way to import the logs properties via action or trigger in the future.
 *
 * @since 5.0
 *
 * @version 1.0.0
 */
trait Log_Properties {

	/**
	 * Sets the logs properties. This variadic method. Pass all arrays of properties as different argument.
	 *
	 * @param array{array{type:string,label:string,value:string,attributes:array{code_language:string}}}
	 *
	 * @return void
	 */
	public function set_log_properties( ...$props ) {

		Automator()->helpers->recipe->set_log_properties( $props );

	}

	/**
	 * Sets the trigger logs properties. This variadic method. Pass all arrays of properties as different argument.
	 *
	 * @param array{array{type:string,label:string,value:string,attributes:array{code_language:string}}}
	 *
	 * @since 5.2
	 *
	 * @return void
	 */
	public function set_trigger_log_properties( ...$props ) {

		Automator()->helpers->recipe->set_trigger_log_properties( $props );

	}

}
