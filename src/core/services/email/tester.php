<?php

namespace Uncanny_Automator\Services\Email;

use Exception;
use Uncanny_Automator\Services\Email\Attachment\Handler;
use Uncanny_Automator\Utilities;


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * @since 5.5
 *
 * @package Uncanny_Automator\Services\Email_Tester
 */
class Tester {

	/**
	 * The parameters.
	 *
	 * @var array
	 */
	private $parameters = array();

	/**
	 * The file attachments path.
	 *
	 * @var array{}|string[]
	 */
	protected $attachments = array();

	/**
	 * @var Handler[]
	 */
	protected $attachment_handlers = array();

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
	 * Get attachments.
	 *
	 * @return string[]
	 */
	public function get_attachments() {
		return $this->attachments;
	}

	/**
	 * Adds the given path to the attachments property.
	 *
	 * @param string $path
	 *
	 * @return string[]
	 */
	public function add_attachment( $path ) {
		$this->attachments[] = $path;
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
			'attachments'  => array(),
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

		try {
			$this->process_attachments( $params );
			$is_sent = wp_mail( $params['to'], $params['subject'], $params['body'], $this->resolve_headers(), $this->get_attachments() );
		} finally {
			// Always delete temporary attachment files, even when an exception is thrown.
			foreach ( $this->attachment_handlers as $handler ) {
				$handler->cleanup();
			}
		}

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
	 * Process the attachments.
	 *
	 * @param mixed[] $params
	 *
	 * @return void
	 */
	public function process_attachments( $params ) {

		$attachments = $params['attachments'] ?? array();

		if ( ! empty( $attachments ) ) {
			foreach ( $attachments as $attachment_url ) {
				$path = $this->process_attachment( $attachment_url );

				if ( is_wp_error( $path ) ) {
					throw new Exception( $path->get_error_message(), 400 );
				}

				if ( file_exists( $path ) ) {
					$this->add_attachment( $path );
				}
			}
		}

	}

	/**
	 * Processes the attachment.
	 *
	 * @param mixed $attachment_url
	 *
	 * @return WP_Error|array|string
	 */
	public function process_attachment( $attachment_url ) {

		$handler                    = new Handler( $attachment_url );
		$this->attachment_handlers[] = $handler;

		return $handler->process_attachment();

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

		$action_id               = $request->get_param( 'action_id' );
		$email_address           = $request->get_param( 'email_address' );
		$email_body              = $request->get_param( 'email_body' );
		$attachments_stringified = $request->get_param( 'attachments' );
		$attachments_array       = (array) json_decode( $attachments_stringified, true );

		$first_attachment_url = $attachments_array[0]['url'] ?? '';

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
			'subject' => esc_html__( 'Uncanny Automator Test', 'uncanny-automator' ),
			'body'    => $email_body,
		);

		if ( ! empty( $first_attachment_url ) ) {
			$args['attachments'][] = $first_attachment_url;
		}

		if ( isset( $action_meta['EMAILCONTENTTYPE'] ) && 'plain' === $action_meta['EMAILCONTENTTYPE'] ) {

			$args['content-type'] = 'text/plain; charset=UTF-8';

			$body = preg_replace( '/<br\s*\/?>/', PHP_EOL, $args['body'] );

			$body = wp_strip_all_tags( $body );

			$args['body'] = $body;

		}

		return $args;
	}


}
