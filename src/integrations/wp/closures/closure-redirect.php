<?php

namespace Uncanny_Automator;

/**
 * Class CLOSURE_REDIRECT
 *
 * @package Uncanny_Automator
 */
class Closure_Redirect {
	use Recipe\Closure;

	/**
	 * Closure_Redirect constructor.
	 */
	public function __construct() {
		$this->setup_closure();
	}

	/**
	 *
	 * @throws Automator_Exception
	 */
	protected function setup_closure() {
		$this->set_integration( 'WP' );
		$this->set_closure_code( 'REDIRECT' );
		$this->set_closure_meta( 'REDIRECTURL' );
		/* translators: Closure - WordPress */
		$this->set_sentence( sprintf( esc_attr__( 'Redirect to {{a link:%1$s}} when recipe is completed', 'uncanny-automator' ), $this->get_closure_meta() ) );
		/* translators: Closure - WordPress */
		$this->set_readable_sentence( esc_attr__( 'Redirect when recipe is completed', 'uncanny-automator' ) );
		$this->set_options( Automator()->helpers->recipe->get_redirect_url() );
		$this->register_closure();
	}
}
