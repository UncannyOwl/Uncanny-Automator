<?php

namespace Uncanny_Automator;

/**
 * Class JETCRM_CONTACT_STUTUS_UPDATED
 *
 * @package Uncanny_Automator
 */
class JETCRM_CONTACT_STUTUS_UPDATED {


	use Recipe\Triggers;

	public $helpers;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		 //      $this->setup_trigger();
		//$this->helpers = new Jet_Crm_Helpers();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {
		$this->set_integration( 'JETCRM' );
		$this->set_trigger_code( 'JETCRM_CONTACT_STATUS_UPDATED' );
		$this->set_trigger_meta( 'JETCRM_CONTACT_STATUS' );
		$this->set_trigger_type( 'anonymous' );
		$this->set_is_login_required( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->trigger_code, 'integration/jestpack-crm/' ) );
		$this->set_sentence(
		/* Translators: Trigger sentence */
			sprintf( esc_html__( "A contact's status is changed to {{a specific status:%1\$s}}", 'uncanny-automator' ), $this->get_trigger_meta() )
		);
		// Non-active state sentence to show
		$this->set_readable_sentence( esc_attr__( "A contact's status is changed to {{a specific status}}", 'uncanny-automator' ) );
		// Which do_action() fires this trigger.
		$this->set_action_hook( 'jpcrm_after_contact_update' );
		$this->set_action_args_count( 1 );
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
					$this->helpers->contact_statuses( $this->get_trigger_meta(), true ),
				),
			)
		);

	}

	/**
	 * Validate the trigger.
	 *
	 * @param $args
	 *
	 * @return bool
	 */
	protected function validate_trigger( ...$args ) {
		$obj_id = array_shift( $args );

		if ( ! isset( $obj_id ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Prepare to run the trigger.
	 *
	 * @param $data
	 *
	 * @return void
	 */
	public function prepare_to_run( $data ) {
		$this->set_conditional_trigger( true );
	}

	/**
	 * Check contact status against the trigger meta
	 *
	 * @param $args
	 */
	public function validate_conditions( ...$args ) {
		list($obj_id)              = $args[0];
		$this->actual_where_values = array(); // Fix for when not using the latest Trigger_Recipe_Filters version. Newer integration can omit this line.
		// Get status.
		global $wpdb;

		$status = $wpdb->get_var( $wpdb->prepare( "SELECT zbsc_status FROM `{$wpdb->prefix}zbs_contacts` WHERE ID = %d", $obj_id ) );

		// check contact status
		return $this->find_all( $this->trigger_recipes() )
			->where( array( $this->get_trigger_meta() ) )
			->match( array( $status ) )
			->format( array( 'sanitize_text_field' ) )
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
