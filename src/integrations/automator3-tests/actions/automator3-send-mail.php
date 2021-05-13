<?php

namespace Uncanny_Automator;

/**
 * Class AUTOMATOR3_SEND_MAIL
 * @package Uncanny_Automator
 */
class AUTOMATOR3_SEND_MAIL {
	use Recipe\Actions;

	/**
	 * @var false
	 */
	private $key_generated;
	/**
	 * @var null
	 */
	private $key;

	/**
	 * AUTOMATOR3_SEND_MAIL constructor.
	 */
	public function __construct() {
		$this->key_generated = false;
		$this->key           = null;
		$this->setup_action();
	}

	/**
	 *
	 */
	protected function setup_action() {
		$this->set_integration( 'AUTOMATOR3' );
		$this->set_action_code( 'SENDEMAIL1' );
		$this->set_action_meta( 'EMAILTO1' );
		/* translators: Action - WordPress */
		$this->set_sentence( sprintf( esc_attr__( 'Send an email to {{email address:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() ) );
		/* translators: Action - WordPress */
		$this->set_readable_sentence( esc_attr__( 'Send an {{email}}', 'uncanny-automator' ) );
		$options_group = array(
			$this->get_action_meta() => array(
				/* translators: Email field */
				Automator()->helpers->recipe->field->text_field( 'EMAILFROM', esc_attr__( 'From', 'uncanny-automator' ), true, 'email', '{{admin_email}}' ),
				/* translators: Email field */
				Automator()->helpers->recipe->field->text_field( 'EMAILTO', esc_attr__( 'To', 'uncanny-automator' ), true, 'email', '{{user_email}}', true, esc_attr__( 'Separate multiple email addresses with a comma', 'uncanny-automator' ) ),
				/* translators: Email field */
				Automator()->helpers->recipe->field->text_field( 'EMAILCC', esc_attr__( 'CC', 'uncanny-automator' ), true, 'email', '', false ),
				/* translators: Email field */
				Automator()->helpers->recipe->field->text_field( 'EMAILBCC', esc_attr__( 'BCC', 'uncanny-automator' ), true, 'email', '', false ),
				/* translators: Email field */
				Automator()->helpers->recipe->field->text_field( 'EMAILSUBJECT', esc_attr__( 'Subject', 'uncanny-automator' ), true ),
				/* translators: Email field */
				Automator()->helpers->recipe->field->text_field( 'EMAILBODY', esc_attr__( 'Body', 'uncanny-automator' ), true, 'textarea' ),
			),
		);

		$this->set_options_group( $options_group );

		$this->register_action();
	}


	/**
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param $parsed
	 */
	protected function process_action( int $user_id, array $action_data, int $recipe_id, array $args, $parsed ) {
		$to        = isset( $parsed['EMAILTO'] ) ?? $parsed['EMAILTO'];
		$from      = isset( $parsed['EMAILFROM'] ) ?? $parsed['EMAILFROM'];
		$cc        = isset( $parsed['EMAILCC'] ) ?? $parsed['EMAILCC'];
		$bcc       = isset( $parsed['EMAILBCC'] ) ?? $parsed['EMAILBCC'];
		$subject   = isset( $parsed['EMAILSUBJECT'] ) ?? $parsed['EMAILSUBJECT'];
		$body_text = isset( $parsed['EMAILBODY'] ) ?? $parsed['EMAILBODY'];

		if ( false !== strpos( $body_text, '{{reset_pass_link}}' ) ) {
			$reset_pass = ! is_null( $this->key ) ? $this->key : Automator()->parse->generate_reset_token( $user_id );
			$body       = str_replace( '{{reset_pass_link}}', $reset_pass, $body_text );
		} else {
			$body = $body_text;
		}
		$data = array(
			'to'      => $to,
			'from'    => $from,
			'cc'      => $cc,
			'bcc'     => $bcc,
			'subject' => $subject,
			'body'    => $body,
			'content' => $this->get_content_type(),
			'charset' => $this->get_charset(),
		);
		$this->set_mail_values( $data );

		$mailed = $this->send_email();
		//Set $this->set_error_message(); and complete the action automatically. May be use return true / false.
		if ( is_automator_error( $mailed ) ) {
			$error_message = $this->get_error_message();
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );
		}
		Automator()->complete->action( $user_id, $action_data, $recipe_id );
	}
}
