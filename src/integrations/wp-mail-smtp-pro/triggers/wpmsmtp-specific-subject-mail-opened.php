<?php

namespace Uncanny_Automator;

/**
 * Class WPMSMTP_SPECIFIC_SUBJECT_MAIL_OPENED
 *
 * @package Uncanny_Automator
 */
class WPMSMTP_SPECIFIC_SUBJECT_MAIL_OPENED {

	use Recipe\Triggers;

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
		$this->set_integration( 'WPMAILSMTPPRO' );
		$this->set_trigger_code( 'SPECIFIC_SUBJECT_CODE' );
		$this->set_trigger_meta( 'SPECIFIC_SUBJECT_META' );
		$this->set_is_login_required( false );
		$this->set_trigger_type( 'anonymous' );
		$this->set_action_args_count( 1 );

		/* Translators: Trigger sentence */
		$this->set_sentence( sprintf( esc_html__( 'An email with {{specific text:%1$s}} in the subject line is opened', 'uncanny-automator' ), $this->get_trigger_meta() ) );

		/* Translators: Trigger sentence */
		$this->set_readable_sentence( esc_html__( 'An email with {{specific text}} in the subject line is opened', 'uncanny-automator' ) ); // Non-active state sentence to show

		$this->add_action( 'wp_mail_smtp_pro_emails_logs_tracking_handle_injectable_event' );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->register_trigger();
	}

	public function load_options() {

		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					array(
						'option_code'     => $this->get_trigger_meta(),
						'label'           => __( 'Text to match', 'uncanny-automator' ),
						'input_type'      => 'text',
						'required'        => true,
						'relevant_tokens' => array(),
					),
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
			$email_log = array_shift( $args[0] );
			// Only run for open-email.
			if ( isset( $email_log['event_type'] ) && 'open-email' === $email_log['event_type'] ) {
				$is_valid = true;
			}
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
	 * @param $notation
	 * @param $subject
	 * @param $value_in_trigger
	 *
	 * @return false|int
	 */
	public function conditions_matched( $notation, $where, $condition ) {
		$pattern = '/(' . addcslashes( $where, '/-:\\' ) . ')/i';

		return preg_match( $pattern, $condition );
	}

	/**
	 * Check email subject against the trigger meta
	 *
	 * @param $args
	 */
	public function validate_conditions( ...$args ) {
		list( $email_tracking_details ) = $args[0];

		$this->actual_where_values = array(); // Fix for when not using the latest Trigger_Recipe_Filters version. Newer integration can omit this line.
		// Get Email and its message ID.
		$email   = new \WPMailSMTP\Pro\Emails\Logs\Email( $email_tracking_details['email_log_id'] );
		$subject = $email->get_subject();

		// Find the text in email subject
		return $this->find_all( $this->trigger_recipes() )
					->where( array( $this->get_trigger_meta() ) )
					->match( array( $subject ) )
					->format( array( 'trim' ) )
					->get();
	}

	public function do_continue_anon_trigger( ...$args ) {
		return true;
	}

}
