<?php
namespace Uncanny_Automator;

class HELPSCOUT_CONVERSATION_CREATE {

	use Recipe\Actions;

	use Recipe\Action_Tokens;

	protected $helper = null;

	public function __construct() {

		$this->setup_action();

		$this->helper = new Helpscout_Helpers( false );

	}


	/**
	 * Setups our action.
	 *
	 * @return void.
	 */
	protected function setup_action() {

		$this->set_integration( 'HELPSCOUT' );

		$this->set_action_code( 'HELPSCOUT_CONVERSATION_CREATE' );

		$this->set_action_meta( 'HELPSCOUT_CONVERSATION_CREATE_META' );

		$this->set_is_pro( false );

		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/helpscout/' ) );

		$this->set_requires_user( false );

		/* translators: Action - WordPress */
		$this->set_sentence( sprintf( esc_attr__( 'Create a conversation in {{a mailbox:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() ) );

		/* translators: Action - WordPress */
		$this->set_readable_sentence( esc_attr__( 'Create a conversation in {{a mailbox}}', 'uncanny-automator' ) );

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->set_background_processing( true );

		$this->set_action_tokens(
			array(
				'CONVERSATION_ID'  => array(
					'name' => __( 'Conversation ID', 'uncanny-automator' ),
					'type' => 'int',
				),
				'CONVERSATION_URL' => array(
					'name' => __( 'Conversation URL', 'uncanny-automator' ),
					'type' => 'url',
				),
			),
			$this->get_action_code()
		);

		$this->register_action();

	}

	public function load_options() {

		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->get_action_meta() => array(
						array(
							'option_code'           => $this->get_action_meta(),
							'label'                 => esc_html__( 'Mailbox', 'uncanny-automator' ),
							'input_type'            => 'select',
							'options'               => $this->helper->fetch_mailboxes(),
							'is_ajax'               => true,
							'endpoint'              => 'automator_helpscout_fetch_mailbox_users',
							'fill_values_in'        => 'CREATED_BY',
							'supports_custom_value' => false,
							'required'              => true,
						),
						array(
							'option_code'           => 'CREATED_BY',
							'label'                 => esc_html__( 'Created by', 'uncanny-automator' ),
							'input_type'            => 'select',
							'is_ajax'               => true,
							'endpoint'              => 'automator_helpscout_fetch_mailbox_users',
							'fill_values_in'        => 'ASSIGNEE',
							'options'               => array(),
							'supports_custom_value' => false,
							'required'              => true,
							'options_show_id'       => false,
						),
						array(
							'option_code'           => 'FIRST_NAME',
							'label'                 => esc_html__( 'Customer first name', 'uncanny-automator' ),
							'input_type'            => 'text',
							'supports_custom_value' => true,
						),
						array(
							'option_code'           => 'LAST_NAME',
							'label'                 => esc_html__( 'Customer last name', 'uncanny-automator' ),
							'input_type'            => 'text',
							'supports_custom_value' => true,
						),
						array(
							'option_code'           => 'EMAIL',
							'label'                 => esc_html__( 'Customer email', 'uncanny-automator' ),
							'input_type'            => 'email',
							'supports_custom_value' => true,
							'required'              => true,
						),
						array(
							'option_code'           => 'SUBJECT',
							'label'                 => esc_html__( 'Subject', 'uncanny-automator' ),
							'input_type'            => 'text',
							'supports_custom_value' => true,
							'required'              => true,
						),
						array(
							'option_code'           => 'BODY',
							'label'                 => esc_html__( 'Body', 'uncanny-automator' ),
							'input_type'            => 'textarea',
							'supports_custom_value' => true,
							'required'              => true,
						),
						array(
							'option_code'           => 'TAGS',
							'label'                 => esc_html__( 'Tags', 'uncanny-automator' ),
							'description'           => esc_html__( 'Comma separated list of tags.', 'uncanny-automator' ),
							'input_type'            => 'text',
							'supports_custom_value' => true,
						),
						array(
							'option_code'     => 'STATUS',
							'label'           => esc_html__( 'Status', 'uncanny-automator' ),
							'input_type'      => 'select',
							'options'         => array(
								'active'  => 'Active',
								'closed'  => 'Closed',
								'pending' => 'Pending',
							),
							'required'        => true,
							'options_show_id' => false,
						),
						array(
							'option_code'           => 'ASSIGNEE',
							'label'                 => esc_html__( 'Assign to', 'uncanny-automator' ),
							'input_type'            => 'select',
							'options'               => array(),
							'required'              => true,
							'supports_custom_value' => false,
							'options_show_id'       => false,
						),
					),
				),
			)
		);
	}


