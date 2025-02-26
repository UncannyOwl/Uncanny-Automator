<?php
namespace Uncanny_Automator\Integrations\Advanced;

use Uncanny_Automator\Tokens\Universal_Token;

class Usermeta_Token extends Universal_Token {

	/**
	 * setup
	 *
	 * @return void
	 */
	public function setup() {
		$this->integration = 'ADVANCED';
		$this->id          = 'USERMETA';
		$this->id_template = 'KEY';
		// translators: 1: User meta key
		$this->name_template = sprintf( esc_attr_x( 'User meta key: %1$s', 'Token', 'uncanny-automator' ), '{{KEY}}' );
		$this->name          = esc_attr_x( 'User meta', 'Token', 'uncanny-automator' );
		$this->requires_user = true;
		$this->type          = 'text';
		$this->cacheable     = true;
	}

	public function get_fields() {
		return array(
			array(
				'input_type'         => 'text',
				'option_code'        => 'KEY',
				'required'           => true,
				'label'              => esc_attr__( 'Meta key', 'uncanny-automator' ),
				'description'        => esc_attr__( 'The meta key associated with the data you want to retrieve. Only one meta key can be entered per token.', 'uncanny-automator' ),
				'supports_tokens'    => true,
				'unsupported_tokens' => array( 'USERMETA:KEY', 'POSTMETA:POSTID:KEY', 'CALCULATION:FORMULA' ),
			),
		);
	}

	public function parse_integration_token( $return, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		$meta_key = $pieces[3];

		$user_data = get_userdata( $user_id );

		if ( false === $user_data ) {
			return '';
		}

		$user_data = (array) $user_data->data;

		// Support _user columns as user meta.
		if ( isset( $user_data[ $meta_key ] ) ) {
			return $user_data[ $meta_key ];
		}

		// Retrieve the user meta.
		$value = get_user_meta( $user_id, $meta_key, true );

		// If its an array.
		if ( is_array( $value ) ) {
			$value = join( ', ', $value );
		}

		$value = apply_filters(
			'automator_usermeta_token_parsed',
			$value,
			$user_id,
			$meta_key,
			$replace_args,
			$trigger_data
		);

		return $value;
	}
}
