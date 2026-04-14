<?php

namespace Uncanny_Automator\Integrations\ConvertKit;

/**
 * ConvertKit - Add subscriber to a sequence
 *
 * @property ConvertKit_App_Helpers $helpers
 * @property ConvertKit_Api_Caller $api
 */
class CONVERTKIT_SUBSCRIBER_SEQUENCE_ADD extends \Uncanny_Automator\Recipe\App_Action {

	use ConvertKit_Subscriber_Tokens_Trait;

	/**
	 * Setup Action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'CONVERTKIT' );
		$this->set_action_code( 'CONVERTKIT_SUBSCRIBER_SEQUENCE_ADD' );
		$this->set_action_meta( 'CONVERTKIT_SUBSCRIBER_SEQUENCE_ADD_META' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/convertkit/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Add {{a subscriber}} to {{a sequence}}', 'ConvertKit', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the email address, %2$s is the sequence name
				esc_attr_x( 'Add {{a subscriber:%1$s}} to {{a sequence:%2$s}}', 'ConvertKit', 'uncanny-automator' ),
				'EMAIL:' . $this->get_action_meta(),
				$this->get_action_meta()
			)
		);
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_attr_x( 'Sequence', 'ConvertKit', 'uncanny-automator' ),
				'input_type'  => 'select',
				'options'     => array(),
				'required'    => true,
				'ajax'        => array(
					'endpoint' => 'automator_convertkit_sequence_dropdown_handler',
					'event'    => 'on_load',
				),
			),
			$this->helpers->get_email_option_config(),
			$this->helpers->get_first_name_option_config(),
		);
	}

	/**
	 * Define tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return $this->get_subscriber_token_definitions();
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$sequence_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );
		$email       = $this->helpers->require_valid_email( $parsed['EMAIL'] ?? '' );
		$first_name  = sanitize_text_field( $parsed['FIRST_NAME'] ?? '' );

		if ( empty( $sequence_id ) ) {
			throw new \Exception(
				esc_html_x( 'Please provide a valid sequence.', 'ConvertKit', 'uncanny-automator' )
			);
		}

		$body = array(
			'action'        => 'add_subscriber_to_sequence',
			'sequence_id'   => $sequence_id,
			'email_address' => $email,
			'first_name'    => $first_name,
		);

		$response = $this->api->api_request( $body, $action_data );
		$this->hydrate_tokens( $this->hydrate_subscriber_tokens( $response ) );

		return true;
	}
}
