<?php

namespace Uncanny_Automator;

use Exception;

/**
 * Class SHEET_UPDATERECORD_V2
 *
 * @package Uncanny_Automator
 */
class SHEET_UPDATERECORD_V2 {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'GOOGLESHEET';

	/**
	 * The action code.
	 *
	 * @var string
	 */
	private $action_code = 'SHEET_UPDATE_ROW_V2';

	/**
	 * The action meta.
	 *
	 * @var string
	 */
	private $action_meta = 'SHEET_UPDATE_ROW_V2_META';

	/**
	 * The Automator endpoint url.
	 *
	 * @var string
	 */
	public $endpoint_url = '';

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {

		$this->endpoint_url = AUTOMATOR_API_URL;
		$this->define_action();

	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'                => Automator()->get_author_name( $this->action_code ),
			'support_link'          => Automator()->get_author_support_link( $this->action_code, 'knowledge-base/google-sheets/' ),
			'is_pro'                => false,
			'integration'           => self::$integration,
			'code'                  => $this->action_code,
			'sentence'              => sprintf(
				/* translators: Action sentence */
				esc_html__( 'Update a row in a {{Google Sheet:%1$s}}', 'uncanny-automator' ),
				$this->action_meta
			),
			'select_option_name'    => esc_html__( 'Update a row in a {{Google Sheet}}', 'uncanny-automator' ),
			'priority'              => 10,
			'accepted_args'         => 1,
			'requires_user'         => false,
			'execution_function'    => array( $this, 'update_row_google_sheet' ),
			'options_callback'      => array( $this, 'load_options' ),
			'background_processing' => true,
		);

