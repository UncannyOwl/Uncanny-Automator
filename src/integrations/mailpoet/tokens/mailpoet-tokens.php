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

		add_action( 'automator_before_trigger_completed', array( $this, 'save_token_data' ), 20, 2 );

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

		if ( 'ANON_MAILPOETSUBFORM' === $args['entry_args']['code'] ) {
			list( $data, $segmentIds, $form ) = $args['trigger_args'];
			$trigger_log_entry                = $args['trigger_entry'];
			if ( ! empty( $data ) && ! empty( $form ) ) {
				Automator()->db->token->save( 'MAILPOETFORMS', esc_html( $form->getName() ), $trigger_log_entry );
				Automator()->db->token->save( 'MAILPOETFORMS_ID', esc_html( $form->getID() ), $trigger_log_entry );
				Automator()->db->token->save( 'MAILPOETFORMS_EMAIL', $data['email'], $trigger_log_entry );
				Automator()->db->token->save( 'MAILPOETFORMS_FIRSTNAME', $data['first_name'], $trigger_log_entry );
				Automator()->db->token->save( 'MAILPOETFORMS_LASTNAME', $data['last_name'], $trigger_log_entry );
			}
		}
	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return mixed|string
	 */
	public function mailpoet_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args = array() ) {

		if ( empty( $pieces ) ) {
			return $value;
		}

		if ( ! array_intersect(
			array(
				'MAILPOETSUBFORM',
				'ANON_MAILPOETSUBFORM',
			),
			$pieces
		) ) {
			return $value;
		}

		if ( empty( $trigger_data ) ) {
			return $value;
		}

		// Parse Form title token.
		if ( isset( $pieces[1] ) && ( 'MAILPOETSUBFORM' === $pieces[1] || 'ANON_MAILPOETSUBFORM' === $pieces[1] ) && isset( $pieces[2] ) && 'MAILPOETFORMS' === $pieces[2] ) {
			return Automator()->db->token->get( 'MAILPOETFORMS', $replace_args );
		}

		// Parse Form ID token.
		if ( isset( $pieces[1] ) && ( 'MAILPOETSUBFORM' === $pieces[1] || 'ANON_MAILPOETSUBFORM' === $pieces[1] ) && isset( $pieces[2] ) && 'MAILPOETFORMS_ID' === $pieces[2] ) {
			return Automator()->db->token->get( 'MAILPOETFORMS_ID', $replace_args );
		}

		// Parse Form email token.
		if ( isset( $pieces[1] ) && ( 'MAILPOETSUBFORM' === $pieces[1] || 'ANON_MAILPOETSUBFORM' === $pieces[1] ) && isset( $pieces[2] ) && 'MAILPOETFORMS_EMAIL' === $pieces[2] ) {
			return Automator()->db->token->get( 'MAILPOETFORMS_EMAIL', $replace_args );
		}

		// Parse First name token.
		if ( isset( $pieces[1] ) && ( 'MAILPOETSUBFORM' === $pieces[1] || 'ANON_MAILPOETSUBFORM' === $pieces[1] ) && isset( $pieces[2] ) && 'MAILPOETFORMS_FIRSTNAME' === $pieces[2] ) {
			return Automator()->db->token->get( 'MAILPOETFORMS_FIRSTNAME', $replace_args );
		}

		// Parse Last name token.
		if ( isset( $pieces[1] ) && ( 'MAILPOETSUBFORM' === $pieces[1] || 'ANON_MAILPOETSUBFORM' === $pieces[1] ) && isset( $pieces[2] ) && 'MAILPOETFORMS_LASTNAME' === $pieces[2] ) {
			return Automator()->db->token->get( 'MAILPOETFORMS_LASTNAME', $replace_args );
		}

		return $value;
	}

}
