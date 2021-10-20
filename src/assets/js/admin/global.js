jQuery(function($){
	// Then call these when the DOM is ready
	$(document).ready(function(){
		UA_ReviewsBanner.init();
		UA_TrackingSwitch.init();
	});

	var UA_ReviewsBanner = {
		init: function(){
			// Check if the banner exists
			if ( this.hasBanner() ){
				// Get elements
				this.getElements();

				// Add actions
				this.addActions();
			}
		},

		getElements: function(){
			this.$elements = {
				container: $( '#uap-review-banner' ),
				actions:   $( '.uap-review-banner__action' ),
				close:     $( '#uap-review-banner__close' )
			}
		},

		addActions: function(){
			// Create reference to this object instance
			let _this = this;

			// Listen clicks to the actions
			this.$elements.actions.on( 'click', function( event ){
				// Get the clicked button
				let $button = $( event.currentTarget );

				// Get the ID of the action
				let action = $button.data( 'action' );

				// Perform action
				_this.doAction( action, $button, 'uap-review-banner__action--loading' );
			});

			// Listen click to the close button
			this.$elements.close.on( 'click', function(){
				// Perform action
				_this.doAction( 'hide-forever', _this.$elements.close, 'uap-review-banner__close--loading' );
			});
		},

		doAction: function( action, $button, loadingClass ){
			// Create reference to this object instance
			let _this = this;

			// Add loading animation to the button
			$button.addClass( loadingClass );

			// Do rest call
			UA_Utilities.restCall( 'review-banner-visibility', 'POST', {
				action: action
			}, function( response ){
				if ( response.success ){
					// Remove the loading animation from the button
					$button.removeClass( loadingClass );

					// Hide the banner
					_this.closeBanner();
				}
				else {
					// If it fails, add a parameter to the page and reload
					UA_Utilities.insertParameterToURL( 'ua_review-banner-visibility', action, true );

					// Hide the banner
					_this.closeBanner();
				}
			}, function(){
				// If it fails, add a parameter to the page and reload
				UA_Utilities.insertParameterToURL( 'ua_review-banner-visibility', action, true );

				// Hide the banner
				_this.closeBanner();
			});
		},

		closeBanner: function(){
			// Reference to the object instance
			let _this = this;

			this.$elements.container.slideUp( 500, function(){
				_this.$elements.container.remove();
			});
		},

		hasBanner: function(){
			return $( '#uap-review-banner' ).length > 0;
		}
	}

	var UA_TrackingSwitch = {
		init: function(){
			// Check if the banner exists
			if ( this.hasSwitch() ){
				// Get elements
				this.getElements();

				// Add actions
				this.addActions();
			}
		},

		getElements: function(){
			this.$elements = {
				actions:   $( '#uap_automator_allow_tracking_button' ),
				checkbox:   $( '#uap_automator_allow_tracking' ),
				close:     $( '#uap-tracking-banner__close' ),
			}
		},

		addActions: function(){
			// Create reference to this object instance
			let _this = this;

			// Listen clicks to the actions
			this.$elements.actions.on( 'click', function( event ){

				_this.$elements.actions.addClass('uo-settings-btn--loading');
				// Get the clicked button
				let $switch = _this.$elements.checkbox.is(':checked');

				// Get the ID of the action
				let action = 'tracking-settings';

				// Perform action
				// Do rest call
				UA_Utilities.restCall( 'allow-tracking-switch', 'POST', {
					action: action,
					swtich: $switch
				}, function( response ){
					if ( response.success ){
						// Silence please
						location.reload();
					}
					else {
						// Silence please
						location.reload();
					}
				}, function(){
					// Keep quite
				});
			});

			// Listen click to the close button
			this.$elements.close.on( 'click', function(){
				// Do rest call
				UA_Utilities.restCall( 'allow-tracking-switch', 'POST', {
					action: 'tracking-settings',
					swtich: false,
					hide: true
				}, function( response ){
					if ( response.success ){
						// Silence please
						location.reload();
					}
					else {
						// Silence please
						location.reload();
					}
				}, function(){
					// Keep quite
				});
			});
		},

		hasSwitch: function(){
			return $( '#uap_automator_allow_tracking' ).length > 0;
		}
	}

	var UA_Utilities = {
		isDefined: function( variable ){
			return typeof variable !== 'undefined' && variable !== null;
		},

		// https://stackoverflow.com/a/487049/4418559
		insertParameterToURL: function( key, value, reload ){
			key = encodeURI( key );
			value = encodeURI( value );

			var kvp = document.location.search.substr( 1 ).split( '&' );

			var i = kvp.length;
			var x;

			while( i-- ){
				x = kvp[i].split( '=' );

				if ( x[ 0 ] == key ){
					x[ 1 ] = value;
					kvp[i] = x.join( '=' );
					break;
				}
			}

			if ( i < 0 ){
				kvp[ kvp.length ] = [ key, value ].join( '=' );
			}

			if ( reload ){
				// This will reload the page, it's likely better to store this until finished
				document.location.search = kvp.join( '&' );
			}
			else {
				// Get title of the current page
				let pageTitle = document.title;

				// Remove the empty ones
				kvp = kvp.filter(function( parameter ){
					return parameter != '';
				});

				// Push history and update URL
				window.history.pushState({}, pageTitle, '?' + kvp.join( '&' ) );
			}
		},

		restCall: function( endPoint, method, data, onSuccess, onFail ){
			// Do AJAX
			$.ajax({
				method: method,
				url:    UncannyAutomatorBackend.rest.url + '/' + endPoint + '/',
				data:   $.param( data ) + '&' + $.param({ doing_rest: 1 }),

				// Attach Nonce the the header of the request
				beforeSend: function( xhr ){
					xhr.setRequestHeader( 'X-WP-Nonce', UncannyAutomatorBackend.rest.nonce );
				},

				success: function( response ){
					// Check if the request succeeded
					if ( response.success ){
						// Check if onSuccess
						if ( UA_Utilities.isDefined( onSuccess ) ){
							// Invoke callback
							onSuccess( response );
						}
					}
					else {
						// The call was successful, but there were errors
						console.error( 'The call was successful, but there were errors.' );

						// Check if the onFail callback is defined
						if ( UA_Utilities.isDefined( onFail ) ){
							// Invoke callback
							onFail( response );
						}
					}
				},

				statusCode: {
					403: function(){
						location.reload();
					}
				},

				fail: function ( response ){
					if ( UA_Utilities.isDefined( onFail ) ){
						onFail( response );
					}
				},
			});
		},
	}
});

