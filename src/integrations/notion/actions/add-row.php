<?php
//phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound
namespace Uncanny_Automator\Integrations\Notion\Actions;

use Exception;

/**
 * @package Uncanny_Automator\Integrations\Notion\Actions
 */
class Add_Row extends \Uncanny_Automator\Recipe\Action {

	/**
	 * IDE Support.
	 *
	 * @var \Uncanny_Automator\Integrations\Notion\Notion_Helpers
	 */
	protected $helper = null;

	const INTEGRATION = 'NOTION';
	const ACTION_CODE = 'NOTION_ADD_ROW';
	const ACTION_META = 'NOTION_ADD_ROW_META';

	/**
	 * Setup the action basic properties like Integration, Sentence, etc.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->helper = array_shift( $this->dependencies );

		$this->set_integration( self::INTEGRATION );
		$this->set_action_code( self::ACTION_CODE );
		$this->set_action_meta( self::ACTION_META );
		$this->set_requires_user( false );

		/* translators: Action sentence */
		$this->set_sentence( sprintf( esc_attr_x( 'Create a {{database:%1$s}} item', 'Notion', 'uncanny-automator' ), $this->get_action_meta() ) );

		/* translators: Action sentence */
		$this->set_readable_sentence( esc_attr_x( 'Create a {{database}} item', 'Notion', 'uncanny-automator' ) );

		$action_tokens = array(
			'DB_NAME'    => array(
				'name' => esc_attr_x( 'Database name', 'Notion', 'uncanny-automator' ),
				'type' => 'string',
			),
			'DB_URL'     => array(
				'name' => esc_attr_x( 'Database URL', 'Notion', 'uncanny-automator' ),
				'type' => 'url',
			),
			'PAGE_ID'    => array(
				'name' => esc_attr_x( 'Page ID', 'Notion', 'uncanny-automator' ),
				'type' => 'int',
			),
			'PROPERTIES' => array(
				'name' => esc_attr_x( 'Properties (JSON)', 'Notion', 'uncanny-automator' ),
				'type' => 'string',
			),
		);

		$this->set_action_tokens( $action_tokens, $this->get_action_code() );
	}

	/**
	 * Defines the options.
	 *
	 * @return array<array{
	 *  'text': string,
	 *  'value': mixed
	 * }>
	 */
	public function options() {

		$database = array(
			'input_type'            => 'select',
			'option_code'           => $this->get_action_meta(),
			'label'                 => esc_html_x( 'Database', 'Notion', 'uncanny-automator' ),
			'required'              => true,
			'options'               => array(),
			'options_show_id'       => false,
			'token_name'            => esc_html_x( 'Database ID', 'Notion', 'uncanny-automator' ),
			'ajax'                  => array(
				'endpoint' => 'automator_notion_list_databases',
				'event'    => 'on_load',
			),
			'supports_custom_value' => false, // Impossible to get databse UUID from the UI.
		);

		$key_value_pairs = array(
			'option_code'     => 'FIELD_COLUMN_VALUE',
			'input_type'      => 'repeater',
			'relevant_tokens' => array(),
			'label'           => esc_html_x( 'Row', 'Notion', 'uncanny-automator' ),
			'required'        => true,
			'layout'          => 'transposed',
			'hide_header'     => true,
			'hide_actions'    => true,
			'fields'          => array(),
			'ajax'            => array(
				'event'         => 'parent_fields_change',
				'endpoint'      => 'automator_notion_get_database',
				'listen_fields' => array( $this->get_action_meta() ),
			),
		);

		return array(
			$database,
			$key_value_pairs,
		);
	}

	/**
	 * Processes the action.
	 *
	 * @link https://developer.automatorplugin.com/adding-a-custom-action-to-uncanny-automator/ Processing the action.
	 *
	 * @param int     $user_id The user ID. Use this argument to pass the User ID instead of get_current_user_id().
	 * @param mixed[] $action_data The action data.
	 * @param int     $recipe_id The recipe ID.
	 * @param mixed[] $args The args.
	 * @param mixed[] $parsed The parsed variables.
	 *
	 * @return bool True if the action is successful. Returns false, otherwise.
	 * @throws Exception If there is an issue with the action data.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		// Retrieve the column value from action data.
		// Using action data instead of parsed variables to avoid issues with the repeater field.
		$column_value = $action_data['meta']['FIELD_COLUMN_VALUE'] ?? array();

		// Create the payload for the fields.
		$field_data = $this->helper->make_fields_payload( $column_value );

		// Get the database ID from the parsed variables. The $parsed is fine because this is not a repeater field.
		$db_id = $parsed[ $this->get_action_meta() ] ?? '';

		// Construct the request body.
		$body = array(
			'action'     => 'db_add_row',
			'db_id'      => $db_id,
			'field_data' => $field_data,
		);

		// Send the API request.
		$response = $this->helper->api_request( $body, $action_data );

		$at_key_values = array(
			'DB_NAME'    => $action_data['meta'][ $this->get_action_meta() . '_readable' ] ?? '',
			'DB_URL'     => $response['data']['url'],
			'PAGE_ID'    => $response['data']['id'],
			'PROPERTIES' => wp_json_encode( (array) $response['data']['properties'], true ),
		);

		$this->hydrate_tokens( $at_key_values );

		return true;
	}
}
