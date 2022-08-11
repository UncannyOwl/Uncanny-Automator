<?php

namespace Uncanny_Automator;

/**
 * Class Wpmsmtp_Tokens
 *
 * @package Uncanny_Automator
 */
class Wpmsmtp_Tokens {
	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'automator_before_trigger_completed', array( $this, 'save_token_data' ), 20, 2 );

		add_filter(
			'automator_maybe_trigger_wpmailsmtppro_tokens',
			array(
				$this,
				'wp_mail_smtp_possible_tokens',
			),
			20,
			2
		);

		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_wp_mail_smtp_tokens' ), 2122, 6 );
	}

	/**
	 * save_token_data
	 *
	 * @param mixed $args
	 * @param mixed $trigger
	 *
	 * @return void
	 */
	public function save_token_data( $args, $trigger ) {
		if ( ! isset( $args['trigger_args'] ) || ! isset( $args['entry_args']['code'] ) ) {
			return;
		}

		$trigger_meta_validations = apply_filters(
			'automator_wp_mail_smtp_validate_trigger_meta_pieces',
			array( 'SPECIFIC_SUBJECT_CODE' ),
			$args
		);

		if ( in_array( $args['entry_args']['code'], $trigger_meta_validations, true ) ) {
			$email_tracking_details = array_shift( $args['trigger_args'] );
			$trigger_log_entry      = $args['trigger_entry'];
			if ( ! empty( $email_tracking_details ) ) {
				Automator()->db->token->save( 'tracking_data', maybe_serialize( $email_tracking_details ), $trigger_log_entry );
			}
		}
	}

	/**
	 * WP MAIl SMTP possible tokens.
	 *
	 * @param $tokens
	 * @param $args
	 *
	 * @return array|mixed|\string[][]
	 */
	public function wp_mail_smtp_possible_tokens( $tokens = array(), $args = array() ) {
		$trigger_code = $args['triggers_meta']['code'];

		$trigger_meta_validations = apply_filters(
			'automator_wp_mail_smtp_validate_trigger_meta_pieces',
			array( 'SPECIFIC_SUBJECT_CODE' ),
			$args
		);

		if ( in_array( $trigger_code, $trigger_meta_validations, true ) ) {

			$fields = array(
				array(
					'tokenId'         => 'EMAIL_LOG_ID',
					'tokenName'       => __( 'Email log ID', 'uncanny-automator' ),
					'tokenType'       => 'int',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'TO_EMAIL',
					'tokenName'       => __( 'To email', 'uncanny-automator' ),
					'tokenType'       => 'email',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'FROM_EMAIL',
					'tokenName'       => __( 'From email', 'uncanny-automator' ),
					'tokenType'       => 'email',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EMAIL_SOURCE',
					'tokenName'       => __( 'Email source', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EMAIL_SUBJECT',
					'tokenName'       => __( 'Email subject', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EMAIL_BODY',
					'tokenName'       => __( 'Email body', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EMAIL_STATUS',
					'tokenName'       => __( 'Email status', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EMAIL_TECH_DETAILS',
					'tokenName'       => __( 'Technical details', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'EMAIL_SENT_DATE',
					'tokenName'       => __( 'Date sent', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
			);

			$tokens = array_merge( $tokens, $fields );
		}

		return $tokens;
	}

	/**
	 * parse_tokens
	 *
	 * @param mixed $value
	 * @param mixed $pieces
	 * @param mixed $recipe_id
	 * @param mixed $trigger_data
	 * @param mixed $user_id
	 * @param mixed $replace_args
	 *
	 * @return void
	 */
	public function parse_wp_mail_smtp_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( ! is_array( $pieces ) || ! isset( $pieces[1] ) || ! isset( $pieces[2] ) ) {
			return $value;
		}

		$trigger_meta_validations = apply_filters(
			'automator_wp_mail_smtp_validate_trigger_meta_pieces',
			array( 'SPECIFIC_SUBJECT_CODE' ),
			array(
				'pieces'       => $pieces,
				'recipe_id'    => $recipe_id,
				'trigger_data' => $trigger_data,
				'user_id'      => $user_id,
				'replace_args' => $replace_args,
			)
		);

		if ( ! array_intersect( $trigger_meta_validations, $pieces ) ) {
			return $value;
		}

		$to_replace       = $pieces[2];
		$tracking_details = Automator()->db->token->get( 'tracking_data', $replace_args );
		$tracking_details = maybe_unserialize( $tracking_details );
		$email_log_id     = $tracking_details['email_log_id'];
		$email_log        = new \WPMailSMTP\Pro\Emails\Logs\Email( $email_log_id );

		switch ( $to_replace ) {
			case 'TO_EMAIL':
				$tos   = $email_log->get_people( 'to' );
				$value = is_array( $tos ) ? join( ', ', $tos ) : $tos;
				break;
			case 'FROM_EMAIL':
				$froms = $email_log->get_people( 'from' );
				$value = is_array( $froms ) ? join( ', ', $froms ) : $froms;
				break;
			case 'EMAIL_SOURCE':
				$value = $email_log->get_mailer();
				break;
			case 'EMAIL_SUBJECT':
				$value = $email_log->get_subject();
				break;
			case 'EMAIL_BODY':
				$value = $email_log->get_content();
				break;
			case 'EMAIL_STATUS':
				$value = $email_log->get_status_name();
				break;
			case 'EMAIL_TECH_DETAILS':
				$value = $email_log->get_headers();
				break;
			case 'EMAIL_SENT_DATE':
				$value = $email_log->get_date_sent()->format( 'Y-m-d H:i:s' );
				break;
			case 'EMAIL_LOG_ID':
			default:
				$value = $email_log_id;
				break;
		}

		return $value;
	}
}
