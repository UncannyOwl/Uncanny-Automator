<?php
namespace Uncanny_Automator\Integrations\Advanced;

use Uncanny_Automator\Tokens\Universal_Token;

class Postmeta_Token extends Universal_Token {

	/**
	 * setup
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

	public function get_fields() {
		return array(
			array(
				'input_type'         => 'text',
				'option_code'        => 'POSTID',
				'required'           => true,
				'label'              => esc_attr__( 'Post ID', 'uncanny-automator' ),
				'description'        => esc_attr__( 'The ID of the post that contains the meta data.', 'uncanny-automator' ) . sprintf( ' <a href="%2$s">%1$s</a>', esc_attr__( 'Learn more', 'uncanny-automator' ) . ' <uo-icon id="external-link"></uo-icon>', 'https://automatorplugin.com/knowledge-base/post-meta-tokens/?utm_source=uncanny_automator_pro&utm_medium=add_token&utm_content=post_meta_post_id_learn_more' ),
				'supports_tokens'    => true,
				'unsupported_tokens' => array( 'USERMETA:KEY', 'POSTMETA:POSTID:KEY', 'CALCULATION:FORMULA' ),
			),
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

		$post_id  = $pieces[3];
		$meta_key = $pieces[4];

		$post_data = get_post( $post_id, ARRAY_A );

		// Support _post columns as post meta.
		if ( isset( $post_data[ $meta_key ] ) ) {
			return $post_data[ $meta_key ];
		}

		$post_meta = get_post_meta( $post_id, $meta_key, true );

		if ( empty( $post_meta ) ) {
			return '';
		}

		if ( is_array( $post_meta ) ) {
			$post_meta = join( ', ', $post_meta );
		}

		return $post_meta;
	}
}
