class AutomatorSlackSettings {
	constructor() {
		// Set default name
		this.defaultName = 'Uncanny Automator';
		this.defaultIcon = document.getElementById( 'uap-slack-preview-generator' ).dataset.icon;

		// Listen fields
		this.listenFields();
	}

	/**
	 * Listens to the fields to update the previews
	 * 
	 * @return {undefined}
	 */
	listenFields() {
		// Check if the "Name" field exists
		if ( typeof this.$inputName !== 'undefined' && this.$inputName ) {
			// Listen the "Name" input
			this.$inputName.addEventListener( 'input', event => {
				this.setPreviews( event );
			} );
		}

		// Check if the "Name" field exists
		if ( typeof this.$inputIcon !== 'undefined' && this.$inputIcon ) {
			// Listen the "Icon" input
			this.$inputIcon.addEventListener( 'input', event => {
				this.setPreviews( event );
			} );
		}
	}

	/**
	 * Returns the "Bot name" field
	 * 
	 * @return {Node} The field
	 */
	get $inputName() {
		return document.getElementById( 'uap_automator_slack_api_bot_name' );
	}

	/**
	 * Returns the "Bot icon" field
	 * 
	 * @return {Node} The field
	 */
	get $inputIcon() {
		return document.getElementById( 'uap_automator_alck_api_bot_icon' );
	}

	/**
	 * Sets the previews content
	 */
	setPreviews( event ) {
		// Get the values
		const config = {
			name: this.$inputName.value,
			icon: this.$inputIcon.value,
		}

		// Default name
		config.name = typeof config.name !== 'undefined' && config.name !== '' ? config.name : this.defaultName;

		// Default icon
		config.icon = typeof config.icon !== 'undefined' && config.icon !== '' ? config.icon : this.defaultIcon;

		// Set the name
		this.$lightModeName.innerText = config.name;
		this.$darkModeName.innerText = config.name;

		// Set the icon
		this.$lightModeIcon.src = config.icon;
		this.$darkModeIcon.src = config.icon;
	}

	/**
	 * Returns the light mode icon
	 * 
	 * @return {Node} The icon
	 */
	get $lightModeIcon() {
		return document.getElementById( 'uap-slack-preview-light-icon' );
	}

	/**
	 * Returns the dark mode icon
	 * 
	 * @return {Node} The icon
	 */
	get $darkModeIcon() {
		return document.getElementById( 'uap-slack-preview-dark-icon' );
	}

	/**
	 * Returns the light mode name
	 * 
	 * @return {Node} The name
	 */
	get $lightModeName() {
		return document.getElementById( 'uap-slack-preview-light-name' );
	}

	/**
	 * Returns the dark mode name
	 * 
	 * @return {Node} The name
	 */
	get $darkModeName() {
		return document.getElementById( 'uap-slack-preview-dark-name' );
	}
}

new AutomatorSlackSettings();
