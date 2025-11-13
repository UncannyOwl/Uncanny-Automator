<?php

namespace Uncanny_Automator\Integrations\Helpscout;

/**
 * Class Helpscout_Conversation_Create
 *
 * @package Uncanny_Automator
 *
 * @property Helpscout_App_Helpers $helpers
 * @property Helpscout_Api_Caller $api
 */
class Helpscout_Conversation_Create extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'HELPSCOUT' );
		$this->set_action_code( 'HELPSCOUT_CONVERSATION_CREATE' );
		$this->set_action_meta( 'HELPSCOUT_CONVERSATION_CREATE_META' );
		$this->set_is_pro( false );
		$this->set_support_link( \Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/helpscout/' ) );
		$this->set_requires_user( false );

		/* translators: %1$s: Mailbox */
		$this->set_sentence( sprintf( esc_html_x( 'Create a conversation in {{a mailbox:%1$s}}', 'Help Scout', 'uncanny-automator' ), $this->get_action_meta() ) );

		$this->set_readable_sentence( esc_html_x( 'Create a conversation in {{a mailbox}}', 'Help Scout', 'uncanny-automator' ) );

		$this->set_background_processing( true );

		$this->set_action_tokens(
			array(
				'CONVERSATION_ID'  => array(
					'name' => esc_html_x( 'Conversation ID', 'Help Scout', 'uncanny-automator' ),
					'type' => 'int',
				),
				'CONVERSATION_URL' => array(
					'name' => esc_html_x( 'Conversation URL', 'Help Scout', 'uncanny-automator' ),
					'type' => 'url',
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Define options
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_html_x( 'Mailbox', 'Help Scout', 'uncanny-automator' ),
				'input_type'            => 'select',
				'options'               => $this->helpers->get_mailboxes(),
				'supports_custom_value' => false,
				'required'              => true,
			),
			array(
				'option_code'           => 'CREATED_BY',
				'label'                 => esc_html_x( 'Created by', 'Help Scout', 'uncanny-automator' ),
				'input_type'            => 'select',
				'options'               => array(),
				'supports_custom_value' => false,
				'required'              => true,
				'options_show_id'       => false,
				'ajax'                  => array(
					'endpoint'      => 'automator_helpscout_fetch_mailbox_users',
					'event'         => 'parent_fields_change',
					'listen_fields' => array( $this->get_action_meta() ),
				),
			),
			array(
				'option_code'           => 'FIRST_NAME',
				'label'                 => esc_html_x( 'Customer first name', 'Help Scout', 'uncanny-automator' ),
				'input_type'            => 'text',
				'supports_custom_value' => true,
			),
			array(
				'option_code'           => 'LAST_NAME',
				'label'                 => esc_html_x( 'Customer last name', 'Help Scout', 'uncanny-automator' ),
				'input_type'            => 'text',
				'supports_custom_value' => true,
			),
			array(
				'option_code'           => 'EMAIL',
				'label'                 => esc_html_x( 'Customer email', 'Help Scout', 'uncanny-automator' ),
				'input_type'            => 'email',
				'supports_custom_value' => true,
				'required'              => true,
			),
			array(
				'option_code'           => 'SUBJECT',
				'label'                 => esc_html_x( 'Subject', 'Help Scout', 'uncanny-automator' ),
				'input_type'            => 'text',
				'supports_custom_value' => true,
				'required'              => true,
			),
			array(
				'option_code'           => 'BODY',
				'label'                 => esc_html_x( 'Body', 'Help Scout', 'uncanny-automator' ),
				'input_type'            => 'textarea',
				'supports_custom_value' => true,
				'required'              => true,
			),
			array(
				'option_code'           => 'TAGS',
				'label'                 => esc_html_x( 'Tags', 'Help Scout', 'uncanny-automator' ),
				'description'           => esc_html_x( 'Comma separated list of tags.', 'Help Scout', 'uncanny-automator' ),
				'input_type'            => 'text',
				'supports_custom_value' => true,
			),
			array(
				'option_code'     => 'STATUS',
				'label'           => esc_html_x( 'Status', 'Help Scout', 'uncanny-automator' ),
				'input_type'      => 'select',
				'options'         => array(
					array(
						'text'  => 'Active',
						'value' => 'active',
					),
					array(
						'text'  => 'Closed',
						'value' => 'closed',
					),
					array(
						'text'  => 'Pending',
						'value' => 'pending',
					),
				),
				'required'        => true,
				'options_show_id' => false,
			),
			array(
				'option_code'           => 'ASSIGNEE',
				'label'                 => esc_html_x( 'Assign to', 'Help Scout', 'uncanny-automator' ),
				'input_type'            => 'select',
				'options'               => array(),
				'required'              => true,
				'supports_custom_value' => false,
				'options_show_id'       => false,
				'ajax'                  => array(
					'endpoint'      => 'automator_helpscout_fetch_mailbox_users',
					'event'         => 'parent_fields_change',
					'listen_fields' => array( 'CREATED_BY' ),
				),
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 * @throws \Exception
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
		$status     = isset( $parsed['STATUS'] ) ? sanitize_text_field( $parsed['STATUS'] ) : '';
		$tags       = isset( $parsed['TAGS'] ) ? sanitize_text_field( $parsed['TAGS'] ) : '';

		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new \Exception( esc_html_x( 'Email is empty or invalid', 'Help Scout', 'uncanny-automator' ), 422 );
		}

		$body_params = array(
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

		$body_params['first_name'] = $customer_complete_name['first_name'];
		$body_params['last_name']  = $customer_complete_name['last_name'];

		$response = $this->api->api_request( $body_params, $action_data );

		$this->hydrate_tokens(
			array(
				'CONVERSATION_ID'  => isset( $response['data']['data']['resourceId'] ) ? sanitize_text_field( $response['data']['data']['resourceId'] ) : 0,
				'CONVERSATION_URL' => isset( $response['data']['data']['webLocation'] ) ? esc_url( $response['data']['data']['webLocation'] ) : '',
			)
		);

		return true;
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
