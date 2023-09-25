<?php
namespace Uncanny_Automator;

use Exception;

/**
 * Class Zoho_Campaigns_Contact_List_Sub
 *
 * @package Uncanny_Automator
 */
class Zoho_Campaigns_Contact_List_Sub {

	use Recipe\Actions;
	use Recipe\Action_Tokens;

	/**
	 * Method __construct
	 *
	 * @return void
	 */
	public function __construct() {

		$this->setup_action();

	}

	/**
	 * Setups the Action.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->set_integration( 'ZOHO_CAMPAIGNS' );

		$this->set_action_code( 'ZOHO_CAMPAIGNS_CONTACT_LIST_SUB' );

		$this->set_action_meta( 'ZOHO_CAMPAIGNS_CONTACT_LIST_SUB_META' );

		$this->set_is_pro( false );

		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/zoho-campaigns/' ) );

		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: Action sentence */
				esc_attr__( 'Subscribe {{a contact:%1$s}} to {{a list:%2$s}}', 'uncanny-automator' ),
				$this->get_action_meta(),
				'LIST:' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_attr__( 'Subscribe {{a contact}} to {{a list}}', 'uncanny-automator' ) );

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->set_background_processing( true );

		$this->set_action_tokens(
			array(
				'TOPIC_NAME' => array(
					'name' => esc_attr__( 'Topic name', 'uncanny-automator' ),
					'type' => 'text',
				),
			),
			$this->get_action_code()
		);

		$this->register_action();

	}

	/**
	 * Loads options.
	 *
	 * @return void.
	 */
	public function load_options() {

		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->get_action_meta() => array(
						array(
							'option_code'              => 'LIST',
							/* translators: Action field */
							'label'                    => esc_attr__( 'List', 'uncanny-automator' ),
							'custom_value_description' => esc_attr__( 'List key', 'uncanny-automator' ),
							'input_type'               => 'select',
							'options'                  => array(),
							'ajax'                     => array(
								'event'    => 'on_load',
								'endpoint' => 'automator-fetch-lists',
							),
							'required'                 => true,
							'options_show_id'          => false,
						),
						array(
							'option_code' => $this->get_action_meta(),
							/* translators: Action field */
							'label'       => esc_attr__( 'Email', 'uncanny-automator' ),
							'input_type'  => 'email',
							'required'    => true,
						),
						array(
							'option_code'              => 'TOPIC',
							/* translators: Action field */
							'label'                    => esc_attr__( 'Topic', 'uncanny-automator' ),
							'custom_value_description' => esc_attr__( 'Topic ID', 'uncanny-automator' ),
							'token_name'               => 'Topic ID',
							'input_type'               => 'select',
							'options'                  => array(),
							'ajax'                     => array(
								'event'    => 'on_load',
								'endpoint' => 'automator-fetch-topics',
							),
							'required'                 => true,
							'options_show_id'          => false,
						),
						array(
							'option_code'  => 'CONTACT_FIELDS',
							'input_type'   => 'repeater',
							/* translators: Action field */
							'label'        => _x( 'Fields', 'ZohoCampaigns', 'uncanny-automator' ),
							'required'     => false,
							'fields'       => array(
								array(
									'label'       => _x( 'Field name', 'ZohoCampaigns', 'uncanny-automator' ),
									'option_code' => 'FIELD_NAME',
									'input_type'  => 'text',
									'read_only'   => true,
								),
								array(
									'label'       => _x( 'Value', 'ZohoCampaigns', 'uncanny-automator' ),
									'option_code' => 'FIELD_VALUE',
									'input_type'  => 'text',
								),
							),
							'ajax'         => array(
								'event'          => 'on_load',
								'endpoint'       => 'automator-zoho-campaigns-fetch-fields',
								'mapping_column' => 'FIELD_NAME',
							),
							'hide_actions' => true,
						),
					),

				),
			)
		);

	}

	/**
	 * Processes the action.
	 *
	 * @return void.
	 */
	public function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$topic_id = isset( $parsed['TOPIC'] ) ? sanitize_text_field( $parsed['TOPIC'] ) : '';
		$contact  = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : '';
		$list     = isset( $parsed['LIST'] ) ? sanitize_text_field( $parsed['LIST'] ) : '';

		// The $parsed is breaking JSON format. Use $action_data instead.
		$fields = isset( $action_data['meta']['CONTACT_FIELDS'] ) ? $action_data['meta']['CONTACT_FIELDS'] : '';

		$contact_fields = json_decode( $fields, true );

		try {

			// Making sure we have a valid JSON here.
			if ( false === $contact_fields || ! is_array( $contact_fields ) ) {
				throw new Exception(
					sprintf(
						_x( 'Failed to decode the contact fields from given the JSON input: %s', 'ZohoCampaigns', 'uncanny-automator' ),
						$fields
					)
				);
			}

			$contact_fields = $this->parse_fields( $contact_fields, $recipe_id, $user_id, $args );

			$this->set_helpers( new Zoho_Campaigns_Helpers( false ) );

			$this->get_helpers()->require_dependency( 'client/actions/zoho-campaigns-actions' );
			$this->get_helpers()->require_dependency( 'client/auth/zoho-campaigns-client-auth' );

			$authentication = new Zoho_Campaigns_Client_Auth();
			$zoho_action    = new Zoho_Campaigns_Actions( Api_Server::get_instance(), $authentication );

			$response = $zoho_action->contact_list_sub(
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
					'TOPIC_NAME' => $args['action_meta']['TOPIC_readable'],
				)
			);

			if ( $response['data']['message'] ) {

				$action_data['complete_with_notice'] = true;

				Automator()->complete->action( $user_id, $action_data, $recipe_id, $response['data']['message'] );

				return;

			}

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

		}

	}

	/**
	 * Parse the contact fields.
	 *
	 * @param mixed[] $contact_fields
	 * @param string $field_text
	 * @param int $recipe_id
	 * @param int $user_id
	 * @param int $args
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
