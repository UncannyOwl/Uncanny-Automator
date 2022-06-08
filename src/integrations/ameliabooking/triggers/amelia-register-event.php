<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe;

/**
 * Class AMELIA_REGISTER_EVENT
 *
 * @package Uncanny_Automator
 */
class AMELIA_REGISTER_EVENT {

	use Recipe\Triggers;

	/**
	 * Trigger code.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'AMELIA_REGISTER_EVENT';

	/**
	 * Trigger meta.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'AMELIA_REGISTER_EVENT_META';

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {

		$this->setup_trigger();

	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function setup_trigger() {

		$this->set_integration( 'AMELIABOOKING' );

		$this->set_trigger_code( self::TRIGGER_CODE );

		$this->set_trigger_meta( self::TRIGGER_META );

		$this->set_is_login_required( false );

		$this->set_trigger_type( 'anonymous' );

		$this->set_action_args_count( 2 );

		/* Translators: Trigger sentence */
		$this->set_sentence( sprintf( esc_html__( 'A guest registers for {{an event:%1$s}}', 'uncanny-automator' ), $this->get_trigger_meta() ) );

		/* Translators: Trigger sentence */
		$this->set_readable_sentence( esc_html__( 'A guest registers for {{an event}}', 'uncanny-automator' ) ); // Non-active state sentence to show

		$this->add_action( 'AmeliaBookingAddedBeforeNotify' );

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->register_trigger();

	}

	public function load_options() {

		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					array(
						'option_code'     => $this->get_trigger_meta(),
						'label'           => __( 'Events', 'uncanny-automator' ),
						'input_type'      => 'select',
						'required'        => true,
						'options'         => Automator()->helpers->recipe->ameliabooking->options->get_events_dropdown(),
						'relevant_tokens' => array(),
					),
				),
			)
		);

	}

	public function validate_trigger( ...$args ) {

		$is_valid = false;

		if ( isset( $args[0] ) ) {
			$reservation = array_shift( $args[0] );
			// Only run for reservation type 'event'.
			if ( isset( $reservation['type'] ) && 'event' === $reservation['type'] ) {
				$is_valid = true;
			}
		}

		return $is_valid;

	}

	public function prepare_to_run( $data ) {

		$this->set_conditional_trigger( true );

	}

	/**
	 * Check booking ID against the trigger meta
	 *
	 * @param $args
	 */
	public function trigger_conditions( $args ) {

		$this->do_find_any( true ); // Support "Any event" option

		// Find the tag in trigger meta
		$this->do_find_this( $this->get_trigger_meta() );

		$this->do_find_in( array( $args[0]['event']['id'] ) );

	}

	public function do_continue_anon_trigger( ...$args ) {

		return true;

	}

}
