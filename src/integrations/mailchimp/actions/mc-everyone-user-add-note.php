<?php
namespace Uncanny_Automator;

class MC_EVERYONE_USER_ADD_NOTE {

	use Recipe\Actions;

	const INTEGRATION = 'MAILCHIMP';

	const CODE = 'MC_EVERYONE_USER_ADD_NOTE';

	const META = 'MC_EVERYONE_USER_ADD_NOTE_META';

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
		$this->set_sentence( sprintf( 'Add {{a note:%1$s}} to {{a contact:%2$s}}', $this->get_action_meta(), self::META . '_EMAIL' ) );

		/* translators: Action - WordPress */
		$this->set_readable_sentence( 'Add {{a note}} to {{a contact}}' );

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/mailchimp/' ) );

		$this->set_author( Automator()->get_author_name( $this->get_action_code() ) );

	}

	public function load_options() {

		$textarea = Automator()->helpers->recipe->field->text_field( 'MCNOTE', __( 'Note', 'uncanny-automator' ), true, 'textarea', null, false, __( 'Note length is limited to 1,000 characters.', 'uncanny-automator' ) );

		$textarea['supports_tinymce'] = false;

		return array(
			'options'       => array(
				Automator()->helpers->recipe->mailchimp->options->get_email_field( self::META . '_EMAIL' ),
			),
			'options_group' => array(
				$this->action_meta => array(
					Automator()->helpers->recipe->mailchimp->options->get_all_lists( __( 'Audience', 'uncanny-automator' ), 'MCLIST' ),
					$textarea,
				),
			),
		);

	}

	public function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$helpers = Automator()->helpers->recipe->mailchimp->options;

		try {

			$list_id = $action_data['meta']['MCLIST'];

			$note = isset( $parsed['MCNOTE'] ) ? sanitize_text_field( $parsed['MCNOTE'] ) : '';

			$email = isset( $parsed[ self::META . '_EMAIL' ] ) ? trim( sanitize_text_field( $parsed[ self::META . '_EMAIL' ] ) ) : '';

			$user_hash = md5( strtolower( $email ) );

			$note_body = array(
				'note' => substr( wp_strip_all_tags( $note ), 0, 1000 ),
			);

			$request_params = array(
				'action'    => 'add_subscriber_note',
				'list_id'   => $list_id,
				'user_hash' => $user_hash,
				'note'      => wp_json_encode( $note_body ),
			);

			$response = $helpers->api_request( $request_params, $action_data );

			Automator()->complete_action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {

			$helpers->complete_with_error( $e->getMessage(), $user_id, $action_data, $recipe_id );

		}

	}

}
