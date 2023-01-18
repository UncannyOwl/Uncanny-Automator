/**
 * Handles some DOM manipulation for ConvertKit's settings page.
 * 
 * @since 1.0.0
 */
class ConvertKit {

    get isAPIKeyValid() {
        return !document.querySelector('#automator_convertkit_api_key').shadowRoot
            .querySelector('.wrapper').classList.contains('error');
    }

    get isAPISecretValid() {
        return !document.querySelector('#automator_convertkit_api_secret').shadowRoot
            .querySelector('.wrapper').classList.contains('error');
    }

    get submitBtn() {
        return document.getElementById('convertKitConnectBtn');
    }

}

/**
 * Start listening to form submission on DomContentLoaded.
 * 
 * @since 1.0.0
 */
document.addEventListener("DOMContentLoaded", function (event) {

    const form = document.getElementById('uaConvertKitSettingsForm');

    form.addEventListener('submit', element => {
        const convertKit = new ConvertKit();
        // Add small delay to make sure error class is appended.
        setTimeout(() => {
            if (convertKit.isAPIKeyValid && convertKit.isAPISecretValid) {
                convertKit.submitBtn.setAttribute('loading', 'true');
            }
        }, 100);

    });

});