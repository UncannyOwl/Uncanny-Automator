/**
 * This class is responsible for showing the correct fields based on the selected connection method.
 * It provides methods to get the connection method and the field sections.
 */
class ConnectionMethod {
	/**
	 * Constructor.
	 */
	constructor() {
		// Show the correct section on load
		this.showFields();

		// Listen changes to the connection method field
		this.handleChanges();
	}

	/**
	 * Show the correct fields based on the connection method.
	 */
	showFields() {
		// Hide sections
		for ( let section in this.$fieldSections ) {
			this.$fieldSections[ section ].forEach( $section => {
				$section.style.display = 'none';
			} );
		}

		// Show the selected section
		if ( this.connectionMethod === 'custom-app' ) {
			this.$fieldSections.customApp.forEach( $section => {
				$section.style.display = 'block';
			} );
		}

		if ( this.connectionMethod === 'quick-connect' ) {
			this.$fieldSections.quickConnect.forEach( $section => {
				$section.style.display = 'block';
			} );
		}
	}

	/**
	 * Listen changes to the connection method field. Then show the correct fields.
	 */
	handleChanges() {
		this.$connectionMethodField.forEach( $field => {
			$field.addEventListener( 'change', () => {
				this.showFields();
			}) 
		});
	}

	/**
	 * Get the connection method.
	 * 
	 * @return {String} The connection method.
	 */
	get connectionMethod() {
		return document.querySelector( 'input[name="uap-twitter-connect-method"]:checked' ).value;
	}

	/**
	 * Get the connection method field.
	 */
	get $connectionMethodField() {
		return document.querySelectorAll( 'input[name="uap-twitter-connect-method"]' );
	}

	/**
	 * Get the field sections.
	 * 
	 * @return {Object} The field sections.
	 */
	get $fieldSections() {
		return {
			customApp: document.querySelectorAll( '.uap-settings-panel-content-connection-custom-app' ),
			quickConnect: document.querySelectorAll( '.uap-settings-panel-content-connection-quick-connect' ),
		}
	}
}

new ConnectionMethod();
