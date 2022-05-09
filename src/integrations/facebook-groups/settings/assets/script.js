class AutomatorFacebookGroupSettings {
    constructor() {
        // Retrieve groups on load
        this.retrieveGroups();
    }

    /**
     * Retrieve the Facebook Pages linked
     * 
     * @return {undefined}
     */
    retrieveGroups() {
        // Set the loading status of the button
        this.$preloaderButton.setAttribute('loading', '');

        // Fetch data
        _uo.utility.fetchData({
            url: UncannyAutomatorBackend.ajax.url,
            data: {
                action: 'ua_facebook_group_list_groups',
                nonce: UncannyAutomatorBackend.ajax.nonce
            },
            onSuccess: (response) => {
                // Remove the loading animation.
                this.$preloaderButton.remove();
                // Check if there are pages
                if (!_uo.utility.isEmpty(response.items)) {
                    // Set pages
                    this.createList(response.items);
                } else {
                    // Check if there is an error defined
                    if (
                        false === response.success
                        && !_uo.utility.isEmpty(response.message)
                    ) {
                        // Set error
                        this.setError(response.message);
                    }
                }
            },
            onFail: (response, message) => {
                console.info(message);
                // Remove the loading animation.
                if (this.$preloaderButton) {
                    this.$preloaderButton.remove();
                }
                // Show error
                this.setError(message.error);
            },
        });
    }

    /**
     * Creates the list with the Facebook pages
     * 
     * @param  {Array}  pages An array with the Facebook pages
     * @return {undefined}       
     */
    createList(groups = []) {
        // Remove the current content
        this.$listWrapper.innerHTML = '';
        // Iterate list
        groups.forEach((group) => {
            // Append page
            this.$listWrapper.insertAdjacentHTML(
                'beforeEnd',
                `
                    <div class="uap-facebook-group uap-spacing-top">
                        <a 
                            href="https://facebook.com/${group.id}"
                            target="_blank"
                        >
                            ${group.text}
                        </a>

                        <uo-icon id="facebook"></uo-icon>
                    </div>
                `
            );
        });
    }

    /**
     * Displays an error
     * 
     * @param {String} error The error
     */
    setError(error = '') {

        // Check if there is an error defined
        if (!_uo.utility.isEmpty(error)) {
            this.$errorWrapper.innerHTML = error;
            this.$errorWrapper.style.display = 'block';
        } else {
            // Remove the current error, and hide the notice
            this.$errorWrapper.innerHTML = '';
            this.$errorWrapper.style.display = 'none';
        }
    }

    /**
     * Returns the pre-loader button
     * 
     * @return {Node} The button
     */
    get $preloaderButton() {
        return document.getElementById('facebook-groups-preloader');
    }

    /**
     * Returns the list wrapper
     * 
     * @return {Node} The wrapper
     */
    get $listWrapper() {
        return document.getElementById('facebook-groups-list');
    }

    /**
     * Returns the wrapper used to show errors
     * 
     * @return {Node} The wrapper
     */
    get $errorWrapper() {
        return document.getElementById('facebook-groups-errors');
    }
}

// Create instance
new AutomatorFacebookGroupSettings();
