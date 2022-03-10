class AutomatorFacebookSettings {
    constructor() {
        // Retrieve pages on load
        this.retrievePages();
    }

    /**
     * Retrieve the Facebook Pages linked
     * 
     * @return {undefined}
     */
    retrievePages() {
        // Set the loading status of the button
        this.$updateListButton.setAttribute( 'loading', '' );

        // Hide error, if visible
        this.setError( '' );

        // Fetch data
        _uo.utility.fetchData({
            url: UncannyAutomatorBackend.ajax.url,
            data: {
                action: 'automator_integration_facebook_capture_token_fetch_user_pages',
                nonce: UncannyAutomatorBackend.ajax.nonce
            },
            onSuccess: ( response ) => {
                // Remove the loading animation frmo the "Update list" button
                this.$updateListButton.removeAttribute( 'loading' );

                // Check if there are pages
                if ( ! _uo.utility.isEmpty( response.pages ) ) {
                    // Set pages
                    this.createList( response.pages );
                } else {
                    // Check if there is an error defined
                    if (
                        _uo.utility.isDefined( response.error )
                        && ! _uo.utility.isEmpty( response.error_message )
                    ) {
                       // Set error
                       this.setError( response.error_message ); 
                    }
                }
            },
            onFail: ( response, message ) => {
                // Remove the loading animation frmo the "Update list" button
                this.$updateListButton.removeAttribute( 'loading' );

                // Show error
                this.setError( message.error );
            },
        });
    }

    /**
     * Creates the list with the Facebook pages
     * 
     * @param  {Array}  pages An array with the Facebook pages
     * @return {undefined}       
     */
    createList( pages = [] ) {
        // Remove the current content
        this.$listWrapper.innerHTML = '';

        // Iterate list
        pages.forEach( ( page ) => {
            // Append page
            this.$listWrapper.insertAdjacentHTML(
                'beforeEnd',
                `
                    <div class="uap-facebook-page uap-spacing-top">
                        <a 
                            href="https://facebook.com/${ page.value }"
                            target="_blank"
                        >
                            ${ page.text }
                        </a>

                        <uo-icon id="facebook"></uo-icon>
                    </div>
                `
            );
        } );
    }

    /**
     * Displays an error
     * 
     * @param {String} error The error
     */
    setError( error = '' ) {
        // Check if there is an error defined
        if ( ! _uo.utility.isEmpty( error ) ) {

        } else {
            // Remove the current error, and hide the notice
            this.$errorWrapper.innerHTML = '';
            this.$errorWrapper.style.display = 'none';
        }
    }

    /**
     * Returns the "Update linked pages" button
     * 
     * @return {Node} The button
     */
    get $updateListButton() {
        return document.getElementById( 'facebook-pages-update-button' );
    }

    /**
     * Returns the list wrapper
     * 
     * @return {Node} The wrapper
     */
    get $listWrapper() {
        return document.getElementById( 'facebook-pages-list' );
    }

    /**
     * Returns the wrapper used to show errors
     * 
     * @return {Node} The wrapper
     */
    get $errorWrapper() {
        return document.getElementById( 'facebook-pages-errors' );
    }
}

// Create instance
new AutomatorFacebookSettings();
