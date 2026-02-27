<?php

namespace Uncanny_Automator\Integrations\Easy_Wp_Smtp;

/**
 * Class Ewpsmtp_Email_Failed
 *
 * @package Uncanny_Automator
 */
class Ewpsmtp_Email_Failed extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Setup trigger configuration.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_integration( 'EASY_WP_SMTP' );
		$this->set_trigger_code( 'EWPSMTP_EMAIL_FAILED' );
		$this->set_trigger_meta( 'EWPSMTP_EMAIL' );
		$this->set_is_pro( false );
		$this->set_is_login_required( false );
		$this->set_trigger_type( 'anonymous' );
		$this->set_uses_api( false );
		$this->set_sentence( esc_html_x( 'An email fails to send', 'Easy WP SMTP', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'An email fails to send', 'Easy WP SMTP', 'uncanny-automator' ) );
		$this->add_action( 'easy_wp_smtp_mailcatcher_send_failed', 10, 3 );
	}

	/**
	 * Define trigger options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array();
	}

	/**
	 * Define available tokens.
	 *
	 * @param array $trigger The trigger settings.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array(
			array(
				'tokenId'   => 'TO',
				'tokenName' => esc_html_x( 'To', 'Easy WP SMTP', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'FROM',
				'tokenName' => esc_html_x( 'From email', 'Easy WP SMTP', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'FROM_NAME',
				'tokenName' => esc_html_x( 'From name', 'Easy WP SMTP', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'SUBJECT',
				'tokenName' => esc_html_x( 'Subject', 'Easy WP SMTP', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'BODY',
				'tokenName' => esc_html_x( 'Body', 'Easy WP SMTP', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'ERROR_MESSAGE',
				'tokenName' => esc_html_x( 'Error message', 'Easy WP SMTP', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'MAILER_TYPE',
				'tokenName' => esc_html_x( 'Mailer type', 'Easy WP SMTP', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Validate trigger against hook arguments.
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		return true;
	}

	/**
	 * Hydrate token values from hook arguments.
	 *
	 * @param array $trigger   The completed trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		// Hook signature: do_action( 'easy_wp_smtp_mailcatcher_send_failed', $exception, $mailcatcher, $connection ).
		list( $error_msg, $mailcatcher, $mail_mailer ) = $hook_args;

		return array(
			'TO'            => implode( ', ', array_column( $mailcatcher->getToAddresses(), 0 ) ),
			'FROM'          => $mailcatcher->From,
			'FROM_NAME'     => $mailcatcher->FromName,
			'SUBJECT'       => $mailcatcher->Subject,
			'BODY'          => $mailcatcher->Body,
			'ERROR_MESSAGE' => is_wp_error( $error_msg ) ? $error_msg->get_error_message() : (string) $error_msg,
			'MAILER_TYPE'   => $mailcatcher->Mailer,
		);
	}
}
