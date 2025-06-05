class AutomatorSendySettings {
    constructor() {
        // Register Event Listeners.
		this.registerEventListeners();
    }

	/**
     * Register Transient Sync event handlers.
     *
     * @return {void}
     */
	registerEventListeners() {

		// listen for click events on buttons with class 'uap-sendy-transient-sync-refresh'
		document.querySelectorAll('.uap-sendy-transient-sync-refresh').forEach(($button) => {
			$button.addEventListener('click', () => {
				this.doTransientSync($button);
			});
		});
	}

	/**
	 * Start local sync.
	 *
	 * @param {HTMLElement} $button
	 * @return {void}
	 */
	doTransientSync( $button ) {

		$button.setAttribute('loading', true);
		const $wrapper = $button.closest('.uap-sendy-transient-sync-wrapper');
		const $count = $wrapper.querySelector('.uap-sendy-sync-items-count');
		const key = $button.dataset.key;

		fetch( UncannyAutomatorBackend.ajax.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Cache-Control': 'no-cache'
            },
            body: new URLSearchParams({
                action: 'automator_sendy_sync_transient_data',
                nonce: UncannyAutomatorBackend.ajax.nonce,
				key: key,
            } )
        })
            .then( ( response ) => response.json() )
			.then( ( response ) => {
				console.info(response);
				$count.innerHTML = response?.data?.count;
			} )
			.catch( ( error ) => {
				console.warn(error);
			} )
			.finally( () => {
				$button.removeAttribute('loading');
			} );
	}
}

new AutomatorSendySettings();
