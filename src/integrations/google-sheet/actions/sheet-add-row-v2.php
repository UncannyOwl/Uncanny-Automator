<?php
namespace Uncanny_Automator\Integrations\Google_Sheet;

use Uncanny_Automator\Recipe\App_Action;
use Exception;

/**
 * Google Sheets - Create a row in a Google Sheet (V2)
 *
 * @package Uncanny_Automator\Integrations\Google_Sheet
 * @since 5.0
 *
 * @property Google_Sheet_Helpers $helpers
 * @property Google_Sheet_Api_Caller $api
 */
class GOOGLESHEET_ADD_ROW_V2 extends App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'GOOGLESHEET' );
		$this->set_action_code( 'SHEET_ADD_ROW_V2' );
		$this->set_action_meta( 'SHEET_ADD_ROW_V2_META' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Google Sheet
				esc_html_x( 'Create a row in {{a Google Sheet:%1$s}}', 'Google Sheet', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Create a row in {{a Google Sheet}}', 'Google Sheet', 'uncanny-automator' ) );
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
				'option_code'       => 'WORKSHEET_FIELDS',
				'input_type'        => 'repeater',
				'relevant_tokens'   => array(),
				'label'             => esc_html_x( 'Row', 'Google Sheets', 'uncanny-automator' ),
				'description'       => '',
				'required'          => true,
				'add_row_button'    => esc_html_x( 'Add pair', 'Google Sheets', 'uncanny-automator' ),
				'remove_row_button' => esc_html_x( 'Remove pair', 'Google Sheets', 'uncanny-automator' ),
				'hide_actions'      => true,
				'default_value'     => array(
					array(
						'GS_COLUMN_NAME'  => '',
						'GS_COLUMN_VALUE' => '',
					),
				),
				'fields'            => array(
					array(
						'option_code' => 'GS_COLUMN_NAME',
						'label'       => esc_html_x( 'Column', 'Google Sheets', 'uncanny-automator' ),
						'input_type'  => 'text',
						'required'    => true,
						'read_only'   => true,
						'options'     => array(),
					),
					array(
						'option_code' => 'GS_COLUMN_VALUE',
						'label'       => esc_html_x( 'Value', 'Google Sheets', 'uncanny-automator' ),
						'input_type'  => 'text',
						'options'     => array(),
					),
				),
				'ajax'              => array(
					'endpoint'       => 'automator_fetch_googlesheets_worksheets_columns',
					'event'          => 'parent_fields_change',
					'listen_fields'  => array( 'GSWORKSHEET' ),
					'mapping_column' => 'GS_COLUMN_NAME',
				),
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
	 * @throws Exception If failed to create row in Google Sheets.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$spreadsheet     = $this->get_parsed_meta_value( 'GSSPREADSHEET', $action_data );
		$worksheet       = $this->get_parsed_meta_value( 'GSWORKSHEET', $action_data );
		$worksheet_field = $this->get_parsed_meta_value( 'WORKSHEET_FIELDS', $action_data );
		$fields          = json_decode( $worksheet_field, true );
		$row_data        = array();
		$check_all_empty = true;

		// Backwards compatibility.
		$worksheet = $this->helpers->calculate_hash( $worksheet );

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

		foreach ( $fields as $field ) {
			$key   = $field['GS_COLUMN_NAME'];
			$value = $field['GS_COLUMN_VALUE'];
			$value = $strip_html ? wp_strip_all_tags( $value ) : $value;

			$row_data[ $key ] = $value;

			if ( ! empty( $value ) ) {
				$check_all_empty = false;
			}
		}

		if ( $check_all_empty ) {
			throw new Exception( esc_html_x( 'Trying to add an empty row.', 'Google Sheets', 'uncanny-automator' ) );
		}

		// Make the API request
		$response = $this->api->append_row( $spreadsheet, $worksheet, $row_data, $action_data );

		if ( empty( $response['data'] ) ) {
			throw new Exception( esc_html_x( 'Failed to create row in Google Sheet', 'Google Sheet', 'uncanny-automator' ) );
		}

		return true;
	}
}