/**
 * Dropdowns
 * TODO: Move to dropdown.js
 */

document.addEventListener( 'DOMContentLoaded', () => {
	new UAP_Dropdowns();
});

class UAP_Dropdowns {
	constructor(){
		// Create dropdowns
		this.createDropdowns();
	}

	createDropdowns(){
		// Get elements
		[ ...document.querySelectorAll( '.uap-dropdown' ) ].forEach(( dropdown ) => {
			// Create a new dropdown
			new UAP_Dropdown( dropdown );
		});
	}
}

class UAP_Dropdown {
	constructor( dropdown = null ){
		// Check if the dropdown element exists
		if ( this.hasDropdown( dropdown ) ){
			// Get elements
			this.getElements( dropdown );

			// Listen clicks to toggle open/close
			this.listenClicks();
		}
	}

	getElements( dropdown ){
		this.$elements = {
			dropdown:  dropdown,
			toggleBtn: dropdown.querySelector( '.uap-dropdown-toggle' )
		}
	}

	listenClicks(){
		// Listen clicks
		this.$elements.toggleBtn.addEventListener( 'click', () => {
			// Toggle visibility
			this.toggleMenuVisibility();
		});
	}

	toggleMenuVisibility(){
		// Check if it's open
		if ( this.isOpen ){
			// Close it
			this.closeMenu();
		}
		else {
			// Open it
			this.openMenu();
		}
	}

	openMenu(){
		this.$elements.dropdown.classList.add( 'uap-dropdown--open' );

		// Listen clicks outside
		const listenClicksOutside = ( event ) => {
			// If the click happened outside, close the menu
			if ( ! event.target.closest( '.uap-dropdown' ) ){
				// Remove event listener
				document.removeEventListener( 'click', listenClicksOutside );

				// Close it
				this.closeMenu();
			}
		}

		// Listen clicks everywhere
		document.addEventListener( 'click', listenClicksOutside, false );
	}

	closeMenu(){
		this.$elements.dropdown.classList.remove( 'uap-dropdown--open' );
	}

	hasDropdown( dropdown ){
		return dropdown != null;
	}

	get isOpen(){
		return this.$elements.dropdown.classList.contains( 'uap-dropdown--open' );
	}
}

/**
 * Accordion
 * TODO: Move to accordion.js
 */

document.addEventListener( 'DOMContentLoaded', () => {
	new UAP_Accordions();
});

class UAP_Accordions {
	constructor(){
		// Create accordions
		this.createAccordions();
	}

	createAccordions(){
		// Iterate accordions on the DOM
		[ ...this.$elements.accordions ].forEach(( $accordion ) => {
			// Create accordions
			new UAP_Accordion( $accordion );
		});
	}

	get $elements(){
		return {
			accordions: document.querySelectorAll( '.uap-accordion' )
		}
	}
}

class UAP_Accordion {
	constructor( $accordion ){
		// Save the accordion element
		this.$accordion = $accordion;

		// Listen clicks to the toggles
		this.listenClicksToToggles();
	}

	listenClicksToToggles(){
		// Iterate toggles
		[ ...this.$elements.toggles ].forEach(( $toggle ) => {
			// Listen clicks to the toggle
			$toggle.addEventListener( 'click', ( event ) => {
				// Get parent element
				const $item = $toggle.closest( `.${ this.cssSelectors.itemClass }` );

				// Toggle item
				this.toggleItem( $item );
			});
		});
	}

