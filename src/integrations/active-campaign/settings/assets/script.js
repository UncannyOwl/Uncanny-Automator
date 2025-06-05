'use strict';

class AutomatorActiveCampaignSettings {

	constructor() {
		// Start watching for click events, etc.
		this.registerEventListeners();
	}

	/**
	 * Declare all the event listeners in one location.
	 * 
	 * @return {undefined}
	 */
	registerEventListeners() {

		// Register the switch event for Triggers enable/disable.
		this.triggersEnablingSwitch();

		// Register local sync button event handler.
		this.$localSyncBtn.addEventListener('click', () => {
			this.doLocalSync();
		});
	}

	/**
	 * Start local sync.
	 * 
	 * @return {undefined}
	 */
	doLocalSync() {

		this.$localSyncBtn.setAttribute('loading', true);

		fetch( UncannyAutomatorBackend.ajax.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Cache-Control': 'no-cache'
            },
            body: new URLSearchParams({
                action: 'active-campaign-sync-data',
                nonce: UncannyAutomatorBackend.ajax.nonce,
            } )
        })
            .then( ( response ) => response.json() )
			.then( ( response ) => {
				this.$syncContainer.innerHTML = response.messages;
			} )
			.catch( ( error ) => {
				console.warn(error);
			} )
			.finally( () => {
				this.$localSyncBtn.removeAttribute('loading');
			} );
	}

	get $syncAlert() {
		return document.getElementById('active-campaign-local-sync-alert');
	}

	get $syncContainer() {
		return document.getElementById('active-campaign-local-sync-p');
	}

	get $localSyncBtn() {
		return document.getElementById('active-campaign-local-syn-btn');
	}

	/**
	 * Converted as a method. Called in _construct().
	 * 
	 * @return {void}
	 */
	triggersEnablingSwitch() {

		// Get the switch element
		const $switch = document.getElementById('uap_active_campaign_enable_webhook');

		// Get the content element
		const $content = document.getElementById('uap-activecampaign-webhook');

		/**
		 * Sets the visibility of the content
		 * 
		 * @return {undefined}
		 */
		const setContentVisibility = () => {

			// Check if it's enabled
			if ($switch.checked) {
				// Show
				$content.style.display = 'block';
			} else {
				// Hide
				$content.style.display = 'none';
			}
		}

		// Evaluate on load
		setContentVisibility();

		// Evaluate when the value of the switch changes
		$switch.addEventListener('change', () => {
			// Evaluate the visibility
			setContentVisibility();
		});
	}


}

// Create instance
new AutomatorActiveCampaignSettings();