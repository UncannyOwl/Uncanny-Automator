<?php
namespace Uncanny_Automator\Integrations\Advanced;

use Uncanny_Automator\Tokens\Universal_Token;

class Postmeta_Token extends Universal_Token {

	/**
	 * Setup the token.
	 *
	 * @return void
	 */
	public function setup() {
		$this->integration = 'ADVANCED';
		$this->id          = 'POSTMETA';
		$this->id_template = 'POSTID:KEY';
		// translators: 1: Post ID, 2: Meta key
		$this->name_template = sprintf( esc_attr_x( 'Post: %1$s meta: %2$s', 'Token', 'uncanny-automator' ), '{{POSTID}}', '{{KEY}}' );
		$this->name          = esc_attr_x( 'Post meta', 'Token', 'uncanny-automator' );
		$this->requires_user = false;
		$this->type          = 'text';
		$this->cacheable     = true;
	}

	/**
	 * Get fields.
	 *
	 * @return mixed
	 */
	public function get_fields() {
		return array(
			array(
				'input_type'         => 'text',
				'option_code'        => 'POSTID',
				'required'           => true,
				'label'              => esc_attr_x( 'Post ID', 'Advanced', 'uncanny-automator' ),
				'description'        => esc_attr_x( 'The ID of the post that contains the meta data.', 'Advanced', 'uncanny-automator' ) . sprintf( ' <a href="%2$s">%1$s</a>', esc_attr_x( 'Learn more', 'Advanced', 'uncanny-automator' ) . ' <uo-icon id="external-link"></uo-icon>', 'https://automatorplugin.com/knowledge-base/post-meta-tokens/?utm_source=uncanny-automator-pro&utm_medium=in-plugin&utm_content=add-token-post-meta-post-id-learn-more' ),
				'supports_tokens'    => true,
				'unsupported_tokens' => array( 'USERMETA:KEY', 'POSTMETA:POSTID:KEY', 'CALCULATION:FORMULA' ),
			),
			array(
				'input_type'         => 'text',
				'option_code'        => 'KEY',
				'required'           => true,
				'label'              => esc_attr_x( 'Meta key', 'Advanced', 'uncanny-automator' ),
				'description'        => esc_attr_x( 'The meta key associated with the data you want to retrieve. Only one meta key can be entered per token.', 'Advanced', 'uncanny-automator' ),
				'supports_tokens'    => true,
				'unsupported_tokens' => array( 'USERMETA:KEY', 'POSTMETA:POSTID:KEY', 'CALCULATION:FORMULA' ),
			),
		);
	}

	/**
	 * Parse integration token.
	 *
	 * @param mixed $return_value The return.
	 * @param mixed $pieces The pieces.
	 * @param mixed $recipe_id The ID.
	 * @param mixed $trigger_data The data.
	 * @param mixed $user_id The user ID.
	 * @param mixed $replace_args The arguments.
	 * @return mixed
	 */
	public function parse_integration_token( $return_value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		$post_id  = $pieces[3];
		$meta_key = $pieces[4];

		$post_data = get_post( $post_id, ARRAY_A );

		// Support _post columns as post meta.
		if ( isset( $post_data[ $meta_key ] ) ) {
			return $post_data[ $meta_key ];
		}

		$post_meta = get_post_meta( $post_id, $meta_key, true );

		if ( is_array( $post_meta ) ) {
			$post_meta = join( ', ', $post_meta );
		}

		return $post_meta;
	}
}
