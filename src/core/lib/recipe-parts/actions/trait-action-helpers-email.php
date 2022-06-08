<?php


namespace Uncanny_Automator\Recipe;

use Uncanny_Automator\Automator_WP_Error;

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
	 * @param $data
	 */
	public function set_mail_values( $data ) {

		$defaults = array(
			'from'      => Automator()->parse->text( '{{admin_email}}' ),
			'from_name' => Automator()->parse->text( '{{site_name}}' ),
			'to'        => Automator()->parse->text( '{{user_email}}' ),
			'cc'        => '',
			'bcc'       => '',
			'reply_to'  => '',
			'content'   => $this->get_content_type(),
			'charset'   => $this->get_charset(),
			'subject'   => '',
			'body'      => '',
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
		$subject    = sanitize_text_field( stripslashes( $data['subject'] ) );
		if ( 'text/html' !== $this->get_content_type() ) {
			$body = wp_filter_post_kses( stripslashes( $data['body'] ) );
		} else {
			$body = stripslashes( $data['body'] );
		}
		// Resetting to array
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
	 * maybe_santize_email
	 *
	 * @param mixed $emails
	 *
	 * @return void
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
			'attachment' => $attachments,
			'is_html'    => $this->is_is_html(),
		);
		$mailed      = Automator()->helpers->email->send( $pass );
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
}
