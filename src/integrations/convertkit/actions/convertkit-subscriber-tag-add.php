<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class CONVERTKIT_SUBSCRIBER_TAG_ADD
 *
 * @package Uncanny_Automator
 */
class CONVERTKIT_SUBSCRIBER_TAG_ADD {

	use Recipe\Actions;
	use Recipe\Action_Tokens;

	public function __construct() {

		$this->set_helpers( new ConvertKit_Helpers( false ) );

		$this->setup_action();

	}

	/**
	 * Setup Action.
	 *
	 * @return void.
	 */
	protected function setup_action() {

		$this->set_integration( 'CONVERTKIT' );

		$this->set_action_code( 'CONVERTKIT_SUBSCRIBER_TAG_ADD' );

		$this->set_action_meta( 'CONVERTKIT_SUBSCRIBER_TAG_ADD_META' );

		$this->set_is_pro( false );

		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/convertkit/' ) );

		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: Action sentence - WordPress */
				esc_attr__( 'Add {{a tag:%1$s}} to {{a subscriber:%2$s}}', 'uncanny-automator' ),
				$this->get_action_meta(),
				'EMAIL:' . $this->get_action_meta()
			)
		);

		/* translators: Action - WordPress */
		$this->set_readable_sentence( esc_attr__( 'Add {{a tag}} to {{a subscriber}}', 'uncanny-automator' ) );

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->set_background_processing( true );

		$this->set_action_tokens(
			array(
				'TAG_NAME'                => array(
					'name' => __( 'Tag name', 'uncanny-automator' ),
					'type' => 'text',
				),
				'SUBSCRIPTION_ID'         => array(
					'name' => __( 'Tag subscription ID', 'uncanny-automator' ),
					'type' => 'int',
				),
				'SUBSCRIPTION_STATE'      => array(
					'name' => __( 'Tag subscription state', 'uncanny-automator' ),
					'type' => 'text',
				),
				'SUBSCRIPTION_DATE'       => array(
					'name' => __( 'Tag subscription date', 'uncanny-automator' ),
					'type' => 'date',
				),
				'SUBSCRIBABLE_ID'         => array(
					'name' => __( 'Tag subscribable ID', 'uncanny-automator' ),
					'type' => 'int',
				),
				'SUBSCRIBABLE_TYPE'       => array(
					'name' => __( 'Tag subscription type', 'uncanny-automator' ),
					'type' => 'text',
				),
				'SUBSCRIBER_ID'           => array(
					'name' => __( 'Tag subscriber ID', 'uncanny-automator' ),
					'type' => 'int',
				),
				'SUBSCRIBER_FIRST_NAME'   => array(
					'name' => __( 'Tag subscriber name', 'uncanny-automator' ),
					'type' => 'text',
				),
				'SUBSCRIBER_STATE'        => array(
					'name' => __( 'Tag subscriber state', 'uncanny-automator' ),
					'type' => 'text',
				),
				'SUBSCRIBER_DATE_CREATED' => array(
					'name' => __( 'Date subscribed', 'uncanny-automator' ),
					'type' => 'date',
				),
			),
			$this->get_action_code()
		);

		$this->register_action();

	}

	public function load_options() {

		return array(
			'options_group' => array(
				$this->get_action_meta() => array(
					array(
						'option_code'              => $this->get_action_meta(),
						'label'                    => esc_attr__( 'Tag', 'uncanny-automator' ),
						'input_type'               => 'select',
						'options'                  => array(),
						'token_name'               => esc_attr__( 'Tag ID', 'uncanny-automator' ),
						'custom_value_description' => esc_attr__( 'Tag ID', 'uncanny-automator' ),
						'endpoint'                 => 'automator_convertkit_tags_dropdown_handler',
						'is_ajax'                  => true,
						'required'                 => true,
					),
					array(
						'option_code'           => 'EMAIL',
						'label'                 => esc_attr__( 'Email address', 'uncanny-automator' ),
						'input_type'            => 'email',
						'supports_custom_value' => false,
						'required'              => true,
					),
				),
			),
		);

	}


	/**
	 * Processes action.
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return void.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$tag_id = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : 0;

		$email_address = isset( $parsed['EMAIL'] ) ? sanitize_text_field( $parsed['EMAIL'] ) : '';

		try {

			$body = array(
				'action'        => 'add_tag_to_subscriber',
				'tag_id'        => $tag_id,
				'email_address' => $email_address,
				'access_token'  => get_option( ConvertKit_Settings::OPTIONS_API_SECRET, null ),
			);

			$response = $this->get_helpers()->api_request( $body, $action_data );

			$this->hydrate_tokens(
				array(
					'TAG_NAME'                => $action_data['meta'][ $this->get_action_meta() . '_readable' ],
					'SUBSCRIPTION_ID'         => $response['data']['subscription']['id'],
					'SUBSCRIPTION_STATE'      => $response['data']['subscription']['state'],
					'SUBSCRIPTION_DATE'       => $this->get_helpers()->get_formatted_time( $response['data']['subscription']['created_at'] ),
					'SUBSCRIBABLE_ID'         => $response['data']['subscription']['subscribable_id'],
					'SUBSCRIBABLE_TYPE'       => $response['data']['subscription']['subscribable_type'],
					'SUBSCRIBER_ID'           => $response['data']['subscription']['subscriber']['id'],
					'SUBSCRIBER_FIRST_NAME'   => $response['data']['subscription']['subscriber']['first_name'],
					'SUBSCRIBER_STATE'        => $response['data']['subscription']['subscriber']['state'],
					'SUBSCRIBER_DATE_CREATED' => $this->get_helpers()->get_formatted_time( $response['data']['subscription']['subscriber']['created_at'] ),
				)
			);

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

		}

	}

}
