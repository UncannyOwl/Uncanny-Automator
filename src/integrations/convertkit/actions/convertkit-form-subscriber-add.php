<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class CONVERTKIT_FORM_SUBSCRIBER_ADD
 *
 * @package Uncanny_Automator
 */
class CONVERTKIT_FORM_SUBSCRIBER_ADD {

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

		$this->set_action_code( 'CONVERTKIT_FORM_SUBSCRIBER_ADD' );

		$this->set_action_meta( 'CONVERTKIT_FORM_SUBSCRIBER_ADD_META' );

		$this->set_is_pro( false );

		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/convertkit/' ) );

		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: Action sentence - WordPress */
				esc_attr__( 'Add {{a subscriber:%1$s}} to {{a form:%2$s}}', 'uncanny-automator' ),
				'EMAIL:' . $this->get_action_meta(),
				$this->get_action_meta()
			)
		);

		/* translators: Action - WordPress */
		$this->set_readable_sentence( esc_attr__( 'Add {{a subscriber}} to {{a form}}', 'uncanny-automator' ) );

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->set_background_processing( true );

		$this->set_action_tokens(
			array(
				'SUBSCRIPTION_ID'    => array(
					'name' => __( 'Subscription ID', 'uncanny-automator' ),
					'type' => 'int',
				),
				'SUBSCRIPTION_STATE' => array(
					'name' => __( 'Subscription state', 'uncanny-automator' ),
					'type' => 'text',
				),
				'SUBSCRIPTION_DATE'  => array(
					'name' => __( 'Subscription date', 'uncanny-automator' ),
					'type' => 'date',
				),
				'SUBSCRIBABLE_ID'    => array(
					'name' => __( 'Subscribable ID', 'uncanny-automator' ),
					'type' => 'int',
				),
				'SUBSCRIBABLE_TYPE'  => array(
					'name' => __( 'Subscription type', 'uncanny-automator' ),
					'type' => 'text',
				),
				'SUBSCRIBER_ID'      => array(
					'name' => __( 'Subscriber ID', 'uncanny-automator' ),
					'type' => 'int',
				),
			),
			$this->get_action_code()
		);

		$this->register_action();

	}

	/**
	 * Loads the options.
	 *
	 * @return array The options.
	 */
	public function load_options() {

		return array(
			'options_group' => array(
				$this->get_action_meta() => array(
					array(
						'option_code' => $this->get_action_meta(),
						'label'       => esc_attr__( 'Form', 'uncanny-automator' ),
						'input_type'  => 'select',
						'is_ajax'     => true,
						'endpoint'    => 'automator_convertkit_forms_dropdown_handler',
						'options'     => array(),
						'required'    => true,
					),
					array(
						'option_code' => 'EMAIL',
						'label'       => esc_attr__( 'Email address', 'uncanny-automator' ),
						'input_type'  => 'email',
						'required'    => true,
					),
					array(
						'option_code' => 'FIRST_NAME',
						'label'       => esc_attr__( 'First name', 'uncanny-automator' ),
						'input_type'  => 'text',
					),
				),
			),
		);

	}

	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$form_id = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : 0;

		$email_address = isset( $parsed['EMAIL'] ) ? sanitize_text_field( $parsed['EMAIL'] ) : '';

		$first_name = isset( $parsed['FIRST_NAME'] ) ? sanitize_text_field( $parsed['FIRST_NAME'] ) : '';

		try {

			$body = array(
				'action'        => 'add_subscriber_to_form',
				'form_id'       => $form_id,
				'email_address' => $email_address,
				'access_token'  => get_option( ConvertKit_Settings::OPTIONS_API_KEY, null ),
				'first_name'    => $first_name,
			);

			$response = $this->get_helpers()->api_request( $body, $action_data );

			$this->hydrate_tokens(
				array(
					'SUBSCRIPTION_ID'    => $response['data']['subscription']['id'],
					'SUBSCRIPTION_STATE' => $response['data']['subscription']['state'],
					'SUBSCRIPTION_DATE'  => $this->get_helpers()->get_formatted_time( $response['data']['subscription']['created_at'] ),
					'SUBSCRIBABLE_ID'    => $response['data']['subscription']['subscribable_id'],
					'SUBSCRIBABLE_TYPE'  => $response['data']['subscription']['subscribable_type'],
					'SUBSCRIBER_ID'      => $response['data']['subscription']['subscriber']['id'],
				)
			);

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

		}

	}

}
