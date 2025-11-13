<?php

namespace Uncanny_Automator\Integrations\Twilio;

use Exception;
use Uncanny_Automator\Recipe\Log_Properties;


/**
 * Class TWILIO_SEND_SMS
 *
 * @package Uncanny_Automator
 *
 * @property Twilio_App_Helpers $helpers
 * @property Twilio_Api_Caller $api
 */
class TWILIO_SEND_SMS extends \Uncanny_Automator\Recipe\App_Action {

	use Log_Properties;

	/**
	 * Setup action
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'TWILIO' );
		$this->set_action_code( 'TWILIOSENDSMS' );
		$this->set_action_meta( 'TWSENDSMS' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );

		$this->set_sentence(
			sprintf(
				// translators: %1$s: Phone number
				esc_attr_x( 'Send an SMS {{to a number:%1$s}}', 'Twilio', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_attr_x( 'Send an SMS {{to a number}}', 'Twilio', 'uncanny-automator' ) );

		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/twilio/' ) );
	}

	/**
	 * Define options
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_attr_x( 'To', 'Twilio', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
				'tokens'      => true,
				'description' => esc_html_x( 'Separate multiple phone numbers with a comma', 'Twilio', 'uncanny-automator' ),
			),
			array(
				'option_code' => 'SMSBODY',
				'label'       => esc_attr_x( 'Body', 'Twilio', 'uncanny-automator' ),
				'input_type'  => 'textarea',
				'required'    => true,
				'tokens'      => true,
				'description' => sprintf(
					// translators: %1$s: Allowed characters count, %2$s: Single ASCII count, %3$s: Single Unicode count
					esc_html_x( 'SMS allows up to %1$s. A single SMS can contain up to %2$s GSM characters, or %3$s Unicode characters. Multiple SMS segments are supported.', 'Twilio', 'uncanny-automator' ),
					'<strong>' . esc_html_x( '1600 characters', 'Twilio', 'uncanny-automator' ) . '</strong>',
					'<strong>' . esc_html_x( '160', 'Twilio', 'uncanny-automator' ) . '</strong>',
					'<strong>' . esc_html_x( '70', 'Twilio', 'uncanny-automator' ) . '</strong>'
				),
			),
		);
	}

	/**
	 * Define tokens
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'SMS_STATUS' => array(
				'name' => esc_attr_x( 'SMS status', 'Twilio', 'uncanny-automator' ),
				'type' => 'text',
			),
			'SMS_SID'    => array(
				'name' => esc_attr_x( 'SMS ID', 'Twilio', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Process the action
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param array $parsed
	 * @return bool
	 * @throws Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		// Get parsed values.
		$to_numbers = $this->get_parsed_meta_value( $this->get_action_meta() );
		$body       = $this->get_parsed_meta_value( 'SMSBODY' );

		// Check for required fields.
		if ( empty( $to_numbers ) ) {
			throw new Exception( esc_html_x( 'Phone number is required', 'Twilio', 'uncanny-automator' ) );
		}

		if ( empty( $body ) ) {
			throw new Exception( esc_html_x( 'Message body is required', 'Twilio', 'uncanny-automator' ) );
		}

		// Parse multiple numbers
		$numbers = array_map( 'trim', explode( ',', $to_numbers ) );

		// Remove empty values
		$numbers = array_filter( $numbers );

		if ( empty( $numbers ) ) {
			throw new Exception( esc_html_x( 'No valid phone numbers provided', 'Twilio', 'uncanny-automator' ) );
		}

		// Send SMS to each number
		$results       = array();
		$errors        = array();
		$success_count = 0;
		$last_sms_sid  = '';

		foreach ( $numbers as $number ) {
			try {
				// Send individual SMS
				$result = $this->api->send_sms( $number, $body, $action_data );

				$results[] = array(
					'number' => $number,
					'status' => 'sent',
					'sid'    => ! empty( $result['sid'] ) ? $result['sid'] : '',
				);

				++$success_count;

				// Store last successful SMS SID
				if ( ! empty( $result['sid'] ) ) {
					$last_sms_sid = $result['sid'];
				}

				// Store in user meta for backward compatibility
				if ( $user_id > 0 ) {
					update_user_meta( $user_id, '_twilio_sms_', $result );
				}
			} catch ( Exception $e ) {
				$errors[] = sprintf(
					// translators: 1. Phone number, 2. Error message
					esc_html_x( '%1$s: %2$s', 'Twilio', 'uncanny-automator' ),
					$number,
					$e->getMessage()
				);

				$results[] = array(
					'number' => $number,
					'status' => 'failed',
					'error'  => $e->getMessage(),
				);
			}
		}

		// If all SMS failed, throw error
		if ( 0 === $success_count && ! empty( $errors ) ) {
			throw new Exception( esc_html( implode( '; ', $errors ) ) );
		}

		// Hydrate tokens with last successful SMS data.
		$this->hydrate_tokens(
			array(
				'SMS_STATUS' => $success_count > 0 ? 'sent' : 'failed',
				'SMS_SID'    => $last_sms_sid,
			)
		);

		// Add log entry for results.
		if ( ! empty( $errors ) ) {
			// Some messages failed.
			$log_message = sprintf(
				// translators: %1$d Success count, %2$d Total count, %3$s Errors
				esc_html_x( 'Sent %1$d of %2$d SMS messages. Failed: %3$s', 'Twilio', 'uncanny-automator' ),
				$success_count,
				count( $numbers ),
				implode( '; ', $errors )
			);

			// Log as notice, not error, since some succeeded.
			$this->set_complete_with_notice( true );
			$this->add_log_error( $log_message );

			return null;
		}

		return true;
	}
}
