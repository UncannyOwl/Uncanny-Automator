<?php

namespace Uncanny_Automator;

/**
 * Class SHEET_ADDARECORD
 *
 * @package Uncanny_Automator
 */
class SHEET_ADDARECORD {

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
	 */
	public function __construct() {
		$this->action_code = 'GOOGLESHEETADDRECORD';
		$this->action_meta = 'GOOGLESHEETROW';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'knowledge-base/google-sheets/' ),
			'is_pro'             => false,
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Action sentence */
			'sentence'           => sprintf( __( 'Create a row in a {{Google Sheet:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			'select_option_name' => __( 'Create a row in a {{Google Sheet}}', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'requires_user'      => false,
			'execution_function' => array( $this, 'add_row_google_sheet' ),
			'options_callback'   => array( $this, 'load_options' ),
			'buttons'            => array(
				array(
					'show_in'     => $this->action_meta,
					'text'        => __( 'Get columns', 'uncanny-automator' ),
					'css_classes' => 'uap-btn uap-btn--red',
					'on_click'    => $this->get_samples_js(),
					'modules'     => array( 'modal', 'markdown' ),
				),
			),
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
					Automator()->helpers->recipe->google_sheet->options->get_google_drives(
						__( 'Drive', 'uncanny-automator' ),
						'GSDRIVE',
						array(
							'is_ajax'      => true,
							'target_field' => 'GSSPREADSHEET',
							'endpoint'     => 'select_gsspreadsheet_from_gsdrive',
						)
					),
					Automator()->helpers->recipe->google_sheet->get_google_spreadsheets(
						__( 'Spreadsheet', 'uncanny-automator' ),
						'GSSPREADSHEET',
						array(
							'is_ajax'      => true,
							'target_field' => 'GSWORKSHEET',
							'endpoint'     => 'select_gsworksheet_from_gsspreadsheet',
						)
					),
					Automator()->helpers->recipe->google_sheet->get_google_worksheets(
						__( 'Worksheet', 'uncanny-automator' ),
						'GSWORKSHEET',
						array(
							'is_ajax' => true,
						)
					),
					array(
						'option_code'       => 'WORKSHEET_FIELDS',
						'input_type'        => 'repeater',
						'label'             => __( 'Row', 'uncanny-automator' ),
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
								'label'       => __( 'Column', 'uncanny-automator' ),
								'input_type'  => 'text',
								'required'    => true,
								'read_only'   => true,
								'options'     => array(),
							),
							Automator()->helpers->recipe->field->text_field( 'GS_COLUMN_VALUE', __( 'Value', 'uncanny-automator' ), true, 'text', '', false ),
						),
						'add_row_button'    => __( 'Add pair', 'uncanny-automator' ),
						'remove_row_button' => __( 'Remove pair', 'uncanny-automator' ),
						'hide_actions'      => true,
					),
				),
			),
		);

		return $options;
	}

	/**
	 * Anonymous JS function invoked as callback when clicking
	 * the custom button "Send test". The JS function requires
	 * the JS module "modal". Make sure it's included in
	 * the "modules" array
	 *
	 * @return string The JS code, with or without the <script> tags
	 */
	public function get_samples_js() {
		// Start output
		ob_start();

		// It's optional to add the <script> tags
		// This must have only one anonymous function
		?>

		<script>

			// Do when the user clicks on send test
			function ($button, data, modules) {

				// Create a configuration object
				let config = {
					// In milliseconds, the time between each call
					timeBetweenCalls: 1 * 1000,
					// In milliseconds, the time we're going to check for samples
					checkingTime: 60 * 1000,
					// Links
					links: {
						noResultsSupport: 'https://automatorplugin.com/knowledge-base/google-sheets/'
					},
					// i18n
					i18n: {
						checkingHooks: "<?php /* translators: Columns */ printf( esc_html__( "We're checking for columns. We'll keep trying for %s seconds.", 'uncanny-automator' ), '{{time}}' ); ?>",
						noResultsTrouble: "<?php esc_html_e( 'We had trouble finding columns.', 'uncanny-automator' ); ?>",
						noResultsSupport: "<?php esc_html_e( 'See more details or get help', 'uncanny-automator' ); ?>",
						samplesModalTitle: "<?php esc_html_e( "Here is the data we've collected", 'uncanny-automator' ); ?>",
						samplesModalWarning: "<?php /* translators: 1. Button */ printf( esc_html__( 'Clicking on \"%1$s\" will remove your current fields and will use the ones on the table above instead.', 'uncanny-automator' ), '{{confirmButton}}' ); ?>",
						samplesTableValueType: "<?php esc_html_e( 'Value type', 'uncanny-automator' ); ?>",
						samplesTableReceivedData: "<?php esc_html_e( 'Received data', 'uncanny-automator' ); ?>",
						samplesModalButtonConfirm: "<?php /* translators: Non-personal infinitive verb */ esc_html_e( 'Use these fields', 'uncanny-automator' ); ?>",
						samplesModalButtonCancel: "<?php /* translators: Non-personal infinitive verb */ esc_html_e( 'Do nothing', 'uncanny-automator' ); ?>",
					}
				}

				// Create the variable we're going to use to know if we have to keep doing calls
				let foundResults = false;

				// Get the date when this function started
				let startDate = new Date();

				// Create array with the data we're going to send
				let dataToBeSent = {
					action: 'get_worksheet_ROWS_GOOGLESHEETS',
					nonce: UncannyAutomator.nonce,

					recipe_id: UncannyAutomator.recipe.id,
					item_id: data.item.id,
					drive: data.values.GSDRIVE,
					sheet: data.values.GSSPREADSHEET,
					worksheet: data.values.GSWORKSHEET
				};

				// Add notice to the item
				// Create notice
				let $notice = jQuery('<div/>', {
					'class': 'item-options__notice item-options__notice--warning'
				});

				// Add notice message
				$notice.html(config.i18n.checkingHooks.replace('{{time}}', parseInt(config.checkingTime / 1000)));

				// Get the notices container
				let $noticesContainer = jQuery('.item[data-id="' + data.item.id + '"] .item-options__notices');

				// Add notice
				$noticesContainer.html($notice);

				// Create the function we're going to use recursively to
				// do check for the samples
				var getSamples = function () {
					// Do AJAX call
					jQuery.ajax({
						method: 'POST',
						dataType: 'json',
						url: ajaxurl,
						data: dataToBeSent,

						// Set the checking time as the timeout
						timeout: config.checkingTime,

						success: function (response) {
							// Get new date
							let currentDate = new Date();

							// Define the default value of foundResults
							let foundResults = false;

							// Check if the response was successful
							if (response.success) {
								// Check if we got the rows from a sample
								if (response.samples.length > 0) {
									// Update foundResults
									foundResults = true;
								}
							}

							// Check if we have to do another call
							let shouldDoAnotherCall = false;

							// First, check if we don't have results
							if (!foundResults) {
								// Check if we still have time left
								if ((currentDate.getTime() - startDate.getTime()) <= config.checkingTime) {
									// Update result
									shouldDoAnotherCall = true;
								}
							}

							if (shouldDoAnotherCall) {
								// Wait and do another call
								setTimeout(function () {
									// Invoke this function again
									getSamples();
								}, config.timeBetweenCalls);
							} else {
								// Add loading animation to the button
								$button.removeClass('uap-btn--loading uap-btn--disabled');
								// Iterate samples and create an array with the rows
								let rows = [];
								let keys = {}
								jQuery.each(response.samples, function (index, sample) {
									// Iterate keys
									jQuery.each(sample, function (index, row) {
										// Check if the we already added this key
										if (typeof keys[row.key] !== 'undefined') {
											// Then just append the value
											// rows[ keys[ row.key ] ].data = rows[ keys[ row.key ] ].data + ', ' + row.data;
										} else {
											// Add row and save the index
											keys[row.key] = rows.push(row);
										}
									});
								});
								// Get the field with the fields (WEBHOOK_DATA)

								let worksheetFields = data.item.options.GOOGLESHEETROW.fields[3];

								// Remove all the current fields
								worksheetFields.fieldRows = [];

								// Add new rows. Iterate rows from the sample
								jQuery.each(rows, function (index, row) {
									// Add row
									worksheetFields.addRow({
										GS_COLUMN_NAME: row.key
									}, false);
								});

								// Render again
								worksheetFields.reRender();

								// Check if it has results
								if (foundResults) {
									// Remove notice
									$notice.remove();

								} else {
									// Change the notice type
									$notice.removeClass('item-options__notice--warning').addClass('item-options__notice--error');

									// Create a new notice message
									let noticeMessage = config.i18n.noResultsTrouble;

									// Change the notice message
									$notice.html(noticeMessage + ' ');

									// Add help link
									let $noticeHelpLink = jQuery('<a/>', {
										target: '_blank',
										href: config.links.noResultsSupport
									}).text(config.i18n.noResultsSupport);
									$notice.append($noticeHelpLink);
								}
							}
						},

						statusCode: {
							403: function () {
								location.reload();
							}
						},

						fail: function (response) {
						}
					});
				}

				// Add loading animation to the button
				$button.addClass('uap-btn--loading uap-btn--disabled');

				// Try to get samples
				getSamples();
			}

		</script>

		<?php

		// Get output
		$output = ob_get_clean();

		// Return output
		return $output;
	}

	/**
	 * Validation function when the action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 */
	public function add_row_google_sheet( $user_id, $action_data, $recipe_id, $args ) {

		$gs_drive        = $action_data['meta']['GSDRIVE'];
		$gs_spreadsheet  = $action_data['meta']['GSSPREADSHEET'];
		$gs_worksheet    = $action_data['meta']['GSWORKSHEET'];
		$worksheet_field = $action_data['meta']['WORKSHEET_FIELDS'];
		$fields          = json_decode( $worksheet_field, true );
		$key_values      = array();
		$check_all_empty = true;
		$hashed          = sha1( Google_Sheet_Helpers::$hash_string );
		$sheet_id        = substr( $hashed, 0, 9 );

		if ( (string) $gs_worksheet === (string) $sheet_id || intval( '-1' ) === intval( $gs_worksheet ) ) {
			$gs_worksheet = 0;
		}

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
			// log error no heading found.
			$error_msg                           = __( 'Trying to add an empty row.', 'uncanny-automator' );
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_msg );

			return;
		}

		try {
			$response = Automator()->helpers->recipe->google_sheet->api_append_row( $gs_spreadsheet, $gs_worksheet, $key_values, $action_data );

			Automator()->complete_action( $user_id, $action_data, $recipe_id );

			return;

		} catch ( \Exception $e ) {
			$error_msg                           = $e->getMessage();
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_msg );

			return;
		}//end try
	}

}
