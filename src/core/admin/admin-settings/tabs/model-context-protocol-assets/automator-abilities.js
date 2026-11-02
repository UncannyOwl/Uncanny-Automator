/**
 * MCP Automator abilities settings.
 *
 * The page is a shell. One request returns the whole catalogue, built by the
 * server inside a load-all request so connected apps are recognised, and this
 * script renders it from the markup templates in the view. Integration rows
 * stay collapsed; action rows are drawn when a row is opened. Every switch
 * saves immediately and reverts with an error if the save fails.
 */
( function () {
	const config = window.AutomatorMcpAbilities;

	if ( ! config || ! config.ajaxUrl ) {
		return;
	}

	const root = document.querySelector( '.automator-mcp-abilities' );
	const list = document.getElementById( 'automator-mcp-abilities-list' );
	const rowTemplate = document.getElementById( 'automator-mcp-abilities-row-template' );
	const unavailableTemplate = document.getElementById( 'automator-mcp-abilities-unavailable-template' );
	const alertContainer = document.getElementById( 'automator-mcp-abilities-alert' );

	if ( ! root || ! list || ! rowTemplate || ! unavailableTemplate ) {
		return;
	}

	const catalog = {};
	let catalogLoaded = false;
	const viewKey = 'automator-mcp-abilities:' + window.location.pathname + ':' + config.userId;
	let savedView = {};
	try {
		savedView = JSON.parse( window.sessionStorage.getItem( viewKey ) || '{}' ) || {};
	} catch ( error ) { /* The catalogue remains usable without browser storage. */ }
	window.addEventListener( 'pagehide', function () {
		const opened = Array.from( root.querySelectorAll( 'uas-accordion-item[data-integration]' ) )
			.filter( function ( item ) { return item.open && item.dataset.searchOpened !== '1'; } )
			.map( function ( item ) { return item.dataset.integration; } );
		try {
			const more = document.querySelector( '#automator-mcp-abilities-unavailable uas-accordion-item' );
			window.sessionStorage.setItem( viewKey, JSON.stringify( {
				query: searchField.value,
				opened,
				moreOpen: !! ( more && ( 'searchPreviousOpen' in more.dataset ? more.dataset.searchPreviousOpen === '1' : more.open ) ),
				setupSearch: more && 'searchPreviousOpen' in more.dataset
					? { query: searchField.value.trim(), open: !! more.open } : null,
			} ) );
		} catch ( error ) { /* Only presentation preferences are stored. */ }
	} );
	let saving = false;
	let outcomeUnknown = false;

	// A save replaces a shared settings list. Keep requests sequential, including
	// switches recreated while searching, so sibling changes cannot overwrite it.
	const syncDisabled = function () {
		root.querySelectorAll( '.automator-mcp-integration-toggle, .automator-mcp-ability-toggle' ).forEach( function ( toggle ) {
			const entry = catalog[ toggle.dataset.integration ];
			const isAction = toggle.classList.contains( 'automator-mcp-ability-toggle' );
			if ( ! saving && ! outcomeUnknown ) {
				const action = isAction && entry.actions.find( function ( candidate ) { return candidate.code === toggle.dataset.code; } );
				const checked = isAction ? !! ( action && action.enabled && ! action.locked ) : !! entry.enabled;
				if ( toggle.checked !== checked ) {
					toggle.checked = checked;
				}
			}
			toggle.disabled = saving || outcomeUnknown || toggle.dataset.locked === '1'
				|| ( isAction && ! entry.enabled );
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
	const statusLine = document.getElementById( 'automator-mcp-abilities-status' );
	const showStatus = function ( message ) {
		if ( statusLine ) {
			statusLine.textContent = message || '';
		}
	};

	const named = function ( key, name ) {
		return String( config.strings[ key ] || '' ).replace( '%s', function () { return name; } );
	};

	/**
	 * POST one request. A server answer that refuses the change throws an error
	 * marked `rejected`; a lost or unreadable response throws without it.
	 */
	const post = async function ( action, fields ) {
		const body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', config.nonce );
		Object.keys( fields ).forEach( function ( key ) {
			body.append( key, fields[ key ] );
		} );

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
			const error = new Error( payload.data && payload.data.message ? payload.data.message : config.strings.unknownError );
			error.rejected = ! ( payload.data && payload.data.outcome_unknown );
			throw error;
		}

		return payload.data || {};
	};

	/**
	 * Settle a switch after a failed save: a refusal restores the prior state;
	 * an unknown outcome leaves the switch where it is and locked until refresh.
	 */
	const settleFailure = function ( toggle, enabled, error ) {
		if ( error && error.rejected ) {
			toggle.checked = ! enabled;
			saving = false;
			syncDisabled();
			showAlert( config.strings.errorHeading, error.message || config.strings.unknownError );
			return;
		}

		outcomeUnknown = true;
		saving = false;
		syncDisabled();
		showAlert( config.strings.unknownOutcome, '' );
	};

	const text = function ( key, count ) {
		return ( config.strings[ key ] || '' ).replace( '%d', String( count ) );
	};

	// Literal plural messages let WordPress extract all language forms.
	const _n = window.wp && window.wp.i18n ? window.wp.i18n._n : function ( single, many, count ) {
		return count === 1 ? single : many;
	};
	const plural = function ( kind, count ) {
		let template;
		if ( kind === 'integrations' ) {
			/* translators: %d Number of integrations that can run on this site. */
			template = _n( '%d integration available', '%d integrations available', count, 'uncanny-automator' );
		} else if ( kind === 'selected' ) {
			/* translators: %d Number of selected actions. */
			template = _n( '%d action selected', '%d actions selected', count, 'uncanny-automator' );
		} else {
			/* translators: %d Number of actions. */
			template = _n( '%d action', '%d actions', count, 'uncanny-automator' );
		}
		return template.replace( '%d', String( count ) );
	};

	const setIcon = function ( img, url ) {
		if ( url ) {
			img.src = url;
			img.style.visibility = '';
		} else {
			img.removeAttribute( 'src' );
			img.style.visibility = 'hidden';
		}
	};

	/**
	 * Keep saved child selections distinct from the parent gate's On/Off state.
	 */
	const renderCounts = function ( item, entry ) {
		const count = item.querySelector( '.automator-mcp-ability-count' );
		const stateChip = item.querySelector( '.automator-mcp-ability-state' );
		const parentOff = item.querySelector( '.automator-mcp-ability-parent-off' );
		const total = entry.actions.length;
		const selected = entry.actions.filter( function ( action ) {
			return action.enabled && ! action.locked;
		} ).length;

		count.textContent = plural( 'actions', total );
		stateChip.label = plural( 'selected', selected );
		stateChip.hidden = false;

		if ( parentOff ) {
			parentOff.textContent = named( 'parentOffHelp', item.dataset.name || entry.code );
			parentOff.hidden = !! entry.enabled;
		}
	};

	/**
	 * Draw the action rows inside an opened integration.
	 */
	let query = '';

	const matches = function ( value ) {
		return String( value || '' ).toLowerCase().indexOf( query ) !== -1;
	};

	const visibleActions = function ( entry, item ) {
		// While searching, a row that matched on its own name shows every action; a
		// row that matched only through actions shows just the matching ones.
		if ( '' === query || matches( item.dataset.name ) ) {
			return entry.actions;
		}

		return entry.actions.filter( function ( action ) {
			return matches( action.sentence ) || matches( action.code );
		} );
	};

	const renderActions = function ( item, entry ) {
		const container = item.querySelector( '.automator-mcp-ability-actions' );
		container.replaceChildren();

		if ( ! entry.actions.length ) {
			const empty = document.createElement( 'div' );
			empty.className = 'uap-settings-panel-content-paragraph';
			empty.textContent = config.strings.noActions;
			container.appendChild( empty );
			return;
		}

		visibleActions( entry, item ).forEach( function ( action ) {
			const row = document.createElement( 'div' );
			row.className = 'automator-mcp-ability-row';

			const label = document.createElement( 'div' );
			label.className = 'automator-mcp-ability-row-label';
			const sentence = document.createElement( 'span' );
			sentence.className = 'automator-mcp-ability-sentence';
			sentence.textContent = action.sentence || action.code;
			label.appendChild( sentence );
			row.appendChild( label );

			const controls = document.createElement( 'div' );
			controls.className = 'automator-mcp-ability-row-controls';

			if ( action.locked ) {
				const chip = document.createElement( 'uas-chip' );
				chip.setAttribute( 'size', 'sm' );
				chip.setAttribute( 'color', 'error' );
				chip.setAttribute( 'filled', '' );
				chip.setAttribute( 'title', action.reason || '' );
				chip.label = config.strings.restricted;
				controls.appendChild( chip );
			}

			// Upgrade from inert template markup after insertion, like the parent
			// switch. Its shared constructor assigns an ID and hidden form field.
			const toggle = rowTemplate.content.querySelector( '.automator-mcp-integration-toggle' ).cloneNode( true );
			toggle.className = 'automator-mcp-ability-toggle';
			toggle.dataset.integration = entry.code;
			toggle.dataset.code = action.code;
			toggle.dataset.locked = action.locked ? '1' : '0';
			toggle.setAttribute( 'aria-label', named( 'allowItem', action.sentence || action.code ) );
			toggle.setAttribute( 'status-label', config.strings.statusLabels );
			toggle.setAttribute( 'status-label-position', 'left' );

			if ( action.enabled && ! action.locked ) {
				toggle.setAttribute( 'checked', '' );
			}

			if ( action.locked || ! entry.enabled || saving || outcomeUnknown ) {
				toggle.setAttribute( 'disabled', '' );
			}

			bindActionToggle( toggle, item, entry, action );
			controls.appendChild( toggle );
			row.appendChild( controls );
			container.appendChild( row );
		} );
	};

	const bindActionToggle = function ( toggle, item, entry, action ) {
		toggle.addEventListener( 'click', function ( event ) {
			event.stopPropagation();
		} );

		toggle.addEventListener( 'change', async function ( event ) {
			event.stopPropagation();

			if ( toggle.disabled || saving || outcomeUnknown || ( event.detail && event.detail.context === 'set-value' ) ) {
				return;
			}

			const enabled = !! toggle.checked;
			saving = true;
			syncDisabled();
			showStatus( config.strings.saving );

			try {
				const result = await post( config.toggleAction, { kind: 'action', code: action.code, integration: entry.code, enabled: enabled ? '1' : '0' } );
				if ( result.kind !== 'action' || result.code !== action.code || result.enabled !== enabled ) {
					throw new Error( config.strings.unknownOutcome );
				}
				action.enabled = enabled;
				renderCounts( item, entry );
				renderSummary();
				clearAlert();
				showStatus( named( enabled ? 'actionOn' : 'actionOff', action.sentence || action.code ) );
				saving = false;
				syncDisabled();
			} catch ( error ) {
				showStatus( '' );
				settleFailure( toggle, enabled, error );
			}
		} );
	};

	const bindIntegrationToggle = function ( item, entry ) {
		const toggle = item.querySelector( '.automator-mcp-integration-toggle' );

		toggle.addEventListener( 'click', function ( event ) {
			event.stopPropagation();
		} );

		toggle.addEventListener( 'change', async function ( event ) {
			event.stopPropagation();

			if ( toggle.disabled || saving || outcomeUnknown || ( event.detail && event.detail.context === 'set-value' ) ) {
				return;
			}

			const enabled = !! toggle.checked;
			saving = true;
			syncDisabled();
			showStatus( config.strings.saving );

			try {
				const result = await post( config.toggleAction, { kind: 'integration', code: entry.code, enabled: enabled ? '1' : '0' } );
				if ( result.kind !== 'integration' || result.code !== entry.code || result.enabled !== enabled ) {
					throw new Error( config.strings.unknownOutcome );
				}
				entry.enabled = enabled;
				renderCounts( item, entry );
				if ( item.dataset.rendered === '1' ) {
					renderActions( item, entry );
				}
				renderSummary();
				clearAlert();
				showStatus( named( enabled ? 'integrationOn' : 'integrationOff', item.dataset.name || entry.code ) );
				saving = false;
				syncDisabled();
			} catch ( error ) {
				showStatus( '' );
				settleFailure( toggle, enabled, error );
			}
		} );
	};

	/**
	 * Header chips: integrations, actions, and what is switched off.
	 */
	const renderSummary = function () {
		const codes = Object.keys( catalog );
		let actions = 0;

		codes.forEach( function ( code ) {
			actions += catalog[ code ].actions.length;
		} );

		const availableChip = document.getElementById( 'automator-mcp-abilities-available' );
		const totalChip = document.getElementById( 'automator-mcp-abilities-total' );

		availableChip.label = plural( 'integrations', codes.length );
		availableChip.hidden = codes.length === 0;
		totalChip.label = plural( 'actions', actions );
		totalChip.hidden = actions === 0;
	};

	const renderAvailable = function ( rows ) {
		if ( ! rows.length ) {
			const empty = document.createElement( 'div' );
			empty.className = 'uap-settings-panel-content-paragraph';
			const heading = document.createElement( 'strong' );
			heading.textContent = config.strings.noIntegrations;
			const help = document.createElement( 'span' );
			help.textContent = ' ' + config.strings.noIntegrationsHelp;
			empty.appendChild( heading );
			empty.appendChild( help );
			list.replaceChildren( empty );
			return;
		}

		const accordion = document.createElement( 'uas-accordion' );

		rows.forEach( function ( row ) {
			const entry = row.entry;
			entry.code = row.code;
			catalog[ row.code ] = entry;

			const item = rowTemplate.content.firstElementChild.cloneNode( true );
			item.dataset.integration = row.code;
			item.dataset.name = row.name;
			setIcon( item.querySelector( '.automator-mcp-ability-icon' ), row.icon );
			item.querySelector( '.automator-mcp-ability-title strong' ).textContent = row.name;

			const toggle = item.querySelector( '.automator-mcp-integration-toggle' );
			toggle.dataset.integration = row.code;
			toggle.setAttribute( 'aria-label', named( 'allowItem', row.name || row.code ) );
			if ( entry.enabled ) {
				toggle.setAttribute( 'checked', '' );
			}

			renderCounts( item, entry );
			bindIntegrationToggle( item, entry );
			if ( Array.isArray( savedView.opened ) && savedView.opened.indexOf( row.code ) !== -1 ) {
				// Template clones upgrade when inserted. Restore through the attribute
				// so the initial filter sees the same open state as the component.
				item.setAttribute( 'open', '' );
			}

			item.addEventListener( 'uas.accordion-item.toggled', function () {
				if ( item.open && item.dataset.rendered !== '1' ) {
					renderActions( item, entry );
					item.dataset.rendered = '1';
				}
			} );

			accordion.appendChild( item );
		} );

		list.replaceChildren( accordion );
	};

	const renderUnavailable = function ( rows ) {
		const section = document.getElementById( 'automator-mcp-abilities-unavailable' );
		const heading = document.getElementById( 'automator-mcp-abilities-unavailable-heading' );
		const container = document.getElementById( 'automator-mcp-abilities-unavailable-list' );

		if ( ! section || ! rows.length ) {
			return;
		}

		heading.textContent = text( 'unavailableHeading', rows.length );
		container.replaceChildren();

		rows.forEach( function ( row ) {
			const item = unavailableTemplate.content.firstElementChild.cloneNode( true );
			item.dataset.name = row.name;
			setIcon( item.querySelector( '.automator-mcp-ability-icon' ), row.icon );
			item.querySelector( '.automator-mcp-ability-unavailable-name' ).textContent = row.name;

			const requirements = row.requirements || [ row.reason ];
			const statusKeys = { plugin_not_installed: 'notInstalled', plugin_inactive: 'inactive', not_connected: 'notConnected', requires_plan: 'requiresPlan' };
			const statusChip = item.querySelector( '.automator-mcp-ability-status' );
			requirements.forEach( function ( requirement, index ) {
				const status = statusKeys[ requirement ] || 'setupRequired';
				const chip = index === 0 ? statusChip : statusChip.cloneNode( true );
				const label = requirement === 'requires_plan'
					? String( config.strings.requiresPlan || '' ).replace( '%s', function () { return row.tier_label || config.strings.proPlan; } )
					: config.strings[ status ];
				chip.label = label;
				if ( index > 0 ) {
					item.appendChild( chip );
				}
			} );

			container.appendChild( item );
		} );

		section.hidden = false;
		section.querySelector( 'uas-accordion-item' ).open = savedView.moreOpen === true;
	};

	/**
	 * Apply the current search to both lists. Rows that match through an
	 * action open so the match is visible.
	 */
	const applyFilter = function () {
		if ( ! catalogLoaded ) {
			return;
		}
		let availableShown = 0;
		let unavailableShown = 0;

		root.querySelectorAll( 'uas-accordion-item[data-integration]' ).forEach( function ( item ) {
			const entry = catalog[ item.dataset.integration ];
			const byName = '' === query || matches( item.dataset.name );
			const byAction = ! byName && entry.actions.some( function ( action ) {
				return matches( action.sentence ) || matches( action.code );
			} );

			// A row the search opened closes again as soon as it stops matching
			// through an action, including when the box is cleared. Rows the
			// person opened themselves are left alone.
			if ( ! byAction && item.dataset.searchOpened === '1' ) {
				item.open = false;
				delete item.dataset.searchOpened;
			}

			if ( ! byName && ! byAction ) {
				item.dataset.filtered = 'out';
				item.dataset.rendered = '0';
				return;
			}

			delete item.dataset.filtered;
			availableShown += 1;

			if ( byAction && ! item.open ) {
				item.open = true;
				item.dataset.searchOpened = '1';
			}

			if ( item.open ) {
				renderActions( item, entry );
				item.dataset.rendered = '1';
			} else {
				// The filter changed; draw the full list again on the next open.
				item.dataset.rendered = '0';
			}
		} );

		document.querySelectorAll( '.automator-mcp-ability-unavailable-item' ).forEach( function ( item ) {
			if ( '' === query || matches( item.dataset.name ) ) {
				delete item.dataset.filtered;
				unavailableShown += 1;
			} else {
				item.dataset.filtered = 'out';
			}
		} );

		list.hidden = '' !== query && availableShown === 0;
		const setupSection = document.getElementById( 'automator-mcp-abilities-unavailable' );
		if ( setupSection ) {
			setupSection.hidden = unavailableShown === 0;
		}
		const setupItem = setupSection && setupSection.querySelector( 'uas-accordion-item' );
		if ( setupItem ) {
			const setupOnly = '' !== query && availableShown === 0 && unavailableShown > 0;
			if ( setupOnly && ! ( 'searchPreviousOpen' in setupItem.dataset ) ) {
				setupItem.dataset.searchPreviousOpen = setupItem.open ? '1' : '0';
				setupItem.open = savedView.setupSearch && savedView.setupSearch.query === rawQuery
					? savedView.setupSearch.open === true : true;
				delete savedView.setupSearch;
			} else if ( ! setupOnly && 'searchPreviousOpen' in setupItem.dataset ) {
				setupItem.open = setupItem.dataset.searchPreviousOpen === '1';
				delete setupItem.dataset.searchPreviousOpen;
			}
		}

		const empty = document.getElementById( 'automator-mcp-abilities-no-results' );
		if ( empty ) {
			const emptyText = document.getElementById( 'automator-mcp-abilities-no-results-text' );
			if ( emptyText ) {
				emptyText.textContent = named( unavailableShown > 0 ? 'noAvailableResults' : 'noResults', rawQuery ) + ' ';
			}
			empty.hidden = '' === query || availableShown > 0;
		}
	};

	let rawQuery = '';
	const searchField = document.getElementById( 'automator-mcp-abilities-search' );
	if ( searchField ) {
		if ( typeof savedView.query === 'string' ) {
			searchField.value = savedView.query;
			rawQuery = savedView.query.trim();
			query = rawQuery.toLowerCase();
		}
		let timer = null;
		searchField.addEventListener( 'input', function () {
			window.clearTimeout( timer );
			timer = window.setTimeout( function () {
				rawQuery = String( searchField.value || '' ).trim();
				query = rawQuery.toLowerCase();
				applyFilter();
			}, 120 );
		} );

		const clearButton = document.getElementById( 'automator-mcp-abilities-clear-search' );
		if ( clearButton ) {
			clearButton.addEventListener( 'click', function () {
				searchField.value = '';
				rawQuery = '';
				query = '';
				applyFilter();
				searchField.focus();
			} );
		}
	}

	post( config.catalogAction, {} )
		.then( function ( data ) {
			if ( ! Array.isArray( data.available ) || ! Array.isArray( data.unavailable ) ) {
				throw new Error( config.strings.catalogError );
			}
			renderAvailable( data.available || [] );
			renderUnavailable( data.unavailable || [] );
			renderSummary();
			catalogLoaded = true;
			applyFilter();
		} )
		.catch( function () {
			// A failed fetch is reported as such, never as "no integrations".
			list.replaceChildren();
			showAlert( config.strings.catalogError, '' );
		} );
}() );
