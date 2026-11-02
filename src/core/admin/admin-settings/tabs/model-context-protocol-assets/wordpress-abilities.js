/**
 * MCP WordPress abilities settings: per-ability switches.
 *
 * Every switch saves immediately and reverts with an error if the save fails.
 * Group and header counts update in place.
 */
( function () {
	const config = window.AutomatorMcpWpAbilities;

	if ( ! config || ! config.ajaxUrl ) {
		return;
	}

	const root = document.querySelector( '.automator-mcp-wp-abilities' );
	const alertContainer = document.getElementById( 'automator-mcp-wp-abilities-alert' );

	if ( ! root ) {
		return;
	}

	let saving = false;
	let outcomeUnknown = false;
	const switchSelector = '.automator-mcp-wp-ability-toggle, .automator-mcp-tool-toggle';
	const confirmed = new WeakMap();
	root.querySelectorAll( switchSelector ).forEach( function ( toggle ) {
		confirmed.set( toggle, !! toggle.checked );
	} );
	const viewKey = 'automator-mcp-wp-abilities:' + window.location.pathname + ':' + config.userId;
	try {
		const opened = JSON.parse( window.sessionStorage.getItem( viewKey ) || '[]' );
		root.querySelectorAll( 'uas-accordion-item[data-provider]' ).forEach( function ( item ) {
			item.open = Array.isArray( opened ) && opened.indexOf( item.dataset.provider ) !== -1;
		} );
	} catch ( error ) { /* Provider rows remain usable without browser storage. */ }
	window.addEventListener( 'pagehide', function () {
		const opened = Array.from( root.querySelectorAll( 'uas-accordion-item[data-provider]' ) )
			.filter( function ( item ) { return item.open; } ).map( function ( item ) { return item.dataset.provider; } );
		try {
			window.sessionStorage.setItem( viewKey, JSON.stringify( opened ) );
		} catch ( error ) { /* Only disclosure state is stored. */ }
	} );
	const syncDisabled = function () {
		root.querySelectorAll( '.automator-mcp-wp-ability-toggle' ).forEach( function ( toggle ) {
			toggle.disabled = saving || outcomeUnknown;
		} );
	};

	const showAlert = function ( heading, message ) {
		if ( ! alertContainer ) {
			return;
		}

		const alert = document.createElement( 'uo-alert' );
		alert.setAttribute( 'type', 'error' );
		alert.setAttribute( 'role', 'alert' );
		alert.setAttribute( 'heading', heading );
		alert.textContent = message;
		alertContainer.replaceChildren( alert );
	};

	const clearAlert = function () {
		if ( alertContainer ) {
			alertContainer.replaceChildren();
		}
	};

	// Routine outcomes go to the live region as plain text; errors keep the
	// alert. Focus never moves.
	const statusLine = document.getElementById( 'automator-mcp-wp-abilities-status' );
	const showStatus = function ( message ) {
		if ( statusLine ) {
			statusLine.textContent = message || '';
		}
	};

	const named = function ( key, name ) {
		return String( config.strings[ key ] || '' ).replace( '%s', function () { return name; } );
	};

	const text = function ( key, count ) {
		return ( config.strings[ key ] || '' ).replace( '%d', String( count ) );
	};

	const countOn = function ( scope ) {
		return Array.from( scope.querySelectorAll( switchSelector ) ).filter( function ( toggle ) {
			return confirmed.get( toggle );
		} ).length;
	};

	const renderCounts = function ( item ) {
		const groupChip = item.querySelector( '.automator-mcp-ability-state' );
		const groupOn = countOn( item );

		if ( groupChip ) {
			groupChip.label = text( 'someOn', groupOn );
			groupChip.hidden = groupOn === 0;
		}

		const totalChip = document.getElementById( 'automator-mcp-wp-abilities-on' );
		const totalOn = countOn( root );

		if ( totalChip ) {
			totalChip.label = text( 'switchedOn', totalOn );
			totalChip.hidden = totalOn === 0;
		}
	};

	root.addEventListener( 'automator-mcp-tool-saved', function ( event ) {
		if ( ! confirmed.has( event.target ) || ! event.detail || 'boolean' !== typeof event.detail.enabled ) {
			return;
		}
		confirmed.set( event.target, event.detail.enabled );
		renderCounts( event.target.closest( 'uas-accordion-item[data-provider]' ) );
	} );

	root.querySelectorAll( '.automator-mcp-wp-ability-toggle' ).forEach( function ( toggle ) {
		toggle.addEventListener( 'click', function ( event ) {
			event.stopPropagation();
		} );

		toggle.addEventListener( 'change', async function ( event ) {
			event.stopPropagation();

			if ( toggle.disabled || saving || outcomeUnknown || ( event.detail && event.detail.context === 'set-value' ) ) {
				return;
			}

			const enabled = !! toggle.checked;
			const name = toggle.dataset.title || toggle.dataset.ability || '';
			saving = true;
			syncDisabled();
			showStatus( config.strings.saving );

			const body = new FormData();
			body.append( 'action', config.action );
			body.append( 'nonce', config.nonce );
			body.append( 'ability', toggle.dataset.ability || '' );
			body.append( 'enabled', enabled ? '1' : '0' );

			try {
				const response = await fetch( config.ajaxUrl, {
					method: 'POST',
					body,
					credentials: 'same-origin',
					cache: 'no-store',
					headers: {
						'X-Requested-With': 'XMLHttpRequest',
					},
				} );

				let payload;
				try {
					payload = await response.json();
				} catch ( parseError ) {
					throw new Error( config.strings.unknownOutcome );
				}

				if ( ! payload || 'boolean' !== typeof payload.success ) {
					throw new Error( config.strings.unknownOutcome );
				}

				if ( ! response.ok || ! payload.success ) {
					const refusal = new Error( payload.data && payload.data.message ? payload.data.message : config.strings.unknownError );
					refusal.rejected = ! ( payload.data && payload.data.outcome_unknown );
					throw refusal;
				}
				if ( ! payload.data || payload.data.ability !== toggle.dataset.ability || payload.data.enabled !== enabled ) {
					throw new Error( config.strings.unknownOutcome );
				}

				confirmed.set( toggle, enabled );
				renderCounts( toggle.closest( 'uas-accordion-item[data-provider]' ) );
				clearAlert();
				showStatus( named( enabled ? 'savedOn' : 'savedOff', name ) );
				saving = false;
				syncDisabled();
			} catch ( error ) {
				showStatus( '' );
				if ( error && error.rejected ) {
					// The server refused the change: the prior state still stands.
					toggle.checked = ! enabled;
					saving = false;
					syncDisabled();
					showAlert( config.strings.errorHeading, error.message || config.strings.unknownError );
				} else {
					// No readable answer: the saved state is unknown, so the switch
					// stays put and locked until the page is refreshed.
					outcomeUnknown = true;
					saving = false;
					syncDisabled();
					showAlert( config.strings.unknownOutcome, '' );
				}
			}
		} );
	} );
}() );
