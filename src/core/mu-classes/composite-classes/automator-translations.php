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
				/* translators: Trigger type. Logged-in triggers are triggered only by logged-in users */
				'userTriggers' => __( 'Logged-in triggers', 'uncanny-automator' ),
				// UncannyAutomator.i18n.trigger.userTriggers
				/* translators: Non-personal infinitive verb */
				'add'          => __( 'Add trigger', 'uncanny-automator' ),
				// UncannyAutomator.i18n.trigger.add
				/* translators: Non-personal infinitive verb */
				'addAnother'   => __( 'Add another trigger', 'uncanny-automator' ),
				// UncannyAutomator.i18n.trigger.addAnother
				/* translators: Verb conjugated in present-tense second-person singular */
				'select'       => __( 'Select a trigger', 'uncanny-automator' ),
				// UncannyAutomator.i18n.trigger.select
				/* translators: Non-personal infinitive verb */
				'search'       => __( 'Search for triggers', 'uncanny-automator' ),
				// UncannyAutomator.i18n.trigger.search
				/* translators: Non-personal infinitive verb */
				'save'         => __( 'Save trigger', 'uncanny-automator' ),
				// UncannyAutomator.i18n.trigger.save
			),
			'action'             => array(
				'name'         => __( 'Actions', 'uncanny-automator' ),
				// UncannyAutomator.i18n.action.name
				'singularName' => __( 'Action', 'uncanny-automator' ),
				// UncannyAutomator.i18n.aciton.singularName
				/* translators: Non-personal infinitive verb */
				'add'          => __( 'Add action', 'uncanny-automator' ),
				// UncannyAutomator.i18n.action.add
				/* translators: Non-personal infinitive verb */
				'addAnother'   => __( 'Add another action', 'uncanny-automator' ),
				// UncannyAutomator.i18n.action.addAnother
				/* translators: Verb conjugated in present-tense second-person singular */
				'select'       => __( 'Select an action', 'uncanny-automator' ),
				// UncannyAutomator.i18n.action.select
				/* translators: Non-personal infinitive verb */
				'search'       => __( 'Search for actions', 'uncanny-automator' ),
				// UncannyAutomator.i18n.action.search
				/* translators: Non-personal infinitive verb */
				'save'         => __( 'Save action', 'uncanny-automator' ),
				// UncannyAutomator.i18n.action.save
			),
			'closure'            => array(
				'name' => 'Closures', // UncannyAutomator.i18n.closure.name
			),
			'validation'         => array(
				'recipe' => array(
					'oneTrigger'   => __( 'You have to add at least one live trigger to your recipe', 'uncanny-automator' ),
					// UncannyAutomator.i18n.validation.recipe.oneTrigger
					'oneAction'    => __( 'You have to add at least one live action to your recipe', 'uncanny-automator' ),
					// UncannyAutomator.i18n.validation.recipe.oneAction
					/* translators: Verb conjugated in present-tense second-person singular */
					'liveItems'    => __( 'Add live items to your recipe', 'uncanny-automator' ),
					// UncannyAutomator.i18n.validation.recipe.liveItems
					/* translators: Verb conjugated in present-tense second-person singular */
					'userSelector' => __( 'Specify the user the actions will be run on', 'uncanny-automator' ),
					// UncannyAutomator.i18n.validation.recipe.userSelector
				),
				'field'  => array(
					'select'      => array(
						'empty'           => __( 'Please select a value from the dropdown list.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.select.empty
						'invalid'         => __( 'Please select a valid option.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.select.invalid
						'otherOptions' => __( 'Other options', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.select.otherOptions
						/* translators: Non-personal infinitive verb */
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
						'empty'   => __( 'Please enter a URL.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.url.empty
						'invalid' => __( 'Please enter a valid URL.', 'uncanny-automator' ),
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
				/* translators: Recipe status */
				'liveRecipe'      => _x( 'Live', 'Recipe', 'uncanny-automator' ),
				// UncannyAutomator.i18n.status.liveRecipe
				/* translators: Recipe status */
				'draftRecipe'     => _x( 'Draft', 'Recipe', 'uncanny-automator' ),
				// UncannyAutomator.i18n.status.draftRecipe
				/* translators: Recipe item status */
				'liveRecipeItem'  => _x( 'Live', 'Recipe item', 'uncanny-automator' ),
				// UncannyAutomator.i18n.status.liveRecipeItem
				/* translators: Recipe item status */
				'draftRecipeItem' => _x( 'Draft', 'Recipe item', 'uncanny-automator' ),
				// UncannyAutomator.i18n.status.draftRecipeItem
			),
			'tokens'             => array(
				/* translators: Non-personal infinitive verb */
				'search' => __( 'Search tokens', 'uncanny-automator' ), // UncannyAutomator.i18n.tokens.search
				'tokenType' => array(
					/* translators: Token type */
					'text'  => _x( 'Text', 'Token', 'uncanny-automator' ),
					/* translators: Token type */
					'email' => _x( 'Email', 'Token', 'uncanny-automator' ),
					/* translators: Token type */
					'url'   => _x( 'URL', 'Token', 'uncanny-automator' ),
					/* translators: Token type */
					'float' => _x( 'Float', 'Token', 'uncanny-automator' ),
					/* translators: Token type */
					'int'   => _x( 'Integer', 'Token', 'uncanny-automator' ),
				),
				'global' => array(
					/* translators: Token category. It refers to common tokens. */
					'common'            => _x( 'Common', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.common
					/* translators: Token name */
					'siteName'          => _x( 'Site name', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.siteName
					/* translators: Token name */
					'userID'            => _x( 'User ID', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.userId
					/* translators: Token name */
					'userUsername'      => _x( 'User username', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.userUsername
					/* translators: Token name */
					'userFirstName'     => _x( 'User first name', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.userFirstName
					/* translators: Token name */
					'userLastName'      => _x( 'User last name', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.userLastName
					/* translators: Token name */
					'userEmail'         => _x( 'User email', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.userEmail
					/* translators: Token name */
					'userDisplay'       => _x( 'User display name', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.userDisplay
					/* translators: Token name */
					'userResetPassLink' => _x( 'User reset password URL', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.userDisplay
					/* translators: Token name */
					'adminEmail'        => _x( 'Admin email', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.adminEmail
					/* translators: Token name */
					'siteUrl'           => _x( 'Site URL', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.siteUrl
					/* translators: Token name */
					'recipeName'        => _x( 'Recipe name', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.recipeName
					/* translators: Token category. It refers to advanced tokens. */
					'advanced'          => _x( 'Advanced', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.advanced
					/* translators: Token name */
					'userMetaKey'       => _x( 'User meta key', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.userMetaKey
					/* translators: Token name */
					'currentDate'       => _x( 'Current date', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.currentDate
					/* translators: Token name */
					'currentTime'       => _x( 'Current time', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.currentTime
				),
			),
			'integration'        => array(
				/* translators: Verb conjugated in present-tense second-person singular */
				'select'         => __( 'Select an integration', 'uncanny-automator' ),
				// UncannyAutomator.i18n.integration.select
				/* translators: Verb conjugated in present-tense second-person singular */
				'discoverMore'   => __( 'Discover more awesome integrations.', 'uncanny-automator' ),
				// UncannyAutomator.i18n.integration.discoverMore
			),
			'publish'            => array(
				/* translators: Number of times the recipe can be triggered per user */
				'timesPerUser' => __( 'Times per user:', 'uncanny-automator' ),
				// UncannyAutomator.i18n.publish.timesPerUser
				/* translators: Verb conjugated in present-tense second-person singular */
				'leaveEmpty'   => __( 'Leave empty for unlimited times', 'uncanny-automator' ),
				// UncannyAutomator.i18n.publish.leaveEmpty
				/* translators: Unlimited times */
				'unlimited'    => _x( 'Unlimited', 'Publish', 'uncanny-automator' ),
				// UncannyAutomator.i18n.publish.unlimited
				'oneOrBigger'  => __( 'This number has to be 1 or bigger', 'uncanny-automator' ),
				// UncannyAutomator.i18n.publish.oneOrBigger
				/* translators: Recipe creation date */
				'createdOn'    => _x( 'Created on:', 'Recipe', 'uncanny-automator' ),
				// UncannyAutomator.i18n.publish.createdOn
				/* translators: Recipe type */
				'recipeType'   => _x( 'Type:', 'Recipe', 'uncanny-automator' ),
				// UncannyAutomator.i18n.publish.recipeType
			),
			'confirmationModal'  => array(
				'title'         => __( 'Are you sure?', 'uncanny-automator' ),
				// UncannyAutomator.i18n.confirmationModal.title
				'deleteWarning' => __( 'Deleting items in a {{live}} recipe can lead to unexpected behaviors.', 'uncanny-automator' ),
				// UncannyAutomator.i18n.confirmationModal.deleteWarning
				'statusWarning' => __( 'Setting items to draft in a {{live}} recipe can lead to unexpected behaviors.', 'uncanny-automator' ),
				// UncannyAutomator.i18n.confirmationModal.statusWarning
			),
			'proOnly'            => array(
				'pro'              => 'Pro',
				// Don't make this string translatable
				// UncannyAutomator.i18n.proOnly.pro
				/* translators: 1. Trademarked term */
				'warning'          => sprintf( __( 'This is a %1$s feature.', 'uncanny-automator' ), 'Pro' ),
				// UncannyAutomator.i18n.proOnly.warning
				/* translators: 1. Plugin name */
				'multipleTriggers' => __( 'This recipe contains multiple triggers and requires %1$s.', 'uncanny-automator' ),
				// UncannyAutomator.i18n.proOnly.multipleTriggers
				'proActive'        => __( 'Please ensure the plugin is installed and activated.', 'uncanny-automator' ),
				// UncannyAutomator.i18n.proOnly.proActive
				/* translators: 1. Plugin name */
				'proToPublish'     => __( 'Please install %1$s to activate this recipe.', 'uncanny-automator' ),
				// UncannyAutomator.i18n.proOnly.proToPublish
				/* translators: Non-personal infinitive verb */
				'moveToTrash'      => __( 'Move to trash', 'uncanny-automator' ),
				// UncannyAutomator.i18n.proOnly.moveToTrash
				// 'unlockTriggers'   => sprintf( __( 'Get %s to unlock these triggers', 'uncanny-automator' ), 'Uncanny Automator Pro' ),
				// UncannyAutomator.i18n.proOnly.unlockTriggers
				// 'unlockActions'    => sprintf( __( 'Get %s to unlock these actions', 'uncanny-automator' ), 'Uncanny Automator Pro' ),
				// UncannyAutomator.i18n.proOnly.unlockActions
				/* translators: 1. Trademarked term */
				'requiresPro'      => __( 'Requires %1$s', 'uncanny-automator' ),
				// UncannyAutomator.i18n.proOnly.requiresPro
			),
			'sendFeedback'       => array(
				/* translators: Non-personal infinitive verb */
				'title'         => __( 'Send feedback', 'uncanny-automator' ),
				// UncannyAutomator.i18n.sendFeedback.title
				/* translators: 1. Plugin name */
				// 'message'       => __( 'Help us improve %1$s! Click the icon below to send feedback', 'uncanny-automator' ),
				// UncannyAutomator.i18n.sendFeedback.message
				// 'dontShowAgain' => __( 'Don\'t show again', 'uncanny-automator' ),
				// UncannyAutomator.i18n.sendFeedback.dontShowAgain
				// 'gotIt'         => __( 'Got it', 'uncanny-automator' ),
				// UncannyAutomator.i18n.sendFeedback.gotIt
			),
			'recipeType'         => array(
				/* translators: Verb conjugated in present-tense second-person singular */
				'title'                       => __( 'Select a recipe type', 'uncanny-automator' ),
				// UncannyAutomator.i18n.recipeType.title
				/* translators: Recipe type. Logged-in recipes are triggered only by logged-in users */
				'userRecipeName'              => _x( 'Logged-in', 'Recipe', 'uncanny-automator' ),
				// UncannyAutomator.i18n.recipeType.userRecipeName
				'userRecipeDescription'       => __( 'Triggered by logged-in users; supports multiple triggers and many integrations', 'uncanny-automator' ),
				// UncannyAutomator.i18n.recipeType.userRecipeDescription
				'cantChangeLaterNotice'       => __( 'Note: Recipe type cannot be changed later.', 'uncanny-automator' ),
				// UncannyAutomator.i18n.recipeType.cantChangeLaterNotice
				'errorDidNotSelectType'       => __( 'Please select an option.', 'uncanny-automator' ),
				// UncannyAutomator.i18n.recipeType.errorDidNotSelectType
				'errorTryingToSaveOtherValue' => __( 'Error when saving value.', 'uncanny-automator' ),
				// UncannyAutomator.i18n.recipeType.errorTryingToSaveOtherValue
				'errorSomethingWentWrong'     => __( 'Sorry, something went wrong. Please try again.', 'uncanny-automator' ),
				// UncannyAutomator.i18n.recipeType.errorSomethingWentWrong
			),
			'userSelector'       => array(
				/* translators: Verb conjugated in present-tense second-person singular */
				'firstStepTitle' => __( 'Choose who will do the actions', 'uncanny-automator' ),
				// UncannyAutomator.i18n.userSelector.firstStepTitle
				/* translators: Verb conjugated in present-tense second-person singular */
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
					/* translators: 1. Field name, 2. Field value */
					'matches'   => _x( '%1$s matches %2$s', 'User selector', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.summary.matches
					'email'     => __( 'Email', 'quickbooks-training' ),
					// UncannyAutomator.i18n.userSelector.summary.email
					'id'        => __( 'ID', 'quickbooks-training' ),
					// UncannyAutomator.i18n.userSelector.summary.id
					'username'  => __( 'Username', 'quickbooks-training' ),
					// UncannyAutomator.i18n.userSelector.summary.username
					/* translators: 1. An email address */
					'withEmail' => _x( 'With the email %1$s', 'User selector', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.summary.withEmail
					/* translators: 1. An action */
					'otherwise' => _x( 'Otherwise, %1$s', 'User selector', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.summary.otherwise
					/* translators: Non-personal infinitive verb. It's used after "Otherwise, %1$s" */
					'doNothing' => _x( 'do nothing', 'User selector', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.summary.doNothing
					/* translators: Non-personal infinitive verb. It's used after "Otherwise, %1$s" */
					'createNewUser' => _x( 'create a new user', 'User selector', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.summary.createNewUser
					/* translators: Non-personal infinitive verb. It's used after "Otherwise, %1$s" */
					'selectExistingUser' => _x( 'select an existing user', 'User selector', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.summary.selectExistingUser
				),
				'existingUser' => array(
					'uniqueFieldLabel' => __( 'Unique field', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.existingUser.uniqueFieldLabel
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
					/* translators: Non-personal infinitive verb */
					'createNewUserFieldOptionCreateUser' => __( 'Create new user', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.existingUser.createNewUserFieldOptionCreateUser
					/* translators: Non-personal infinitive verb */
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
					/* translators: WordPress roles */
					'roles' => __( 'Roles', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.roles
					'existingUserFieldLabel' => __( 'What to do if the user already exists', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.existingUserFieldLabel
					/* translators: Non-personal infinitive verb */
					'existingUserFieldOptionExisting' => __( 'Select existing user', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.existingUserFieldOptionExisting
					/* translators: Non-personal infinitive verb */
					'existingUserFieldOptionDoNothing' => __( 'Do nothing', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.existingUserFieldOptionDoNothing
					'doNothingMessage' => __( 'If there is already a user with the defined email address or username, the actions are not going to be executed.', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.doNothingMessage
					'prioritizedFieldLabel' => __( 'Prioritized field', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.prioritizedFieldLabel
					'prioritizedFieldDescription' => __( 'Select the field that should be prioritized if, during creation of the user, two different users are found (one that matches the email field and another one that matches the username field).', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.prioritizedFieldDescription
				),
			),
			'title'              => array(
				'saving' => __( 'Saving title', 'uncanny-automator' ),
				// UncannyAutomator.i18n.title.saving
				'saved'  => __( 'Saved', 'Title', 'uncanny-automator' ),
				// UncannyAutomator.i18n.title.saved
			),
			'noResults'          => __( 'No results found', 'uncanny-automator' ),
			// UncannyAutomator.i18n.noResults
			/* translators: Non-personal infinitive verb */
			'save'               => __( 'Save', 'uncanny-automator' ),
			// UncannyAutomator.i18n.save
			/* translators: Non-personal infinitive verb */
			'search'             => __( 'Search', 'uncanny-automator' ),
			// UncannyAutomator.i18n.search
			'searching'          => __( 'Searching', 'uncanny-automator' ),
			// UncannyAutomator.i18n.searching
			/* translators: Non-personal infinitive verb */
			'confirm'            => __( 'Confirm', 'uncanny-automator' ),
			// UncannyAutomator.i18n.confirm
			/* translators: Non-personal infinitive verb */
			'cancel'             => __( 'Cancel', 'uncanny-automator' ),
			// UncannyAutomator.i18n.cancel
			/* translators: Non-personal infinitive verb */
			'delete'             => __( 'Delete', 'uncanny-automator' ),
			// UncannyAutomator.i18n.delete
			/* translators: Non-personal infinitive verb */
			'edit'               => __( 'Edit', 'uncanny-automator' ),
			// UncannyAutomator.i18n.edit
			/* translators: Noun */
			'support'            => _x( 'Support', 'Item options', 'uncanny-automator' ),
			// UncannyAutomator.i18n.support
			/* translators: Non-personal infinitive verb */
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
			/* translators: Non-personal infinitive verb */
			'addRow'             => __( 'Add row', 'uncanny-automator' ),
			// UncannyAutomator.i18n.addRow
			/* translators: Non-personal infinitive verb */
			'removeRow'          => __( 'Remove row', 'uncanny-automator' ),
			// UncannyAutomator.i18n.removeRow
			/* translators: 1. Row number */
			'rowNumber'          => __( 'Row %1$s', 'uncanny-automator' ),
			// UncannyAutomator.i18n.rowNumber
			'yes'                => __( 'Yes', 'uncanny-automator' ),
			// UncannyAutomator.i18n.yes
			'no'                 => __( 'No', 'uncanny-automator' ),
			// UncannyAutomator.i18n.no
			'uncannyAutomator'   => 'Uncanny Automator',
			// Don't translate this string
			// UncannyAutomator.i18n.uncannyAutomator
			'uncannyAutomatorPro' => 'Uncanny Automator Pro',
			// Don't translate this string
			// UncannyAutomator.i18n.uncannyAutomatorPro
		);
	}
}