	/**
	 * Process action.
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

		$mailbox    = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : 0;
		$subject    = isset( $parsed['SUBJECT'] ) ? sanitize_text_field( $parsed['SUBJECT'] ) : 0;
		$email      = isset( $parsed['EMAIL'] ) ? sanitize_text_field( $parsed['EMAIL'] ) : '';
		$body       = isset( $parsed['BODY'] ) ? sanitize_textarea_field( $parsed['BODY'] ) : '';
		$created_by = isset( $parsed['CREATED_BY'] ) ? sanitize_text_field( $parsed['CREATED_BY'] ) : '';
		$first_name = isset( $parsed['FIRST_NAME'] ) ? sanitize_text_field( $parsed['FIRST_NAME'] ) : '';
		$last_name  = isset( $parsed['LAST_NAME'] ) ? sanitize_text_field( $parsed['LAST_NAME'] ) : '';
		$assignee   = isset( $parsed['ASSIGNEE'] ) ? sanitize_text_field( $parsed['ASSIGNEE'] ) : '';
		$status     = isset( $parsed['ASSIGNEE'] ) ? sanitize_text_field( $parsed['STATUS'] ) : '';
		$tags       = isset( $parsed['TAGS'] ) ? sanitize_text_field( $parsed['TAGS'] ) : '';

		try {

			if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
				throw new \Exception( 'Email is empty or invalid', 422 );
			}

			$body = array(
				'mailbox'    => $mailbox,
				'subject'    => $subject,
				'email'      => $email,
				'body'       => $body,
				'created_by' => $created_by,
				'assign_to'  => $assignee,
				'status'     => $status,
				'tags'       => $tags,
				'action'     => 'create_conversation',
			);

			$customer_complete_name = $this->generate_first_and_last_name( $first_name, $last_name );

			$body['first_name'] = $customer_complete_name['first_name'];
			$body['last_name']  = $customer_complete_name['last_name'];

			$response = $this->helper->api_request( $body, $action_data );

			$this->hydrate_tokens(
				array(
					'CONVERSATION_ID'  => isset( $response['data']['data']['resourceId'] ) ? sanitize_text_field( $response['data']['data']['resourceId'] ) : 0,
					'CONVERSATION_URL' => isset( $response['data']['data']['webLocation'] ) ? esc_url( $response['data']['data']['webLocation'] ) : '',
				)
			);

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			// Log error if there are any error messages.
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

		}

	}

	/**
	 * Generates first and last name.
	 *
	 * Replaces empty first name or empty last name with a dash.
	 *
	 * @param string $first_name The customer's first name.
	 * @param string $last_name The customer's last name.
	 *
	 * @return array The customer's first_name and customer's last_name.
	 */
	public function generate_first_and_last_name( $first_name = '', $last_name = '' ) {

		if ( empty( $first_name ) && empty( $last_name ) ) {
			return array(
				'first_name' => '',
				'last_name'  => '',
			);
		}

		return array(
			'first_name' => ! empty( $first_name ) ? $first_name : '-',
			'last_name'  => ! empty( $last_name ) ? $last_name : '-',
		);

	}

}
