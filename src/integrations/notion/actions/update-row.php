<?php
//phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound
/**
 * @package Uncanny_Automator\Integrations\Notion\Actions
 */
namespace Uncanny_Automator\Integrations\Notion\Actions;

use Exception;
use Uncanny_Automator\Integrations\Notion\Notion_Helpers;

/**
 * @package Uncanny_Automator\Integrations\Notion\Actions
 */
class Update_Row extends \Uncanny_Automator\Recipe\Action {

	/**
	 * IDE Support.
	 *
	 * @var \Uncanny_Automator\Integrations\Notion\Notion_Helpers
	 */
	protected $helper = null;

	/**
	 * Setups the action basic properties like Integration, Sentence, etc.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->helper = array_shift( $this->dependencies );

		$this->set_integration( 'NOTION' );
		$this->set_action_code( 'NOTION_UPDATE_ROW' );
		$this->set_action_meta( 'NOTION_UPDATE_ROW_META' );
		$this->set_requires_user( false );

		/* translators: Action sentence */
		$this->set_sentence( sprintf( esc_attr_x( 'Update {{a database item:%1$s}}', 'Notion', 'uncanny-automator' ), $this->get_action_meta() ) );

		/* translators: Action sentence */
		$this->set_readable_sentence( esc_attr_x( 'Update {{a database item}}', 'Notion', 'uncanny-automator' ) );

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
			'input_type'      => 'select',
			'option_code'     => $this->get_action_meta(),
			'label'           => esc_html_x( 'Database', 'Notion', 'uncanny-automator' ),
			'required'        => true,
			'options'         => array(),
			'options_show_id' => false,
			'token_name'      => esc_html_x( 'Database ID', 'Notion', 'uncanny-automator' ),
			'ajax'            => array(
				'endpoint' => 'automator_notion_list_databases',
				'event'    => 'on_load',
			),
		);

		$column_search = array(
			'option_code'     => 'COLUMN_SEARCH',
			'input_type'      => 'select',
			'label'           => esc_html_x( 'Column search', 'Notion', 'uncanny-automator' ),
			'required'        => true,
			'relevant_tokens' => array(),
			'options_show_id' => false,
			'fields'          => array(),
			'ajax'            => array(
				'event'         => 'parent_fields_change',
				'endpoint'      => 'automator_notion_get_database_columns',
				'listen_fields' => array( $this->get_action_meta() ),
			),
		);

		$value = array(
			'input_type'      => 'text',
			'option_code'     => 'MATCH_VALUE',
			'label'           => esc_html_x( 'Match value', 'Notion', 'uncanny-automator' ),
			'required'        => true,
			'relevant_tokens' => array(),
		);

		$key_value_pairs = array(
			'option_code'     => 'FIELD_COLUMN_VALUE',
			'input_type'      => 'repeater',
			'relevant_tokens' => array(),
			'label'           => esc_html_x( 'Row', 'Notion', 'uncanny-automator' ),
			'description'     => esc_html_x( "Leaving fields blank keeps current values. Checkbox selections are reflected (unchecked or checked), so ensure checkboxes have the correct value if you're matching against them.", 'Notion', 'uncanny-automator' ),
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
			$column_search,
			$value,
			$key_value_pairs,
		);
	}

	/**
	 * Processes the action.
	 *
	 * @link https://developer.automatorplugin.com/adding-a-custom-action-to-uncanny-automator/ Processing the action.
	 *
	 * @param int     $user_id The user ID. Use this argument to passed the User ID instead of get_current_user_id().
	 * @param mixed[] $action_data The action data.
	 * @param int     $recipe_id The recipe ID.
	 * @param mixed[] $args The args.
	 * @param mixed[] $parsed The parsed variables.
	 *
	 * @return bool True if the action is successful. Returns false, otherwise.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$column             = $parsed['COLUMN_SEARCH'] ?? '';
		$match_value        = $parsed['MATCH_VALUE'] ?? '';
		$field_column_value = $action_data['meta']['FIELD_COLUMN_VALUE'] ?? '';

		// Make sure the match value is compatible with notion.
		$match_value = $this->compat_match_value( $match_value );

		$column_extracted = $this->extract_column_name( $column );

		$property_type = $column_extracted['type'];

		// Create the payload for the fields.
		$field_data = $this->helper->make_fields_payload( $recipe_id, $args, $parsed, $field_column_value );

		// Get the database ID from the parsed variables.
		$db_id = $parsed[ $this->get_action_meta() ] ?? '';

		// Construct the request body.
		$body = array(
			'action'        => 'db_update_row',
			'db_id'         => $db_id,
			'field_data'    => $field_data,
			'match_against' => $match_value,
			'column_name'   => $column_extracted['name'],
			'property_type' => $property_type,
			'operator'      => $this->get_operator( $property_type ),
		);

		// Send the API request.
		$response = $this->helper->api_request( $body, $action_data );

		$at_key_values = array(
			'DB_NAME'    => $action_data['meta'][ $this->get_action_meta() . '_readable' ] ?? '',
			'DB_URL'     => $response['data']['url'] ?? '',
			'PAGE_ID'    => $response['data']['id'] ?? '',
			'PROPERTIES' => wp_json_encode( (array) $response['data']['properties'], true ),
		);

		$this->hydrate_tokens( $at_key_values );

		return true;
	}

	/**
	 * Retrieve the operator.
	 *
	 * @param string $type
	 *
	 * @return string
	 */
	public function get_operator( $type ) {

		switch ( $type ) {
			case 'tags':
			case 'multi_select':
				return 'contains';
		}

		return 'equals';
	}

	/**
	 * Extract the column name and return the array result.
	 *
	 * @param string $column_name
	 *
	 * @return string[]
	 * @throws Exception
	 */
	private function extract_column_name( $column_name ) {

		$parts = Notion_Helpers::extract_field_parameters_columns( $column_name );

		if ( count( $parts ) !== 5 ) {
			throw new Exception(
				sprintf(
				/* translators: %s: Column name */
					esc_html_x( 'Invalid column length after extracting: %s', 'Notion', 'uncanny-automator' ),
					esc_html( $column_name )
				),
				400
			);
		}

		list( $notion, $field, $name, $id, $type ) = $parts;

		return array(
			'name' => $name,
			'id'   => $id,
			'type' => $type,
		);
	}

	/**
	 * Make sure the column value is compatible with Notion.
	 *
	 * @param string $string
	 *
	 * @return mixed
	 */
	private function compat_match_value( $date_string ) {

		// Check if the date is valid and convert it to ISO 8601 format.
		if ( $this->helper->is_valid_date( $date_string ) ) {
			return $this->helper->convert_to_iso_8601( $date_string );
		}

		return $date_string;
	}
}
