<?php

namespace Uncanny_Automator;
/**
 * Class GEN_USERROLE
 * @package Uncanny_Automator
 */
class WP_SENDEMAIL {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WP';

	private $action_code;
	private $action_meta;
	private $key_generated;
	private $key;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code   = 'SENDEMAIL';
		$this->action_meta   = 'EMAILTO';
		$this->key_generated = false;
		$this->key           = null;
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		global $uncanny_automator;

		$action = array(
			'author'             => $uncanny_automator->get_author_name( $this->action_code ),
			'support_link'       => $uncanny_automator->get_author_support_link( $this->action_code ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action - WordPress */
			'sentence'           => sprintf(  esc_attr__( 'Send an email to {{email address:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			/* translators: Action - WordPress */
			'select_option_name' =>  esc_attr__( 'Send an {{email}}', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'send_email' ),
			// very last call in WP, we need to make sure they viewed the page and didn't skip before is was fully viewable
			'options_group'      => [
				$this->action_meta => [
					/* translators: Email field */
					$uncanny_automator->helpers->recipe->field->text_field( 'EMAILFROM',  esc_attr__( 'From', 'uncanny-automator' ), true, 'email', '{{admin_email}}' ),
					/* translators: Email field */
					$uncanny_automator->helpers->recipe->field->text_field( 'EMAILTO',  esc_attr__( 'To', 'uncanny-automator' ), true, 'email', '{{user_email}}', true,  esc_attr__( 'Separate multiple email addresses with a comma', 'uncanny-automator' ) ),
					/* translators: Email field */
					$uncanny_automator->helpers->recipe->field->text_field( 'EMAILCC',  esc_attr__( 'CC', 'uncanny-automator' ), true, 'email', '', false ),
					/* translators: Email field */
					$uncanny_automator->helpers->recipe->field->text_field( 'EMAILBCC',  esc_attr__( 'BCC', 'uncanny-automator' ), true, 'email', '', false ),
					/* translators: Email field */
					$uncanny_automator->helpers->recipe->field->text_field( 'EMAILSUBJECT',  esc_attr__( 'Subject', 'uncanny-automator' ), true ),
					/* translators: Email field */
					$uncanny_automator->helpers->recipe->field->text_field( 'EMAILBODY',  esc_attr__( 'Body', 'uncanny-automator' ), true, 'textarea' ),
				],
			],
		);

		$uncanny_automator->register->action( $action );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 */
	public function send_email( $user_id, $action_data, $recipe_id, $args ) {

		global $uncanny_automator;
		$to        = $uncanny_automator->parse->text( $action_data['meta']['EMAILTO'], $recipe_id, $user_id, $args );
		$from      = $uncanny_automator->parse->text( $action_data['meta']['EMAILFROM'], $recipe_id, $user_id, $args );
		$cc        = $uncanny_automator->parse->text( $action_data['meta']['EMAILCC'], $recipe_id, $user_id, $args );
		$bcc       = $uncanny_automator->parse->text( $action_data['meta']['EMAILBCC'], $recipe_id, $user_id, $args );
		$subject   = $uncanny_automator->parse->text( $action_data['meta']['EMAILSUBJECT'], $recipe_id, $user_id, $args );
		$subject   = do_shortcode( $subject );
		$body_text = $action_data['meta']['EMAILBODY'];

		if ( false !== strpos( $body_text, '{{reset_pass_link}}' ) ) {
			$reset_pass = ! is_null( $this->key ) ? $this->key : $uncanny_automator->parse->generate_reset_token( $user_id );
			$body       = str_replace( '{{reset_pass_link}}', $reset_pass, $body_text );
		} else {
			$body = $body_text;
		}

		$body = $uncanny_automator->parse->text( $body, $recipe_id, $user_id, $args );
		$body = do_shortcode( $body );

		$headers[] = 'From: <' . $from . '>';

		if ( ! empty( $cc ) ) {
			$headers[] = 'Cc: ' . $cc;
		}

		if ( ! empty( $bcc ) ) {
			$headers[] = 'Bcc: ' . $bcc;
		}

		$headers[] = 'Content-Type: text/html; charset=UTF-8';

		$body = wpautop( $body );

		$mailed = wp_mail( $to, $subject, $body, $headers );

		if ( ! $mailed ) {
			$error_message = $uncanny_automator->error_message->get( 'email-failed' );
			$uncanny_automator->complete->action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		$uncanny_automator->complete->action( $user_id, $action_data, $recipe_id );
	}

}
