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

        fetch( UncannyAutomatorBackend.ajax.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Cache-Control': 'no-cache'
            },
            body: new URLSearchParams({
                action: 'ua_facebook_group_list_groups',
                nonce: UncannyAutomatorBackend.ajax.nonce,
            } )
        })
            .then( ( response ) => response.json() )
			.then( ( response ) => {
                // Check if there are pages
                if ( Array.isArray( response.items ) && response.items.length > 0 ) {
                    // Set pages
                    this.createList(response.items);
                } else {
                    // Check if there is an error defined
                    if (
                        false === response.success
                        && typeof response.message !== 'undefined'
                        && response.message !== ''
                    ) {
                        // Set error
                        this.setError(response.message);
                    }
                }
			} )
			.catch( ( error ) => {
                // Show error
                this.setError(error);
			} )
			.finally( () => {
				// Remove the loading animation.
                this.$preloaderButton.remove();
			} );
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

                        <uo-icon integration="FACEBOOK"></uo-icon>
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
        if ( error ) {
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
