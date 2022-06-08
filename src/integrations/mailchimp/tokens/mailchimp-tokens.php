<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Api_Server;

/**
 * Mailchimp Tokens.
 */
class MAILCHIMP_TOKENS {


	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {

		add_action( 'automator_before_trigger_completed', array( $this, 'save_token_data' ), 20, 2 );

		add_filter( 'automator_maybe_trigger_mailchimp_tokens', array( $this, 'register_tokens' ), 20, 2 );

		add_filter( 'automator_maybe_trigger_mailchimp_anon_mailchimp_contact_unsubscribed_meta_tokens', array( $this, 'audience_field_possible_tokens' ), 20, 2 );

		add_filter( 'automator_maybe_trigger_mailchimp_anon_mailchimp_contact_added_meta_tokens', array( $this, 'audience_field_possible_tokens' ), 20, 2 );

		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_tokens' ), 20, 6 );

	}

	/**
	 * @param $tokens
	 * @param $args
	 *
	 * @return array|mixed
	 */
	public function audience_field_possible_tokens( $tokens = array(), $args = array() ) {

		// If its from heartbeat, or if its not from recipe edit page, or if not from automator rest, bail out.
		if ( false === $this->is_recipe_or_rest() ) {
			return $tokens;
		}

		// If the value is empty, bail.
		if ( empty( $args['value'] ) ) {
			return $tokens;
		}

		$list_id = $args['value'];

		// If value is "Any", bail.
		if ( '-1' === $list_id ) {
			return $tokens;
		}

		$request_params = array(
			'action'  => 'get_list_fields',
			'list_id' => $list_id,
		);

		// Transient is unique for each Audience.
		$key = 'automator_mailchimp_audience_' . $list_id . '_fields';

		$fields = get_transient( $key );

		if ( false === $fields ) {

			try {

				$has_requested = Automator()->cache->get( 'automator_mailchimp_audience_tokens_has_requested' );

				// Avoid multiple API calls.
				if ( false === $has_requested ) {

					// Request from MailChimp api if there are no transients saved.
					$response = Automator()->helpers->recipe->mailchimp->options->api_request( $request_params );

					if ( isset( $response['data']['merge_fields'] ) && ! empty( $response['data']['merge_fields'] ) ) {

						foreach ( $response['data']['merge_fields'] as $field ) {

							$fields[] = array(
								'tokenId'         => $field['tag'],
								'tokenName'       => $field['name'],
								'tokenType'       => 'text',
								'tokenIdentifier' => 'MAILCHIMP_MERGEFIELD_' . $field['tag'],
							);

						}

						set_transient( $key, $fields, 5 * MINUTE_IN_SECONDS );

					}

					Automator()->cache->set( 'automator_mailchimp_audience_tokens_has_requested', 'yes' );
				}
			} catch ( \Exception $e ) {

				$response = array();

				automator_log( $e->getMessage(), 'MailChimp error', true );

			}
		}

		if ( is_array( $fields ) ) {

			$tokens = array_merge( (array) $tokens, (array) $fields );

		}

		return $tokens;

	}

	/**
	 * Register the tokens.
	 *
	 * @param  mixed $tokens
	 * @param  mixed $args
	 * @return void
	 */
	public function register_tokens( $tokens = array(), $args = array() ) {

		$trigger_integration = $args['integration'];

		$trigger_meta = $args['meta'];

		$tokens_collection = array_merge( $this->get_tokens_collection() );

		$arr_column_tokens_collection = array_column( $tokens_collection, 'name' );

		array_multisort( $arr_column_tokens_collection, SORT_ASC, $tokens_collection );

		$tokens = array();

		foreach ( $tokens_collection as $token ) {
			$tokens[] = array(
				'tokenId'         => str_replace( ' ', '_', $token['id'] ),
				'tokenName'       => $token['name'],
				'tokenType'       => 'text',
				'tokenIdentifier' => strtoupper( 'MAILCHIMP_' . $token['id'] ),
			);
		}

		return $tokens;

	}


	/**
	 * Tokens collection.
	 */
	public function get_tokens_collection() {

		return array(
			array(
				'name' => esc_html__( 'Email', 'uncanny-automator' ),
				'id'   => 'email',
			),
		);

	}

