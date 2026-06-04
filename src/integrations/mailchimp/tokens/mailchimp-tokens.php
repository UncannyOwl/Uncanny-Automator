<?php

namespace Uncanny_Automator\Integrations\Mailchimp;

/**
 * Mailchimp Tokens Class
 *
 * Handles token registration, saving, and parsing for Mailchimp triggers.
 * Uses legacy hook-based approach for full backwards compatibility.
 *
 * @package Uncanny_Automator\Integrations\Mailchimp\Tokens
 */
class Mailchimp_Tokens {

	/**
	 * The helpers instance.
	 *
	 * @var Mailchimp_App_Helpers
	 */
	private $helpers;

	/**
	 * The trigger codes that receive merge data.
	 *
	 * @var array
	 */
	private $trigger_codes = array(
		'ANON_MAILCHIMP_CONTACT_ADDED',
		'ANON_MAILCHIMP_CONTACT_EMAIL_CHANGED',
		'ANON_MAILCHIMP_CONTACT_UNSUBSCRIBED',
	);

	/**
	 * Register hooks for token handling.
	 *
	 * @param Mailchimp_App_Helpers $helpers The helpers instance.
	 *
	 * @return void
	 */
	public function register_hooks( $helpers ) {
		$this->helpers = $helpers;

		// Save webhook data for token parsing.
		add_action( 'automator_before_trigger_completed', array( $this, 'save_token_data' ), 20, 2 );

		// Register base tokens.
		add_filter( 'automator_maybe_trigger_mailchimp_tokens', array( $this, 'register_tokens' ), 20, 2 );

		// Register dynamic merge field tokens (only for triggers that receive merge data).
		add_filter( 'automator_maybe_trigger_mailchimp_anon_mailchimp_contact_added_meta_tokens', array( $this, 'add_merge_field_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_trigger_mailchimp_anon_mailchimp_contact_unsubscribed_meta_tokens', array( $this, 'add_merge_field_tokens' ), 20, 2 );

		// Parse tokens.
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_tokens' ), 20, 6 );
	}

	/**
	 * Register base tokens.
	 *
	 * @param array $tokens Existing tokens.
	 * @param array $args   Filter arguments.
	 *
	 * @return array
	 */
	public function register_tokens( $tokens = array(), $args = array() ) {
		return array(
			array(
				'tokenId'         => 'email',
				'tokenName'       => esc_html_x( 'Email', 'Mailchimp', 'uncanny-automator' ),
				'tokenType'       => 'email',
				'tokenIdentifier' => 'MAILCHIMP_EMAIL',
			),
		);
	}

	/**
	 * Add dynamic merge field tokens for a specific audience.
	 *
	 * @param array $tokens Existing tokens.
	 * @param array $args   Filter arguments containing 'value' (audience ID).
	 *
	 * @return array
	 */
	public function add_merge_field_tokens( $tokens = array(), $args = array() ) {
		if ( ! $this->is_recipe_or_rest() ) {
			return $tokens;
		}

		if ( empty( $args['value'] ) || '-1' === $args['value'] ) {
			return $tokens;
		}

		$list_id = $args['value'];

		try {
			$merge_fields = $this->helpers->get_merge_fields( $list_id );

			if ( ! empty( $merge_fields ) ) {
				foreach ( $merge_fields as $field ) {
					$tokens[] = array(
						'tokenId'         => $field['tag'],
						'tokenName'       => $field['name'],
						'tokenType'       => 'text',
						'tokenIdentifier' => 'MAILCHIMP_MERGEFIELD_' . $field['tag'],
					);
				}
			}
		} catch ( \Exception $e ) {
			automator_log( $e->getMessage(), 'Mailchimp merge fields error', true );
		}

		return $tokens;
	}

	/**
	 * Save webhook data for token parsing.
	 *
	 * @param array $args    The trigger args.
	 * @param mixed $trigger The trigger instance.
	 *
	 * @return void
	 */
	public function save_token_data( $args, $trigger ) {
		if ( empty( $args['trigger_args'][0] ?? '' ) || empty( $args['entry_args']['code'] ?? '' ) ) {
			return;
		}

		if ( ! in_array( $args['entry_args']['code'], $this->trigger_codes, true ) ) {
			return;
		}

		$mailchimp_event_data = $args['trigger_args'][0];

		if ( ! empty( $mailchimp_event_data['data'] ?? '' ) ) {
			Automator()->db->token->save(
				'MAILCHIMP_WEBHOOK_EVENT_DATA',
				wp_json_encode( $mailchimp_event_data['data'] ),
				$args['entry_args']
			);
		}
	}

	/**
	 * Parse tokens.
	 *
	 * @param mixed $value        The token value.
	 * @param array $pieces       The token pieces.
	 * @param int   $recipe_id    The recipe ID.
	 * @param array $trigger_data The trigger data.
	 * @param int   $user_id      The user ID.
	 * @param array $replace_args The replace args.
	 *
	 * @return mixed
	 */
	public function parse_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		$trigger_code = $trigger_data[0]['meta']['code'] ?? '';

		if ( empty( $trigger_code ) || ! in_array( $trigger_code, $this->trigger_codes, true ) ) {
			return $value;
		}

		if ( ! is_array( $pieces ) || ! isset( $pieces[1] ) || ! isset( $pieces[2] ) ) {
			return $value;
		}

		$webhook_data = json_decode(
			Automator()->db->token->get( 'MAILCHIMP_WEBHOOK_EVENT_DATA', $replace_args ),
			true
		);

		if ( empty( $webhook_data ) ) {
			return $value;
		}

		$code = $pieces[2];

		// Handle base data fields (like email).
		if ( ! empty( $webhook_data[ $code ] ) ) {
			$value = $webhook_data[ $code ];
		}

		// Handle email change event (new_email field).
		if ( 'email' === $code && ! empty( $webhook_data['new_email'] ?? '' ) ) {
			$value = $webhook_data['new_email'];
		}

		// Handle merge fields.
		if ( false !== strpos( $pieces[1], 'MAILCHIMP_MERGEFIELD' ) ) {
			if ( ! empty( $webhook_data['merges'][ $code ] ?? '' ) ) {
				$value = $webhook_data['merges'][ $code ];
			}
		}

		// Handle array values (like address).
		if ( is_array( $value ) ) {
			// Skip nested arrays (like GROUPINGS).
			if ( ! empty( $value ) && is_array( reset( $value ) ) ) {
				return '';
			}
			$value = implode( ', ', array_filter( $value ) );
		}

		return $value;
	}

	/**
	 * Check if request is from recipe page or REST API.
	 *
	 * @return bool
	 */
	private function is_recipe_or_rest() {
		if ( isset( $_REQUEST['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$action = sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( in_array( $action, array( 'heartbeat', 'wp-remove-post-lock' ), true ) ) {
				return false;
			}
		}

		return Automator()->helpers->recipe->is_edit_page() || Automator()->helpers->recipe->is_rest();
	}
}
