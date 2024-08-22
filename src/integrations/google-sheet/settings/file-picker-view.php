<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$this->load_css( '/google-sheet/settings/assets/style.css' );

use Uncanny_Automator\Google_Sheet_Helpers;

if ( $this->client ) { ?>

	<?php $nonce = wp_create_nonce( 'automator_google_sheet_remove_spreadsheet' ); ?>

<h5 class="uap-spacing-top">
	<?php echo esc_html_x( 'Spreadsheets', 'Google Sheets', 'uncanny-automator' ); ?>
</h5>

<p>
	<?php echo esc_html_x( 'The following spreadsheets are available for selection in the recipe editor:', 'Google Sheets', 'uncanny-automator' ); ?>
</p>
<div id="spreadsheetContainerWrap">
	<?php $spreadsheets = Google_Sheet_Helpers::get_spreadsheets(); ?>
	<?php if ( ! empty( $spreadsheets ) ) { ?> 
		<table id="spreadSheetTable">
			<tbody>
				<?php foreach ( $spreadsheets as $spreadsheet ) { ?>
					<?php if ( ! isset( $spreadsheet['id'] ) ) { ?>
						<?php continue; ?>
					<?php } ?>
					<tr class="item">
					<?php $remove_url = admin_url( "admin-ajax.php?nonce={$nonce}&action=automator_google_sheet_remove_spreadsheet&id={$spreadsheet['id']}" ); ?>
					<?php if ( isset( $spreadsheet['name'] ) ) { ?>
						<td>
							<?php echo esc_html( $spreadsheet['name'] ); ?>
						</td>
						<td>
							<uo-button 
								class="uap-logs-action-button uap-logs-action-button--delete" 
								size="small" 
								color="transparent" 
								href="<?php echo esc_url( $remove_url ); ?>" 
								uap-tooltip="Remove" 
								needs-confirmation="" 
								confirmation-heading="This action is irreversible" 
								confirmation-content="Are you sure you want to remove this spreadsheet?" 
								confirmation-button-label="Confirm">
								<uo-icon id="trash"></uo-icon>
							</uo-button>
						</td>
					<?php } ?>
					</tr>
				<?php } ?>
			</tbody>
		</table>
	<?php } else { ?>
		<uo-alert type="info" heading="<?php echo esc_attr_x( 'There are currently no spreadsheets selected', 'Google Sheets', 'uncanny-automator' ); ?>">
			<?php echo esc_attr_x( 'Click the button below to select sheets', 'Google Sheets', 'uncanny-automator' ); ?>
		</uo-alert>
	<?php } ?>
</div>

<div id="filePickerErrorContainer" class="uap-spacing-top" style="display: none">
	<uo-alert heading="<?php echo esc_html_x( 'An error occurred while authorizing the request to use File selection feature.', 'Google Sheets', 'uncanny-automator' ); ?>" type="error">
	</uo-alert>
</div>

<div class="uap-spacing-top" id="filePickerBtn">

	<uo-button 
		uap-tooltip="Hold down the Shift key to select multiple sheets, or use Ctrl (Cmd on Mac) to select specific sheets." 
		id="filePickerBtnComponent" 
		onclick="createFilePickerButton();"
		color="secondary">
		<?php echo esc_html_x( 'Select new sheet(s)', 'Google Sheets', 'uncanny-automator' ); ?>
	</uo-button>
	
</div>

<script>
/**
 * The delete placeholder.
 */
let deleteUrlPlaceHolder = '<?php echo admin_url( "admin-ajax.php?nonce={$nonce}&action=automator_google_sheet_remove_spreadsheet&id={{__ID__}}" ); ?>';

/**
 * Determines whether the picker is initiated already or not.
 * 
 * @var bool
 */
let pickerInited = false;

/**
 * Callback after api.js <https://apis.google.com/js/api.js> is loaded.
 * 
 * @return void
 */
function gapiLoaded() {
	gapi.load('client:picker', initializePicker);
}

/**
 * Callback after the API client is loaded. Loads the discovery doc to initialize the API.
 * 
 * @return void
 */
async function initializePicker() {
	await gapi.client.load('https://www.googleapis.com/discovery/v1/apis/drive/v3/rest');
	pickerInited = true;
}

/**
 * Create a file picker button.
 *
 * @return void
 */
async function createFilePickerButton() {
	await createPicker();
}

/**
 * Sends POST request method.
 * 
 * @return void
 */
async function sendPostRequest(url, data, callback) {

	document.getElementById('filePickerBtnComponent').setAttribute('loading', true);

	try {

		const response = await fetch(url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
			},
			body: JSON.stringify(data)
		});

		if (!response.ok) {

			console.log( JSON.stringify(response));
			document.getElementById('spreadsheetContainerWrap').innerHTML = 'An error has occured while saving the spreadsheet. Status: ' + response.status

			return;

		}

		const responseData = await response.json();

		callback( responseData );

	} catch (error) {

		console.error('Error:', error);

	}

	document.getElementById('filePickerBtnComponent').removeAttribute('loading');

}

