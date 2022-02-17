<?php


namespace Uncanny_Automator;

/**
 * Class Automator_Email_Helpers
 *
 * @package Uncanny_Automator
 */
class Automator_Email_Helpers {
	/**
	 * @var
	 */
	public static $instance;

	/**
	 * @return Automator_Email_Helpers
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * @param array $header_data
	 *
	 * @return array
	 */
	public function headers( $header_data = array() ) {

		/**
		 * @param string $from
		 * @param string $from_name
		 * @param array $cc
		 * @param array $bcc
		 * @param string $reply_to
		 * @param string $content
		 * @param string $charset
		 */
		extract( $header_data ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		$headers = array();

		// Add From in headers.
		if ( ! empty( $from_name ) && ! empty( $from ) ) {
			$headers[] = 'From: ' . $from_name . ' <' . $from . '>';
		} elseif ( ! empty( $from ) ) {
			$headers[] = 'From: <' . $from . '>';
		}

		// Add CC in headers.
		if ( ! empty( $cc ) ) {
			$cced      = join( ', ', $cc );
			$headers[] = "Cc: $cced";
		}

		// Add BCC in headers.
		if ( ! empty( $bcc ) ) {
			$bcced     = join( ', ', $bcc );
			$headers[] = "Bcc: $bcced";
		}

		// Add Reply-to in headers.
		if ( ! empty( $reply_to ) ) {
			$headers[] = "Reply-To: $reply_to";
		}

		// Add Content-type and charset in headers.
		if ( ! empty( $content ) ) {
			$type = 'Content-Type: ' . $content . ';';
			if ( ! empty( $charset ) ) {
				$type .= ' charset=' . $charset . ';';
			}
			$headers[] = $type;
		}

		return $headers;
	}

	/**
	 * @param $mail
	 *
	 * @return bool|mixed|void|Automator_WP_Error
	 */
	public function send( $mail ) {
		/**
		 * @param string $to
		 * @param string $subject
		 * @param string $body
		 * @param array $headers
		 * @param array $attachments
		 * @param array $is_html
		 */

		$to          = $mail['to'];
		$subject     = $mail['subject'];
		$body        = $mail['body'];
		$headers     = $mail['headers'];
		$attachments = $mail['attachment'];
		$is_html     = $mail['is_html'];
		$error       = Automator()->error;

		if ( ! $error->get_message( 'wp_mail_to' ) ) {
			if ( is_array( $to ) ) {
				foreach ( $to as $to_email ) {
					if ( empty( $to_email ) ) {
						$error->add_error( 'wp_mail_to', esc_attr__( '"To" address is empty.', 'uncanny-automator' ), $mail );
					}
					if ( ! is_email( $to_email ) ) {
						$error->add_error( 'wp_mail_to', esc_attr__( '"To" address is invalid.', 'uncanny-automator' ), $mail );
					}
				}
			} else {
				if ( empty( $to ) ) {
					$error->add_error( 'wp_mail_to', esc_attr__( '"To" address is empty.', 'uncanny-automator' ), $mail );
				}
				if ( ! is_email( $to ) ) {
					$error->add_error( 'wp_mail_to', esc_attr__( '"To" address is invalid.', 'uncanny-automator' ), $mail );
				}
			}
		}

		if ( empty( $headers ) ) {
			$headers = array();
		}

		if ( empty( $attachments ) ) {
			$attachments = array();
		}

		if ( ! empty( $error->get_messages( 'wp_mail_to' ) ) ) {
			return $error;
		}

		return wp_mail( $to, $subject, $body, $headers, $attachments );
	}
}
