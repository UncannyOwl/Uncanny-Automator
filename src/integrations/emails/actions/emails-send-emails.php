<?php

namespace Uncanny_Automator;

/**
 * Class EMAILS_SEND_EMAILS
 *
 * @package Uncanny_Automator
 */
class EMAILS_SEND_EMAILS {

	use Recipe\Actions;

	/**
	 * Property $key_generated.
	 *
	 * @var false
	 */
	private $key_generated;

	/**
	 * Property $key.
	 *
	 * @var null
	 */
	private $key;

	/**
	 * WP_SENDEMAIL constructor.
	 *
	 * @return void.
	 */
	public function __construct() {
		$this->key_generated = false;
		$this->key           = null;
		$this->setup_action();
	}

	/**
	 * Setup SENDEMAIL Automator Action.
	 *
	 * @return void.
	 */
	protected function setup_action() {

		$this->set_integration( 'EMAILS' );

		$this->set_action_code( 'SENDEMAIL' );

		$this->set_action_meta( 'EMAILTO' );

		$this->set_requires_user( false );

		/* translators: Action - WordPress */
		$this->set_sentence( sprintf( esc_attr__( 'Send an email to {{email address:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() ) );

		/* translators: Action - WordPress */
		$this->set_readable_sentence( esc_attr__( 'Send an {{email}}', 'uncanny-automator' ) );

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->set_should_apply_extra_formatting( true );

		$this->register_action();

	}

	/**
	 * Method load_options
	 *
	 * @return array
	 */
	public function load_options() {
		$options_group = array(

			$this->get_action_meta() => array(

				Automator()->helpers->recipe->field->select(
					array(
						'option_code'           => 'EMAILCONTENTTYPE',
						'label'                 => esc_attr__( 'Content type', 'uncanny-automator' ),
						'input_type'            => 'select',
						'required'              => false,
						'supports_custom_value' => false,
						'options'               => array(
							'html'  => 'HTML',
							'plain' => 'Plain text',
						),
					)
				),

				// Email From Field.
				Automator()->helpers->recipe->field->text(
					array(
						'option_code' => 'EMAILFROM',
						/* translators: Email field */
						'label'       => esc_attr__( 'From', 'uncanny-automator' ),
						'input_type'  => 'email',
						'default'     => '{{admin_email}}',
					)
				),

				// Email From Field.
				Automator()->helpers->recipe->field->text(
					array(
						'option_code' => 'EMAILFROMNAME',
						/* translators: Email field */
						'label'       => esc_attr__( 'From name', 'uncanny-automator' ),
						'input_type'  => 'text',
						'default'     => '{{site_name}}',
					)
				),

				// Email To Field.
				Automator()->helpers->recipe->field->text(
					array(
						'option_code' => 'EMAILTO',
						/* translators: Email field */
						'label'       => esc_attr__( 'To', 'uncanny-automator' ),
						'description' => esc_attr__( 'Separate multiple email addresses with a comma', 'uncanny-automator' ),
						'input_type'  => 'email',
					)
				),

				// Email To Field.
				Automator()->helpers->recipe->field->text(
					array(
						'option_code' => 'REPLYTO',
						/* translators: Email field */
						'label'       => esc_attr__( 'Reply to', 'uncanny-automator' ),
						'input_type'  => 'email',
						'required'    => false,
					)
				),

				// Email CC field.
				Automator()->helpers->recipe->field->text(
					array(
						'option_code' => 'EMAILCC',
						/* translators: Email field */
						'label'       => esc_attr__( 'CC', 'uncanny-automator' ),
						'input_type'  => 'email',
						'required'    => false,
					)
				),

				// Email BCC field.
				Automator()->helpers->recipe->field->text(
					array(
						'option_code' => 'EMAILBCC',
						/* translators: Email field */
						'label'       => esc_attr__( 'BCC', 'uncanny-automator' ),
						'input_type'  => 'email',
						'required'    => false,
					)
				),

				// Email Subject field.
				Automator()->helpers->recipe->field->text(
					array(
						'option_code' => 'EMAILSUBJECT',
						/* translators: Email field */
						'label'       => esc_attr__( 'Subject', 'uncanny-automator' ),
						'required'    => true,
					)
				),

				// Email Content Field.
				Automator()->helpers->recipe->field->text(
					array(
						'option_code'               => 'EMAILBODY',
						/* translators: Email field */
						'label'                     => esc_attr__( 'Body', 'uncanny-automator' ),
						'input_type'                => 'textarea',
						'supports_fullpage_editing' => true,
					)
				),

			),
		);

		$options = Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => $options_group,
			)
		);

		return $options;
	}


	/**
	 * Method process_action.
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return void.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		// Clear the error messages.
		$this->clear_error_message();
		// Reset the errors.
		Automator_WP_Error::get_instance()->reset_errors();

		$body_text    = isset( $parsed['EMAILBODY'] ) ? $parsed['EMAILBODY'] : '';
		$content_type = isset( $parsed['EMAILCONTENTTYPE'] ) ? $parsed['EMAILCONTENTTYPE'] : 'text/html';

		if ( false !== strpos( $body_text, '{{reset_pass_link}}' ) ) {
			$reset_pass = ! is_null( $this->key ) ? $this->key : Automator()->parse->generate_reset_token( $user_id );
			$body       = str_replace( '{{reset_pass_link}}', $reset_pass, $body_text );
		} else {
			$body = $body_text;
		}

		if ( 'plain' === (string) $content_type ) {
			$content_type = 'text/plain';
			$body         = preg_replace( '/<br\s*\/?>/', PHP_EOL, $body );
			$body         = wp_strip_all_tags( $body );
			$this->set_is_html( false );
		} else {
			$content_type = 'text/html';
		}

		if ( empty( wp_strip_all_tags( $body ) ) ) {
			$this->set_error_message( esc_html__( 'Cannot send email with an empty body.', 'uncanny-automator' ) );
		}

		$this->set_content_type( $content_type );
		$data = array(
			'to'        => isset( $parsed['EMAILTO'] ) ? $parsed['EMAILTO'] : '',
			'reply_to'  => isset( $parsed['REPLYTO'] ) ? $parsed['REPLYTO'] : '',
			'from'      => isset( $parsed['EMAILFROM'] ) ? $parsed['EMAILFROM'] : '',
			'from_name' => isset( $parsed['EMAILFROMNAME'] ) ? $parsed['EMAILFROMNAME'] : '',
			'cc'        => isset( $parsed['EMAILCC'] ) ? $parsed['EMAILCC'] : '',
			'bcc'       => isset( $parsed['EMAILBCC'] ) ? $parsed['EMAILBCC'] : '',
			'subject'   => isset( $parsed['EMAILSUBJECT'] ) ? $parsed['EMAILSUBJECT'] : '',
			'body'      => $body,
			'content'   => $this->get_content_type(),
			'charset'   => $this->get_charset(),
		);

		$this->set_mail_values( $data, $user_id, $recipe_id, $args );
		$mailed = $this->send_email();

		// Set $this->set_error_message(); and complete the action automatically. May be use return true / false.
		if ( false === $mailed && ! empty( $this->get_error_message() ) ) {

			$error_message                       = $this->get_error_message();
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		$sent_email_completed = absint( get_option( 'automator_sent_email_completed', 0 ) );

		update_option( 'automator_sent_email_completed', $sent_email_completed + 1 );

		Automator()->complete->action( $user_id, $action_data, $recipe_id );

	}

}
