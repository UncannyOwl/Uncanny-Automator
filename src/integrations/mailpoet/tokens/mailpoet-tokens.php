<?php //phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

use MailPoet\FormEntity;

/**
 * Class mailpoet_Tokens
 *
 * @package Uncanny_Automator
 */
class Mailpoet_Tokens {

	/**
	 * mailpoet_Tokens constructor.
	 */
	public function __construct() {

		add_filter(
			'automator_maybe_parse_token',
			array(
				$this,
				'mailpoet_tokens',
			),
			20,
			6
		);

	}

	public function mailpoet_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args = array() ) {

		if ( empty( $pieces ) ) {
			return $value;
		}

		if ( ! array_intersect(
			array(
				'MAILPOETSUBFORM',
			),
			$pieces
		) ) {
			return $value;
		}

		if ( empty( $trigger_data ) ) {
			return $value;
		}

		// Parse Form title token.
		if ( isset( $pieces[1] ) && 'MAILPOETSUBFORM' === $pieces[1] && isset( $pieces[2] ) && 'MAILPOETFORMS' === $pieces[2] ) {
			return Automator()->db->token->get( 'MAILPOETFORMS', $replace_args );
		}

		// Parse Form ID token.
		if ( isset( $pieces[1] ) && 'MAILPOETSUBFORM' === $pieces[1] && isset( $pieces[2] ) && 'MAILPOETFORMS_ID' === $pieces[2] ) {
			return Automator()->db->token->get( 'MAILPOETFORMS_ID', $replace_args );
		}

		// Parse Form email token.
		if ( isset( $pieces[1] ) && 'MAILPOETSUBFORM' === $pieces[1] && isset( $pieces[2] ) && 'MAILPOETFORMS_EMAIL' === $pieces[2] ) {
			return Automator()->db->token->get( 'MAILPOETFORMS_EMAIL', $replace_args );
		}

		// Parse First name token.
		if ( isset( $pieces[1] ) && 'MAILPOETSUBFORM' === $pieces[1] && isset( $pieces[2] ) && 'MAILPOETFORMS_FIRSTNAME' === $pieces[2] ) {
			return Automator()->db->token->get( 'MAILPOETFORMS_FIRSTNAME', $replace_args );
		}

		// Parse Last name token.
		if ( isset( $pieces[1] ) && 'MAILPOETSUBFORM' === $pieces[1] && isset( $pieces[2] ) && 'MAILPOETFORMS_LASTNAME' === $pieces[2] ) {
			return Automator()->db->token->get( 'MAILPOETFORMS_LASTNAME', $replace_args );
		}

		return $value;
	}

}
