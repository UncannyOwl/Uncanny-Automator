<?php
namespace Uncanny_Automator;

class MC_EVERYONE_CONTACT_REMOVE {

	use Recipe\Actions;

	const INTEGRATION = 'MAILCHIMP';

	const CODE = 'MC_EVERYONE_CONTACT_REMOVE';

	const META = 'MC_EVERYONE_CONTACT_REMOVE_MET';

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
		$this->set_sentence( sprintf( 'Unsubscribe {{a contact:%1$s}} from {{an audience:%2$s}}', self::META . '_EMAIL', $this->get_action_meta() ) );

		/* translators: Action - WordPress */
		$this->set_readable_sentence( 'Unsubscribe {{a contact}} from {{an audience}}' );

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
				$this->action_meta => array(
					Automator()->helpers->recipe->mailchimp->options->get_all_lists(
						__( 'Audience', 'uncanny-automator' ),
						'MCLIST'
					),
					Automator()->helpers->recipe->mailchimp->options->get_double_opt_in(
						__( 'Delete subscriber from Mailchimp?', 'uncanny-automator' ),
						'MCDELETEMEMBER',
						array(
							'description' => __( 'Yes, delete from Mailchimp, No, only unsubscribe from audience', 'uncanny-automator' ),
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

			$delete_member = isset( $parsed['MCDELETEMEMBER'] ) ? sanitize_text_field( $parsed['MCDELETEMEMBER'] ) : '';

			$email = isset( $parsed[ self::META . '_EMAIL' ] ) ? trim( sanitize_text_field( $parsed[ self::META . '_EMAIL' ] ) ) : '';

			if ( empty( filter_var( $email, FILTER_VALIDATE_EMAIL ) ) ) {
				throw new \Exception( 'Invalid email address format.' );
			}

			$user_hash = md5( strtolower( $email ) );

			if ( 'no' === $delete_member ) {

				$user_data = array(
					'status' => 'unsubscribed',
				);

				$request_params = array(
					'action'    => 'update_subscriber',
					'list_id'   => $list_id,
					'user_hash' => $user_hash,
					'user_data' => wp_json_encode( $user_data ),
				);

			} else {

				$request_params = array(
					'action'    => 'delete_subscriber',
					'list_id'   => $list_id,
					'user_hash' => $user_hash,
				);

			}

			$response = $helpers->api_request( $request_params, $action_data );

			Automator()->complete_action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {

			$helpers->complete_with_error( $e->getMessage(), $user_id, $action_data, $recipe_id );

		}

	}

}
