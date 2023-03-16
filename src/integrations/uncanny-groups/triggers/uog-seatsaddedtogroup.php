<?php

namespace Uncanny_Automator;

class UOG_SEATSADDEDTOGROUP {

	use Recipe\Triggers;

	/**
	 * Trigger code.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'UOG_SEATSADDEDTOGROUP';

	/**
	 * Trigger meta.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'UOG_SEATSADDEDTOGROUP_META';

	public function __construct() {

		if ( class_exists( '\Uncanny_Automator\Uncanny_Groups_Tokens' ) && class_exists( '\Uncanny_Automator\Uncanny_Groups_Helpers' ) ) {

			$this->set_helper( new Uncanny_Groups_Helpers( false ) );

			/** @var \Uncanny_Automator\Uncanny_Groups_Tokens */
			$this->set_tokens_class( new Uncanny_Groups_Tokens( false ) );

			$this->setup_trigger();
		}

	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {

		$this->set_integration( 'UOG' );

		$this->set_trigger_code( self::TRIGGER_CODE );

		$this->set_trigger_meta( self::TRIGGER_META );

		$this->set_is_login_required( false );

		$this->set_trigger_type( 'anonymous' );

		/* Translators: Trigger sentence */
		$this->set_sentence( sprintf( esc_html__( 'A number of seats {{greater than, less than, equal to, not equal to:%1$s}}{{a specific number:%2$s}} are added to {{an Uncanny group:%3$s}}', 'uncanny-automator' ), 'NUMBERCOND', $this->get_trigger_meta() . '_NUMOFSEATS', $this->get_trigger_meta() ) );

		$this->set_readable_sentence(
			esc_html__( 'A number of seats {{greater than, less than, equal to, not equal to}} {{a specific number}} are added to {{an Uncanny group}}', 'uncanny-automator' )
		);

		$this->add_action( 'ulgm_seats_added' );

		$this->set_action_args_count( 5 );

		if ( null !== $this->get_tokens_class() ) {

			$this->set_tokens( $this->get_tokens_class()->seats_added_tokens() );

		}

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->register_trigger();

	}

	public function load_options() {

		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					Automator()->helpers->recipe->field->less_or_greater_than(),
					array(
						'input_type'      => 'int',
						'option_code'     => $this->get_trigger_meta() . '_NUMOFSEATS',
						'label'           => esc_attr__( 'Seats', 'uncanny-automator' ),
						'supports_tokens' => true,
						'required'        => true,
						'relevant_tokens' => array(),
					),
					Automator()->helpers->recipe->uncanny_groups->options->all_ld_groups( null, $this->get_trigger_meta(), array( 'relevant_tokens' => array() ) ),
				),
			)
		);

	}

	public function validate_trigger( ...$args ) {

		list( $count, $ld_group_id ) = $args[0];

		if ( isset( $count ) && absint( $count ) > 0 && isset( $ld_group_id ) && 'groups' === get_post_type( $ld_group_id ) ) {
			return true;
		}

		return false;

	}

	public function prepare_to_run( $data ) {

		// Set the user to complete with the one we are editing instead of current login user.
		$this->set_user_id( wp_get_current_user()->ID );

		$this->set_conditional_trigger( true );

	}

	/**
	 * Method parse_additional_tokens.
	 *
	 * @param $parsed
	 * @param $args
	 * @param $trigger
	 *
	 * @return array
	 */
	public function parse_additional_tokens( $parsed, $args, $trigger ) {

		return $this->get_tokens_class()->seats_added_tokens_hydrate_tokens( $parsed, $args, $trigger );

	}

	/**
	 * Check email subject against the trigger meta
	 *
	 * @param mixed ...$args
	 *
	 * @return array
	 */
	public function validate_conditions( ...$args ) {

		list( $seat_count, $group_id ) = $args[0];

		$this->actual_where_values = array(); // Fix for when not using the latest Trigger_Recipe_Filters version. Newer integration can omit this line.

		// Bailout if `with_number_condition` is not found.
		if ( ! method_exists( $this, 'with_number_condition' ) ) {
			return array();
		}

		// Find the receiver user id
		return $this->find_all( $this->trigger_recipes() )
					->where( array( $this->get_trigger_meta() ) )
					->equals( array( $group_id ) ) // to match group id
					->compare( array( '=' ) ) // equal to
					->with_number_condition( $seat_count, $this->get_trigger_meta() . '_NUMOFSEATS' ) // to match seat number and condition
					->format( array( 'intval' ) )
					->get();
	}

	public function do_continue_anon_trigger( ...$args ) {
		return true;
	}

}
