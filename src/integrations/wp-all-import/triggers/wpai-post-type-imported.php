<?php

namespace Uncanny_Automator;

/**
 * Class WPAI_POST_TYPE_IMPORTED
 *
 * @package Uncanny_Automator
 */
class WPAI_POST_TYPE_IMPORTED {

	use Recipe\Triggers;

	public $helpers;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->helpers = new Wp_All_Import_Helpers();
		if ( Automator()->helpers->recipe->is_edit_page() ) {
			add_action(
				'wp_loaded',
				function () {
					$this->setup_trigger();
				},
				99
			);

			return;
		}
		$this->setup_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {
		$this->set_integration( 'WPAI' );
		$this->set_trigger_code( 'WPAI_POSTTYPE_IMPORTED' );
		$this->set_trigger_meta( 'WPAI_POSTTYPE' );
		$this->set_trigger_type( 'anonymous' );
		$this->set_is_login_required( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->trigger_code, 'integration/wp-all-import/' ) );
		$this->set_sentence(
		/* Translators: Trigger sentence */
			sprintf( esc_html__( 'A {{type of post:%1$s}} is imported', 'uncanny-automator' ), $this->get_trigger_meta() )
		);
		// Non-active state sentence to show
		$this->set_readable_sentence( esc_attr__( 'A {{type of post}} is imported', 'uncanny-automator' ) );
		// Which do_action() fires this trigger.
		$this->set_action_hook( 'pmxi_saved_post' );
		$this->set_action_args_count( 3 );
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
					$this->helpers->get_post_types_options( null, $this->get_trigger_meta() ),
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
		list( $post_id, $xml_node, $is_updated ) = array_shift( $args );

		if ( empty( $post_id ) ) {
			return false;
		}

		// Check if trigger should run on update.
		if ( ! empty( $is_updated ) ) {
			$run_on_update = apply_filters( 'automator_wpai_post_type_imported_run_on_update', false, $post_id );
			if ( ! $run_on_update ) {
				return false;
			}
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
		list( $post_id, $xml_node, $is_updated ) = $args[0];
		$this->actual_where_values               = array(); // Fix for when not using the latest Trigger_Recipe_Filters version. Newer integration can omit this line.
		// Get post type.
		$post_type = get_post_type( $post_id );

		// check post type
		return $this->find_all( $this->trigger_recipes() )
					->where( array( $this->get_trigger_meta() ) )
					->match( array( $post_type ) )
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
