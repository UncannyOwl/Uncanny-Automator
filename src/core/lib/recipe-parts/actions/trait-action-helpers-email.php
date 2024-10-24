<?php


namespace Uncanny_Automator\Recipe;

use Uncanny_Automator\Automator_WP_Error;
use Uncanny_Automator\Services\Email\Attachment\Handler;
use WP_Error;

/**
 * Trait Action_Helpers_Email
 *
 * @package Uncanny_Automator\Recipe
 */
trait Action_Helpers_Email {
	/**
	 * @var
	 */
	private $headers;
	/**
	 * @var
	 */
	private $from;
	/**
	 * @var
	 */
	private $from_name;
	/**
	 * @var
	 */
	private $cc;
	/**
	 * @var
	 */
	private $bcc;
	/**
	 * @var
	 */
	private $reply_to;
	/**
	 * @var
	 */
	private $to;
	/**
	 * @var
	 */
	private $subject;
	/**
	 * @var
	 */
	private $body;
	/**
	 * @var bool
	 */
	private $is_html = true;
	/**
	 * @var string
	 */
	private $content_type = 'text/html';
	/**
	 * @var string
	 */
	private $charset = 'utf-8';
	/**
	 * @var
	 */
	private $attachments;
	/**
	 * @var array
	 */
	private $all = array();

	/**
	 * @var array
	 */
	private $actions_data = array();

	/**
	 * @var Handler
	 */
	protected $attachment_handler = null;

	/**
	 * @return array
	 */
	public function get_actions_data() {
		return $this->actions_data;
	}

	/**
	 * @param $data
	 * @param $user_id
	 * @param $recipe_id
	 * @param $args
	 *
	 * @return void
	 */
	public function set_actions_data( $data, $user_id, $recipe_id, $args ) {
		$this->actions_data = array(
			'data'      => $data,
			'user_id'   => $user_id,
			'recipe_id' => $recipe_id,
			'args'      => $args,
		);
	}

	/**
	 * @param array $data
	 * @param null $user_id
	 * @param null $recipe_id
	 * @param array $args
	 */
	public function set_mail_values( $data = array(), $user_id = null, $recipe_id = null, $args = array() ) {

		$defaults = array(
			'from'       => Automator()->parse->text( '{{admin_email}}', $recipe_id, $user_id, $args ),
			'from_name'  => Automator()->parse->text( '{{site_name}}', $recipe_id, $user_id, $args ),
			'to'         => Automator()->parse->text( '{{user_email}}', $recipe_id, $user_id, $args ),
			'cc'         => '',
			'bcc'        => '',
			'reply_to'   => '',
			'content'    => $this->get_content_type(),
			'charset'    => $this->get_charset(),
			'subject'    => '',
			'body'       => '',
			'attachment' => '',
		);

		$data = wp_parse_args( $data, $defaults );

		$from_email = sanitize_email( $data['from'] );
		$from_name  = sanitize_text_field( $data['from_name'] );
		$to_email   = $data['to']; // The sanitize_email is added to Automator Email Helpers sent method.
		$cc_email   = ! empty( $data['cc'] ) ? $data['cc'] : '';
		$bcc_email  = ! empty( $data['bcc'] ) ? $data['bcc'] : '';
		$reply_to   = sanitize_email( $data['reply_to'] );
		$content    = sanitize_text_field( $data['content'] );
		$charset    = sanitize_text_field( $data['charset'] );
		$subject    = sanitize_text_field( stripslashes( html_entity_decode( $data['subject'] ) ) );
		$attachment = $defaults['attachment'];

		if ( 'text/html' !== $this->get_content_type() ) {
			$body = wp_filter_post_kses( stripslashes( $data['body'] ) );
		} else {
			$body = stripslashes( $data['body'] );
		}

		// Resetting to array.
		$this->to = array();

		$this->set_to( $to_email );
		$this->set_from( $from_email );
		$this->set_from_name( $from_name );
		$this->set_reply_to( $reply_to );
		$this->set_cc( $cc_email );
		$this->set_bcc( $bcc_email );
		$this->set_content_type( $content );
		$this->set_charset( $charset );
		$this->set_subject( $subject );
		$this->set_body( $body );
		$this->set_attachments( $attachment );

		if ( ! empty( $data['attachment'] ) ) {
			$this->set_attachments( $data['attachment'] );
		}

		$this->set_actions_data( $data, $user_id, $recipe_id, $args );

	}

	/**
	 * @return mixed
	 */
	public function get_headers() {
		return $this->headers;
	}

	/**
	 * @param mixed $headers
	 */
	public function set_headers( $headers ) {
		$this->headers = $headers;
	}

	/**
	 * @return mixed
	 */
	public function get_from() {
		return $this->from;
	}

	/**
	 * @param mixed $from
	 */
	public function set_from( $from ) {
		$this->from = $from;
	}

	/**
	 * @return mixed
	 */
	public function get_from_name() {
		return $this->from_name;
	}

	/**
	 * @param mixed $from_name
	 */
	public function set_from_name( $from_name ) {
		$this->from_name = $from_name;
	}

	/**
	 * @return mixed
	 */
	public function get_cc() {
		return $this->cc;
	}

	/**
	 * @param mixed $cc
	 */
	public function set_cc( $cc ) {
		$this->cc = $this->santize_emails( explode( ',', $cc ) );
	}

	/**
	 * @return mixed
	 */
	public function get_bcc() {
		return $this->bcc;
	}

	/**
	 * @param mixed $bcc
	 */
	public function set_bcc( $bcc ) {
		$this->bcc = $this->santize_emails( explode( ',', $bcc ) );
	}

