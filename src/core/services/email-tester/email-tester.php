<?php

namespace Uncanny_Automator\Services\Email_Tester;

use Exception;
use Uncanny_Automator\Automator_Utilities;
use Uncanny_Automator\Utilities;

/**
 * @since 5.5
 * @package Uncanny_Automator\Services\Email_Tester
 */
class Email_Sender {

	/**
	 * The parameters.
	 *
	 * @var array
	 */
	private $parameters = array();

	/**
	 * Sets the default args.
	 *
	 * @param mixed[] $args
	 *
	 * @return void
	 */
	public function __construct( $args = array() ) {
		$this->set_default_args( $args );
	}

	/**
	 * Retrieve the parameters.
	 *
	 * @return mixed[]
	 */
	public function get_parameters() {
		return $this->parameters;
	}

	/**
	 * Merges the given parameters with the default ones.
	 *
	 * @param mixed[] $args
	 *
	 * @return mixed[]
	 */
	private function set_default_args( $args ) {

		$admin_email = get_option( 'admin_email' );

		$defaults = array(
			'from'         => 'Uncanny Automator email test <' . $admin_email . '>',
			'from-name'    => $admin_email,
			'content-type' => 'text/html; charset=UTF-8',
			'reply-to'     => null,
			'cc'           => null,
			'bcc'          => null,
			'to'           => '',
			'subject'      => '',
			'body'         => '',
		);

		$this->parameters = wp_parse_args( $args, $defaults );

		return $this->parameters;

	}

	/**
	 * Validates the recipient email address.
	 *
	 * @return true
	 *
	 * @throws Exception
	 */
	public function validate_recipient() {

		$params = $this->get_parameters();

		if ( false === filter_var( $params['to'], FILTER_VALIDATE_EMAIL ) ) {
			throw new Exception( 'Invalid recipient email address: <' . esc_html( $params['to'] ) . '>', 500 );
		}

		return true;

	}

	/**
	 * Validates the recipient email address.
	 *
	 * @return true
	 *
	 * @throws Exception
	 */
	public function validate_body() {

		$params = $this->get_parameters();

		$email_body = trim( $params['body'] );

		if ( '' === $email_body || 0 === strlen( $email_body ) ) {
			throw new Exception( 'Empty email body', 500 );
		}

		return true;

	}

	/**
	 * Sends the email.
	 *
	 * @return bool
	 *
	 * @throws Exception
	 */
	public function send() {

		$params = $this->get_parameters();

		$this->validate_recipient();
		$this->validate_body();

		$is_sent = wp_mail( $params['to'], $params['subject'], $params['body'], $this->resolve_headers() );

		if ( isset( $_ENV['DOING_AUTOMATOR_TEST'] ) ) {
			return array( 'success' => $is_sent );
		}

		wp_send_json(
			array(
				'success' => $is_sent,
			)
		);
	}

	/**
	 * Resolves the email headers from the parameters.
	 *
	 * @return string[]
	 */
	private function resolve_headers() {

		$parameters = $this->get_parameters();

		$headers = array(
			'From: ' . $parameters['from'],
			'Content-Type: ' . $parameters['content-type'],
		);

		return $headers;

	}

	/**
	 * Generates array for this object consumption.
	 *
	 * @param int $action_id
	 *
	 * @return mixed[]
	 */
	public static function generate_args( $request ) {

		$action_id     = $request->get_param( 'action_id' );
		$email_address = $request->get_param( 'email_address' );
		$email_body    = $request->get_param( 'email_body' );

		// Replace all tokens
		$regex = '/<ins contenteditable="false">.*?<span class="uap-token__name">(.*?)<\/span>.*?<\/ins>/s';

		$email_body = preg_replace_callback(
			$regex,
			function ( $matches ) {
				return '{' . $matches[1] . '}'; // Replace with the captured text inside the <span> tags
			},
			$email_body
		);

		$action_meta = Utilities::flatten_post_meta( get_post_meta( $action_id ) );

		$args = array(
			'to'      => $email_address,
			'subject' => __( 'Uncanny Automator Test', 'uncanny-automator' ),
			'body'    => $email_body,
		);

		if ( isset( $action_meta['EMAILCONTENTTYPE'] ) && 'plain' === $action_meta['EMAILCONTENTTYPE'] ) {

			$args['content-type'] = 'text/plain; charset=UTF-8';

			$body = preg_replace( '/<br\s*\/?>/', PHP_EOL, $args['body'] );

			$body = wp_strip_all_tags( $body );

			$args['body'] = $body;

		}

		return $args;
	}


}
