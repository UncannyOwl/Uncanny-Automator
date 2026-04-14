<?php
namespace Uncanny_Automator\Integrations\Zoho_Campaigns;

use Exception;

/**
 * @property Zoho_Campaigns_App_Helpers $helpers
 * @property Zoho_Campaigns_Api_Caller $api
 */
class ZOHO_CAMPAIGNS_CONTACT_LIST_SUB extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'ZOHO_CAMPAIGNS' );
		$this->set_action_code( 'ZOHO_CAMPAIGNS_CONTACT_LIST_SUB' );
		$this->set_action_meta( 'ZOHO_CAMPAIGNS_CONTACT_LIST_SUB_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/zoho-campaigns/' ) );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_readable_sentence( esc_attr_x( 'Subscribe {{a contact}} to {{a list}}', 'ZohoCampaigns', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: contact meta, %2$s: list meta
				esc_attr_x( 'Subscribe {{a contact:%1$s}} to {{a list:%2$s}}', 'ZohoCampaigns', 'uncanny-automator' ),
				$this->get_action_meta(),
				'LIST:' . $this->get_action_meta()
			)
		);

		$this->set_action_tokens(
			array(
				'TOPIC_NAME' => array(
					'name' => esc_attr_x( 'Topic name', 'ZohoCampaigns', 'uncanny-automator' ),
					'type' => 'text',
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Loads options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_list_option_config(),
			$this->helpers->get_email_option_config( $this->get_action_meta() ),
			array(
				'option_code'              => 'TOPIC',
				'label'                    => esc_attr_x( 'Topic', 'ZohoCampaigns', 'uncanny-automator' ),
				'custom_value_description' => esc_attr_x( 'Topic ID', 'ZohoCampaigns', 'uncanny-automator' ),
				'token_name'               => 'Topic ID',
				'input_type'               => 'select',
				'options'                  => array(),
				'ajax'                     => array(
					'event'    => 'on_load',
					'endpoint' => 'automator_zoho_campaigns_get_topic_options',
				),
				'required'                 => true,
				'options_show_id'          => false,
			),
			array(
				'option_code'     => 'CONTACT_FIELDS',
				'input_type'      => 'repeater',
				'relevant_tokens' => array(),
				'label'           => esc_attr_x( 'Fields', 'ZohoCampaigns', 'uncanny-automator' ),
				'required'        => false,
				'fields'          => array(
					array(
						'label'       => esc_attr_x( 'Field name', 'ZohoCampaigns', 'uncanny-automator' ),
						'option_code' => 'FIELD_NAME',
						'input_type'  => 'text',
						'read_only'   => true,
					),
					array(
						'label'       => esc_attr_x( 'Value', 'ZohoCampaigns', 'uncanny-automator' ),
						'option_code' => 'FIELD_VALUE',
						'input_type'  => 'text',
					),
				),
				'ajax'            => array(
					'event'          => 'on_load',
					'endpoint'       => 'automator_zoho_campaigns_get_fields_rows',
					'mapping_column' => 'FIELD_NAME',
				),
				'hide_actions'    => true,
			),
		);
	}

	/**
	 * Processes the action.
	 *
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool|null True on success, null with notice on partial success.
	 * @throws Exception On failure.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$topic_id = sanitize_text_field( $parsed['TOPIC'] ?? '' );
		$contact  = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' );
		$list     = sanitize_text_field( $parsed['LIST'] ?? '' );

		// The $parsed is breaking JSON format. Use $action_data instead.
		$fields         = $action_data['meta']['CONTACT_FIELDS'] ?? '';
		$contact_fields = ! empty( $fields ) ? json_decode( $fields, true ) : array();

		// Making sure we have a valid JSON here.
		if ( false === $contact_fields || ! is_array( $contact_fields ) ) {
			throw new Exception(
				sprintf(
					// translators: %s: JSON input
					esc_html_x( 'Failed to decode the contact fields from given the JSON input: %s', 'Zoho Campaigns', 'uncanny-automator' ),
					esc_html( $fields )
				)
			);
		}

		$contact_fields = $this->parse_fields( $contact_fields, $recipe_id, $user_id, $args );

		$response = $this->api->contact_list_sub(
			array(
				'contact'  => $contact,
				'list_key' => $list,
				'topic_id' => $topic_id,
				'fields'   => wp_json_encode( $contact_fields ),
			),
			$action_data
		);

		$this->hydrate_tokens(
			array(
				'TOPIC_NAME' => $args['action_meta']['TOPIC_readable'] ?? '',
			)
		);

		// Handle API response with a message (partial success/notice).
		if ( ! empty( $response['data']['message'] ) ) {
			$this->set_complete_with_notice( true );
			$this->add_log_error( $response['data']['message'] );
			return null;
		}

		return true;
	}

	/**
	 * Parse the contact fields.
	 *
	 * @param mixed[] $contact_fields
	 * @param int $recipe_id
	 * @param int $user_id
	 * @param array $args
	 *
	 * @return mixed[]
	 */
	public function parse_fields( $contact_fields, $recipe_id, $user_id, $args ) {

		$contact_fields_parsed = array();

		foreach ( (array) $contact_fields as $field ) {
			if ( is_array( $field ) && ( isset( $field['FIELD_NAME'] ) && isset( $field['FIELD_VALUE'] ) ) ) {
				$contact_fields_parsed[] = array(
					'FIELD_NAME'  => $field['FIELD_NAME'],
					'FIELD_VALUE' => Automator()->parse->text( $field['FIELD_VALUE'], $recipe_id, $user_id, $args ),
				);
			}
		}

		return $contact_fields_parsed;
	}
}