/**
 * Create and render a Picker object for searching Spreadsheets.
 * 
 * @return void
 */
function createFilePickerFromAuth( auth ) {

	const view = new google.picker.View(google.picker.ViewId.DOCS);

	view.setMimeTypes('application/vnd.google-apps.spreadsheet');

	const picker = new google.picker.PickerBuilder()
		.enableFeature(google.picker.Feature.MULTISELECT_ENABLED)
		.enableFeature(google.picker.Feature.NAV_HIDDEN)
		.setOAuthToken(auth)
		.addView(view)
		.addView(new google.picker.DocsUploadView())
		.setCallback(pickerCallback)
		.build();

	picker.setVisible(true);

}

/**
 * Show some error message.
 * 
 * @return void
 */
function showError( errorMessage ){
	document.querySelector('#filePickerErrorContainer').style.display = 'block';
	document.querySelector('#filePickerErrorContainer > uo-alert').innerHTML = errorMessage;
}

/**
 * Hide errors.
 *
 * @return void
 */
function hideError() {
	document.querySelector('#filePickerErrorContainer').style.display = 'none';
}

/**
 * Initializes a file picker event.
 *
 * @return void
 */
function createPicker() {

	document.getElementById('filePickerBtnComponent').setAttribute('loading', true);

	// URL endpoint to send the POST request to.
	const url = '<?php echo esc_url( 'admin-ajax.php?action=automator_googlesheets_file_picker_auth' ); ?>';

	// Data to be sent in the request body.
	const data = {
		nonce: '<?php echo esc_js( wp_create_nonce( 'automator_file_picker_create_picker' ) ); ?>',
	};

	// Configuration for the fetch request.
	const options = {
		method: 'POST',
		headers: {
			'Content-Type': 'application/json',
		},
		body: JSON.stringify(data) // Convert data to JSON string
	};

	// Make the POST request
	fetch(url, options)
		.then(response => {
			// Parse response body as JSON regardless of response status
			return response.json()
			.then(data => {
				if (!response.ok) {
					// If response is not OK, throw an error with the response data
					throw data;
				}
				// If response is OK, return the response data
				return data;
			});
		})
		.then(data => {
			// Handle successful response data here.
			createFilePickerFromAuth(data.data.access_token);
			document.getElementById('filePickerBtnComponent').removeAttribute('loading');
		})
		.catch(error => {
			// Handle errors here
			showError( JSON.stringify(error));
			document.getElementById('filePickerBtnComponent').removeAttribute('loading');
		});
}

/**
 * Displays the file details of the user's selection.
 * 
 * @param {object} data - Containers the user selection from the picker
 */
async function pickerCallback(data) {

	if (data.action === google.picker.Action.PICKED) {

		const documents = data[google.picker.Response.DOCUMENTS];
		const url = '<?php echo esc_url( admin_url( 'admin-ajax.php?action=automator_handle_file_picker' ) ); ?>';
		const nonce = '<?php echo esc_html( wp_create_nonce( 'automator_google_file_picker' ) ); ?>';

		sendPostRequest(url, {
			'spreadsheets': documents,
			'nonce': nonce
		}, ( response ) => {

			let spreadSheetsHtmlItems = '';
			
			response.data.forEach( item=>{
				
				let itemRemoveUrl = deleteUrlPlaceHolder.replaceAll( '{{__ID__}}', item.id );

				spreadSheetsHtmlItems += `
						<tr class="item">
							<td>
								${item.name}
							</td>
							<td>
								<uo-button 
									class="uap-logs-action-button uap-logs-action-button--delete" 
									size="small" 
									color="transparent" 
									href="${itemRemoveUrl}" 
									uap-tooltip="Remove" 
									needs-confirmation="" 
									confirmation-heading="This action is irreversible" 
									confirmation-content="Are you sure you want to remove this spreadsheet?" 
									confirmation-button-label="Confirm">
									<uo-icon id="trash"></uo-icon>
								</uo-button
							</td>
						</tr>
					`;
			});

			document.getElementById('spreadsheetContainerWrap').innerHTML=`<table id="spreadSheetTable"><tbody>${spreadSheetsHtmlItems}</tbody></table>`;
		});

	}

	if ( data.action === 'cancel') {
		document.getElementById('filePickerBtnComponent').removeAttribute('loading');
	}

}

</script>

<script async defer src="https://apis.google.com/js/api.js" onload="gapiLoaded()"></script><?php //phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript ?>
<?php } // Endif. ?>
