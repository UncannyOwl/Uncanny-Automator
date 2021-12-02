/**
 * WPForms - Uncanny Automator tab
 * This file is loaded in the Uncanny Automator tab in WPForms
 * As this file is not getting compiled, it has to use
 * ES5 for better compability.
 */

/**
 * Invokes callback when DOM is ready
 *
 * @param {Function} callback The callback
 */
function onReady( callback ){
	// If the document isn't ready yet, add the event listener
	// eslint-disable-next-line @wordpress/no-global-event-listener
	document.addEventListener( 'DOMContentLoaded', callback );

	// If it's already loaded, invoke
	if ( document.readyState === 'interactive' || document.readyState === 'complete' ) {
		callback();
	}
}

/**
 * Display the correct submit button based on the current selected trigger
 */
var DisplayCorrectButton = {
	init: function(){
		// On load, show the correct button
		this.showCorrectButton();

		// Listen changes to the triggers
		this.listenTriggerChanges();
	},

	/**
	 * Shows the correct button based on the current selected trigger
	 * 
	 * @return {undefined}
	 */
	showCorrectButton: function(){
		// Get the current trigger
		var currentTriggerID = this.getCurrentTriggerID();

		// Hide all the submit buttons
		this.hideSubmitButtons();

		// Show the correct submit button
		this.showSubmitButton( currentTriggerID );
	},

	/**
	 * Listens changes to the trigger selection and invokes
	 * the method to show the correct button
	 * 
	 * @return {undefined}
	 */
	listenTriggerChanges: function(){
		// Instance reference
		var _this = this;

		// Listen changes
		var $triggers = this.$getTriggers();
		for ( var i = 0; i < $triggers.length; i++ ){
			// Add event listeners
			$triggers[ i ].addEventListener( 'change', function( event ){
				// Get selected option
				const selectedOption = event.currentTarget.value;

				// Unselect all options
				_this.deselectAllTriggers({
					except: selectedOption
				});

				// Show the correct button
				_this.showCorrectButton();
			});
		}
	},

	/**
	 * Returns the ID of the selected trigger
	 * 
	 * @return {String} The ID of the selected trigger
	 */
	getCurrentTriggerID: function(){
		// First, let's get the Node of the selected radio
		var $triggers = this.$getTriggers();
		var selectedTriggerID = null;

		for ( var i = 0, len = $triggers.length; i < len; ++i ){
			if ( $triggers[i].checked ){
				selectedTriggerID = $triggers[ i ].value;
			}
		}

		return selectedTriggerID;
	},

	/**
	 * Unselects all radio buttons
	 *
	 * @param  {Object}    options         Options
	 * @param  {String}    options.except  The value of the option we don't have to deselect
	 * @return {undefined}
	 */
	deselectAllTriggers: function( options ){
		// First, let's get the Nodes of the radios
		var $triggers = this.$getTriggers();

		// Check if options is defined
		var hasOptions = typeof options !== 'undefined' && typeof options.except !== 'undefined';

		// Iterate them
		for ( var i = 0, len = $triggers.length; i < len; ++i ){
			$triggers[i].checked = false;

			if ( hasOptions && options.except == $triggers[i].value ){
				$triggers[i].checked = true;
			}
		}
	},

	/**
	 * Hides all the submit buttons
	 * 
	 * @return {undefined}
	 */
	hideSubmitButtons: function(){
		// Iterate submit buttons and hide them
		var $submitButtons = this.$getSubmitButtons();
		for ( var i = 0; i < $submitButtons.length; i++ ){
			// Hide the buttons
			$submitButtons[ i ].style.display = 'none';
		}
	},

	/**
	 * Shows a submit button given an action ID
	 * 
	 * @param  {String} id The ID of the action to perform
	 * @return {undefined}    
	 */
	showSubmitButton: function( id ){
		// Get the button
		var $submitButton = this.$getSubmitButton( id );

		// Check if the button exists
		if ( $submitButton != null ){
			// Show button
			$submitButton.style.display = 'block';
		}
	},

	/**
	 * Returns the submit buttons
	 * 
	 * @return {NodeList} The submit buttons
	 */
	$getSubmitButtons: function(){
		return document.querySelectorAll( '.uap-wpf-integration-create-recipe-btn' );
	},

	/**
	 * Returns the triggers
	 * 
	 * @return {NodeList} The triggers
	 */
	$getTriggers: function(){
		return document.querySelectorAll( '.uap-wpf-integration-recipe-trigger-radio' );
	},

	/**
	 * Returns a submit button given an ID
	 * 
	 * @param  {String} id The ID of the action to perform
	 * @return {Node}      The button
	 */
	$getSubmitButton: function( id ){
		return document.querySelector( '.uap-wpf-integration-create-recipe-btn[data-id="' + id +'"]' );
	}
}

// Invoke when DOM is ready
onReady(function(){
	DisplayCorrectButton.init(); 
});