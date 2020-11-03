<?php

namespace Uncanny_Automator;

/**
 * Class Automator_Translations
 * @package Uncanny_Automator
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
			'trigger'             => array(
				'name'         =>  esc_attr__( 'Triggers', 'uncanny-automator' ),
				// UncannyAutomator.i18n.trigger.name
				'singularName' =>  esc_attr__( 'Trigger', 'uncanny-automator' ),
				// UncannyAutomator.i18n.trigger.singularName
				/* translators: Trigger type. Logged-in triggers are triggered only by logged-in users */
				'userTriggers' =>  esc_attr__( 'Logged-in triggers', 'uncanny-automator' ),
				// UncannyAutomator.i18n.trigger.userTriggers
				/* translators: Non-personal infinitive verb */
				'add'          =>  esc_attr__( 'Add trigger', 'uncanny-automator' ),
				// UncannyAutomator.i18n.trigger.add
				/* translators: Non-personal infinitive verb */
				'addAnother'   =>  esc_attr__( 'Add another trigger', 'uncanny-automator' ),
				// UncannyAutomator.i18n.trigger.addAnother
				/* translators: Verb conjugated in present-tense second-person singular */
				'select'       =>  esc_attr__( 'Select a trigger', 'uncanny-automator' ),
				// UncannyAutomator.i18n.trigger.select
				/* translators: Non-personal infinitive verb */
				'search'       =>  esc_attr__( 'Search for triggers', 'uncanny-automator' ),
				// UncannyAutomator.i18n.trigger.search
				/* translators: Non-personal infinitive verb */
				'save'         =>  esc_attr__( 'Save trigger', 'uncanny-automator' ),
				// UncannyAutomator.i18n.trigger.save
			),
			'action'              => array(
				'name'         =>  esc_attr__( 'Actions', 'uncanny-automator' ),
				// UncannyAutomator.i18n.action.name
				'singularName' =>  esc_attr__( 'Action', 'uncanny-automator' ),
				// UncannyAutomator.i18n.aciton.singularName
				/* translators: Non-personal infinitive verb */
				'add'          =>  esc_attr__( 'Add action', 'uncanny-automator' ),
				// UncannyAutomator.i18n.action.add
				/* translators: Non-personal infinitive verb */
				'addAnother'   =>  esc_attr__( 'Add another action', 'uncanny-automator' ),
				// UncannyAutomator.i18n.action.addAnother
				/* translators: Verb conjugated in present-tense second-person singular */
				'select'       =>  esc_attr__( 'Select an action', 'uncanny-automator' ),
				// UncannyAutomator.i18n.action.select
				/* translators: Non-personal infinitive verb */
				'search'       =>  esc_attr__( 'Search for actions', 'uncanny-automator' ),
				// UncannyAutomator.i18n.action.search
				/* translators: Non-personal infinitive verb */
				'save'         =>  esc_attr__( 'Save action', 'uncanny-automator' ),
				// UncannyAutomator.i18n.action.save
			),
			'closure'             => array(
				'name' => 'Closures', // UncannyAutomator.i18n.closure.name
			),
			'validation'          => array(
				'recipe' => array(
					'oneTrigger'   =>  esc_attr__( 'You have to add at least one live trigger to your recipe', 'uncanny-automator' ),
					// UncannyAutomator.i18n.validation.recipe.oneTrigger
					'oneAction'    =>  esc_attr__( 'You have to add at least one live action to your recipe', 'uncanny-automator' ),
					// UncannyAutomator.i18n.validation.recipe.oneAction
					/* translators: Verb conjugated in present-tense second-person singular */
					'liveItems'    =>  esc_attr__( 'Add live items to your recipe', 'uncanny-automator' ),
					// UncannyAutomator.i18n.validation.recipe.liveItems
					/* translators: Verb conjugated in present-tense second-person singular */
					'userSelector' =>  esc_attr__( 'Specify the user the actions will be run on', 'uncanny-automator' ),
					// UncannyAutomator.i18n.validation.recipe.userSelector
				),
				'field'  => array(
					'select'      => array(
						'empty'        =>  esc_attr__( 'Please select a value from the dropdown list.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.select.empty
						'invalid'      =>  esc_attr__( 'Please select a valid option.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.select.invalid
						'otherOptions' =>  esc_attr__( 'Other options', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.select.otherOptions
						/* translators: Non-personal infinitive verb */
						'customValue'  =>  esc_attr__( 'Use a custom value', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.select.customValue
						'customValueToken'  => esc_attr__( '%s (custom value)', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.select.customValueToken
					),
					'text'        => array(
						'empty'   =>  esc_attr__( 'Please fill out this field.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.text.empty
						'invalid' =>  esc_attr__( 'Please fill out this field with a valid value.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.text.invalid
					),
					'textarea'    => array(
						'empty'   =>  esc_attr__( 'Please fill out this field.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.textarea.empty
						'invalid' =>  esc_attr__( 'Please fill out this field with a valid value.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.textarea.invalid
					),
					'int'         => array(
						'empty'          =>  esc_attr__( 'Please enter a number.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.int.empty
						'invalid'        =>  esc_attr__( 'Please enter a valid whole number (no decimal places).', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.int.invalid
						'multipleTokens' =>  esc_attr__( 'This field only supports one token at a time.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.int.multipleTokens
					),
					'float'       => array(
						'empty'          =>  esc_attr__( 'Please enter a number.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.float.empty
						'invalid'        =>  esc_attr__( 'Please enter a valid number.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.float.invalid
						'multipleTokens' =>  esc_attr__( 'This field only supports one token at a time.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.float.multipleTokens
					),
					'email'       => array(
						'empty'    =>  esc_attr__( 'Please enter an email address.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.email.empty
						'single'   => array(
							'invalid'        =>  esc_attr__( 'Please enter a valid email address.', 'uncanny-automator' ),
							// UncannyAutomator.i18n.validation.field.email.single.invalid
							'multipleTokens' =>  esc_attr__( 'This field only supports one token per email.', 'uncanny-automator' ),
							// UncannyAutomator.i18n.validation.field.email.single.multipleTokens
						),
						'multiple' => array(
							'invalid'        =>  esc_attr__( 'Please enter a list of valid email addresses.', 'uncanny-automator' ),
							// UncannyAutomator.i18n.validation.field.email.multiple.invalid
							'multipleTokens' =>  esc_attr__( 'This field only supports one token per email.', 'uncanny-automator' ),
							// UncannyAutomator.i18n.validation.field.email.multiple.multipleTokens
						),
					),
					'url'         => array(
						'empty'   =>  esc_attr__( 'Please enter a URL.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.url.empty
						'invalid' =>  esc_attr__( 'Please enter a valid URL.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.url.invalid
					),
					'checkbox'    => array(
						'empty'   =>  esc_attr__( 'Please check this checkbox.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.checkbox.empty
						'invalid' =>  esc_attr__( 'Please select valid options.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.checkbox.invalid
					),
					'radio'       => array(
						'empty'   =>  esc_attr__( 'Please select one option.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.radio.empty
						'invalid' =>  esc_attr__( 'Please select a valid option.', 'uncanny-automator' ),
						// UncannyAutomator.i18n.validation.field.radio.invalid
					),
					'invalidType' =>  esc_attr__( 'Invalid field type.', 'uncanny-automator' ),
					// UncannyAutomator.i18n.validation.field.invalidType
				),
			),
			'status'              => array(
				/* translators: Recipe status */
				'liveRecipe'      =>  esc_attr_x( 'Live', 'Recipe', 'uncanny-automator' ),
				// UncannyAutomator.i18n.status.liveRecipe
				/* translators: Recipe status */
				'draftRecipe'     =>  esc_attr_x( 'Draft', 'Recipe', 'uncanny-automator' ),
				// UncannyAutomator.i18n.status.draftRecipe
				/* translators: Recipe item status */
				'liveRecipeItem'  =>  esc_attr_x( 'Live', 'Recipe item', 'uncanny-automator' ),
				// UncannyAutomator.i18n.status.liveRecipeItem
				/* translators: Recipe item status */
				'draftRecipeItem' =>  esc_attr_x( 'Draft', 'Recipe item', 'uncanny-automator' ),
				// UncannyAutomator.i18n.status.draftRecipeItem
			),
			'tokens'              => array(
				/* translators: Non-personal infinitive verb */
				'search'    =>  esc_attr__( 'Search tokens', 'uncanny-automator' ), // UncannyAutomator.i18n.tokens.search
				'tokenType' => array(
					/* translators: Token type */
					'text'  =>  esc_attr_x( 'Text', 'Token', 'uncanny-automator' ),
					/* translators: Token type */
					'email' =>  esc_attr_x( 'Email', 'Token', 'uncanny-automator' ),
					/* translators: Token type */
					'url'   =>  esc_attr_x( 'URL', 'Token', 'uncanny-automator' ),
					/* translators: Token type */
					'float' =>  esc_attr_x( 'Float', 'Token', 'uncanny-automator' ),
					/* translators: Token type */
					'int'   =>  esc_attr_x( 'Integer', 'Token', 'uncanny-automator' ),
				),
				'global'    => array(
					/* translators: Token category. It refers to common tokens. */
					'common'            =>  esc_attr_x( 'Common', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.common
					/* translators: Token name */
					'siteName'          =>  esc_attr_x( 'Site name', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.siteName
					/* translators: Token name */
					'userID'            =>  esc_attr_x( 'User ID', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.userId
					/* translators: Token name */
					'userUsername'      =>  esc_attr_x( 'User username', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.userUsername
					/* translators: Token name */
					'userFirstName'     =>  esc_attr_x( 'User first name', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.userFirstName
					/* translators: Token name */
					'userLastName'      =>  esc_attr_x( 'User last name', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.userLastName
					/* translators: Token name */
					'userEmail'         =>  esc_attr_x( 'User email', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.userEmail
					/* translators: Token name */
					'userDisplay'       =>  esc_attr_x( 'User display name', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.userDisplay
					/* translators: Token name */
					'userResetPassLink' =>  esc_attr_x( 'User reset password URL', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.userDisplay
					/* translators: Token name */
					'adminEmail'        =>  esc_attr_x( 'Admin email', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.adminEmail
					/* translators: Token name */
					'siteUrl'           =>  esc_attr_x( 'Site URL', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.siteUrl
					/* translators: Token name */
					'recipeName'        =>  esc_attr_x( 'Recipe name', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.recipeName
					/* translators: Token category. It refers to advanced tokens. */
					'advanced'          =>  esc_attr_x( 'Advanced', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.advanced
					/* translators: Token name */
					'userMetaKey'       =>  esc_attr_x( 'User meta key', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.userMetaKey
					/* translators: Token name */
					'currentDate'       =>  esc_attr_x( 'Current date', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.currentDate
					/* translators: Token name */
					'currentTime'       =>  esc_attr_x( 'Current time', 'Token', 'uncanny-automator' ),
					// UncannyAutomator.i18n.tokens.global.currentTime
				),
			),
			'integration'         => array(
				/* translators: Verb conjugated in present-tense second-person singular */
				'select'       =>  esc_attr__( 'Select an integration', 'uncanny-automator' ),
				// UncannyAutomator.i18n.integration.select
				/* translators: Verb conjugated in present-tense second-person singular */
				'discoverMore' =>  esc_attr__( 'Discover more awesome integrations.', 'uncanny-automator' ),
				// UncannyAutomator.i18n.integration.discoverMore
			),
			'publish'             => array(
				/* translators: Number of times the recipe can be triggered per user */
				'timesPerUser' =>  esc_attr__( 'Times per user:', 'uncanny-automator' ),
				// UncannyAutomator.i18n.publish.timesPerUser
				/* translators: Verb conjugated in present-tense second-person singular */
				'leaveEmpty'   =>  esc_attr__( 'Leave empty for unlimited times', 'uncanny-automator' ),
				// UncannyAutomator.i18n.publish.leaveEmpty
				/* translators: Unlimited times */
				'unlimited'    =>  esc_attr_x( 'Unlimited', 'Publish', 'uncanny-automator' ),
				// UncannyAutomator.i18n.publish.unlimited
				'oneOrBigger'  =>  esc_attr__( 'This number has to be 1 or bigger', 'uncanny-automator' ),
				// UncannyAutomator.i18n.publish.oneOrBigger
				/* translators: Recipe creation date */
				'createdOn'    =>  esc_attr_x( 'Created on:', 'Recipe', 'uncanny-automator' ),
				// UncannyAutomator.i18n.publish.createdOn
				/* translators: Recipe type */
				'recipeType'   =>  esc_attr_x( 'Type:', 'Recipe', 'uncanny-automator' ),
				// UncannyAutomator.i18n.publish.recipeType
			),
			'confirmationModal'   => array(
				'title'         =>  esc_attr__( 'Are you sure?', 'uncanny-automator' ),
				// UncannyAutomator.i18n.confirmationModal.title
				'deleteWarning' =>  esc_attr__( 'Deleting items in a {{live}} recipe can lead to unexpected behaviors.', 'uncanny-automator' ),
				// UncannyAutomator.i18n.confirmationModal.deleteWarning
				'statusWarning' =>  esc_attr__( 'Setting items to draft in a {{live}} recipe can lead to unexpected behaviors.', 'uncanny-automator' ),
				// UncannyAutomator.i18n.confirmationModal.statusWarning
			),
			'proOnly'             => array(
				'pro'          => 'Pro',
				// Don't make this string translatable
				// UncannyAutomator.i18n.proOnly.pro
				/* translators: 1. Trademarked term */
				'warning'      => sprintf(  esc_attr__( 'This is a %1$s feature.', 'uncanny-automator' ), 'Pro' ),
				// UncannyAutomator.i18n.proOnly.warning
				/* translators: 1. Plugin name */
				// 'multipleTriggers' =>  esc_attr__( 'This recipe contains multiple triggers and requires %1$s.', 'uncanny-automator' ),
				// UncannyAutomator.i18n.proOnly.multipleTriggers
				'proActive'    =>  esc_attr__( 'Please ensure the plugin is installed and activated.', 'uncanny-automator' ),
				// UncannyAutomator.i18n.proOnly.proActive
				/* translators: 1. Plugin name */
				'proToPublish' =>  esc_attr__( 'Please install %1$s to activate this recipe.', 'uncanny-automator' ),
				// UncannyAutomator.i18n.proOnly.proToPublish
				/* translators: Non-personal infinitive verb */
				'moveToTrash'  =>  esc_attr__( 'Move to trash', 'uncanny-automator' ),
				// UncannyAutomator.i18n.proOnly.moveToTrash
				// 'unlockTriggers'   => sprintf(  esc_attr__( 'Get %s to unlock these triggers', 'uncanny-automator' ), 'Uncanny Automator Pro' ),
				// UncannyAutomator.i18n.proOnly.unlockTriggers
				// 'unlockActions'    => sprintf(  esc_attr__( 'Get %s to unlock these actions', 'uncanny-automator' ), 'Uncanny Automator Pro' ),
				// UncannyAutomator.i18n.proOnly.unlockActions
				/* translators: 1. Trademarked term */
				'requiresPro'  =>  esc_attr__( 'Requires %1$s', 'uncanny-automator' ),
				// UncannyAutomator.i18n.proOnly.requiresPro
			),
			'sendFeedback'        => array(
				/* translators: Non-personal infinitive verb */
				'title' =>  esc_attr__( 'Send feedback', 'uncanny-automator' ),
				// UncannyAutomator.i18n.sendFeedback.title
				/* translators: 1. Plugin name */
				// 'message'       =>  esc_attr__( 'Help us improve %1$s! Click the icon below to send feedback', 'uncanny-automator' ),
				// UncannyAutomator.i18n.sendFeedback.message
				// 'dontShowAgain' =>  esc_attr__( 'Don\'t show again', 'uncanny-automator' ),
				// UncannyAutomator.i18n.sendFeedback.dontShowAgain
				// 'gotIt'         =>  esc_attr__( 'Got it', 'uncanny-automator' ),
				// UncannyAutomator.i18n.sendFeedback.gotIt
			),
			'recipeType'          => array(
				/* translators: Verb conjugated in present-tense second-person singular */
				'title'                       =>  esc_attr__( 'Select a recipe type', 'uncanny-automator' ),
				// UncannyAutomator.i18n.recipeType.title
				/* translators: Recipe type. Logged-in recipes are triggered only by logged-in users */
				'userRecipeName'              =>  esc_attr_x( 'Logged-in', 'Recipe', 'uncanny-automator' ),
				// UncannyAutomator.i18n.recipeType.userRecipeName
				'userRecipeDescription'       =>  esc_attr__( 'Triggered by logged-in users; supports multiple triggers and many integrations', 'uncanny-automator' ),
				// UncannyAutomator.i18n.recipeType.userRecipeDescription
				'cantChangeLaterNotice'       =>  esc_attr__( 'Note: Recipe type cannot be changed later.', 'uncanny-automator' ),
				// UncannyAutomator.i18n.recipeType.cantChangeLaterNotice
				'errorDidNotSelectType'       =>  esc_attr__( 'Please select an option.', 'uncanny-automator' ),
				// UncannyAutomator.i18n.recipeType.errorDidNotSelectType
				'errorTryingToSaveOtherValue' =>  esc_attr__( 'Error when saving value.', 'uncanny-automator' ),
				// UncannyAutomator.i18n.recipeType.errorTryingToSaveOtherValue
				'errorSomethingWentWrong'     =>  esc_attr__( 'Sorry, something went wrong. Please try again.', 'uncanny-automator' ),
				// UncannyAutomator.i18n.recipeType.errorSomethingWentWrong
			),
			'userSelector'        => array(
				/* translators: Verb conjugated in present-tense second-person singular */
				'firstStepTitle' =>  esc_attr__( 'Choose who will do the actions', 'uncanny-automator' ),
				// UncannyAutomator.i18n.userSelector.firstStepTitle
				/* translators: Verb conjugated in present-tense second-person singular */
				'setOptions'     =>  esc_attr__( 'Set user data', 'uncanny-automator' ),
				// UncannyAutomator.i18n.userSelector.setOptions
				'sentenceTitle'  =>  esc_attr__( 'Actions will be run on...', 'uncanny-automator' ),
				// UncannyAutomator.i18n.userSelector.sentenceTitle
				'logUserIn'      =>  esc_attr__( 'Log the new user in?', 'uncanny-automator' ),
				// UncannyAutomator.i18n.userSelector.logUserIn
				'userType'       => array(
					'existing' =>  esc_attr__( 'Existing user', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.userType.existing
					'new'      =>  esc_attr__( 'New user', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.userType.new
				),
				'summary'        => array(
					/* translators: 1. Field name, 2. Field value */
					'matches'            =>  esc_attr_x( '%1$s matches %2$s', 'User selector', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.summary.matches
					'email'              =>  esc_attr__( 'Email', 'quickbooks-training' ),
					// UncannyAutomator.i18n.userSelector.summary.email
					'id'                 =>  esc_attr__( 'ID', 'quickbooks-training' ),
					// UncannyAutomator.i18n.userSelector.summary.id
					'username'           =>  esc_attr__( 'Username', 'quickbooks-training' ),
					// UncannyAutomator.i18n.userSelector.summary.username
					/* translators: 1. An email address */
					'withEmail'          =>  esc_attr_x( 'With the email %1$s', 'User selector', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.summary.withEmail
					/* translators: 1. An action */
					'otherwise'          =>  esc_attr_x( 'Otherwise, %1$s', 'User selector', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.summary.otherwise
					/* translators: Non-personal infinitive verb. It's used after "Otherwise, %1$s" */
					'doNothing'          =>  esc_attr_x( 'do nothing', 'User selector', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.summary.doNothing
					/* translators: Non-personal infinitive verb. It's used after "Otherwise, %1$s" */
					'createNewUser'      =>  esc_attr_x( 'create a new user', 'User selector', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.summary.createNewUser
					/* translators: Non-personal infinitive verb. It's used after "Otherwise, %1$s" */
					'selectExistingUser' =>  esc_attr_x( 'select an existing user', 'User selector', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.summary.selectExistingUser
				),
				'existingUser'   => array(
					'uniqueFieldLabel'                   =>  esc_attr__( 'Unique field', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.existingUser.uniqueFieldLabel
					'uniqueFieldOptionEmail'             =>  esc_attr__( 'Email', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.existingUser.uniqueFieldOptionEmail
					'uniqueFieldOptionId'                =>  esc_attr__( 'ID', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.existingUser.uniqueFieldOptionId
					'uniqueFieldOptionUsername'          =>  esc_attr__( 'Username', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.existingUser.uniqueFieldOptionUsername
					'valueFieldLabel'                    =>  esc_attr__( 'Value', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.existingUser.valueFieldLabel
					'valueFieldPlaceholder'              =>  esc_attr__( 'Value of the unique field', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.existingUser.valueFieldPlaceholder
					'createNewUserFieldLabel'            =>  __( "What to do if the user doesn't exist", 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.existingUser.createNewUserFieldLabel
					/* translators: Non-personal infinitive verb */
					'createNewUserFieldOptionCreateUser' =>  esc_attr__( 'Create new user', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.existingUser.createNewUserFieldOptionCreateUser
					/* translators: Non-personal infinitive verb */
					'createNewUserFieldOptionDoNothing'  =>  esc_attr__( 'Do nothing', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.existingUser.createNewUserFieldOptionDoNothing
					'doNothingMessage'                   =>  esc_attr__( 'If no user matches the unique field and value then the actions are not going to be executed.', 'uncanny-automator' )
					// UncannyAutomator.i18n.userSelector.existingUser.doNothingMessage
				),
				'newUser'        => array(
					'firstName'                        =>  esc_attr__( 'First name', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.firstName
					'lastName'                         =>  esc_attr__( 'Last name', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.lastName
					'email'                            =>  esc_attr__( 'Email', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.email
					'username'                         =>  esc_attr__( 'Username', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.username
					'displayName'                      =>  esc_attr__( 'Display name', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.displayName
					'password'                         =>  esc_attr__( 'Password', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.password
					'passwordDescription'              =>  sprintf( esc_attr__( 'If left empty, the user will need to reset their password to log in. Send an email containing the %1$s token to simplify the process.', 'uncanny-automator' ), '<em>' . esc_attr__( 'User reset password URL', 'uncanny-automator' ) . '</em>' ),
					// UncannyAutomator.i18n.userSelector.newUser.passwordDescription
					/* translators: WordPress roles */
					'roles'                            =>  esc_attr__( 'Roles', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.role
					'existingUserFieldLabel'           =>  esc_attr__( 'What to do if the user already exists', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.existingUserFieldLabel
					/* translators: Non-personal infinitive verb */
					'existingUserFieldOptionExisting'  =>  esc_attr__( 'Select existing user', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.existingUserFieldOptionExisting
					/* translators: Non-personal infinitive verb */
					'existingUserFieldOptionDoNothing' =>  esc_attr__( 'Do nothing', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.existingUserFieldOptionDoNothing
					'doNothingMessage'                 =>  esc_attr__( 'If there is already a user with the defined email address or username, the actions are not going to be executed.', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.doNothingMessage
					'prioritizedFieldLabel'            =>  esc_attr__( 'Prioritized field', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.prioritizedFieldLabel
					'prioritizedFieldDescription'      =>  esc_attr__( 'Select the field that should be prioritized if, during creation of the user, two different users are found (one that matches the email field and another one that matches the username field).', 'uncanny-automator' ),
					// UncannyAutomator.i18n.userSelector.newUser.prioritizedFieldDescription
				),
			),
			'title'               => array(
				'saving' =>  esc_attr__( 'Saving title', 'uncanny-automator' ),
				// UncannyAutomator.i18n.title.saving
				'saved'  =>  esc_attr__( 'Saved', 'Title', 'uncanny-automator' ),
				// UncannyAutomator.i18n.title.saved
			),
			'noResults'           =>  esc_attr__( 'No results found', 'uncanny-automator' ),
			// UncannyAutomator.i18n.noResults
			/* translators: Character to separate items */
			'itemSeparator'       => __( ',', 'uncanny-automator' ),
			// UncannyAutomator.i18n.itemSeparator
			/* translators: Non-personal infinitive verb */
			'save'                =>  esc_attr__( 'Save', 'uncanny-automator' ),
			// UncannyAutomator.i18n.save
			/* translators: Non-personal infinitive verb */
			'search'              =>  esc_attr__( 'Search', 'uncanny-automator' ),
			// UncannyAutomator.i18n.search
			'searching'           =>  esc_attr__( 'Searching', 'uncanny-automator' ),
			// UncannyAutomator.i18n.searching
			/* translators: Non-personal infinitive verb */
			'confirm'             =>  esc_attr__( 'Confirm', 'uncanny-automator' ),
			// UncannyAutomator.i18n.confirm
			/* translators: Non-personal infinitive verb */
			'cancel'              =>  esc_attr__( 'Cancel', 'uncanny-automator' ),
			// UncannyAutomator.i18n.cancel
			/* translators: Non-personal infinitive verb */
			'delete'              =>  esc_attr__( 'Delete', 'uncanny-automator' ),
			// UncannyAutomator.i18n.delete
			/* translators: Non-personal infinitive verb */
			'edit'                =>  esc_attr__( 'Edit', 'uncanny-automator' ),
			// UncannyAutomator.i18n.edit
			/* translators: Noun */
			'support'             =>  esc_attr_x( 'Support', 'Item options', 'uncanny-automator' ),
			// UncannyAutomator.i18n.support
			/* translators: Non-personal infinitive verb */
			'learnMore'           =>  esc_attr__( 'Learn more', 'uncanny-automator' ),
			// UncannyAutomator.i18n.learnMore
			'trueLabel'           =>  esc_attr__( 'True', 'uncanny-automator' ),
			// UncannyAutomator.i18n.trueLabel
			'falseLabel'          =>  esc_attr__( 'False', 'uncanny-automator' ),
			// UncannyAutomator.i18n.falseLabel
			'loadingMoreResults'  =>  esc_attr__( 'Loading more results...', 'uncanny-automator' ),
			// UncannyAutomator.i18n.loadingMoreResults
			/* translators: 1. Post ID */
			'postIDPlaceholder'  => esc_attr__( 'ID: %1$s' ),
			// UncannyAutomator.i18n.postIDPlaceholder
			'itemMissing'        => esc_attr__( 'This item was disabled because it could not be found on the system. To re-enable, ensure the associated plugin is installed and activated.', 'uncanny-automator' ),
			// UncannyAutomator.i18n.itemMissing
			/* translators: Non-personal infinitive verb */
			'addRow'              =>  esc_attr__( 'Add row', 'uncanny-automator' ),
			// UncannyAutomator.i18n.addRow
			/* translators: Non-personal infinitive verb */
			'removeRow'           =>  esc_attr__( 'Remove row', 'uncanny-automator' ),
			// UncannyAutomator.i18n.removeRow
			/* translators: 1. Row number */
			'rowNumber'           =>  esc_attr__( 'Row %1$s', 'uncanny-automator' ),
			// UncannyAutomator.i18n.rowNumber
			'yes'                 =>  esc_attr__( 'Yes', 'uncanny-automator' ),
			// UncannyAutomator.i18n.yes
			'no'                  =>  esc_attr__( 'No', 'uncanny-automator' ),
			// UncannyAutomator.i18n.no
			'uncannyAutomator'    => 'Uncanny Automator',
			// Don't translate this string
			// UncannyAutomator.i18n.uncannyAutomator
			'uncannyAutomatorPro' => 'Uncanny Automator Pro',
			// Don't translate this string
			// UncannyAutomator.i18n.uncannyAutomatorPro
		);
	}
}