	/**
	 * @return mixed
	 */
	public function get_reply_to() {
		return $this->reply_to;
	}

	/**
	 * @param mixed $reply_to
	 */
	public function set_reply_to( $reply_to ) {
		$this->reply_to = $reply_to;
	}

	/**
	 * @return mixed
	 */
	public function get_to() {
		return $this->to;
	}

	/**
	 * @param mixed $to
	 */
	public function set_to( $to ) {
		$this->to = $this->santize_emails( explode( ',', $to ) );
	}

	/**
	 * @return mixed
	 */
	public function get_subject() {
		return $this->subject;
	}

	/**
	 * @param mixed $subject
	 */
	public function set_subject( $subject ) {
		$this->subject = $subject;
	}

	/**
	 * @return mixed
	 */
	public function get_body() {
		return $this->body;
	}

	/**
	 * @param mixed $body
	 */
	public function set_body( $body ) {
		$this->body = $body;
	}

	/**
	 * @return bool
	 */
	public function is_is_html() {
		return $this->is_html;
	}

	/**
	 * @param $is_html
	 */
	public function set_is_html( $is_html ) {
		$this->is_html = $is_html;
	}

	/**
	 * @return string
	 */
	public function get_content_type() {
		return $this->content_type;
	}

	/**
	 * @param $content_type
	 */
	public function set_content_type( $content_type ) {
		$this->content_type = $content_type;
	}

	/**
	 * @return string
	 */
	public function get_charset() {
		return $this->charset;
	}

	/**
	 * @param $charset
	 */
	public function set_charset( $charset ) {
		$this->charset = $charset;
	}

	/**
	 * @return mixed
	 */
	public function get_attachments() {
		return $this->attachments;
	}

	/**
	 * @param mixed $attachments
	 */
	public function set_attachments( $attachments ) {
		$this->attachments[] = $attachments;
	}

	/**
	 * @param $emails
	 *
	 * @return array
	 */
	public function santize_emails( $emails ) {

		$sanitized_emails = array();

		foreach ( $emails as $key => $email ) {
			$sanitized_emails[ $key ] = sanitize_email( $email );
		}

		return $sanitized_emails;
	}

	/**
	 * @return bool|mixed|void|Automator_WP_Error
	 */
	public function send_email() {

		$header_raw = array(
			'from'      => $this->get_from(),
			'from_name' => $this->get_from_name(),
			'cc'        => $this->get_cc(),
			'bcc'       => $this->get_bcc(),
			'reply_to'  => $this->get_reply_to(),
			'content'   => $this->get_content_type(),
			'charset'   => $this->get_charset(),
		);

		// Process the attachments.
		$attachments = $this->get_attachments();

		$local_attachments = array();

		foreach ( $attachments as $attachment_url ) {
			if ( ! empty( $attachment_url ) ) {

				$attachment          = $this->process_attachment( $attachment_url );
				$local_attachments[] = $attachment;

				if ( is_wp_error( $attachment ) ) {
					$this->set_error_message( $attachment->get_error_message() );
					return false;
				}
			}
		}

		$headers     = apply_filters( 'automator_email_headers', Automator()->helpers->email->headers( $header_raw ), $this );
		$to          = apply_filters( 'automator_email_to', $this->get_to(), $this );
		$subject     = apply_filters( 'automator_email_subject', stripslashes( $this->get_subject() ), $this );
		$body        = apply_filters( 'automator_email_body', stripslashes( $this->get_body() ), $this );
		$attachments = apply_filters( 'automator_email_attachments', $this->get_attachments(), $this );
		$pass        = array(
			'to'         => $to,
			'subject'    => $subject,
			'body'       => $body,
			'headers'    => $headers,
			'attachment' => $local_attachments,
			'is_html'    => $this->is_is_html(),
		);

		if ( true === apply_filters( 'automator_send_email', true, $pass, $this->get_actions_data(), $this ) ) {
			$mailed = Automator()->helpers->email->send( $pass );
		} else {
			$error = Automator()->error;
			$error->add_error( 'wp_mail', esc_attr__( 'Email action is disabled by `automator_send_email` filter.', 'uncanny-automator' ), $pass );
			$mailed = $error;
		}

		if ( is_automator_error( $mailed ) ) {
			$errors = $mailed->get_messages( 'wp_mail' ) + $mailed->get_messages( 'wp_mail_to' );
			if ( $errors ) {
				foreach ( $errors as $error ) {
					$this->set_error_message( $error );
				}
			}

			return false;
		}

		return $mailed;
	}

	/**
	 * Processes the attachment.
	 *
	 * @param mixed $attachment_url
	 *
	 * @return WP_Error|array|string
	 */
	public function process_attachment( $attachment_url ) {

		$this->attachment_handler = new Handler( $attachment_url );

		$attachment_path = $this->attachment_handler->process_attachment();

		// Use pathinfo to get the extension.
		$path_info = pathinfo( wp_parse_url( $attachment_url, PHP_URL_PATH ) );

		// Check if an extension is available.
		// @phase 1. Only require file extension.
		if ( empty( $path_info['extension'] ) ) {
			return new WP_Error(
				'file_extension_empty',
				__( 'Please ensure that the URL you entered ends with a valid file extension, such as .pdf, .png, or .doc. This ensures the file can be properly accessed and opened.', 'uncanny-automator' ),
			);
		}

		return $attachment_path;

	}

	/**
	 * Clean up the files.
	 *
	 * @return void
	 */
	public function __destruct() {

		if ( $this->attachment_handler && $this->attachment_handler instanceof Handler ) {
			$this->attachment_handler->cleanup();
		}
	}

}
