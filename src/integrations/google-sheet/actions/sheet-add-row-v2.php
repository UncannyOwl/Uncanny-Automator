<?php
/**
 * @package Uncanny_Automator
 *
 * @since 5.7
 */
namespace Uncanny_Automator;

/**
 * Class SHEET_ADDARECORD
 *
 * @package Uncanny_Automator
 *
 * @since 5.7
 */
class SHEET_ADD_ROW_V2 {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'GOOGLESHEET';

	/**
	 * Property action code.
	 *
	 * @var string
	 */
	private $action_code;

	/**
	 * Property action meta.
	 *
	 * @var string
	 */
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->action_code = 'SHEET_ADD_ROW_V2';
		$this->action_meta = 'SHEET_ADD_ROW_V2_META';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object.
	 *
	 * @return void
	 */
	public function define_action() {

		$action = array(
			'author'                => Automator()->get_author_name( $this->action_code ),
			'support_link'          => Automator()->get_author_support_link( $this->action_code, 'knowledge-base/google-sheets/' ),
			'is_pro'                => false,
			'integration'           => self::$integration,
			'code'                  => $this->action_code,
			/* translators: Action sentence */
			'sentence'              => sprintf( esc_html__( 'Create a row in a {{Google Sheet:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			'select_option_name'    => esc_html__( 'Create a row in a {{Google Sheet}}', 'uncanny-automator' ),
			'priority'              => 10,
			'accepted_args'         => 1,
			'requires_user'         => false,
			'execution_function'    => array( $this, 'add_row_google_sheet' ),
			'options_callback'      => array( $this, 'load_options' ),
			'background_processing' => true,
		);

		Automator()->register->action( $action );
	}

	/**
	 * Method load_options.
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
									"If you don't see your spreadsheet or haven't selected any files yet, please go to the %1\$ssettings page%2\$s to add them.",
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
				),
			),
		);

		return $options;
	}

	/**
	 * Validation function when the action is hit.
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 *
	 * @return void
	 */
	public function add_row_google_sheet( $user_id, $action_data, $recipe_id, $args ) {

		$gs_spreadsheet  = $action_data['meta']['GSSPREADSHEET'];
		$gs_worksheet    = $action_data['meta']['GSWORKSHEET'];
		$worksheet_field = $action_data['meta']['WORKSHEET_FIELDS'];
		$fields          = json_decode( $worksheet_field, true );
		$key_values      = array();
		$check_all_empty = true;

		// Backwards compatibility.
		$gs_worksheet = Google_Sheet_Helpers::calculate_hash( $gs_worksheet );

		// Check if fields is a valid array. PHP 8.0+ throws fatal error for null types when called inside count function.
		$fields_count = is_array( $fields ) ? count( $fields ) : 0;

		for ( $i = 0; $i < $fields_count; $i ++ ) {

			$key = $fields[ $i ]['GS_COLUMN_NAME'];

			$value = Automator()->parse->text( $fields[ $i ]['GS_COLUMN_VALUE'], $recipe_id, $user_id, $args );

			// Allow overwrite.
			if ( true === apply_filters( 'automator_google_sheets_disable_tokens_html', true ) ) {
				$value = wp_strip_all_tags( $value );
			}

			$key_values[ $key ] = $value;

			if ( ! empty( $value ) ) {
				$check_all_empty = false;
			}
		}

		if ( $check_all_empty ) {
			// Log error no heading found.
			$error_msg                           = esc_html__( 'Trying to add an empty row.', 'uncanny-automator' );
			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $error_msg );

			return;
		}

		try {

			$response = Automator()->helpers->recipe->google_sheet->api_append_row( $gs_spreadsheet, $gs_worksheet, $key_values, $action_data );
			Automator()->complete->action( $user_id, $action_data, $recipe_id );

			return;

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

			return;
		}
	}

}
