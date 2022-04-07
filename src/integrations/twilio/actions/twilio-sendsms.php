<?php

namespace Uncanny_Automator_Pro;

/**
 * Class TWILIO_SENDSMS
 * @package Uncanny_Automator_Pro
 */
class TWILIO_SENDSMS {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'TWILIO';

	private $action_code;
	private $action_meta;
	private $key_generated;
	private $key;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code   = 'TWILIOSENDSMS';
		$this->action_meta   = 'TWSENDSMS';
		$this->key_generated = false;
		$this->key           = null;
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$number_field_args    = array(
			'option_code' => $this->action_meta,
			'input_type'  => 'text',
			'label'       => esc_attr__( 'To', 'uncanny-automator' ),
			'description' => __( 'Separate multiple phone numbers with a comma', 'uncanny-automator' ),
			'required'    => true,
			'tokens'      => true,
		);

		$body_field_args = array(
			'option_code' => 'SMSBODY',
			'input_type'  => 'textarea',
			'label'       => esc_attr__( 'Body', 'uncanny-automator' ),
			'required'    => true,
			'tokens'      => true,
			'supports_tinymce' => false
		);

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/twilio/' ),
			'is_pro'             => false,
			'requires_user'      => false,
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			'sentence'           => sprintf( __( 'Send an SMS message to {{a number:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			'select_option_name' => __( 'Send an SMS message to {{a number}}', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'twilio_send_sms' ),
			'options_group'      => [
				$this->action_meta => [
					Automator()->helpers->recipe->field->text( $number_field_args ),
					Automator()->helpers->recipe->field->text( $body_field_args ),
				],
			],
		);

		Automator()->register->action( $action );
	}

	/**
	 * Validation function when the action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 */
	public function twilio_send_sms( $user_id, $action_data, $recipe_id, $args ) {

		$to         = Automator()->parse->text( $action_data['meta'][ $this->action_meta ], $recipe_id, $user_id, $args );
		$body_text  = $action_data['meta']['SMSBODY'];
		$reset_pass = ! is_null( $this->key ) ? $this->key : Automator()->parse->generate_reset_token( $user_id );
		$body       = str_replace( '{{reset_pass_link}}', $reset_pass, $body_text );
		$body       = Automator()->parse->text( $body, $recipe_id, $user_id, $args );
		$body       = do_shortcode( $body );

		$to_numbers = explode( ',', $to );
		if ( ! empty( $to_numbers ) ) {
			$is_error  = false;
			$error_msg = '';
			foreach ( $to_numbers as $to_num ) {
				$result = Automator()->helpers->recipe->twilio->send_sms( $to_num, wp_strip_all_tags( $body ), $user_id, $action_data );

				if ( ! $result['result'] ) {
					$error_msg = $result['message'];
					$is_error  = true;
				}
			}
			if ( $is_error ) {
				$action_data['do-nothing']           = true;
				$action_data['complete_with_errors'] = true;
				Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_msg );

				return;
			}
		}

		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}

}
