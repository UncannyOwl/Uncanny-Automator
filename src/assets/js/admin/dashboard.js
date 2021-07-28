document.addEventListener( 'DOMContentLoaded', () => {
	new UAP_Dashboard();
});

class UAP_Dashboard {
	constructor(){
		// Start the "Credits" section
		new UAP_Dashboard_Credits();
	}
}

class UAP_Dashboard_Credits {
	constructor(){
		// Set data
		this.setData();

		// Get credits left on load
		this.getCreditsLeft();

		// Get recipes using credits on load
		this.getRecipesUsingCredits();
	}

	setData(){
		this.data = {}
	}

	getCreditsLeft(){
		// Check if we should get the credits
		if ( 
			UncannyAutomatorDashboard.hasSiteConnected 
			&& ! UncannyAutomatorDashboard.isPro
		){
			// Do a request to get the credits left
			Automator_Utility.restCall(
				'get-credits',
				// No data, get it from the backend
				{},
				// On success
				( response ) => {
					// Check if the call was successful
					if ( response.success ){
						// Set total credits
						this.totalCredits = response.total_credits;

						// Set credits left
						this.creditsLeft  = response.credits_left;
					}
					else {
						// TODO: Show error
					}
				},
				// On fail
				( response ) => {
					// TODO: Show error
					console.error( response );
				}
			);
		}
	}

	getRecipesUsingCredits(){
		// Check if we should get the recipes
		if ( UncannyAutomatorDashboard.hasSiteConnected ){
			// Do a request to get the credits left
			Automator_Utility.restCall(
				'get-recipes-using-credits',
				// No data, get it from the backend
				{},
				// On success
				( response ) => {
					// Check if the call was successful
					if ( response.success ){
						// Set the recipes
						this.recipesUsingCredits = response.recipes;
					}
					else {
						// TODO: Show error
					}
				},
				// On fail
				( response ) => {
					// TODO: Show error
					console.error( response );
				}
			);
		}
	}

	get $elements(){
		return {
			creditsLeft: {
				box:         document.getElementById( 'uap-dashboard-credits-left' ),
				progressBar: document.getElementById( 'uap-dashboard-credits-left-progress-bar' ),
				left:        document.getElementById( 'uap-dashboard-credits-left-quantity' ),
				total:       document.getElementById( 'uap-dashboard-credits-left-total' ),
			},
			recipesUsingCredits: {
				content:     document.getElementById( 'uap-dashboard-credits-recipes-content' )
			}
		}
	}

	get totalCredits(){
		return this.data.totalCredits;
	}

	set totalCredits( totalCredits = 0 ){
		// Save data
		this.data.totalCredits = totalCredits;

		// Check if the DOM element is defined
		if ( Automator_Utility.isDefined( this.$elements.creditsLeft.total ) ){
			// Try to format the number
			try {
				// Update the UI
				this.$elements.creditsLeft.total
					.innerText = new Intl.NumberFormat().format( totalCredits );
			} catch ( e ){
				// Update the UI
				this.$elements.creditsLeft.total
					.innerText = totalCredits;
			}
		}	
	}

	get creditsLeft(){
		return this.data.creditsLeft;
	}

	set creditsLeft( creditsLeft = 0 ){
		// Update the UI
		// Start by getting the porcentage of used credits
		const percentageUsedCredits = ( creditsLeft / this.totalCredits ) * 100;

		// Set the percentage in the progress bar
		this.$elements.creditsLeft.progressBar
			.style.width = `${ percentageUsedCredits }%`;

		// Check if the DOM element is defined
		if ( Automator_Utility.isDefined( this.$elements.creditsLeft.left ) ){
			// Try to format the number
			try {
				// Set number
				this.$elements.creditsLeft.left
					.innerText = new Intl.NumberFormat().format( creditsLeft );
			} catch ( e ){
				// Set number
				this.$elements.creditsLeft.left
					.innerText = creditsLeft;
			}
		}	
	}

	set recipesUsingCredits( recipes = [] ){
		// Check if there are recipes
		if ( recipes.length > 0 ){
			// Create array with the LI elements of the list of recipes
			let layoutRecipes = [];

			recipes.forEach(( recipe ) => {
				// Add LI element with the recipe link
				layoutRecipes.push( `
					<tr>
						<td class="uap-dashboard-credits-recipes-table-cell--recipe-name">
							<a href="${ recipe.url }" target="_blank">
								${ recipe.title }
							</a>
						</td>
						<td class="uap-dashboard-credits-recipes-table-cell--allowed-completions">
							${
								recipe.type == 'user' ?
								'<div class="uap-dashboard-credits-recipes-table-allowed-completions--per-user">' + UncannyAutomatorDashboard.i18n.credits.recipesUsingCredits.table.perUser.replace( '%1$s', '<span>' + ( recipe.times_per_user == false || recipe.times_per_user == -1 ? UncannyAutomatorDashboard.i18n.credits.recipesUsingCredits.table.unlimited : recipe.times_per_user ) + '</span>' ) + '</div>'
								: ''
							}

							<div class="uap-dashboard-credits-recipes-table-allowed-completions--total">${ UncannyAutomatorDashboard.i18n.credits.recipesUsingCredits.table.total.replace( '%1$s', '<span>' + ( recipe.allowed_completions_total == false || recipe.allowed_completions_total == -1 ? UncannyAutomatorDashboard.i18n.credits.recipesUsingCredits.table.unlimited : recipe.allowed_completions_total ) + '</span>' ) }</div>
						</td>
						<td class="uap-dashboard-credits-recipes-table-cell--completed-runs">
							${ recipe.completed_runs }
						</td>
					</tr>
				` );
			});

			// Add it to the UI
			this.$elements.recipesUsingCredits.content.innerHTML = `
				<div class="uap-dashboard-box-content-scroll">
					<table class="uap-table">
						<thead>
							<tr>
								<th class="uap-dashboard-credits-recipes-table-cell--recipe-name">
									${ UncannyAutomatorDashboard.i18n.credits.recipesUsingCredits.table.recipe }
								</th>
								<th class="uap-dashboard-credits-recipes-table-cell--allowed-completions">
									${ UncannyAutomatorDashboard.i18n.credits.recipesUsingCredits.table.completionsAllowed }
								</th>
								<th class="uap-dashboard-credits-recipes-table-cell--completed-runs">
									${ UncannyAutomatorDashboard.i18n.credits.recipesUsingCredits.table.completedRuns }
								</th>
							</tr>
						</thead>
						<tbody>
							${ layoutRecipes.join( '' ) }
						</tbody>
					</table>
				</div>
			`;
		}
		else {
			// Add the "no recipes" message
			this.$elements.recipesUsingCredits.content.innerHTML = `
				<div class="uap-dashboard-credits-recipes__no-recipes">
					<span class="uap-text-secondary">
						<span class="uap-icon uap-icon--info-circle"></span> ${ UncannyAutomatorDashboard.i18n.credits.recipesUsingCredits.noRecipes }
					</span>
				</div>
			`;
		}
	}
}