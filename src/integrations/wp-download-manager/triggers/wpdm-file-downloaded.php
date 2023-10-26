<?php

namespace Uncanny_Automator;

/**
 * Class WPDM_FILE_DOWNLOADED
 *
 * @package Uncanny_Automator
 */
class WPDM_FILE_DOWNLOADED {

	use Recipe\Triggers;
	/**
	 * @var
	 */
	public $helpers;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->helpers = new Wp_Download_Manager_Helpers();
		$this->setup_trigger();

	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {
		$this->set_integration( 'WPDM' );
		$this->set_trigger_code( 'SPECIFIC_FILE_DOWNLOADED_CODE' );
		$this->set_trigger_meta( 'SPECIFIC_FILE_DOWNLOADED_META' );
		$this->set_is_login_required( false );
		$this->set_trigger_type( 'anonymous' );
		$this->set_action_args_count( 1 );

		/* Translators: Trigger sentence */
		$this->set_sentence( sprintf( esc_html__( '{{A specific file:%1$s}} is downloaded', 'uncanny-automator' ), $this->get_trigger_meta() ) );

		/* Translators: Trigger sentence */
		$this->set_readable_sentence( esc_html__( '{{A specific file}} is downloaded', 'uncanny-automator' ) ); // Non-active state sentence to show

		$this->set_action_hook( 'wpdm_onstart_download' );
		//      $this->set_action_hook( 'after_download' );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->register_trigger();
	}

	/**
	 * @return array
	 */
	public function load_options() {

		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					$this->helpers->get_all_wpmd_files( $this->get_trigger_meta(), true ),
				),
			)
		);

	}

	/**
	 * @param ...$args
	 *
	 * @return bool
	 */
	public function validate_trigger( ...$args ) {

		$is_valid = false;
		if ( isset( $args[0] ) ) {
			$is_valid = true;
		}

		return $is_valid;

	}

	/**
	 * @param $data
	 *
	 * @return void
	 */
	public function prepare_to_run( $data ) {
		$this->set_conditional_trigger( true );
	}

	/**
	 * Check email subject against the trigger meta
	 *
	 * @param $args
	 */
	public function validate_conditions( ...$args ) {
		list( $package ) = $args[0];

		$this->actual_where_values = array(); // Fix for when not using the latest Trigger_Recipe_Filters version. Newer integration can omit this line.
		// Get package ID.
		$file = $package['ID'];

		// Find the text in email subject
		return $this->find_all( $this->trigger_recipes() )
					->where( array( $this->get_trigger_meta() ) )
					->match( array( $file ) )
					->format( array( 'intval' ) )
					->get();
	}

	/**
	 * @param ...$args
	 *
	 * @return bool
	 */
	public function do_continue_anon_trigger( ...$args ) {
		return true;
	}

}
