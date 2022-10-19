class AutomatorInstagramSettings {
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
        this.$updateListButton.setAttribute('loading', '');

        // Hide error, if visible
        this.setError('');

        // Fetch data
        _uo.utility.fetchData({
            url: UncannyAutomatorBackend.ajax.url,
            data: {
                action: 'automator_integration_instagram_capture_token_fetch_user_pages',
                nonce: UncannyAutomatorBackend.ajax.nonce
            },
            onSuccess: (response) => {
                // Remove the loading animation frmo the "Update list" button
                this.$updateListButton.removeAttribute('loading');

                // Check if there are pages
                if (!_uo.utility.isEmpty(response.pages)) {
                    // Set pages
                    this.createList(response.pages);
                } else {
                    // Check if there is an error defined
                    if (
                        _uo.utility.isDefined(response.error)
                        && !_uo.utility.isEmpty(response.error_message)
                    ) {
                        // Set error
                        this.setError(response.error_message);
                    }
                }
            },
            onFail: (response, message) => {
                // Remove the loading animation frmo the "Update list" button
                this.$updateListButton.removeAttribute('loading');

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
    createList(pages = []) {
        // Remove the current content
        this.$listWrapper.innerHTML = '';

        // Iterate list
        pages.forEach((page) => {
            // Create Facebook page element
            const $facebookPage = document.createElement('div');
            $facebookPage.classList.add('uap-facebook-account');

            // Add inner elements
            $facebookPage.innerHTML = `
                <div class="uap-facebook-page uap-spacing-top">
                    <div class="uap-instagram-account">
                        <span class="uap-placeholder-text" data-placeholder="Icon"></span> <span class="uap-placeholder-text" data-placeholder="account name"></span> <span class="uap-placeholder-text" data-placeholder="followers"></span>
                    </div>
                    <div class="uap-linked-account">
                        ${UncannyAutomatorBackend.i18n.settingsInstagram.linkedFacebookPage}

                        <a 
                            href="https://facebook.com/${page.value}"
                            target="_blank"
                        >
                            ${page.text}
                        </a>
                    </div>
                </div>
            `;

            // Get the element used to show the Instagram account data
            const $instagramWrapper = $facebookPage.querySelector('.uap-instagram-account');

            // Append page
            this.$listWrapper.insertAdjacentElement(
                'beforeEnd',
                $facebookPage
            );

            // Check if there is an Instagram page connected already
            if (_uo.utility.isDefined(page.ig_account) && _uo.utility.isDefined(page.ig_account.data) && _uo.utility.isDefined(page.ig_account.data[0])) {

                // Get data
                const instagramAccountData = page.ig_account.data[0];

                // Remove current elements
                $instagramWrapper.innerHTML = '';

                // Append Instagram data
                $instagramWrapper
                    .appendChild(
                        this.$createInstagramPill({
                            name: instagramAccountData.username,
                            profilePicture: instagramAccountData.profile_pic,
                            IGConnection: page.ig_connection
                        })
                    );

            } else {

                // Get Instagram page
                this.getInstagramAccount({
                    facebookPageId: page.value,
                    onSuccess: (response) => {

                        // Check if we found an account
                        if (response.statusCode == '200') {

                            // Check if the required data is defined
                            if (
                                _uo.utility.isDefined(response.data)
                                && _uo.utility.isDefined(response.data.data)
                                && _uo.utility.isDefined(response.data.data[0])
                            ) {
                                // Get data
                                const instagramAccountData = response.data.data[0];

                                // Remove current elements
                                $instagramWrapper.innerHTML = '';

                                // Append Instagram data
                                $instagramWrapper
                                    .appendChild(
                                        this.$createInstagramPill({
                                            name: instagramAccountData.username,
                                            profilePicture: instagramAccountData.profile_pic,
                                            IGConnection: instagramAccountData.ig_connection
                                        })
                                    );
                            }

                        } else {
                            // A different status code means that there is no account

                            // Add message
                            $instagramWrapper.innerHTML = `
                                <span class="uap-instagram-account-no-account">
                                    ${UncannyAutomatorBackend.i18n.settingsInstagram.noInstagram}
                                </span>
                            `;

                        }
                    }
                });

            }
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

        } else {
            // Remove the current error, and hide the notice
            this.$errorWrapper.innerHTML = '';
            this.$errorWrapper.style.display = 'none';
        }
    }

    /**
     * Gets the Instagram account of a Facebook page
     * 
     * @param  {Object} options                The options
     * @param  {String} options.facebookPageId The ID of the page
     * @return {undefined}                       
     */
    getInstagramAccount({ facebookPageId = '', onSuccess }) {
        // Fetch data
        _uo.utility.fetchData({
            url: UncannyAutomatorBackend.ajax.url,
            data: {
                action: 'automator_integration_instagram_capture_token_fetch_instagram_accounts',
                nonce: UncannyAutomatorBackend.ajax.nonce,
                page_id: facebookPageId
            },
            onSuccess: (response) => {
                // Try to invoke the callback
                try {
                    onSuccess(response);
                } catch (e) { }
            }
        });
    }

    /**
     * Creates an Instagram pill with data about the account
     * 
     * @param  {Object} account                Account data
     * @param  {String} account.name           The name
     * @param  {String} account.profilePicture The URL of the avatar
     * @return {Node}                          A node with the data
     */
    $createInstagramPill({ name = '', profilePicture = '', IGConnection = {} }) {
        // Create element
        const $instagramAccount = document.createElement('div');

        $instagramAccount.classList.add('uap-instagram-account-pill');

        let $template = `
            <img 
                onerror="this.remove()"
                class="uap-instagram-account-pill-avatar"
                src="${profilePicture}"
            >

            <span class="uap-instagram-account-pill-username">
                ${name}
            </span>

            <uo-icon integration="INSTAGRAM"></uo-icon>
        `;

        // Check if connected.
        if (undefined !== IGConnection.is_connected && false === IGConnection.is_connected) {
            $template += `
                <p class="uap-instagram-error-message">
                    ${IGConnection.message}
                </p>
            `;
        }

        // Add the html.
        $instagramAccount.innerHTML = $template;

        // Return Instagram account
        return $instagramAccount;
    }

    /**
     * Returns the "Update linked pages" button
     * 
     * @return {Node} The button
     */
    get $updateListButton() {
        return document.getElementById('facebook-pages-update-button');
    }

    /**
     * Returns the list wrapper
     * 
     * @return {Node} The wrapper
     */
    get $listWrapper() {
        return document.getElementById('facebook-pages-list');
    }

    /**
     * Returns the wrapper used to show errors
     * 
     * @return {Node} The wrapper
     */
    get $errorWrapper() {
        return document.getElementById('facebook-pages-errors');
    }
}

new AutomatorInstagramSettings();
