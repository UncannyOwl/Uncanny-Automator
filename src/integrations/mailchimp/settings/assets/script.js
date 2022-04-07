'use strict';

class AutomatorMailChimpSettings {

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

    }

    /**
     * Converted as a method. Called in _construct().
     * 
     * @return {void}
     */
    triggersEnablingSwitch() {

        // Get the switch element
        const $switch = document.getElementById('uap_mailchimp_enable_webhook');

        // Get the content element
        const $content = document.getElementById('uap-mailchimp-webhook');

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
new AutomatorMailChimpSettings();