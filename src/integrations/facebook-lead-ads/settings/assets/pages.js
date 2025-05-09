/**
 * Makes an HTTP GET request to the API for a specific page ID.
 *
 * @param {string} pageId - The page ID to send the request for.
 * @param {boolean} [bypassCache=false] - Whether to bypass the cache.
 * @param {Function} [callback] - Optional callback to execute after the request.
 * @returns {Promise<object>} - The response data from the API.
 */
const makeApiRequest = async (pageId, bypassCache = false, callback = null) => {
    console.log(`Initiating API request for page_id: ${pageId}`);

    try {
        const force = bypassCache ? 'true' : 'false';
        const apiUrl = `${automator_fbla_config.apiUrl}&page_id=${pageId}&force=${force}&nonce=${fbLeadAdsConfig.nonce}`;

        const response = await fetch(apiUrl, {
            method: "GET",
            headers: {
                "Content-Type": "application/json"
            }
        });

        const data = await response.json();

        if (callback) callback();

        if (!response.ok) {
            throw new Error(`API request failed with status ${response.status}: ${data.message || data.status}`);
        }

        if (data.status === "auth-failed") {
            throw new Error(data.error || "Authentication failed");
        }

        // Update the UI for the specific page ID
        updatePageStatus(pageId, "ready", data.status);

        return data;
    } catch (error) {
        // Update the UI with the error
        updatePageStatus(pageId, "error", error.message);
        console.error(`Error for page_id: ${pageId}`, error);
        throw error; // Re-throw for external error handling
    }
};

/**
 * Updates the status of a page in the UI.
 *
 * @param {string} pageId - The page ID to update.
 * @param {string} statusType - The status type ('ready' or 'error').
 * @param {string} message - The message to display.
 */
const updatePageStatus = (pageId, statusType, message) => {
    const statusElement = document.getElementById(`status-${pageId}`);
    if (statusElement) {
        statusElement.innerHTML = `<span class="status ${statusType}">${message}</span>`;
    } else {
        console.warn(`Status element for page_id: ${pageId} not found.`);
    }
};

/**
 * Processes a list of pages sequentially, making one API request at a time.
 *
 * @param {Array<{page_id: string}>} pageList - An array of objects containing page IDs.
 */
const processPagesSequentially = async (pageList) => {
    console.log("Starting sequential page processing...");

    for (const page of pageList) {
        try {
            await makeApiRequest(page.page_id);
        } catch (error) {
            console.warn(`Skipping page_id: ${page.page_id} due to an error.`);
            // Continue processing other pages despite errors
        }
    }

    console.log("All pages processed.");
};

/**
 * Initiates the page processing workflow.
 */
const startProcessing = () => {
    console.log("Initiating page processing...");
    if (Array.isArray(automator_fbla_config.pages)) {
        processPagesSequentially(automator_fbla_config.pages).catch((error) => {
            console.error("Unexpected error during processing:", error);
        });
    } else {
        console.error("Invalid or missing pages configuration.");
    }
};

/**
 * Adds click event listeners to repair elements to handle API requests.
 */
const setupRepairElements = () => {
    const repairElements = document.querySelectorAll('.uap-fbla-settings-page-fbla-repair');

    if (repairElements.length === 0) {
        console.warn("No elements found with class 'uap-fbla-settings-page-fbla-repair'.");
        return;
    }

    repairElements.forEach((element) => {
        element.addEventListener('click', async () => {
            const pageId = element.getAttribute('data-page-id');
            if (!pageId) {
                console.warn("Element missing 'data-page-id' attribute.");
                return;
            }

            element.setAttribute('loading', true);
            try {
                await makeApiRequest(pageId, true, () => {
                    element.removeAttribute('loading');
                });
            } catch (error) {
                console.error(`Error processing page_id: ${pageId}`, error);
            }
        });
    });
};

/**
 * Entry point for setting up the page processing and event listeners.
 */
document.addEventListener("DOMContentLoaded", () => {
    console.log("DOM fully loaded. Starting setup...");

    // Start sequential processing
    startProcessing();

    // Set up repair element click handlers
    setupRepairElements();
});
