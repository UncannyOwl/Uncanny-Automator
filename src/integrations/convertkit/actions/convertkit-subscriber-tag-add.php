<?php

namespace Uncanny_Automator\Integrations\ConvertKit;

/**
 * ConvertKit - Add a tag to a subscriber
 *
 * @property ConvertKit_App_Helpers $helpers
 * @property ConvertKit_Api_Caller $api
 */
class CONVERTKIT_SUBSCRIBER_TAG_ADD extends \Uncanny_Automator\Recipe\App_Action {

	use ConvertKit_Subscriber_Tokens_Trait;

	/**
	 * Setup Action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'CONVERTKIT' );
		$this->set_action_code( 'CONVERTKIT_SUBSCRIBER_TAG_ADD' );
		$this->set_action_meta( 'CONVERTKIT_SUBSCRIBER_TAG_ADD_META' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/convertkit/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Add {{a tag}} to {{a subscriber}}', 'ConvertKit', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the tag name, %2$s is the email address
				esc_attr_x( 'Add {{a tag:%1$s}} to {{a subscriber:%2$s}}', 'ConvertKit', 'uncanny-automator' ),
				$this->get_action_meta(),
				'EMAIL:' . $this->get_action_meta()
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
			$this->helpers->get_tag_option_config( $this->get_action_meta() ),
			$this->helpers->get_email_option_config( 'EMAIL', false ),
		);
	}

	/**
	 * Define tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {

		$tokens = array(
			'TAG_NAME'                => array(
				'name' => esc_html_x( 'Tag name', 'ConvertKit', 'uncanny-automator' ),
				'type' => 'text',
			),
			'SUBSCRIBER_FIRST_NAME'   => array(
				'name' => esc_html_x( 'Subscriber name', 'ConvertKit', 'uncanny-automator' ),
				'type' => 'text',
			),
			'SUBSCRIBER_DATE_CREATED' => array(
				'name' => esc_html_x( 'Date subscribed', 'ConvertKit', 'uncanny-automator' ),
				'type' => 'date',
			),
		);

		return array_merge( $tokens, $this->get_subscriber_token_definitions() );
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

		$tag_id = $this->helpers->require_valid_tag_id( $parsed[ $this->get_action_meta() ] ?? '' );
		$email  = $this->helpers->require_valid_email( $parsed['EMAIL'] ?? '' );

		$body = array(
			'action'        => 'add_tag_to_subscriber',
			'tag_id'        => $tag_id,
			'email_address' => $email,
		);

		$response     = $this->api->api_request( $body, $action_data );
		$subscriber   = $this->get_subscriber_from_response( $response );
		$token_values = array_merge(
			array(
				'TAG_NAME'                => $action_data['meta'][ $this->get_action_meta() . '_readable' ] ?? '',
				'SUBSCRIBER_FIRST_NAME'   => $subscriber['first_name'] ?? '',
				'SUBSCRIBER_DATE_CREATED' => ! empty( $subscriber['created_at'] )
					? $this->helpers->get_formatted_time( $subscriber['created_at'] )
					: '',
			),
			$this->hydrate_subscriber_tokens( $response, 'tagged_at' )
		);

		$this->hydrate_tokens( $token_values );

		return true;
	}
}