		Automator()->register->action( $action );
	}

	/**
	 * load_options
	 *
	 * @return void
	 */
	public function load_options() {
		$options = array(
			'options_group' => array(
				$this->action_meta => array(
					array(
						'option_code'           => 'GSSPREADSHEET',
						'label'                 => esc_html_x( 'Spreadsheet', 'Google Sheets', 'uncanny-automator' ),
						'description'           => wp_kses(
							sprintf(
								esc_html_x(
									"If you don't see your spreadsheet or haven't selected any files yet, please go to the %1\$ssettings page%2\$s to add.",
									'Google Sheets',
									'uncanny-automator'
								),
								'<a href="' . esc_url( admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-config&tab=premium-integrations&integration=google-sheet' ) ) . '">',
								'</a>'
							),
							array(
								'a' => array(
									'href' => array(),
								),
							)
						),
						'input_type'            => 'select',
						'required'              => true,
						'options'               => array(),
						'supports_custom_value' => false,
						'options_show_id'       => false,
						'ajax'                  => array(
							'endpoint' => 'automator_fetch_googlesheets_spreadsheets',
							'event'    => 'on_load',
						),
					),
					array(
						'option_code'           => 'GSWORKSHEET',
						'label'                 => esc_html__( 'Worksheet', 'uncanny-automator' ),
						'input_type'            => 'select',
						'required'              => true,
						'options'               => array(),
						'options_show_id'       => false,
						'supports_custom_value' => false,
						'ajax'                  => array(
							'endpoint'      => 'automator_fetch_googlesheets_worksheets',
							'event'         => 'parent_fields_change',
							'listen_fields' => array( 'GSSPREADSHEET' ),
						),
					),
					array(
						'option_code'           => 'GSWORKSHEETCOLUMN',
						'label'                 => esc_html__( 'Column search', 'uncanny-automator' ),
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
						'label'       => esc_html__( 'Match value', 'uncanny-automator' ),
						'input_type'  => 'text',
						'required'    => true,
					),
					array(
						'option_code'       => 'WORKSHEET_FIELDS',
						'input_type'        => 'repeater',
						'relevant_tokens'   => array(),
						'label'             => esc_html__( 'Row', 'uncanny-automator' ),
						'description'       => '',
						'required'          => true,
						'default_value'     => array(
							array(
								'FIELD_NAME'  => '',
								'FIELD_VALUE' => '',
							),
						),
						'fields'            => array(
							array(
								'option_code' => 'GS_COLUMN_NAME',
								'label'       => esc_html__( 'Column', 'uncanny-automator' ),
								'input_type'  => 'text',
								'required'    => true,
								'read_only'   => true,
								'options'     => array(),
							),
							array(
								'option_code' => 'COLUMN_UPDATE',
								'label'       => esc_html__( 'Update?', 'uncanny-automator' ),
								'input_type'  => 'checkbox',
								'is_toggle'   => true,
							),
							array(
								'option_code' => 'GS_COLUMN_VALUE',
								'label'       => esc_html__( 'Value', 'uncanny-automator' ),
								'input_type'  => 'text',
								'options'     => array(),
							),
						),
						'add_row_button'    => esc_html__( 'Add pair', 'uncanny-automator' ),
						'remove_row_button' => esc_html__( 'Remove pair', 'uncanny-automator' ),
						'hide_actions'      => true,
						'ajax'              => array(
							'endpoint'       => 'automator_fetch_googlesheets_worksheets_columns',
							'event'          => 'parent_fields_change',
							'listen_fields'  => array( 'GSWORKSHEET' ),
							'mapping_column' => 'GS_COLUMN_NAME',
						),
					),
					array(
						'option_code' => 'UPDATE_MULTIPLE_ROWS',
						'input_type'  => 'checkbox',
						'label'       => esc_html__( 'If multiple matches are found, update all matching rows instead of the first matching row only.', 'uncanny-automator' ),
					),
				),
			),
		);

		return $options;
	}

	/**
	 *
	 * @param mixed $worksheet_fields
	 * @param mixed $recipe_id
	 * @param mixed $user_id
	 * @param mixed $args
	 * @return string|false
	 */
	public static function parse_field_values( $worksheet_fields, $recipe_id, $user_id, $args ) {

		$key_values = array();

		$fields = (array) json_decode( $worksheet_fields, true );

		$fields_count = count( $fields );

		for ( $i = 0; $i < $fields_count; $i ++ ) {
			$key                = $fields[ $i ]['GS_COLUMN_NAME'];
			$value              = Automator()->parse->text( $fields[ $i ]['GS_COLUMN_VALUE'], $recipe_id, $user_id, $args );
			$key_values[ $key ] = $value;
		}

		return wp_json_encode( $key_values );

	}

	/**
	 * Validation function when the action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 */
	public function update_row_google_sheet( $user_id, $action_data, $recipe_id, $args ) {

		$spreadsheet_id       = $action_data['meta']['GSSPREADSHEET'];
		$gs_worksheet         = $action_data['meta']['GSWORKSHEET'];
		$lookup_field         = $action_data['meta']['GSWORKSHEETCOLUMN'];
		$update_multiple_rows = $action_data['meta']['UPDATE_MULTIPLE_ROWS'];
		$lookup_field_value   = Automator()->parse->text( $action_data['meta']['GSWORKSHEET_SOURCE_VALUE'], $recipe_id, $user_id, $args );

		// Fields repeater.
		$row_repeater = $action_data['meta']['WORKSHEET_FIELDS'];
		$fields       = json_decode( $row_repeater, true );

		$hashed   = sha1( Google_Sheet_Helpers::$hash_string );
		$sheet_id = substr( $hashed, 0, 9 );

		if ( (string) $gs_worksheet === (string) $sheet_id || intval( '-1' ) === intval( $gs_worksheet ) ) {
			$gs_worksheet = 0;
		}

		// Process the update.
		$spreadsheet_name = $action_data['meta']['GSWORKSHEET_readable'];

		// Construct the look up column search.
		$lookup_field_parts    = explode( '-', $lookup_field );
		$selected_column_range = $spreadsheet_name . '!' . $lookup_field_parts[1];

		$helper = Automator()->helpers->recipe->google_sheet;

		try {

			$row_values = self::get_activated_row_values( $fields, $recipe_id, $user_id, $args );

			$body = array(
				'action'                      => 'match_and_update',
				'range'                       => $selected_column_range,
				'spreadsheet_id'              => $spreadsheet_id,
				'spreadsheet_name'            => $spreadsheet_name,
				'should_update_multiple_rows' => $update_multiple_rows,
				'lookup_value'                => $lookup_field_value,
				'values'                      => $row_values,
			);

			$helper->api_call( $body, $action_data );

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( Exception $e ) {

			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );
		}

		return;

	}

	/**
	 * Get all the rows ranges that needs to be updated.
	 *
	 * @param mixed $fields
	 * @return string|false
	 */
	public static function get_activated_row_values( $row_repeater, $recipe_id, $user_id, $args ) {

		$row_values = array();

		foreach ( (array) $row_repeater as $field ) {

			$cell_value = null; // Pass null to avoid overwriting the cell value.

			if ( true === $field['COLUMN_UPDATE'] ) {
				$cell_value = Automator()->parse->text( $field['GS_COLUMN_VALUE'], $recipe_id, $user_id, $args );
			}

			// Add the value to our request body.
			$row_values[] = $cell_value;

		}

		return wp_json_encode( array( $row_values ) );  // Wrapping as array for API Backwards compatibility.
	}

	/**
	 * Wrapper method function Automator complete with errors method.
	 *
	 * @return boolean True.
	 */
	protected function complete_with_errors( $user_id, $action_data, $recipe_id, $error_message ) {

		$action_data['complete_with_errors'] = true;

		Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

		return true;

	}

	/**
	 * Get the response status from Google API response.
	 *
	 * @return array The status.
	 */
	public function get_response_status( $response ) {

		$response_status = json_decode( $response );

		// Google returned a valid response but there was a valid error.
		if ( isset( $response_status->error ) && ! empty( $response_status->error ) ) {
			$error_message = sprintf(
				/* translators: The error code, type, and description */
				esc_html__( 'Error: %1$s | %2$s | %3$s', 'uncanny-automator' ),
				$response_status->statusCode, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$response_status->error->type,
				$response_status->error->description
			);
			return array(
				'error'   => true,
				'message' => $error_message,
			);
		}

		return array(
			'error'   => false,
			'message' => null,
		);

	}

}
