/**
 * Google Sheets File Picker JavaScript
 * 
 * This script handles the Google Drive file picker functionality for selecting
 * Google Sheets spreadsheets. It uses the unified REST endpoint instead of
 * traditional AJAX handlers.
 * 
 * Flow:
 * 1. User clicks "Select new sheet(s)" button
 * 2. Script calls REST endpoint to get Google API access token
 * 3. Google Picker opens with the access token
 * 4. User selects spreadsheets
 * 5. Script calls REST endpoint to save selected spreadsheets
 * 6. Page refreshes to show updated spreadsheet list
 * 
 * REST Endpoint: /wp-json/uap/v2/integration-settings/google-sheet
 * Actions: 'file_picker_auth', 'file_picker'
 * 
 * @package Uncanny_Automator\Integrations\Google_Sheet
 * @since 5.0
 */

/**
 * Determines whether the picker is initiated already or not.
 */
let pickerInited = false;

/**
 * Callback after api.js is loaded.
 */
function gapiLoaded() {
	gapi.load('client:picker', initializePicker);
}

/**
 * Callback after the API client is loaded.
 */
async function initializePicker() {
	await gapi.client.load('https://www.googleapis.com/discovery/v1/apis/drive/v3/rest');
	pickerInited = true;
}

/**
 * Create a file picker button.
 */
async function createFilePickerButton() {
	await createPicker();
}

/**
 * Create and render a Picker object for searching Spreadsheets.
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
 * Show error message.
 */
function showError( errorMessage ){
	document.querySelector('#filePickerErrorContainer').style.display = 'block';
	document.querySelector('#filePickerErrorContainer > uo-alert').innerHTML = errorMessage;
}

/**
 * Hide errors.
 */
function hideError() {
	document.querySelector('#filePickerErrorContainer').style.display = 'none';
}

/**
 * Initializes a file picker event.
 */
async function createPicker() {
	document.getElementById('filePickerBtnComponent').setAttribute('loading', true);

	try {
		const result = await wp.apiFetch( {
			path: '/uap/v2/integration-settings/google-sheet',
			method: 'POST',
			data: {
				action: 'file_picker_auth',
				data: {}
			}
		} );

		console.log('File picker auth result:', result);

		if ( result.data ) {
			createFilePickerFromAuth(result.data.access_token);
		} else if ( result.error ) {
			showError(result.error);
		} else {
			showError('Unexpected response format: ' + JSON.stringify(result));
		}
	} catch (error) {
		console.error('File picker auth error:', error);
		showError( JSON.stringify(error));
	}

	document.getElementById('filePickerBtnComponent').removeAttribute('loading');
}

/**
 * Displays the file details of the user's selection.
 */
async function pickerCallback(data) {
	if (data.action === google.picker.Action.PICKED) {
		const documents = data[google.picker.Response.DOCUMENTS];

		try {
			const result = await wp.apiFetch( {
				path: '/uap/v2/integration-settings/google-sheet',
				method: 'POST',
				data: {
					action: 'file_picker',
					data: {
						spreadsheets: documents
					}
				}
			} );

			if ( result.success ) {
				// Refresh the page to show the new spreadsheets
				location.reload();
			} else if ( result.error ) {
				showError(result.error);
			}
		} catch (error) {
			showError( JSON.stringify(error));
		}
	}

	if ( data.action === 'cancel') {
		document.getElementById('filePickerBtnComponent').removeAttribute('loading');
	}
}
