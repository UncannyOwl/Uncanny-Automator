<?php

namespace Uncanny_Automator;

/**
 * Class WP_LOGIN
 * @package Uncanny_Automator
 */
class Automator3_Sample {
	use Recipe\Triggers;

	/**
	 * Automator3_Sample constructor.
	 */
	public function __construct() {
		add_filter( 'automator_auto_complete_trigger', '__return_false' );
		$this->setup_trigger();
	}

	/**
	 *
	 */
	protected function setup_trigger() {
		$this->set_integration( 'AUTOMATOR3' );
		$this->set_trigger_code( 'VIEWPAGE1' );
		$this->set_trigger_meta( 'WPPAGE' );
		/* Translators: Some information for translators */
		$this->set_sentence( sprintf( esc_attr__( 'Yay! A user views {{a page:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ) );
		/* Translators: Some information for translators */
		$this->set_readable_sentence( esc_attr__( 'Yay! A user views {{a page}}', 'uncanny-automator' ) );

		$this->add_action( 'template_redirect', 90 );

		$options = array(
			Automator()->helpers->recipe->wp->options->all_pages( null, $this->get_trigger_meta(), true ),
			Automator()->helpers->recipe->options->number_of_times(),
		);

		$this->set_options( $options );
		$this->set_trigger_tokens( Automator3_Tokens::single_token() );
		$this->set_token_parser( array( __NAMESPACE__ . '\Automator3_Tokens', 'parse_single_token' ) );
		$this->register_trigger();
	}

	/**
	 * @return bool
	 */
	public function validate_trigger(): bool {

		if ( ! is_page() && ! is_archive() ) {
			return false;
		}

		return true;
	}

	/**
	 * @param mixed ...$args
	 */
	protected function prepare_to_run( ...$args ) {
		// Set Post ID here.
		global $post;
		$this->set_post_id( $post->ID );

	}
}
