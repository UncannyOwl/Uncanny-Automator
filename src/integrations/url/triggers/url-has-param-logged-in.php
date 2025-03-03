<?php

namespace Uncanny_Automator\Integrations\URL;

/**
 * Class URL_HAS_PARAM_LOGGED_IN
 * @package Uncanny_Automator
 */
class URL_HAS_PARAM_LOGGED_IN extends \Uncanny_Automator\Recipe\Trigger {

	protected $helper;

	/**
	 * @return mixed
	 */
	protected function setup_trigger() {
		$this->helper = array_shift( $this->dependencies );
		$this->set_integration( 'URL' );
		$this->set_trigger_code( 'URL_HAS_PARAM_LOGGED_IN' );
		$this->set_trigger_meta( 'URL_CONDITION' );
		$this->set_trigger_type( 'user' );

		$this->set_sentence(
			sprintf(
			/* translators: %1$s is the trigger condition */
				esc_attr_x( 'A user visits a URL with {{a URL parameter:%1$s}} set', 'URL', 'uncanny-automator' ),
				'NON_EXISTING:URL_CONDITION'
			)
		);

		$this->set_readable_sentence(
			esc_attr_x( 'A user visits a URL with {{a URL parameter}} set', 'URL', 'uncanny-automator' )
		);

		$this->add_action( 'wp', 10, 3 );
	}

	/**
	 * options
	 *
	 * @return array
	 */
	public function options() {
		return $this->helper->url_has_param_get_options( $this->trigger_code );
	}

	/**
	 * validate
	 *
	 * @param $trigger
	 * @param $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		return $this->helper->url_has_param_validate_trigger( $trigger, $hook_args );
	}

	/**
	 * hydrate_tokens
	 *
	 * @param mixed $trigger
	 * @param mixed $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		return $this->helper->url_has_param_hydrate_tokens( $trigger, $hook_args );
	}

	/**
	 * define_tokens
	 *
	 * @param mixed $trigger
	 * @param mixed $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return $this->helper->get_url_tokens( $this->trigger_code );
	}

}
