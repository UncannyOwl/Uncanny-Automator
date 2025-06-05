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

		add_action( 'wp_loaded', array( $this, 'add_script' ) );
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

	/**
	 * @param $user_id
	 * @param $closure_data
	 * @param $recipe_id
	 * @param $args
	 */
	public function redirect( $user_id, $closure_data, $recipe_id, $args ) {
		$redirect_url_raw = $closure_data['meta'][ $this->get_closure_meta() ] ?? '';

		if ( empty( $redirect_url_raw ) ) {
			return;
		}

		$redirect_url = Automator()->parse->text( $redirect_url_raw, $recipe_id, $user_id, $args );

		Automator()->db->closure->add_entry_meta(
			array(
				'user_id'                  => isset( $args['user_id'] ) ? $args['user_id'] : null,
				'automator_closure_id'     => isset( $closure_data['ID'] ) ? $closure_data['ID'] : null,
				'automator_closure_log_id' => isset( $args['closure_log_id'] ) ? $args['closure_log_id'] : null,
			),
			'field_values',
			wp_json_encode(
				array(
					'raw'    => $redirect_url_raw,
					'parsed' => $redirect_url,
				)
			)
		);

		$this->set_cookie( $redirect_url );
	}

	/**
	 * @return void
	 */
	public function add_script() {

		$check_closure = Automator()->db->closure->get_all();
		if ( empty( $check_closure ) ) {
			return;
		}

		Utilities::enqueue_asset(
			'uap-closure',
			'closure'
		);
	}

	/**
	 * @param $redirect_url
	 *
	 * @return void
	 */
	public function set_cookie( $redirect_url ) {

		//$cookie_name     = 'automator_closure_redirect_' . wp_create_nonce( AUTOMATOR_BASE_FILE ); // future
		$cookie_name     = 'automator_closure_redirect';
		$cookie_lifetime = time() + ( 86400 * 30 ); // 86400 = 1 day
		setcookie( $cookie_name, $redirect_url, $cookie_lifetime, '/' );
	}
}
