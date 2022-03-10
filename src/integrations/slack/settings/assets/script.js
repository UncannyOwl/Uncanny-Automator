class AutomatorSlackSettings {
	constructor() {
		// Set default name
		this.defaultName = 'Uncanny Automator';

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
		if ( _uo.utility.isDefined( this.$inputName ) ) {
			// Listen the "Name" input
			this.$inputName.addEventListener( 'change', _uo.utility.debounce( () => {
				// Update avatar
				this.setPreviews();
			}, 100 ) );
		}

		// Check if the "Name" field exists
		if ( _uo.utility.isDefined( this.$inputIcon ) ) {
			// Listen the "Icon" input
			this.$inputIcon.addEventListener( 'change', _uo.utility.debounce( () => {
				// Update avatar
				this.setPreviews();
			}, 300 ) );
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
	 * @param {Object} options      The options
	 * @param {String} options.icon The icon URL
	 * @param {String} options.name The name URL
	 * @return {undefined}
	 */
	setPreviews() {
		// Get the values
		const config = {
			name: this.$inputName.value,
			icon: this.$inputIcon.value,
		}

		// Default name
		config.name = ! _uo.utility.isEmpty( config.name ) ? config.name : this.defaultName;

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
