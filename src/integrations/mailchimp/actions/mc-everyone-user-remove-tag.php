<?php
namespace Uncanny_Automator;

class MC_EVERYONE_USER_REMOVE_TAG {

	use Recipe\Actions;

	const INTEGRATION = 'MAILCHIMP';

	const CODE = 'MC_EVERYONE_USER_REMOVE_TAG';

	const META = 'MC_EVERYONE_USER_REMOVE_TAG_META';

	public function __construct() {

		$this->setup_action();

		$this->register_action();

	}

	public function setup_action() {

		$this->set_integration( self::INTEGRATION );

		$this->set_action_code( self::CODE );

		$this->set_action_meta( self::META );

		$this->set_is_pro( false );

		$this->set_requires_user( false );

		/* translators: Action - WordPress */
		$this->set_sentence( sprintf( 'Remove {{a tag:%1$s}} from {{a contact:%2$s}}', $this->get_action_meta(), self::META . '_EMAIL' ) );

		/* translators: Action - WordPress */
		$this->set_readable_sentence( 'Remove {{a tag}} from {{a contact}}' );

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/mailchimp/' ) );

		$this->set_author( Automator()->get_author_name( $this->get_action_code() ) );

	}

	public function load_options() {

		return array(
			'options'       => array(
				Automator()->helpers->recipe->mailchimp->options->get_email_field( self::META . '_EMAIL' ),
			),
			'options_group' => array(
				$this->get_action_meta() => array(
					Automator()->helpers->recipe->mailchimp->options->get_all_lists(
						__( 'Audience', 'uncanny-automator' ),
						'MCLIST',
						array(
							'is_ajax'      => true,
							'target_field' => 'MCLISTTAGS',
							'endpoint'     => 'select_mctagslist_from_mclist',
						)
					),
					Automator()->helpers->recipe->mailchimp->options->get_list_tags(
						__( 'Tags', 'uncanny-automator' ),
						'MCLISTTAGS',
						array(
							'is_ajax'                  => true,
							'token'                    => true,
							'custom_value_description' => esc_html__( 'Enter a tag name.', 'uncanny-automator' ),
						)
					),
				),
			),
		);

	}

	public function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$helpers = Automator()->helpers->recipe->mailchimp->options;

		try {

			$list_id = isset( $parsed['MCLIST'] ) ? sanitize_text_field( $parsed['MCLIST'] ) : '';

			$email = isset( $parsed[ self::META . '_EMAIL' ] ) ? trim( sanitize_text_field( $parsed[ self::META . '_EMAIL' ] ) ) : '';

			$tag = isset( $parsed['MCLISTTAGS'] ) ? sanitize_text_field( $parsed['MCLISTTAGS'] ) : '';

			if ( empty( $tag ) ) {
				throw new \Exception( __( 'No tag selected.', 'uncanny-automator' ) );
			}

			if ( empty( filter_var( $email, FILTER_VALIDATE_EMAIL ) ) ) {
				throw new \Exception( 'Invalid email address format.' );
			}

			$user_hash = md5( strtolower( $email ) );

			$tags_body = array(
				'tags' => array(
					array(
						'name'   => $tag,
						'status' => 'inactive',
					),
				),
			);

			$request_params = array(
				'action'    => 'update_subscriber_tags',
				'list_id'   => $list_id,
				'user_hash' => $user_hash,
				'tags'      => wp_json_encode( $tags_body ),
			);

			$response = $helpers->api_request( $request_params, $action_data );

			Automator()->complete_action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {

			$helpers->complete_with_error( $e->getMessage(), $user_id, $action_data, $recipe_id );

		}

	}

}
