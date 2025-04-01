document.addEventListener('DOMContentLoaded', () => {
    const $resultElement = document.getElementById('automatorFblaConnectionResult');
    const connectionCheckBtn = document.getElementById('FBLAConnectionCheckBtn');

    if (!$resultElement || !connectionCheckBtn) {
        console.error("Required DOM elements are missing. Ensure 'automatorFblaConnectionResult' and 'FBLAConnectionCheckBtn' exist.");
        return;
    }

    /**
     * Updates the result element with a styled alert message.
     *
     * @param {string} type - The alert type ('success' or 'warning').
     * @param {string} message - The message to display.
     */
    const updateResultMessage = (type, message) => {
        $resultElement.innerHTML = `
            <uo-alert type="${type}" class="uap-spacing-bottom">
                ${message}
            </uo-alert>`;
    };

    /**
     * Handles the response from the fetch request.
     *
     * @param {Response} response - The fetch response object.
     * @returns {Promise<object>} - Parsed JSON data.
     * @throws {Error} If the response status is not OK.
     */
    const handleResponse = (response) => {
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.json();
    };

    /**
     * Processes the JSON data and updates the UI accordingly.
     *
     * @param {object} data - The JSON response data.
     * @throws {Error} If there are connection errors or authentication issues.
     */
    const processData = (data) => {
        if (data.errors?.connection_error) {
            console.info("Connection Error Data:", data);
            throw new Error(`Connection Error: ${data.errors.connection_error[0]}`);
        }
        if (data.statusCode === 401) {
            console.info("Authentication Error Data:", data);
            throw new Error(data.data.error);
        }

        updateResultMessage(
            'success',
            `Your website has received a webhook, confirming it supports external requests needed for Facebook Lead Ads.`
        );

        console.log("Result element updated successfully:", $resultElement);
    };

    /**
     * Handles errors and displays them in the result element.
     *
     * @param {Error} error - The error object.
     */
    const handleError = (error) => {
        console.error("Error occurred:", error);
        updateResultMessage('warning', error.message);
    };

    /**
     * Event listener for the connection check button click.
     */
    connectionCheckBtn.addEventListener('click', () => {
        console.log("Connection check initiated...");

        // Prepare the POST request data
        const url = `${UncannyAutomatorBackend.ajax.url}?action=facebook_lead_ads_check_connection`;
        const postData = {
            site: UncannyAutomatorBackend.ajax.url,
            nonce: UncannyAutomatorBackend.ajax.nonce,
        };

        // Set the button to a loading state
        connectionCheckBtn.setAttribute('loading', 'true');

        // Perform the fetch request
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(postData),
        })
            .then(handleResponse)
            .then(processData)
            .catch(handleError)
            .finally(() => {
                // Remove the loading state
                connectionCheckBtn.removeAttribute('loading');
                console.log("Connection check completed.");
            });
    });
});
