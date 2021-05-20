<?php
namespace Uncanny_Automator;

class Fcrm_Status_Tokens {


	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'FCRM';

	/**
	 * @var string TOKEN_ID the token prefix.
	 */
	const TOKEN_ID = 'FLUENTCRM_STATUS_FIELD_';

	public $tokens = array();

	/**
	 * Class constructor.
	 * Attaches our parse_tokens cb to automator_maybe_parse_token.
	 * Setups the tokens.
	 *
	 * @return void
	 */
	public function __construct() {

		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_tokens' ), 36, 6 );

	}

	/**
	 * Parses the tokens.
	 *
	 * @param string $value         The value.
	 * @param array  $pieces        The pieces.
	 * @param int    $recipe_id     The recipe id.
	 * @param array  $trigger_data  The trigger data.
	 * @param int    $user_id       The user id.
	 * @param string $replace_args  The replace args
	 *
	 * @return string The token value.
	 */
	public function parse_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( ! function_exists( '\FluentCrmApi' ) ) {
			return $value;
		}

		if ( false !== strpos( $pieces[2], 'FLUENTCRM_STATUS_FIELD_' ) ) {

			$property = str_replace( 'FLUENTCRM_STATUS_FIELD_', '', $pieces[2] );

			$contact_api = \FluentCrmApi( 'contacts' );

			$contact = $contact_api->getContactByUserId( $user_id );

			$token_value = '';

			if ( isset( $contact->$property ) ) {
				$token_value = $contact->$property;
			} else {
				// Try custom field.
				$token_value = $this->get_custom_field_value( $property, $contact->id );
			}

			return $token_value;

		}

		return $value;

	}

	/**
	 * Returns the custom field value.
	 *
	 * @param  mixed $key The custom field key.
	 * @param  mixed $subscriber_id The subscriber id.
	 *
	 * @return string The custom field value. Separated by comma if multiple.
	 */
	protected function get_custom_field_value( $key = '', $subscriber_id = 0 ) {

		$value = '';

		if ( empty( $key ) ) {
			return $value;
		}

		global $wpdb;

		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `value` 
                FROM {$wpdb->prefix}fc_subscriber_meta 
                WHERE subscriber_id = %d AND `key` = %s",
				$subscriber_id,
				$key
			)
		);

		if ( is_serialized( $value ) ) {
			$value = maybe_unserialize( $value );
			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}
		}

		return $value;

	}

}
