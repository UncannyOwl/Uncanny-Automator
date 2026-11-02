/* Keep the native POST and one-time consent checks on the server. */
(() => {
	const form = document.querySelector('.consent-footer');
	if (!form) {
		return;
	}

	const buttons = form.querySelectorAll('button[type="submit"]');
	let decision;
	form.addEventListener('submit', (event) => {
		if (decision) {
			event.preventDefault();
			return;
		}

		const button = event.submitter;
		if (!button || button.name !== 'decision') {
			return;
		}

		// Disabled buttons are omitted from the POST. Preserve the clicked decision.
		decision = document.createElement('input');
		decision.type = 'hidden';
		decision.name = button.name;
		decision.value = button.value;
		form.append(decision);
		button.setAttribute('aria-busy', 'true');
		buttons.forEach((item) => { item.disabled = true; });
	});

	// A restored page must not retain the spinner or a previous decision.
	window.addEventListener('pageshow', () => {
		decision?.remove();
		decision = undefined;
		buttons.forEach((button) => {
			button.disabled = false;
			button.removeAttribute('aria-busy');
		});
	});
})();