	/**
	 * Save the token data.
	 *
	 * @param  mixed $args
	 * @param  mixed $trigger
	 * @return void
	 */
	public function save_token_data( $args, $trigger ) {

		if ( ! isset( $args['trigger_args'] ) || ! isset( $args['entry_args']['code'] ) ) {
			return;
		}

		$triggers = array( 'ANON_MAILCHIMP_CONTACT_UNSUBSCRIBED', 'ANON_MAILCHIMP_CONTACT_ADDED', 'ANON_MAILCHIMP_CONTACT_EMAIL_CHANGED' );

		if ( in_array( $args['entry_args']['code'], $triggers, true ) ) {

			$mailchimp_event_data = array_shift( $args['trigger_args'] );

			if ( isset( $mailchimp_event_data['data'] ) && ! empty( $mailchimp_event_data['data'] ) ) {

				Automator()->db->token->save( 'MAILCHIMP_WEBHOOK_EVENT_DATA', wp_json_encode( $mailchimp_event_data['data'] ), $args['trigger_entry'] );

			}
		}

	}

	/**
	 * Parsing the tokens.
	 *
	 * @param  mixed $value
	 * @param  mixed $pieces
	 * @param  mixed $recipe_id
	 * @param  mixed $trigger_data
	 * @param  mixed $user_id
	 * @param  mixed $replace_args
	 * @return void
	 */
	public function parse_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		$trigger_code = '';

		if ( isset( $trigger_data[0]['meta']['code'] ) ) {
			$trigger_code = $trigger_data[0]['meta']['code'];
		}

		$triggers = array( 'ANON_MAILCHIMP_CONTACT_UNSUBSCRIBED', 'ANON_MAILCHIMP_CONTACT_ADDED', 'ANON_MAILCHIMP_CONTACT_EMAIL_CHANGED' );

		if ( empty( $trigger_code ) || ! in_array( $trigger_code, $triggers, true ) ) {
			return $value;
		}

		if ( ! is_array( $pieces ) || ! isset( $pieces[1] ) || ! isset( $pieces[2] ) ) {
			return $value;
		}

		// Get the meta from database record.
		$mailchimp_webhook_data = json_decode( Automator()->db->token->get( 'MAILCHIMP_WEBHOOK_EVENT_DATA', $replace_args ), true );

		// The pieces[2] is equal to Mailchimp's key.
		if ( ! empty( $mailchimp_webhook_data[ $pieces[2] ] ) ) {

			$value = $mailchimp_webhook_data[ $pieces[2] ];

		}

		// New email or upemail event does not contain 'email' field. It contains 'new_email' instead.
		if ( isset( $mailchimp_webhook_data['new_email'] ) && ! empty( $mailchimp_webhook_data['new_email'] ) ) {

			$value = $mailchimp_webhook_data['new_email'];

		}

		// Handle merge fields.

		if ( false !== strpos( $pieces[1], 'MAILCHIMP_MERGEFIELD' ) ) {

			if ( isset( $mailchimp_webhook_data['merges'][ $pieces[2] ] ) && ! empty( $mailchimp_webhook_data['merges'][ $pieces[2] ] ) ) {

				$value = $mailchimp_webhook_data['merges'][ $pieces[2] ];

			}
		}

		// Handle array values such as address.
		if ( is_array( $value ) ) {
			$value = implode( ', ', $value );
		}

		return $value;

	}

	/**
	 * Check if its from recipe page or from automator rest and not doing heartbeat.
	 *
	 * @return boolean.
	 */
	private function is_recipe_or_rest() {

		if (
			isset( $_REQUEST['action'] ) && //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			(
				'heartbeat' === (string) sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) || //phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'wp-remove-post-lock' === (string) sanitize_text_field( wp_unslash( $_REQUEST['action'] ) )  //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			)
		) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			// if it's heartbeat, post lock actions bail
			return false;
		}

		if ( ! Automator()->helpers->recipe->is_edit_page() && ! Automator()->helpers->recipe->is_rest() ) {
			// If not automator edit page or rest call, bail
			return false;
		}

		return true;

	}


}
