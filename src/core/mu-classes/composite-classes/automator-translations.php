<?php

namespace Uncanny_Automator;

/**
 * Class Automator_Translations
 * @package uncanny_automator
 */
class Automator_Translations {

	/**
	 * Collection of error messages
	 * @var array
	 */
	private $ls = array();

	public function __construct() {
		$this->set_strings();
		do_action( 'uap_localized_string_after' );
	}

	/**
	 * Get the strings associated with the string key
	 *
	 * @param null|string $string_key
	 *
	 * @return null|string
	 */
	public function get( $string_key = null ) {

		if ( isset( $error_messages[ $string_key ] ) ) {
			$localized_string = $this->ls[ $string_key ];
		} else {
			return null;
		}

		/**
		 * Filters the specific string
		 */
		$localized_string = apply_filters( 'uap_localized_string', $localized_string, $string_key );

		return $localized_string;
	}

	/**
	 * Get get all translated strings
	 *
	 * @return array
	 */
	public function get_all() {
		$localized_strings = apply_filters( 'uap_localized_strings', $this->ls );

		return $localized_strings;
	}

	private function set_strings() {

		// Localized strings
		$this->ls = array(
			'trigger'            => array(
				'name'         => __( 'Triggers', 'uncanny-automator' ),
				// UncannyAutomator.i18n.trigger.name
				'singularName' => __( 'Trigger', 'uncanny-automator' ),
				// UncannyAutomator.i18n.trigger.singularName
				'userTriggers' => __( 'Logged-in triggers', 'uncanny-automator' ),
				// UncannyAutomator.i18n.trigger.userTriggers
				'add'          => __( 'Add trigger', 'uncanny-automator' ),
				// UncannyAutomator.i18n.trigger.add
				'addAnother'   => __( 'Add another trigger', 'uncanny-automator' ),
				// UncannyAutomator.i18n.trigger.addAnother
				'select'       => __( 'Select a trigger', 'uncanny-automator' ),
				// UncannyAutomator.i18n.trigger.select
				'search'       => __( 'Search for triggers', 'uncanny-automator' ),
				// UncannyAutomator.i18n.trigger.search
				'save'         => __( 'Save trigger', 'uncanny-automator' ),
				// UncannyAutomator.i18n.trigger.save
			),
			'action'             => array(
				'name'         => __( 'Actions', 'uncanny-automator' ),
				// UncannyAutomator.i18n.action.name
				'singularName' => __( 'Action', 'uncanny-automator' ),
				// UncannyAutomator.i18n.aciton.singularName
				'add'          => __( 'Add action', 'uncanny-automator' ),
				// UncannyAutomator.i18n.action.add
				'addAnother'   => __( 'Add another action', 'uncanny-automator' ),
				// UncannyAutomator.i18n.action.addAnother
				'select'       => __( 'Select an action', 'uncanny-automator' ),
				// UncannyAutomator.i18n.action.select
				'search'       => __( 'Search for actions', 'uncanny-automator' ),
				// UncannyAutomator.i18n.action.search
				'save'         => __( 'Save action', 'uncanny-automator' ),
				// UncannyAutomator.i18n.action.save
			),
			'closure'            => array(
				'name' => __( 'Closures', 'uncanny-automator' ), // UncannyAutomator.i18n.closure.name
			),
			'validation'         => array(
				'recipe' => array(
					'oneTrigger'   => __( 'You have to add at least one live trigger to your recipe', 'uncanny-automator' ),
					// UncannyAutomator.i18n.validation.recipe.oneTrigger
					'oneAction'    => __( 'You have to add at least one live action to your recipe', 'uncanny-automator' ),
					// UncannyAutomator.i18n.validation.recipe.oneAction
					'liveItems'    => __( 'Add live items to your recipe', 'uncanny-automator' ),
					// UncannyAutomator.i18n.validation.recipe.liveItems
					'userSelector' => __( 'Specify the user the actions will be run on', 'uncanny-automator' ),
					// UncannyAutomator.i18n.validation.recipe.userSelector
				),
				'field'  => array(
					'select'      => array(
						'empty'           => __( 'Please select a value from the dropdown list.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.select.empty
						'invalid'         => __( 'Please select a valid option.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.select.invalid
						'errorLoading'    => __( 'The results could not be loaded', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.select.errorLoading
						'tooLong'         => array(
							'singular' => __( 'Please delete one character', 'uncanny-automator' ),
							// UncannyAutomator.i18n.validation.field.select.tooLong.singular
							'plural'   => _x( 'Please delete %s characters', '%s is the number of characters the user has to delete.', 'uncanny-automator' ),
							// UncannyAutomator.i18n.validation.field.select.tooLong.plural
						),
						'tooShort'        => _x( 'Please enter %s or more characters', '%s is the number of characters the user has to add.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.select.tooShort
						'maximumSelected' => array(
							'singular' => __( 'You can only select one option', 'uncanny-automator' ),
							// UncannyAutomator.i18n.validation.field.select.maximumSelected.singular
							'plural'   => _x( 'You can only select %s options', '%s is the number of options the user can select in the dropdown.', 'uncanny-automator' ),
							// UncannyAutomator.i18n.validation.field.select.maximumSelected.plural
						),
						// UncannyAutomator.i18n.validation.field.select.maximumSelected
						'otherOptions' => __( 'Other options', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.select.otherOptions
						'customValue'  => __( 'Use a custom value', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.select.customValue
					),
					'text'        => array(
						'empty'   => __( 'Please fill out this field.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.text.empty
						'invalid' => __( 'Please fill out this field with a valid value.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.text.invalid
					),
					'textarea'    => array(
						'empty'   => __( 'Please fill out this field.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.textarea.empty
						'invalid' => __( 'Please fill out this field with a valid value.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.textarea.invalid
					),
					'int'         => array(
						'empty'          => __( 'Please enter a number.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.int.empty
						'invalid'        => __( 'Please enter a valid whole number (no decimal places).', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.int.invalid
						'multipleTokens' => __( 'This field only supports one token at a time.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.int.multipleTokens
					),
					'float'       => array(
						'empty'          => __( 'Please enter a number.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.float.empty
						'invalid'        => __( 'Please enter a valid number.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.float.invalid
						'multipleTokens' => __( 'This field only supports one token at a time.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.float.multipleTokens
					),
					'email'       => array(
						'empty'    => __( 'Please enter an email address.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.email.empty
						'single'   => array(
							'invalid'        => __( 'Please enter a valid email address.', 'uncanny-automator' ),
							// UncannyAutomator.i18n.validation.field.email.single.invalid
							'multipleTokens' => __( 'This field only supports one token per email.', 'uncanny-automator' ),
							// UncannyAutomator.i18n.validation.field.email.single.multipleTokens
						),
						'multiple' => array(
							'invalid'        => __( 'Please enter a list of valid email addresses.', 'uncanny-automator' ),
							// UncannyAutomator.i18n.validation.field.email.multiple.invalid
							'multipleTokens' => __( 'This field only supports one token per email.', 'uncanny-automator' ),
							// UncannyAutomator.i18n.validation.field.email.multiple.multipleTokens
						),
					),
					'url'         => array(
						'empty'   => __( 'Please enter a url.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.url.empty
						'invalid' => __( 'Please enter a valid url.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.url.invalid
					),
					'checkbox'       => array(
						'empty'   => __( 'Please check this checkbox.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.checkbox.empty
						'invalid' => __( 'Please select valid options.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.checkbox.invalid
					),
					'radio'       => array(
						'empty'   => __( 'Please select one option.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.radio.empty
						'invalid' => __( 'Please select a valid option.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.radio.invalid
					),
					'invalidType' => __( 'Invalid field type.', 'uncanny-automator' ),
					// UncannyAutomator.i18n.validation.field.invalidType
				),
			),
			'status'             => array(
				'live'  => __( 'Live', 'uncanny-automator' ), // UncannyAutomator.i18n.status.live
				'draft' => __( 'Draft', 'uncanny-automator' ), // UncannyAutomator.i18n.status.draft
			),
			'tokens'             => array(
				'search' => __( 'Search tokens', 'uncanny-automator' ), // UncannyAutomator.i18n.tokens.search
				'tokenType' => array(
					'text'  => __( 'Text', 'uncanny-automator' ),
					'email' => __( 'Email', 'uncanny-automator' ),
					'url'   => __( 'URL', 'uncanny-automator' ),
					'float' => __( 'Float', 'uncanny-automator' ),
					'int'   => __( 'Integer', 'uncanny-automator' ),
				),
				'global' => array(
					'siteName'          => _x( 'Site name', 'Token name', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.siteName
					'userID'      => _x( 'User ID', 'Token name', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.userId
					'userUsername'      => _x( 'User username', 'Token name', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.userUsername
					'userFirstName'     => _x( 'User first name', 'Token name', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.userFirstName
					'userLastName'      => _x( 'User last name', 'Token name', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.userLastName
					'userEmail'         => _x( 'User email', 'Token name', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.userEmail
					'userDisplay'       => _x( 'User display name', 'Token name', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.userDisplay
					'userResetPassLink' => _x( 'User reset password URL', 'Token name', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.userDisplay
					'adminEmail'        => _x( 'Admin email', 'Token name', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.adminEmail
					'siteUrl'           => _x( 'Site URL', 'Token name', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.siteUrl
					'recipeName'        => _x( 'Recipe name', 'Token name', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.recipeName
					'advanced'          => _x( 'Advanced', 'Token category', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.advanced
					'userMetaKey'       => _x( 'User meta key', 'Token name', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.userMetaKey
					'userMetaKeyEmail'  => _x( 'Email from user meta key', 'Token name', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.userMetaKey
					'currentDate'       => _x( 'Current date', 'Token name', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.currentDate
					'currentTime'       => _x( 'Current time', 'Token name', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.currentTime
				),
			),
			'integration'        => array(
				'select' => __( 'Select an integration', 'uncanny-automator' ),
				// UncannyAutomator.i18n.integration.select
			),
			'publish'            => array(
				'timesPerUser' => __( 'Times per user:', 'uncanny-automator' ),
				// UncannyAutomator.i18n.publish.timesPerUser
				'leaveEmpty'   => __( 'Leave empty for unlimited times', 'uncanny-automator' ),
				// UncannyAutomator.i18n.publish.leaveEmpty
				'exampleOne'   => __( 'Unlimited', 'uncanny-automator' ),
				// UncannyAutomator.i18n.publish.exampleOne
				'oneOrBigger'  => __( 'This number has to be 1 or bigger', 'uncanny-automator' ),
				// UncannyAutomator.i18n.publish.oneOrBigger
				'createdOn'    => __( 'Created on:', 'uncanny-automator' ),
				// UncannyAutomator.i18n.publish.createdOn
				'recipeType'   => __( 'Type:', 'uncanny-automator' ),
				// UncannyAutomator.i18n.publish.recipeType
			),
			'confirmationModal'  => array(
				'title'         => __( 'Are you sure?', 'uncanny-automator' ),
				// UncannyAutomator.i18n.confirmationModal.title
				'deleteWarning' => _x( 'Deleting items in a {{live}} recipe can lead to unexpected behaviors.', 'Words between double curly braces will be bold', 'uncanny-automator' ),
				// UncannyAutomator.i18n.confirmationModal.deleteWarning
				'statusWarning' => _x( 'Setting items to draft in a {{live}} recipe can lead to unexpected behaviors.', 'Words between double curly braces will be bold', 'uncanny-automator' ),
				// UncannyAutomator.i18n.confirmationModal.statusWarning
			),
			'proOnly'            => array(
				'warning'          => __( 'This is a pro feature.', 'uncanny-automator' ),
				// UncannyAutomator.i18n.proOnly.warning
				'multipleTriggers' => _x( 'This recipe contains multiple triggers and requires %s.', '%s is "Uncanny Automator Pro". Use %s.', 'uncanny-automator' ),
				// UncannyAutomator.i18n.proOnly.multipleTriggers
				'proActive'        => __( 'Please ensure the plugin is installed and activated.', 'uncanny-automator' ),
				// UncannyAutomator.i18n.proOnly.proActive
				'proToPublish'     => _x( 'Please install %s to activate this recipe.', '%s is "Uncanny Automator Pro". Use %s.', 'uncanny-automator' ),
				// UncannyAutomator.i18n.proOnly.proToPublish
				'moveToTrash'      => __( 'Move to trash', 'uncanny-automator' ),
				// UncannyAutomator.i18n.proOnly.moveToTrash
			),
			'sendFeedback'       => array(
				'title'         => __( 'Send feedback', 'uncanny-automator' ),
				// UncannyAutomator.i18n.reportBug.title
				'message'       => _x( 'Help us improve %s! Click the icon below to send feedback', '%s is "Uncanny Automator". Use %s.', 'uncanny-automator' ),
				// UncannyAutomator.i18n.reportBug.message
				'dontShowAgain' => __( "Don't show again", 'uncanny-automator' ),
				// UncannyAutomator.i18n.reportBug.dontShowAgain
				'gotIt'         => __( 'Got it', 'uncanny-automator' ),
				// UncannyAutomator.i18n.reportBug.gotIt
			),
			'recipeType'         => array(
				'title'                       => __( 'Select a recipe type:', 'uncanny-automator' ),
				// UncannyAutomator.i18n.recipeType.title
				'userRecipeName'              => __( 'Logged-in', 'uncanny-automator' ),
				// UncannyAutomator.i18n.recipeType.userRecipeName
				'userRecipeDescription'       => __( 'Typically triggered by logged-in users; supports multiple triggers and many integrations', 'uncanny-automator' ),
				// UncannyAutomator.i18n.recipeType.userRecipeDescription
				'cantChangeLaterNotice'       => __( "Note: Recipe type cannot be changed later.", 'uncanny-automator' ),
				// UncannyAutomator.i18n.recipeType.cantChangeLaterNotice
				'errorDidNotSelectType'       => __( 'Please select an option.', 'uncanny-automator' ),
				// UncannyAutomator.i18n.recipeType.errorDidNotSelectType
				'errorTryingToSaveOtherValue' => __( 'Error when saving value.', 'uncanny-automator' ),
				// UncannyAutomator.i18n.recipeType.errorTryingToSaveOtherValue
				'errorSomethingWentWrong'     => __( 'Sorry, something went wrong. Please try again.', 'uncanny-automator' ),
				// UncannyAutomator.i18n.recipeType.errorSomethingWentWrong
			),
			'userSelector'       => array(
				'firstStepTitle' => __( 'Choose who will do the actions', 'uncanny-automator' ),
				// UncannyAutomator.i18n.userSelector.firstStepTitle
				'setOptions'     => __( 'Set user data', 'uncanny-automator' ),
				// UncannyAutomator.i18n.userSelector.setOptions
				'sentenceTitle'  => __( 'Actions will be run on...', 'uncanny-automator' ),
				// UncannyAutomator.i18n.userSelector.sentenceTitle
				'logUserIn'      => __( 'Log the new user in?', 'uncanny-automator' ),
				// UncannyAutomator.i18n.userSelector.logUserIn
				'userType'       => array(
					'existing'   => __( 'Existing user', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.userType.existing
					'new'        => __( 'New user', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.userType.new
				),
				'summary' => array(
					'matches'   => __( '%1$s matches %2$s', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.summary.matches
					'email'     => _x( 'Email', 'This text is used after "%1$s matches %2$s', 'quickbooks-training' ),
					// UncannyAutomator.i18n.userSelector.summary.email
					'id'        => _x( 'ID', 'This text is used after "%1$s matches %2$s', 'quickbooks-training' ),
					// UncannyAutomator.i18n.userSelector.summary.id
					'username'  => _x( 'Username', 'This text is used after "%1$s matches %2$s', 'quickbooks-training' ),
					// UncannyAutomator.i18n.userSelector.summary.username
					'withEmail' => __( 'With the email %1$s', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.summary.withEmail
					'otherwise' => __( 'Otherwise, %1$s', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.summary.otherwise
					'doNothing' => _x( 'do nothing', 'This text is used after "Otherwise, %1$s"', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.summary.doNothing
					'createNewUser' => _x( 'create new user', 'This text is used after "Otherwise, %1$s"', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.summary.createNewUser
					'selectExistingUser' => _x( 'select existing user', 'This text is used after "Otherwise, %1$s"', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.summary.selectExistingUser
				),
				'existingUser' => array(
					'uniqueFieldLabel' => __( 'Unique field', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.existingUser.uniqueFieldLabel
					'uniqueFieldPlaceholder'    => __( 'Select a unique field', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.existingUser.uniqueFieldPlaceholder
					'uniqueFieldOptionEmail'    => __( 'Email', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.existingUser.uniqueFieldOptionEmail
					'uniqueFieldOptionId'       => __( 'ID', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.existingUser.uniqueFieldOptionId
					'uniqueFieldOptionUsername' => __( 'Username', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.existingUser.uniqueFieldOptionUsername
					'valueFieldLabel' => __( 'Value', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.existingUser.valueFieldLabel
					'valueFieldPlaceholder'     => __( 'Value of the unique field', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.existingUser.valueFieldPlaceholder
					'createNewUserFieldLabel'   => __( "What to do if the user doesn't exist", 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.existingUser.createNewUserFieldLabel
					'createNewUserFieldOptionCreateUser' => __( 'Create new user', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.existingUser.createNewUserFieldOptionCreateUser
					'createNewUserFieldOptionDoNothing' => __( 'Do nothing', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.existingUser.createNewUserFieldOptionDoNothing
					'doNothingMessage' => __( 'If no user matches the unique field and value then the actions are not going to be executed.', 'uncanny-automator' )
					// UncannyAutomator.i18n.userSelector.existingUser.doNothingMessage
				),
				'newUser' => array(
					'firstName' => __( 'First name', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.firstName
					'lastName' => __( 'Last name', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.lastName
					'email' => __( 'Email', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.email
					'username' => __( 'Username', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.username
					'displayName' => __( 'Display name', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.displayName
					'password' => __( 'Password', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.password
					'passwordDescription' => __( 'If left empty, you will need to create an action to send the automatically generated password to the user.', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.passwordDescription
					'roles' => __( 'Roles', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.roles
					'existingUserFieldLabel' => __( 'What to do if the user already exists', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.existingUserFieldLabel
					'existingUserFieldOptionExisting' => __( 'Select existing user', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.existingUserFieldOptionExisting
					'existingUserFieldOptionDoNothing' => __( 'Do nothing', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.existingUserFieldOptionDoNothing
					'doNothingMessage' => __( 'If there is already a user with the defined email address or username, the actions are not going to be executed.', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.doNothingMessage
					'prioritizedFieldLabel' => __( 'Prioritized field', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.prioritizedFieldLabel
					'prioritizedFieldDescription' => __( 'Select the field that should be prioritized if, during the creating of the user, two different users are found (one that matches the email field and another one that matches the username field).', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.prioritizedFieldDescription
				),
			),
			'title'              => array(
				'saving' => __( 'Saving title', 'uncanny-automator' ), // UncannyAutomator.i18n.title.saving
			),
			'noResults'          => __( 'No results found', 'uncanny-automator' ),
			// UncannyAutomator.i18n.noResults
			'save'               => __( 'Save', 'uncanny-automator' ),
			// UncannyAutomator.i18n.save
			'saved'              => __( 'Saved', 'uncanny-automator' ),
			// UncannyAutomator.i18n.saved
			'search'             => __( 'Search', 'uncanny-automator' ),
			// UncannyAutomator.i18n.search
			'searching'          => __( 'Searching', 'uncanny-automator' ),
			// UncannyAutomator.i18n.searching
			'confirm'            => __( 'Confirm', 'uncanny-automator' ),
			// UncannyAutomator.i18n.confirm
			'cancel'             => __( 'Cancel', 'uncanny-automator' ),
			// UncannyAutomator.i18n.cancel
			'delete'             => __( 'Delete', 'uncanny-automator' ),
			// UncannyAutomator.i18n.delete
			'edit'               => __( 'Edit', 'uncanny-automator' ),
			// UncannyAutomator.i18n.edit
			'support'            => __( 'Support', 'uncanny-automator' ),
			// UncannyAutomator.i18n.support
			'unlimited'          => __( 'Unlimited', 'uncanny-automator' ),
			// UncannyAutomator.i18n.unlimited
			'learnMore'          => __( 'Learn more', 'uncanny-automator' ),
			// UncannyAutomator.i18n.learnMore
			'trueLabel'          => __( 'True', 'uncanny-automator' ),
			// UncannyAutomator.i18n.trueLabel
			'falseLabel'         => __( 'False', 'uncanny-automator' ),
			// UncannyAutomator.i18n.falseLabel
			'loadingMoreResults' => __( 'Loading more results...', 'uncanny-automator' ),
			// UncannyAutomator.i18n.loadingMoreResults
			'itemMissing'        => __( 'This item was disabled because it could not be found on the system. To re-enable, ensure the associated plugin is installed and activated.', 'uncanny-automator' ),
			// UncannyAutomator.i18n.itemMissing
			'addRow'             => __( 'Add row', 'uncanny-automator' ),
			// UncannyAutomator.i18n.addRow
			'removeRow'          => __( 'Remove row', 'uncanny-automator' ),
			// UncannyAutomator.i18n.removeRow
			'rowNumber'          => __( 'Row %s', 'uncanny-automator' ),
			// UncannyAutomator.i18n.rowNumber
			'yes'                => __( 'Yes', 'uncanny-automator' ),
			// UncannyAutomator.i18n.yes
			'no'                 => __( 'No', 'uncanny-automator' ),
			// UncannyAutomator.i18n.no
		);
	}
}
