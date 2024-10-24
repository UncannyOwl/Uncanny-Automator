/**
 * This class is responsible for showing the correct fields based on the selected connection method.
 * It provides methods to get the connection method and the field sections.
 */
class stripeModeSelector {
	/**
	 * Constructor.
	 */
	constructor() {

		this.liveModeConnectButton = document.getElementById( 'uap-stripe-connect-live-button' );
		this.testModeConnectButton = document.getElementById( 'uap-stripe-connect-test-button' );

		// Show the correct section on load
		this.showButton();

		// Listen changes to the connection method field
		this.handleChanges();
	}

	/**
	 * Show the correct fields based on the connection method.
	 */
	showButton() {
		// Show the selected section
		if ( ! this.stripeMode ) {
			this.testModeConnectButton.style.display = 'none';
			this.liveModeConnectButton.style.display = 'block';
		} else {
			this.liveModeConnectButton.style.display = 'none';
			this.testModeConnectButton.style.display = 'block';
		}
	}

	/**
	 * Listen changes to the connection method field. Then show the correct fields.
	 */
	handleChanges() {
		this.stripeModeField.addEventListener( 'change', () => {
				this.showButton();
			});
	}

	/**
	 * Get the connection method.
	 * 
	 * @return {String} The connection method.
	 */
	get stripeMode() {
		return this.stripeModeField.checked;
	}

	/**
	 * Get the connection method field.
	 */
	get stripeModeField() {
		return document.getElementById( 'uap_stripe_mode' );
	}
}

new stripeModeSelector();
