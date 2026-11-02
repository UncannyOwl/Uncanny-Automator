/**
 * MCP tools settings: per-tool switches for third-party AI apps.
 *
 * Each switch saves immediately. A rejected save restores the prior state;
 * an uncertain outcome locks changes until the administrator refreshes.
 */
( function () {
	const config = window.AutomatorMcpTools;

	if ( ! config || ! config.ajaxUrl ) {
		return;
	}

	const alertContainer = document.getElementById( 'automator-mcp-tool-toggle-alert' );

	const showAlert = function ( heading, message, container = alertContainer ) {
		if ( ! container ) {
			return;
		}

		const alert = document.createElement( 'uo-alert' );
		alert.setAttribute( 'type', 'error' );
		alert.setAttribute( 'heading', heading );
		alert.textContent = message;
		container.replaceChildren( alert );
	};

	// Routine outcomes are announced as plain text in the live region; errors
	// keep the alert. Focus never moves.
	const showStatus = function ( message ) {
		if ( alertContainer ) {
			alertContainer.textContent = message || '';
		}
	};

	const itemStatus = function ( template, name ) {
		return String( template || '' ).replace( '%s', name );
	};

	const databaseButton = document.getElementById( 'automator-mcp-database-access' );
	const databaseConsent = document.getElementById( 'automator-mcp-database-consent' );
	const databaseAlert = document.getElementById( 'automator-mcp-database-alert' );
	const filesButton = document.getElementById( 'automator-mcp-file-reading' );
	const filesAlert = document.getElementById( 'automator-mcp-file-reading-alert' );
	const excludeField = document.getElementById( 'automator-mcp-file-reading-exclusions' );
	const excludeButton = document.getElementById( 'automator-mcp-file-reading-exclusions-save' );
	const excludeStatus = document.getElementById( 'automator-mcp-file-reading-exclusions-status' );
	let outcomeUnknown = false;
	let saving = false;
	const controls = [ ...document.querySelectorAll( '.automator-mcp-tool-toggle' ), databaseButton, databaseConsent, filesButton, excludeField, excludeButton ].filter( Boolean );
	const initialDisabled = new WeakMap( controls.map( control => [ control, control !== databaseButton && !! control.disabled ] ) );
	const syncDisabled = function () {
		controls.forEach( function ( control ) {
			control.disabled = saving || outcomeUnknown || initialDisabled.get( control )
				|| ( control === databaseButton && databaseConsent && ! databaseConsent.checked );
		} );
	};

	const lockSettings = function () {
		outcomeUnknown = true;
		document.querySelectorAll( '.automator-mcp-tool-toggle' ).forEach( function ( toggle ) {
			toggle.disabled = true;
		} );
		if ( databaseButton ) {
			databaseButton.disabled = true;
		}
		if ( databaseConsent ) {
			databaseConsent.disabled = true;
		}
		[ filesButton, excludeField, excludeButton ].filter( Boolean ).forEach( function ( control ) {
			control.disabled = true;
		} );
	};

	// The saved data, or an error marked `rejected` when the server refused it.
	const readPayload = async function ( response ) {
		let payload;
		try {
			payload = await response.json();
		} catch ( parseError ) {
			throw new Error( config.strings.unknownOutcome );
		}

		if ( payload && payload.success === false ) {
			const error = new Error( payload.data && payload.data.message ? payload.data.message : config.strings.unknownError );
			error.rejected = ! ( payload.data && payload.data.outcome_unknown );
			throw error;
		}

		if ( ! response.ok || ! payload || payload.success !== true || ! payload.data ) {
			throw new Error( config.strings.unknownOutcome );
		}

		return payload.data;
	};

	const readSaveResult = async function ( response, enabled, tool ) {
		const data = await readPayload( response );
		if ( data.enabled !== enabled || ( tool && data.tool !== tool ) ) {
			throw new Error( config.strings.unknownOutcome );
		}
	};

	const post = function ( fields ) {
		const body = new FormData();
		Object.keys( fields ).forEach( function ( key ) {
			body.append( key, fields[ key ] );
		} );

		return fetch( config.ajaxUrl, {
			method: 'POST',
			body,
			credentials: 'same-origin',
			cache: 'no-store',
		} );
	};

	// A refused save leaves the prior state; anything else locks until refresh.
	const handleSaveError = function ( error, container ) {
		if ( error && error.rejected && ! outcomeUnknown ) {
			saving = false;
			syncDisabled();
			showAlert( config.strings.errorHeading, error.message || config.strings.unknownError, container );
		} else {
			lockSettings();
			showAlert( config.strings.errorHeading, config.strings.unknownOutcome, container );
		}
	};


	if ( filesButton ) {
		filesButton.addEventListener( 'click', async function () {
			const enabled = filesButton.dataset.enabled !== '1';
			if ( saving || outcomeUnknown || filesButton.disabled ) {
				return;
			}

			saving = true;
			syncDisabled();
			if ( filesAlert ) {
				filesAlert.replaceChildren();
			}

			try {
				const response = await post( {
					action: config.filesAction,
					nonce: config.filesNonce,
					enabled: enabled ? '1' : '0',
				} );
				await readSaveResult( response, enabled );
				window.location.reload();
			} catch ( error ) {
				handleSaveError( error, filesAlert );
			}
		} );
	}

	if ( excludeButton && excludeField ) {
		excludeButton.addEventListener( 'click', async function () {
			if ( saving || outcomeUnknown || excludeButton.disabled ) {
				return;
			}

			saving = true;
			syncDisabled();
			if ( filesAlert ) {
				filesAlert.replaceChildren();
			}
			if ( excludeStatus ) {
				excludeStatus.textContent = config.strings.saving;
			}

			try {
				const response = await post( {
					action: config.excludeAction,
					nonce: config.excludeNonce,
					exclusions: excludeField.value,
				} );
				const data = await readPayload( response );
				if ( ! Array.isArray( data.exclusions ) || ! Array.isArray( data.rejected ) ) {
					throw new Error( config.strings.unknownOutcome );
				}

				// Show what was saved, so dropped and duplicate lines disappear.
				excludeField.value = data.exclusions.join( '\n' );
				const messages = [ data.exclusions.length ? config.strings.listSaved : config.strings.listEmpty ];
				if ( data.rejected.length ) {
					messages.push( itemStatus( config.strings.listRejected, data.rejected.join( ', ' ) ) );
				}
				if ( data.truncated ) {
					messages.push( String( config.strings.listTruncated || '' ).replace( '%d', String( data.exclusions.length ) ) );
				}
				if ( excludeStatus ) {
					excludeStatus.textContent = messages.join( ' ' );
				}
				saving = false;
				syncDisabled();
			} catch ( error ) {
				if ( excludeStatus ) {
					excludeStatus.textContent = '';
				}
				handleSaveError( error, filesAlert );
			}
		} );
	}

	if ( databaseButton ) {
		if ( databaseConsent ) {
			databaseConsent.addEventListener( 'change', function () {
				syncDisabled();
			} );
		}

		databaseButton.addEventListener( 'click', async function () {
			const enabled = databaseButton.dataset.enabled !== '1';
			if ( saving || outcomeUnknown || databaseButton.disabled || ( enabled && ( ! databaseConsent || ! databaseConsent.checked ) ) ) {
				return;
			}

			saving = true;
			syncDisabled();
			if ( databaseAlert ) {
				databaseAlert.replaceChildren();
			}

			try {
				const response = await post( {
					action: config.databaseAction,
					nonce: config.databaseNonce,
					enabled: enabled ? '1' : '0',
					consent: enabled ? '1' : '0',
				} );
				await readSaveResult( response, enabled );
				window.location.reload();
			} catch ( error ) {
				handleSaveError( error, databaseAlert );
			}
		} );
	}

	/**
	 * Save one switch. A server answer that refuses the change throws an error
	 * marked `rejected`; a lost or unreadable response throws without it, so
	 * the caller can tell "not saved" from "outcome unknown".
	 */
	const saveToggle = async function ( toggle, enabled ) {
		const body = new FormData();
		body.append( 'action', config.action );
		body.append( 'nonce', config.nonce );
		body.append( 'tool', toggle.dataset.tool || '' );
		body.append( 'enabled', enabled ? '1' : '0' );

		const response = await fetch( config.ajaxUrl, {
			method: 'POST',
			body,
			credentials: 'same-origin',
			cache: 'no-store',
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
			},
		} );

		await readSaveResult( response, enabled, toggle.dataset.tool );
	};

	document.querySelectorAll( '.automator-mcp-tool-toggle' ).forEach( function ( toggle ) {
		// The switch sits inside the accordion summary; its clicks must not open or close the item.
		toggle.addEventListener( 'click', function ( event ) {
			event.stopPropagation();
		} );

		toggle.addEventListener( 'change', async function ( event ) {
			event.stopPropagation();

			if ( toggle.disabled || saving || outcomeUnknown || ( event.detail && event.detail.context === 'set-value' ) ) {
				return;
			}

			const enabled = !! toggle.checked;
			const name = toggle.dataset.title || toggle.dataset.tool || '';
			saving = true;
			syncDisabled();
			showStatus( config.strings.saving );

			try {
				await saveToggle( toggle, enabled );
				toggle.dispatchEvent( new CustomEvent( 'automator-mcp-tool-saved', {
					bubbles: true,
					detail: { enabled },
				} ) );
				if ( ! outcomeUnknown ) {
					showStatus( itemStatus( enabled ? config.strings.savedOn : config.strings.savedOff, name ) );
					saving = false;
					syncDisabled();
				}
			} catch ( error ) {
				if ( error && error.rejected ) {
					// The server refused the change: the prior state still stands.
					toggle.checked = ! enabled;
					if ( ! outcomeUnknown ) {
						saving = false;
						syncDisabled();
						showAlert( config.strings.errorHeading, error.message || config.strings.unknownError );
					}
				} else {
					// No readable answer: the saved state is unknown, so the switch
					// stays put and locked until the page is refreshed.
					lockSettings();
					showAlert( config.strings.errorHeading, config.strings.unknownOutcome );
				}
			}
		} );
	} );
}() );