	toggleItem( $item ){
		// Get the item elements
		let $itemElements = {
			toggle:  $item.querySelector( `.${ this.cssSelectors.itemToggleClass }` ),
			content: $item.querySelector( `.${ this.cssSelectors.itemContentClass }` )
		}

		// Check if the item is opened
		if ( $item.classList.contains( this.cssSelectors.itemOpenClass ) ){
			// Close it
			Automator_Utility.slideUp( $itemElements.content, {
				duration:   300,
				onComplete: () => {
					// Remove the "open" class from the item
					$item.classList
						.remove( this.cssSelectors.itemOpenClass );
				}
			});
		}
		else {
			// Open it

			// Add the "open" class
			$item.classList
				.add( this.cssSelectors.itemOpenClass );

			Automator_Utility.slideDown( $itemElements.content, {
				duration:   300
			});
		}
	}

	get $elements(){
		return {
			toggles: this.$accordion.querySelectorAll( `.${ this.cssSelectors.itemToggleClass }` )
		}
	}

	get cssSelectors(){
		return {
			itemClass:        'uap-accordion-item',
			itemOpenClass:    'uap-accordion-item--open',
			itemToggleClass:  'uap-accordion-item__toggle',
			itemContentClass: 'uap-accordion-item__content'
		}
	}
}

/**
 * Utilities
 * TODO: Move to utility.js
 */

class UAP_Utility {
	constructor(){}

	restCall( endPoint = null, data = null, onSuccess = null, onFail = null, options = {} ){
		// Do AJAX
		jQuery.ajax({
			method:   'POST',
			url:      `${ UncannyAutomatorBackend.rest.url }/${ endPoint }/`,
			data:     jQuery.param( data ) + '&' + jQuery.param({ doing_rest: 1 }),
			dataType: 'json',

			// Attach Nonce the the header of the request
			beforeSend: ( xhr ) => {
				xhr.setRequestHeader( 'X-WP-Nonce', UncannyAutomatorBackend.rest.nonce );
			},

			success: ( response ) => {
				// Check if the request succeeded
				if ( response.success ){
					// Check if onSuccess
					if ( this.isDefined( onSuccess ) ){
						// Invoke callback
						onSuccess( response );
					}
				}
				else {
					// The call was successful, but there were errors,
					console.error( 'The call was successful, but there were errors.' );

					// Check if the onFail callback is defined
					if ( this.isDefined( onFail ) ){
						// Invoke callback
						onFail( response );
					}
				}
			},

			statusCode: {
				403: ( response ) => {
					// Check if the onFail callback is defined
					if ( this.isDefined( onFail ) ){
						// Invoke callback
						onFail( response );
					}
				}
			},

			error: ( jqXHR, textStatus, errorThrown ) => {
				// Check if the onFail callback is defined
				if ( this.isDefined( onFail ) ){
					// Invoke callback
					onFail( {} );
				}
			},

			fail: ( response ) => {
				// Check if the onFail callback is defined
				if ( this.isDefined( onFail ) ){
					// Invoke callback
					onFail( response );
				}
			},
		});
	}

	isDefined( variable ){
		// Returns true if the variable is undefined
		return typeof variable !== 'undefined' && variable !== null;
	}

	slideDown( $element, { duration, onComplete } ){
		// Check if there is a custom duration defined
		if ( this.isDefined( duration ) ){
			$element
				.style.transitionDuration = `${ duration }ms`;
		}

		// Display the element
		$element
			.style.removeProperty( 'display' );

		// Set the height to auto to get the real height
		$element
			.style.height = 'auto';

		// Get the height
		const height = `${ $element.clientHeight }px`;

		// Set the height to 0 to prepare the element for the transition
		$element
			.style.height = '0px';

		setTimeout(() => {
			// Set the real height
			$element.style.height = height;

			// Check if there is a callback defined
			$element.addEventListener( 'transitionend', () => {
				// Remove the height property
				$element
					.style.removeProperty( 'height' );

				// Check if there is a custom duration defined
				if ( this.isDefined( duration ) ){
					$element
						.style.removeProperty( 'transition-duration' );
				}

				// Check if there is a callback defined
				if ( this.isDefined( onComplete ) ){
					try {
						onComplete();
					} catch ( e ){}
				}
			}, {
				// Invoke only once
				once: true
			});
		}, 0 );
	}

	slideUp( $element, { duration, onComplete } ){
		// Check if there is a custom duration defined
		if ( this.isDefined( duration ) ){
			$element
				.style.transitionDuration = `${ duration }ms`;
		}

		// Get the height
		const height = `${ $element.clientHeight }px`;

		// Set the height to { height } to prepare the element for the transition
		$element
			.style.height = height;

		setTimeout(() => {
			// Set the height to 0
			$element
				.style.height = '0px';

			$element.addEventListener( 'transitionend', () => {
				// Once finished, hide the element
				$element
					.style.display = 'none';

				// Remove the height property
				$element
					.style.removeProperty( 'height' );

				// Check if there is a custom duration defined
				if ( this.isDefined( duration ) ){
					$element
						.style.removeProperty( 'transition-duration' );
				}

				// Check if there is a callback defined
				if ( this.isDefined( onComplete ) ){
					try {
						onComplete();
					} catch ( e ){}
				}
			}, {
				// Invoke only once
				once: true
			});
		}, 0);
	}
}

const Automator_Utility = new UAP_Utility();
