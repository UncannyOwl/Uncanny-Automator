<?php
namespace Uncanny_Automator\Integrations\Google_Sheet;

use Uncanny_Automator\Recipe\App_Action;
use Exception;

/**
 * Google Sheets - Update a row in a Google Sheet (V2)
 *
 * @package Uncanny_Automator\Integrations\Google_Sheet
 * @since 5.0
 *
 * @property Google_Sheet_Helpers $helpers
 * @property Google_Sheet_Api_Caller $api
 */
class GOOGLESHEET_UPDATERECORD_V2 extends App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'GOOGLESHEET' );
		$this->set_action_code( 'SHEET_UPDATE_ROW_V2' );
		$this->set_action_meta( 'SHEET_UPDATE_ROW_V2_META' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Google Sheet
				esc_html_x( 'Update a row in {{a Google Sheet:%1$s}}', 'Google Sheet', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Update a row in {{a Google Sheet}}', 'Google Sheet', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * Get options for the action.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_spreadsheet_field(),
			$this->helpers->get_worksheet_field(),
			array(
				'option_code'           => 'GSWORKSHEETCOLUMN',
				'label'                 => esc_html_x( 'Column search', 'Google Sheets', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => array(),
				'supports_custom_value' => false,
				'ajax'                  => array(
					'endpoint'      => 'automator_fetch_googlesheets_worksheets_columns_search',
					'event'         => 'parent_fields_change',
					'listen_fields' => array( 'GSWORKSHEET' ),
				),
			),
			array(
				'option_code' => 'GSWORKSHEET_SOURCE_VALUE',
				'label'       => esc_html_x( 'Match value', 'Google Sheets', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
			array(
				'option_code'     => 'WORKSHEET_FIELDS',
				'input_type'      => 'repeater',
				'relevant_tokens' => array(),
				'label'           => esc_html_x( 'Row', 'Google Sheets', 'uncanny-automator' ),
				'required'        => true,
				'hide_actions'    => true,
				'default_value'   => array(
					array(
						'GS_COLUMN_NAME'  => '',
						'COLUMN_UPDATE'   => false,
						'GS_COLUMN_VALUE' => '',
					),
				),
				'fields'          => array(
					array(
						'option_code' => 'GS_COLUMN_NAME',
						'label'       => esc_html_x( 'Column', 'Google Sheets', 'uncanny-automator' ),
						'input_type'  => 'text',
						'required'    => true,
						'read_only'   => true,
						'options'     => array(),
					),
					array(
						'option_code' => 'COLUMN_UPDATE',
						'label'       => esc_html_x( 'Update?', 'Google Sheets', 'uncanny-automator' ),
						'input_type'  => 'checkbox',
						'is_toggle'   => true,
					),
					array(
						'option_code' => 'GS_COLUMN_VALUE',
						'label'       => esc_html_x( 'Value', 'Google Sheets', 'uncanny-automator' ),
						'input_type'  => 'text',
						'options'     => array(),
					),
				),
				'ajax'            => array(
					'endpoint'       => 'automator_fetch_googlesheets_worksheets_columns',
					'event'          => 'parent_fields_change',
					'listen_fields'  => array( 'GSWORKSHEET' ),
					'mapping_column' => 'GS_COLUMN_NAME',
				),
			),
			array(
				'option_code' => 'UPDATE_MULTIPLE_ROWS',
				'input_type'  => 'checkbox',
				'label'       => esc_html_x( 'If multiple matches are found, update all matching rows instead of the first matching row only.', 'Google Sheets', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param       $parsed
	 *
	 * @return boolean
	 * @throws Exception If failed to update row in Google Sheet.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$spreadsheet_id       = $this->get_parsed_meta_value( 'GSSPREADSHEET', $action_data );
		$gs_worksheet         = $this->get_parsed_meta_value( 'GSWORKSHEET', $action_data );
		$lookup_field         = $this->get_parsed_meta_value( 'GSWORKSHEETCOLUMN', $action_data );
		$update_multiple_rows = $action_data['meta']['UPDATE_MULTIPLE_ROWS'];
		$lookup_field_value   = $this->get_parsed_meta_value( 'GSWORKSHEET_SOURCE_VALUE', $action_data );

		// Fields repeater.
		$row_repeater = $this->get_parsed_meta_value( 'WORKSHEET_FIELDS', $action_data );
		$fields       = json_decode( $row_repeater, true );

		// Get the hashed spreadsheet name
		$hashed   = sha1( $this->helpers->get_const( 'HASH_STRING' ) );
		$sheet_id = substr( $hashed, 0, 9 );

		if ( (string) $gs_worksheet === (string) $sheet_id || intval( '-1' ) === intval( $gs_worksheet ) ) {
			$gs_worksheet = 0;
		}

		// Process the update.
		$spreadsheet_name = $action_data['meta']['GSWORKSHEET_readable'];

		// Construct the look up column search.
		$lookup_field_parts    = explode( '-', $lookup_field );
		$selected_column_range = $spreadsheet_name . '!' . $lookup_field_parts[1];

		$row_values = $this->get_activated_row_values( $fields, $recipe_id, $user_id, $args );

		$body = array(
			'action'                      => 'match_and_update',
			'range'                       => $selected_column_range,
			'spreadsheet_id'              => $spreadsheet_id,
			'spreadsheet_name'            => $spreadsheet_name,
			'should_update_multiple_rows' => $update_multiple_rows,
			'lookup_value'                => $lookup_field_value,
			'values'                      => $row_values,
		);

		$this->api->api_request( $body, $action_data );

		return true;
	}

	/**
	 * Get all the rows ranges that needs to be updated.
	 *
	 * @param mixed $row_repeater
	 * @param mixed $recipe_id
	 * @param mixed $user_id
	 * @param mixed $args
	 * @return string|false
	 */
	private function get_activated_row_values( $row_repeater, $recipe_id, $user_id, $args ) {
		$row_values = array();

		/**
		 * Filter to disable tokens HTML.
		 *
		 * @param boolean true to disable tokens HTML.
		 * @return boolean
		 *
		 * @example
		 * add_filter( 'automator_google_sheets_disable_tokens_html', '__return_false' );
		 */
		$strip_html = apply_filters( 'automator_google_sheets_disable_tokens_html', true );

		foreach ( (array) $row_repeater as $field ) {
			$cell_value = null; // Pass null to avoid overwriting the cell value.

			if ( true === $field['COLUMN_UPDATE'] ) {
				// Parse tokens in individual field value before any processing.
				$cell_value = $strip_html
					? wp_strip_all_tags( $field['GS_COLUMN_VALUE'] )
					: $field['GS_COLUMN_VALUE'];
			}

			// Add the value to our request body.
			$row_values[] = $cell_value;
		}

		return wp_json_encode( array( $row_values ) );  // Wrapping as array for API Backwards compatibility.
	}
}
