<?php

namespace Uncanny_Automator;

/**
 * Class Automator_Translations
 *
 * @package Uncanny_Automator
 */
class Automator_Translations {

	/**
	 * @var
	 */
	public static $instance;
	/**
	 * Collection of error messages
	 *
	 * @var array
	 */
	private $ls = array();

	/**
	 *
	 */
	public function __construct() {

	}

	/**
	 *
	 */
	private function set_strings() {

		// if it is already initilized?
		if ( ! empty( $this->ls ) ) {
			return;
		}

		// Localized strings
		$this->ls = array(
			'trigger'                => array(
				'name'            => esc_attr__( 'Triggers', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.trigger.name
				'singularName'    => esc_attr__( 'Trigger', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.trigger.singularName
				/* translators: 1. A condition */
				'logic'           => array(
					'runWhen'    => esc_html_x( 'Run recipe when', 'Triggers logic', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.trigger.logic.runWhen
					/* translators: This string is appended after a dropdown that has the option "Any" selected */
					'runWhenAny' => esc_html_x( 'of the following triggers is completed', 'Triggers logic', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.trigger.logic.runWhenAny
					/* translators: This string is appended after a dropdown that has the option "All" selected */
					'runWhenAll' => esc_html_x( 'of the following triggers are completed', 'Triggers logic', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.trigger.logic.runWhenAll
					/* translators: Text of the "any" option. Full sentence: "ANY" of the following triggers is completed */
					'any'        => esc_html_x( 'Any', 'Triggers logic', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.trigger.logic.any
					/* translators: Text of the "all" option. Full sentence: "ALL" of the following triggers are completed */
					'all'        => esc_html_x( 'All', 'Triggers logic', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.trigger.logic.all
				),
				/* translators: Trigger type. Logged-in triggers are triggered only by logged-in users */
				'userTriggers'    => esc_attr__( 'Logged-in triggers', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.trigger.userTriggers
				'everyoneTrigger' => esc_attr__( 'Trigger', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.trigger.everyoneTrigger
				/* translators: Non-personal infinitive verb */
				'add'             => esc_attr__( 'Add trigger', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.trigger.add
				/* translators: Non-personal infinitive verb */
				'addAnother'      => esc_attr__( 'Add trigger', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.trigger.addAnother
				/* translators: Verb conjugated in present-tense second-person singular */
				'select'          => esc_attr__( 'Select a trigger', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.trigger.select
				/* translators: Non-personal infinitive verb */
				'search'          => esc_attr__( 'Search for triggers', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.trigger.search
				/* translators: Non-personal infinitive verb */
				'save'            => esc_attr__( 'Save trigger', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.trigger.save
			),
			'action'                 => array(
				'name'         => esc_attr__( 'Actions', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.action.name
				'singularName' => esc_attr__( 'Action', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.aciton.singularName
				/* translators: Non-personal infinitive verb */
				'add'          => esc_attr__( 'Add action', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.action.add
				/* translators: Non-personal infinitive verb */
				'addAnother'   => esc_attr__( 'Add action', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.action.addAnother
				/* translators: Verb conjugated in present-tense second-person singular */
				'select'       => esc_attr__( 'Select an action', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.action.select
				/* translators: Non-personal infinitive verb */
				'search'       => esc_attr__( 'Search for actions', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.action.search
				/* translators: Non-personal infinitive verb */
				'save'         => esc_attr__( 'Save action', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.action.save
				'asyncActions' => array(
					'schedule'               => esc_attr__( 'Schedule', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.action.asyncActions.schedule
					'delay'                  => esc_attr__( 'Delay', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.action.asyncActions.delay
					'custom'                 => esc_attr__( 'Use a token/custom value', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.action.asyncActions.custom
					'remove'                 => esc_attr__( 'Remove', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.action.asyncActions.remove
					'new'                    => esc_attr__( 'New', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.action.asyncActions.new
					'cancelled'              => esc_attr__( 'Cancelled', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.action.asyncActions.cancelled
					'error'                  => esc_attr__( 'Error', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.action.asyncActions.error
					'closuresLabelAsync'     => esc_attr__( 'Redirect when instant actions are completed', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.action.asyncActions.closuresLabelAsync
					'closuresLabelInstant'   => esc_attr__( 'Redirect when recipe is completed', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.action.asyncActions.closuresLabelInstant
					'closuresLabelAsyncOnly' => esc_attr__( 'Redirect when all triggers are completed', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.action.asyncActions.closuresLabelAsyncOnly
					'pleaseSave'             => esc_attr__( 'Please save the action first', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.action.asyncActions.pleaseSave
					'modal'                  => array(
						'title'                      => esc_attr__( 'Delay or schedule this action', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.action.asyncActions.modal.title
						'delayDesc'                  => esc_attr__( 'Delay the execution of this action for', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.action.asyncActions.modal.delayDesc
						'second'                     => esc_attr__( 'second', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.action.asyncActions.modal.second
						'seconds'                    => esc_attr__( 'Seconds', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.action.asyncActions.modal.seconds
						'minute'                     => esc_attr__( 'minute', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.action.asyncActions.modal.minute
						'minutes'                    => esc_attr__( 'Minutes', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.action.asyncActions.modal.minutes
						'hour'                       => esc_attr__( 'hour', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.action.asyncActions.modal.hour
						'hours'                      => esc_attr__( 'Hours', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.action.asyncActions.modal.hours
						'day'                        => esc_attr__( 'day', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.action.asyncActions.modal.day
						'days'                       => esc_attr__( 'Days', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.action.asyncActions.modal.days
						'year'                       => esc_attr__( 'year', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.action.asyncActions.modal.year
						'years'                      => esc_attr__( 'Years', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.action.asyncActions.modal.years
						'after'                      => esc_html__( 'after completion of the triggers', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.action.asyncActions.modal.after
						'scheduleDesc'               => esc_attr__( 'Run this action at a specific date and time', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.action.asyncActions.modal.scheduleDesc
						'scheduleNotice1'            => esc_html__( 'Note: If the action is triggered after the specified date, this action will run immediately.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.action.asyncActions.modal.scheduleNotice1
						'scheduleNotice2'            => esc_attr__( 'Note: Changes will not affect previously delayed or scheduled actions.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.action.asyncActions.modal.scheduleNotice2
						'timezone'                   => esc_attr__( 'Timezone', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.action.asyncActions.modal.timezone
						'timezoneString'             => Automator()->get_timezone_string(),
						// UncannyAutomator._core.i18n.action.asyncActions.modal.timezoneString

						'customDescription'          => esc_attr__( 'Run the action after a custom delay or at a custom date and time', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.action.asyncActions.modal.customDescription
						// translators: 1. Unix timestamp
						'customFieldDescription'     => esc_attr__( 'Use tokens and/or text to enter any supported format. Examples: "Friday at 1pm", "November 9", "2 hours", Unix timestamps %1$s.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.action.asyncActions.modal.customFieldDescription

						/* translators: Link to article. Check .customFieldDescription for full string */
						'customFieldDescriptionLink' => esc_attr__( 'and more', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.action.asyncActions.modal.customFieldDescriptionLink

						'timezoneLink'               => admin_url( 'options-general.php#timezone_string' ),
						// UncannyAutomator._core.i18n.action.asyncActions.modal.timezoneLink
						'dateFormat'                 => get_option( 'date_format' ),
						// UncannyAutomator._core.i18n.action.asyncActions.modal.dateFormat
						'today'                      => wp_date( get_option( 'date_format' ) ),
						// UncannyAutomator._core.i18n.action.asyncActions.modal.today
						'timeFormat'                 => get_option( 'time_format' ),
						// UncannyAutomator._core.i18n.action.asyncActions.modal.timeFormat
						'confirm'                    => esc_attr__( 'Set delay', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.action.asyncActions.modal.confirm
						'validation'                 => array(
							'unsupported'    => esc_attr__( 'Unsupported value.', 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.action.asyncActions.modal.validation.positiveNumber
							'positiveNumber' => esc_attr__( 'Please use a positive number.', 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.action.asyncActions.modal.validation.positiveNumber
						),

					),
					/* translators: Uncanny Automator Pro */
					'unlockSchedule'         => sprintf( esc_attr__( 'Get %s to unlock delays/schedules', 'uncanny-automator' ), 'Uncanny Automator Pro' ),
					// UncannyAutomator._core.i18n.action.asyncActions.unlockSchedule
				),
			),
			'closure'                => array(
				'name' => 'Closures', // UncannyAutomator._core.i18n.closure.name
			),
			'conditions'             => array(
				'filter'                               => esc_html__( 'Filter', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.conditions.filter

				'hasConditions'                        => esc_html__( 'Filtered', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.conditions.hasConditions
				'hasConditionsTokensDisclaimer'        => esc_html__( "These tokens may be empty if the associated action's filter conditions are not met.", 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.conditions.hasConditionsTokensDisclaimer

				'addFilter'                            => esc_html__( 'Add filter', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.conditions.addFilter
				/* translators: Uncanny Automator Pro */
				'unlockConditions'                     => sprintf( esc_attr__( 'Get %s to unlock conditions', 'uncanny-automator' ), 'Uncanny Automator Pro' ),
				// UncannyAutomator._core.i18n.conditions.unlockConditions

				'addBtnLabel'                          => esc_html__( 'Add condition', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.conditions.addBtnLabel

				'noConditions'                         => esc_html__( 'No conditions', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.conditions.noConditions

				/* translators: 1. Either "any" or "all". Code inside the curly brackets is not as prominent as the text outside it. */
				'runIfAnyFull'                         => esc_html_x( 'Run if %1$s {{of the following conditions are met}}', 'Conditions - Logic sentence', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.conditions.runIfAnyFull

				/* translators: Used in "Run if %1$s of the following conditions are met" */
				'any'                                  => esc_html_x( 'Any', 'Conditions - Logic sentence', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.conditions.any

				/* translators: Used in "Run if %1$s of the following conditions are met" */
				'all'                                  => esc_html_x( 'All', 'Conditions - Logic sentence', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.conditions.all

				'runIf'                                => esc_html_x( 'Run if', 'Conditions - Logic sentence', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.conditions.runIf

				'options'                              => array(
					'modalTitle'    => esc_html__( 'Configure the rule', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.conditions.options.modalTitle
					'saveCondition' => esc_html__( 'Save condition', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.conditions.options.saveCondition
				),

				'actionWontRun'                        => esc_html__( "This action won't run", 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.conditions.actionWontRun

				'actionsWontRun'                       => esc_html__( "These actions won't run", 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.conditions.actionsWontRun

				/* translators: 1. Trademarked term. */
				'actionWontRunContent'                 => esc_html__( 'Conditions/Filters are a feature of %1$s. Please re-activate Uncanny Automator Pro to enable this action.', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.conditions.actionWontRunContent

				/* translators: 1. Trademarked term. */
				'actionsWontRunContent'                => esc_html__( 'Conditions/Filters are a feature of %1$s. Please re-activate Uncanny Automator Pro to enable these actions.', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.conditions.actionsWontRunContent

				'otherActionsMightRunBefore'           => esc_html__( 'Other actions in this recipe may run before the actions below.', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.conditions.otherActionsMightRunBefore

				'pleaseSaveActionFirst'                => esc_attr__( 'Please save the action first', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.conditions.pleaseSaveActionFirst

				'saveFilter'                           => esc_html__( 'Save filter', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.conditions.saveFilter

				'configureTheRule'                     => esc_html__( 'Configure the rule', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.conditions.configureTheRule

				'errorCouldNotGetFields'               => esc_html__( "Something went wrong. We couldn't get the fields.", 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.conditions.errorCouldNotGetFields

				'requiresUserData'                     => esc_html__( 'Requires user data', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.conditions.requiresUserData

				'conditionRequiresUserData'            => esc_html__( 'This condition requires WordPress user data', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.conditions.conditionRequiresUserData

				'conditionRequiresUserDataDescription' => esc_html__( "Since this is a recipe that runs for everyone, including logged-out users, you'll need to select a new or existing user that the condition can be validated against.", 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.conditions.conditionRequiresUserDataDescription

				'condition'                            => esc_html__( 'Condition', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.conditions.condition

				'searchConditions'                     => esc_html__( 'Search conditions', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.conditions.searchConditions

				'noConditionsFound'                    => esc_html__( 'No conditions found', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.conditions.noConditionsFound

				'missingCondition'                     => esc_html__( 'Missing condition', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.conditions.missingCondition

				'wantToRemoveConditionsGroup'          => array(
					'heading'            => esc_attr__( 'Remove empty conditions block?', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.conditions.wantToRemoveConditionsGroup.heading
					'confirmButtonLabel' => esc_attr__( 'Remove', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.conditions.wantToRemoveConditionsGroup.confirmButtonLabel
					'cancelButtonLabel'  => esc_attr__( 'Keep', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.conditions.wantToRemoveConditionsGroup.cancelButtonLabel
				),
			),
			'validation'             => array(
				'recipe' => array(
					'oneTrigger'   => esc_attr__( 'You have to add at least one live trigger to your recipe', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.validation.recipe.oneTrigger
					'oneAction'    => esc_attr__( 'You have to add at least one live action to your recipe', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.validation.recipe.oneAction
					/* translators: Verb conjugated in present-tense second-person singular */
					'liveItems'    => esc_attr__( 'Add live items to your recipe', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.validation.recipe.liveItems
					/* translators: Verb conjugated in present-tense second-person singular */
					'userSelector' => esc_attr__( 'Specify the user the actions will be run on', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.validation.recipe.userSelector
				),
				'field'  => array(
					'select'      => array(
						'empty'            => esc_attr__( 'Please select a value from the dropdown list.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.select.empty
						'invalid'          => esc_attr__( 'Please select a valid option.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.select.invalid
						'otherOptions'     => esc_attr__( 'Other options', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.select.otherOptions
						/* translators: Non-personal infinitive verb */
						'customValue'      => esc_attr__( 'Use a token/custom value', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.select.customValue
						/* translators: 1. Token name */
						'customValueToken' => esc_attr__( '%s (custom value)', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.select.customValueToken
					),
					'text'        => array(
						'empty'   => esc_attr__( 'Please fill out this field.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.text.empty
						'invalid' => esc_attr__( 'Please fill out this field with a valid value.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.text.invalid
					),
					'textarea'    => array(
						'empty'   => esc_attr__( 'Please fill out this field.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.textarea.empty
						'invalid' => esc_attr__( 'Please fill out this field with a valid value.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.textarea.invalid
					),
					'int'         => array(
						'empty'          => esc_attr__( 'Please enter a number.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.int.empty
						'invalid'        => esc_attr__( 'Please enter a valid whole number (no decimal places).', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.int.invalid
						'multipleTokens' => esc_attr__( 'This field only supports one token at a time.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.int.multipleTokens
						/* translators: 1. The min number */
						'minValue'       => esc_attr__( 'Value must be greater than or equal to %1$s', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.int.minValue
						/* translators: 1. The max number */
						'maxValue'       => esc_attr__( 'Value must be less than or equal to %1$s', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.int.maxValue
					),
					'float'       => array(
						'empty'          => esc_attr__( 'Please enter a number.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.float.empty
						'invalid'        => esc_attr__( 'Please enter a valid number.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.float.invalid
						'multipleTokens' => esc_attr__( 'This field only supports one token at a time.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.float.multipleTokens
						/* translators: 1. The min number */
						'minValue'       => esc_attr__( 'Value must be greater than or equal to %1$s', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.float.minValue
						/* translators: 1. The max number */
						'maxValue'       => esc_attr__( 'Value must be less than or equal to %1$s', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.float.maxValue
					),
					'email'       => array(
						'empty'    => esc_attr__( 'Please enter an email address.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.email.empty
						'single'   => array(
							'invalid'        => esc_attr__( 'Please enter a valid email address.', 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.validation.field.email.single.invalid
							'multipleTokens' => esc_attr__( 'This field only supports one token per email.', 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.validation.field.email.single.multipleTokens
						),
						'multiple' => array(
							'invalid'        => esc_attr__( 'Please enter a list of valid email addresses.', 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.validation.field.email.multiple.invalid
							'multipleTokens' => esc_attr__( 'This field only supports one token per email.', 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.validation.field.email.multiple.multipleTokens
						),
					),
					'url'         => array(
						'empty'   => esc_attr__( 'Please enter a URL.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.url.empty
						'invalid' => esc_attr__( 'Please enter a valid URL.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.url.invalid
					),
					'checkbox'    => array(
						'empty'   => esc_attr__( 'Please check this checkbox.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.checkbox.empty
						'invalid' => esc_attr__( 'Please select valid options.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.checkbox.invalid
					),
					'radio'       => array(
						'empty'   => esc_attr__( 'Please select one option.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.radio.empty
						'invalid' => esc_attr__( 'Please select a valid option.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.radio.invalid
					),
					'date'        => array(
						'empty'   => esc_attr__( 'Please enter a date.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.date.empty
						'invalid' => esc_attr__( 'Please enter a valid date.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.date.invalid
					),
					'time'        => array(
						'empty'   => esc_attr__( 'Please enter a time.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.date.empty
						'invalid' => esc_attr__( 'Please enter a valid time.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.date.invalid
					),
					'file'        => array(
						'empty' => esc_attr__( 'Please select a file.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.validation.field.file.empty
					),
					'invalidType' => esc_attr__( 'Invalid field type.', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.validation.field.invalidType
				),
			),
			'status'                 => array(
				/* translators: Recipe status */
				'liveRecipe'      => esc_attr_x( 'Live', 'Recipe', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.status.liveRecipe
				/* translators: Recipe status */
				'draftRecipe'     => esc_attr_x( 'Draft', 'Recipe', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.status.draftRecipe
				/* translators: Recipe item status */
				'liveRecipeItem'  => esc_attr_x( 'Live', 'Recipe item', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.status.liveRecipeItem
				/* translators: Recipe item status */
				'draftRecipeItem' => esc_attr_x( 'Draft', 'Recipe item', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.status.draftRecipeItem
			),
			'tokens'                 => array(
				/* translators: Non-personal infinitive verb */
				'search'                         => esc_attr__( 'Search tokens', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.tokens.search
				'noResults'                      => esc_attr__( 'No tokens found', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.tokens.noResults
				'noResultsDescriptionWithFilter' => esc_attr__( 'Try searching again or disabling the token type filter.', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.tokens.noResultsDescriptionWithFilter
				'noResultsDescription'           => esc_attr__( 'Try searching again.', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.tokens.noResultsDescription
				'tokenType'                      => array(
					/* translators: Token type */
					'text'  => esc_attr_x( 'Text', 'Token', 'uncanny-automator' ),
					/* translators: Token type */
					'email' => esc_attr_x( 'Email', 'Token', 'uncanny-automator' ),
					/* translators: Token type */
					'url'   => esc_attr_x( 'URL', 'Token', 'uncanny-automator' ),
					/* translators: Token type */
					'float' => esc_attr_x( 'Float', 'Token', 'uncanny-automator' ),
					/* translators: Token type */
					'int'   => esc_attr_x( 'Integer', 'Token', 'uncanny-automator' ),
					/* translators: Token type */
					'date'  => esc_attr_x( 'Date', 'Token', 'uncanny-automator' ),
					/* translators: Time type */
					'time'  => esc_attr_x( 'Time', 'Token', 'uncanny-automator' ),
				),
				'global'                         => array(
					/* translators: Token category. It refers to common tokens. */
					'common'                     => esc_attr_x( 'Common', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.common
					/* translators: Token name */
					'siteName'                   => esc_attr_x( 'Site name', 'Token', 'uncanny-automator' ),
					/* translators: Token name */
					'siteTagline'                => esc_attr_x( 'Site tagline', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.siteName
					/* translators: Token name */
					'currentSiteName'            => esc_attr_x( 'Current site name', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.currentSiteDescription
					/* translators: Token name */
					'currentSiteTagline'         => esc_attr_x( 'Current site tagline', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.currentSiteName
					/* translators: Token name */
					'userID'                     => esc_attr_x( 'User ID', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.userId
					/* translators: Token name */
					'userUsername'               => esc_attr_x( 'User username', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.userUsername
					/* translators: Token name */
					'userFirstName'              => esc_attr_x( 'User first name', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.userFirstName
					/* translators: Token name */
					'userLastName'               => esc_attr_x( 'User last name', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.userLastName
					/* translators: Token name */
					'userEmail'                  => esc_attr_x( 'User email', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.userEmail
					/* translators: Token name */
					'userDisplay'                => esc_attr_x( 'User display name', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.userDisplay
					/* translators: Token name */
					'userResetPassLink'          => esc_attr_x( 'User reset password link', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.userDisplay
					/* translators: Token name */
					'user_reset_pass_url'        => esc_attr_x( 'User reset password URL', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.user_reset_pass_url
					/* translators: Token name */
					'adminEmail'                 => esc_attr_x( 'Admin email', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.adminEmail
					/* translators: Token name */
					'siteUrl'                    => esc_attr_x( 'Site URL', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.siteUrl
					'currentSiteUrl'             => esc_attr_x( 'Current site URL', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.currentSiteUrl
					/* translators: Token name */
					'recipeName'                 => esc_attr_x( 'Recipe name', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.recipeName
					'recipeRunToken'             => esc_attr_x( 'Recipe run #', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.recipeRunToken
					'recipeTotalRunToken'        => esc_attr_x( 'Recipe run # (total)', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.recipeTotalRunToken
					'recipeId'                   => esc_attr_x( 'Recipe ID', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.recipeId
					'userRole'                   => esc_attr_x( 'User role', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.userRole
					/* translators: Token category. It refers to advanced tokens. */
					'advanced'                   => esc_attr_x( 'Advanced', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.advanced
					'date'                       => esc_attr_x( 'Date', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.date
					'formulas'                   => esc_attr_x( 'Modifiers', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.formulas
					/* translators: Token name */
					'userMetaKey'                => esc_attr_x( 'User meta', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.userMetaKey
					/* translators: 1. The user meta key */
					'userMetaKeyTemplate'        => esc_attr_x( 'User meta: %1$s', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.userMetaKeyTemplate
					/* translators: Token name */
					'postMetaKey'                => esc_attr_x( 'Post meta', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.postMetaKey
					/* translators: 1. The post. 2. The meta key */
					'postMetaKeyTemplate'        => esc_attr_x( 'Post: %1$s meta: %2$s', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.postMetaKeyTemplate
					'calculation'                => esc_attr_x( 'Calculation', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.calculationKey
					/* translators: 1. The calculation */
					'calculationTemplate'        => esc_attr_x( 'Calculation: %1$s', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.userMetaKeyTemplate
					'currentDate'                => esc_attr_x( 'Current date', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.currentDate
					'currentTime'                => esc_attr_x( 'Current time', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.currentTime
					'currentDateAndTime'         => esc_attr_x( 'Current date and time', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.currentDateAndTime
					'current_unix_timestamp'     => esc_attr_x( 'Current Unix timestamp', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.current_unix_timestamp
					'currentdate_unix_timestamp' => esc_attr_x( 'Current Unix timestamp (date only)', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.currentdate_unix_timestamp
					'currentBlogId'              => esc_attr_x( 'Current site ID', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.current_blog_id
					'user_ip_address'            => esc_attr_x( 'User IP address', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.user_ip_address
					'user_locale'                => esc_attr_x( 'User locale', 'Token', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.tokens.global.user_locale
				),
				'specialTokens'                  => array(
					'userMeta'    => array(
						'modal' => array(
							'title'                  => esc_attr__( "What's the key of the meta you want to retrieve?", 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.tokens.specialTokens.userMeta.modal.title
							'confirmButton'          => esc_attr__( 'Add token', 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.tokens.specialTokens.userMeta.modal.confirmButton
							'userMetaKey'            => esc_attr_x( 'User meta key', 'Token', 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.tokens.specialTokens.userMeta.modal.userMetaKey
							'userMetaKeyDescription' => esc_attr__( 'For example: admin_color', 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.tokens.specialTokens.userMeta.modal.userMetaKeyDescription
						),
					),
					'postMeta'    => array(
						'modal' => array(
							'title'                => esc_attr__( 'Token data', 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.tokens.specialTokens.postMeta.modal.title
							'confirmButton'        => esc_attr__( 'Add token', 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.tokens.specialTokens.postMeta.modal.confirmButton
							'postFieldLabel'       => esc_attr__( 'Post ID', 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.tokens.specialTokens.postMeta.modal.postFieldLabel
							/* translators: 1. Post ID */
							'postFieldDescription' => esc_attr__( 'The ID of the post that contains the meta data. %1$s.', 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.tokens.specialTokens.postMeta.modal.postFieldDescription
							'keyFieldLabel'        => esc_attr__( 'Meta key', 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.tokens.specialTokens.postMeta.modal.keyFieldLabel
							'keyFieldDescription'  => esc_attr__( 'The meta key associated with the data you want to retrieve. Only one meta key can be entered per token.', 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.tokens.specialTokens.postMeta.modal.keyFieldDescription
						),
					),
					'calculation' => array(
						'modal' => array(
							'title'                   => esc_attr__( 'Calculation', 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.tokens.specialTokens.userMeta.modal.title
							'confirmButton'           => esc_attr__( 'Add token', 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.tokens.specialTokens.userMeta.modal.confirmButton
							'formulaFieldLabel'       => esc_attr_x( 'Formula', 'Token', 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.tokens.specialTokens.userMeta.modal.userMetaKey
							'formulaFieldDescription' => esc_attr__( 'Use + for addition, - for subtraction, / for division and * for multiplication.  Use tokens that output numerical values.  If a token outputs a string, it will be replaced with zero (0).  Example: [Some token] + 1.', 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.tokens.specialTokens.userMeta.modal.userMetaKeyDescription
						),
					),
				),
				/* translators: 1. Token type */
				'filter'                         => esc_attr__( 'Only %1$s tokens', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.tokens.filter
				'addAllTokensInGroup'            => esc_attr__( 'Add all in this group', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.tokens.addAllTokensInGroup
				'includeTokenName'               => esc_attr__( 'Include token name', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.tokens.includeTokenName
				'includeTokenID'                 => esc_attr__( 'Include token ID', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.tokens.includeTokenID
				'addTokens'                      => esc_attr__( 'Add tokens', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.tokens.addTokens
				/* translators: 1. Group name 2. Field name */
				'addAllDescription'              => esc_attr__( 'On confirmation, all the tokens in the group "%1$s" will be added to the field  "%2$s".', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.tokens.addAllDescription

				'isDelayed'                      => esc_html__( 'Delayed', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.tokens.isDelayed
				'isDelayedTokensDisclaimer'      => esc_html__( 'These tokens may be empty if the associated action has not yet run when this action runs.', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.tokens.isDelayedTokensDisclaimer

				'isScheduled'                    => esc_html__( 'Scheduled', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.tokens.isScheduled
				'isScheduledTokensDisclaimer'    => esc_html__( 'These tokens may be empty if the associated action has not yet run when this action runs.', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.tokens.isScheduledTokensDisclaimer
			),
			'integration'            => array(
				/* translators: Verb conjugated in present-tense second-person singular */
				'select'         => esc_attr__( 'Select an integration', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.integration.select
				/* translators: Verb conjugated in present-tense second-person singular */
				'discoverMore'   => esc_attr__( 'Discover more awesome integrations.', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.integration.discoverMore
				/* translators: Verb conjugated in present-tense second-person singular */
				'dontSeeTrigger' => esc_attr__( "Don't see your trigger? Try a recipe that runs for logged-in users only.", 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.integration.dontSeeTrigger
			),
			'publish'                => array(
				/* translators: Number of times the recipe can be triggered per user */
				'timesPerUser'   => esc_attr__( 'Times per user:', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.publish.timesPerUser
				/* translators: Verb conjugated in present-tense second-person singular */
				'leaveEmpty'     => esc_attr__( 'Leave empty for unlimited times', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.publish.leaveEmpty
				/* translators: Unlimited times */
				'unlimited'      => esc_attr_x( 'Unlimited', 'Publish', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.publish.unlimited
				'oneOrBigger'    => esc_attr__( 'This number has to be 1 or bigger', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.publish.oneOrBigger
				/* translators: Recipe creation date */
				'createdOn'      => esc_attr_x( 'Created on:', 'Recipe', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.publish.createdOn
				/* translators: Recipe type */
				'recipeType'     => esc_attr_x( 'User type:', 'Recipe', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.publish.recipeType
				/* translators: Duplicate recipe */
				'copyRecipe'     => esc_attr_x( 'Duplicate this recipe', 'Recipe', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.publish.copyRecipe
				/* translators: Copy recipe */
				'timesPerRecipe' => esc_attr_x( 'Total times:', 'Recipe', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.publish.timesPerRecipe
				'recipeRunTimes' => esc_attr_x( 'Completed runs:', 'Recipe', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.publish.recipeRunTimes
			),
			'confirmationModal'      => array(
				'title'         => esc_attr__( 'Are you sure?', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.confirmationModal.title
				'deleteWarning' => esc_attr__( 'Deleting items in a {{live}} recipe can lead to unexpected behaviors.', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.confirmationModal.deleteWarning
				'statusWarning' => esc_attr__( 'Setting items to draft in a {{live}} recipe can lead to unexpected behaviors.', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.confirmationModal.statusWarning
			),
			'walkthrough'            => array(
				'_global'       => array(
					'set_walkthrough_progress' => array(
						'enabling_walkthrough' => esc_attr__( 'Enabling walkthrough', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough._global.set_walkthrough_progress.enabling_walkthrough
						'walkthrough_enabled'  => esc_attr__( 'Walkthrough enabled', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough._global.set_walkthrough_progress.walkthrough_enabled
						'closing_walkthrough'  => esc_attr__( 'Closing walkthrough', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough._global.set_walkthrough_progress.closing_walkthrough
						'walkthrough_closed'   => esc_attr__( 'Walkthrough closed', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough._global.set_walkthrough_progress.walkthrough_closed
					),
				),
				'create_recipe' => array(
					'close_walkthrough'                  => array(
						'confirmation_title'        => esc_attr__( 'Exit the tutorial', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.close_walkthrough.confirmation_title
						'confirmation_message'      => esc_attr__( 'Are you sure you want to close the walkthrough?', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.close_walkthrough.confirmation_message
						'confirmation_button_label' => esc_attr__( 'Confirm', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.close_walkthrough.confirmation_button_label
					),
					'welcome'                            => array(
						'title'          => esc_attr__( 'Welcome to Uncanny Automator', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.welcome.title
						'message'        => esc_attr__( "Let's take 2 minutes to walk through building your first recipe!", 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.welcome.message
						'next_btn_label' => esc_attr__( 'Next', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.welcome.next_btn_label
					),
					'recipe_type'                        => array(
						'title'      => esc_attr__( 'Recipe type', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.recipe_type.title
						'message'    => esc_attr__( 'The first step is to choose what kind of recipe you are going to use. We will use "Logged in" for this example since the automation we\'re creating is linked to a WordPress user. Click the highlighted box to the right to continue.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.recipe_type.message
						'learn_more' => esc_attr__( 'Learn more about recipe types here', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.recipe_type.learn_more
					),
					'recipe_type_confirm'                => array(
						'title'   => esc_attr__( 'Recipe type', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.recipe_type_confirm.title
						'message' => esc_attr__( 'Confirm your recipe type selection.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.recipe_type_confirm.message
					),
					'set_title'                          => array(
						'title'             => esc_attr__( 'Set a title', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.set_title.title
						'message'           => esc_attr__( "Every recipe must have a title. Use something that makes it easy to identify what your recipe does at a glance. For this example, we'll populate the title for you.", 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.set_title.message
						'next_btn_label'    => esc_attr__( 'Next', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.set_title.next_btn_label
						'set_title_content' => esc_attr__( 'Send email when a post is published', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.set_title.set_title_content
					),
					'triggers_all_integrations'          => array(
						'title'          => esc_attr__( 'Integrations', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.triggers_all_integrations.title
						'message'        => esc_attr__( "You can find all of the integrations that Automator supports at this link. We're always adding more!", 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.triggers_all_integrations.message
						'next_btn_label' => esc_attr__( 'Next', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.triggers_all_integrations.next_btn_label
					),
					'trigger_select'                     => array(
						'title'    => esc_attr__( 'Select an integration', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.trigger_select.title
						'message1' => esc_attr__( "The recipe's triggers specify what will cause the recipe's actions to run.", 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.trigger_select.message1
						'message2' => esc_attr__( 'Our recipe will use a WordPress trigger. Start by clicking the WordPress integration.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.trigger_select.message2
					),
					'trigger_select_item'                => array(
						'title'   => esc_attr__( 'Select a trigger', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.trigger_select_item.title
						'message' => esc_attr__( 'Click "A user publishes a post".', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.trigger_select_item.message
					),
					'trigger_open_select_post_type'      => array(
						'title'   => esc_attr__( 'Set the post type', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.trigger_open_select_post_type.title
						'message' => esc_attr__( 'This trigger allows you to target any post type in WordPress, including custom post types. Click into the Post type drop-down to select a specific post type.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.trigger_open_select_post_type.message
					),
					'trigger_change_select_post_type_value' => array(
						'title'   => esc_attr__( 'Set the post type', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.trigger_change_select_post_type_value.title
						'message' => esc_attr__( 'We want this automation to run whenever a new blog post is published, so choose "Post" as the post type.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.trigger_change_select_post_type_value.message
					),
					'trigger_save_item'                  => array(
						'title'   => esc_attr__( 'Save the trigger', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.trigger_save_item.title
						'message' => esc_attr__( 'Click "Save".', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.trigger_save_item.message
					),
					'trigger_status'                     => array(
						'title'   => esc_attr__( 'Trigger status', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.trigger_status.title
						'message' => esc_attr__( 'A trigger must be in "Live" status to trigger the recipe.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.trigger_status.message
					),
					'action_open_integrations'           => array(
						'title'    => esc_attr__( 'Add an action', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_open_integrations.title
						'message1' => esc_attr__( "A recipe's actions execute automatically once the recipe's triggers have been completed.", 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_open_integrations.message1
						'message2' => esc_attr__( 'Click "Add action" to continue.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_open_integrations.message2
					),
					'action_all_integrations'            => array(
						'title'          => esc_attr__( 'Integrations', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_all_integrations.title
						'message'        => esc_attr__( 'You can find a list of all supported integrations for recipe actions at this link.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_all_integrations.message
						'next_btn_label' => esc_attr__( 'Next', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_all_integrations.next_btn_label
					),
					'action_select_integration'          => array(
						'title'   => esc_attr__( 'Select an integration', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_select_integration.title
						'message' => esc_attr__( 'In this recipe we will send a notification email whenever a new blog post is published, so choose the Emails integration.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_select_integration.message
					),
					'action_select_action'               => array(
						'title'   => esc_attr__( 'Select an action', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_select_action.title
						'message' => esc_attr__( 'Select the "Send an email" action.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_select_action.message
					),
					'action_email_to_field'              => array(
						'title'          => esc_attr__( 'Set the email recipient', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_email_to_field.title
						'message'        => esc_attr__( "In this field we'll specify who the email should be sent to.", 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_email_to_field.message
						'next_btn_label' => esc_attr__( 'Next', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_email_to_field.next_btn_label
					),
					'action_email_to_token'              => array(
						'title'    => esc_attr__( 'Set the email recipient', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_email_to_token.title
						'message1' => esc_attr__( 'Let\'s make sure this email is sent to the administrator of the site. To do this, we\'ll use a "token".', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_email_to_token.message1
						'message2' => esc_attr__( "Tokens allow us to use dynamic data, or variables, in recipe actions. This data might come from information about the trigger, other actions, users, system data, and more. For this example, we want to look up which email address to use dynamically and include it in our recipe, so we'll use the token selector (*) to the right", 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_email_to_token.message2
					),
					'action_email_to_token_group'        => array(
						'title'   => esc_attr__( 'Set the email recipient', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_email_to_token_group.title
						'message' => esc_attr__( 'The user email token is found under "Common" tokens. Click "Common".', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_email_to_token_group.message
					),
					'action_email_to_token_select_token' => array(
						'title'   => esc_attr__( 'Set the email recipient', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_email_to_token_select_token.title
						'message' => esc_attr__( 'Click "Admin email".', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_email_to_token_select_token.message
					),
					'action_email_set_subject'           => array(
						'title'           => esc_attr__( 'Set the subject', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_email_set_subject.title
						'message'         => esc_attr__( 'We\'ll populate the email subject for you. For this example, we\'ll use "A new post is published", along with the title of the post that was published, which we use a token from the trigger to populate.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_email_set_subject.message
						'next_btn_label'  => esc_attr__( 'Next', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_email_set_subject.next_btn_label
						/* translators: 1. Is a dynamic token */
						'subject_content' => esc_attr__( 'A new post was published: %1$s', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_email_set_subject.subject_content
					),
					'action_email_set_body'              => array(
						'title'          => esc_attr__( 'Set the body', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_email_set_body.title
						'message'        => esc_attr__( 'We have pre-populated the email body with some suggested text. Feel free to change it after the tutorial to something that works better for your site.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_email_set_body.message
						'next_btn_label' => esc_attr__( 'Next', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_email_set_body.next_btn_label
						/* translators: 1, 2 and 3 are dynamic tokens */
						'body_content'   => esc_attr__( 'A blog post titled %1$s was published by %2$s to %3$s.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_email_set_body.body_content
					),
					'action_save_item'                   => array(
						'title'   => esc_attr__( 'Save the action.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_save_item.title
						'message' => esc_attr__( 'Click "Save".', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_save_item.message
					),
					'action_status'                      => array(
						'title'   => esc_attr__( 'Action status', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_status.title
						'message' => esc_attr__( 'An action must be in "Live" status to run.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.action_status.message
					),
					'recipe_status'                      => array(
						'title'   => esc_attr__( 'Recipe status', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.recipe_status.title
						'message' => esc_attr__( 'The last step is to take your recipe live! Click the "Live/Draft" toggle switch.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.recipe_status.message
					),
					'recipe_created'                     => array(
						'title'          => esc_attr__( 'Congratulations!', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.recipe_created.title
						'message1'       => esc_attr__( 'Your recipe is now live. Any time you publish a blog post, an email notification will now be sent to the site administrator. You can check the results of this automation from the Automator > Logs page later.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.recipe_created.message1
						'message2'       => esc_attr__( 'Now that you know the steps required to create a recipe, try building some of your own!', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.recipe_created.message2
						'next_btn_label' => esc_attr__( 'Finish tour', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.walkthrough.create_recipe.recipe_created.next_btn_label
					),
				),
			),
			'proOnly'                => array(
				'pro'          => 'Pro',
				// Don't make this string translatable
				// UncannyAutomator._core.i18n.proOnly.pro
				/* translators: 1. Trademarked term */
				'warning'      => sprintf( esc_attr__( 'This is a %1$s feature.', 'uncanny-automator' ), 'Pro' ),
				// UncannyAutomator._core.i18n.proOnly.warning
				/* translators: 1. Plugin name */
				// 'multipleTriggers' =>  esc_attr__( 'This recipe contains multiple triggers and requires %1$s.', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.proOnly.multipleTriggers
				'proActive'    => esc_attr__( 'Please ensure the plugin is installed and activated.', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.proOnly.proActive
				/* translators: 1. Plugin name */
				'proToPublish' => esc_attr__( 'Please install %1$s to activate this recipe.', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.proOnly.proToPublish
				/* translators: Non-personal infinitive verb */
				'moveToTrash'  => esc_attr__( 'Move to trash', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.proOnly.moveToTrash
				// 'unlockTriggers'   => sprintf(  esc_attr__( 'Get %s to unlock these triggers', 'uncanny-automator' ), 'Uncanny Automator Pro' ),
				// UncannyAutomator._core.i18n.proOnly.unlockTriggers
				// 'unlockActions'    => sprintf(  esc_attr__( 'Get %s to unlock these actions', 'uncanny-automator' ), 'Uncanny Automator Pro' ),
				// UncannyAutomator._core.i18n.proOnly.unlockActions
				/* translators: 1. Trademarked term */
				'requiresPro'  => esc_attr__( 'Requires %1$s', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.proOnly.requiresPro
			),
			'eliteOnly'              => array(
				'elite' => 'Elite',
				// Don't make this string translatable
				// UncannyAutomator._core.i18n.eliteOnly.elite
			),
			'sendFeedback'           => array(
				/* translators: Non-personal infinitive verb */
				'title' => esc_attr__( 'Send feedback', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.sendFeedback.title
				/* translators: 1. Plugin name */
				// 'message'       =>  esc_attr__( 'Help us improve %1$s! Click the icon below to send feedback', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.sendFeedback.message
				// 'dontShowAgain' =>  esc_attr__( 'Don\'t show again', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.sendFeedback.dontShowAgain
				// 'gotIt'         =>  esc_attr__( 'Got it', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.sendFeedback.gotIt
			),
			'apiIntegrations'        => array(
				/* translators: 1. Trademarked term */
				'integrationNotConnected'   => esc_attr__( '%1$s account not connected. Click to connect.', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.apiIntegrations.integrationNotConnected
				'allActionsRequireUserData' => esc_attr__( 'All actions need user data', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.apiIntegrations.allActionsRequireUserData
				'notConnected'              => esc_attr__( 'Not connected', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.apiIntegrations.notConnected
				'instructionsToConnect'     => array(
					'connectIntegration' => esc_attr__( 'Connect integration', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.apiIntegrations.instructionsToConnect.connectIntegration
					/* translators: 1. Trademarked term */
					'step1'              => esc_attr__( 'Sign up for a free %1$s account!', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.apiIntegrations.instructionsToConnect.step1
					/* translators: 2. Trademarked term */
					'step1Pro'           => esc_attr__( 'Activate your %1$s license key!', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.apiIntegrations.instructionsToConnect.step1Pro
					/* translators: 1. Number, 2. Number */
					'stepCounter'        => esc_attr__( '%1$s of %2$s steps completed', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.apiIntegrations.instructionsToConnect.stepCounter
					'signUp'             => esc_attr__( 'Sign up', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.apiIntegrations.instructionsToConnect.signUp
					'activate'           => esc_attr__( 'Activate', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.apiIntegrations.instructionsToConnect.activate
					/* translators: 1. Trademarked term, 2. Trademarked term */
					'step2'              => esc_attr__( 'Connect your %1$s account to %2$s.', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.apiIntegrations.instructionsToConnect.step2
					'connectAccount'     => esc_attr__( 'Connect account', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.apiIntegrations.instructionsToConnect.connectAccount
					'completePrevious'   => esc_attr__( 'Please, complete the previous step first.', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.apiIntegrations.instructionsToConnect.completePrevious
					/* translators: 1. Trademarked term */
					'step3'              => esc_attr__( 'Refresh this page and add your %1$s action.', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.apiIntegrations.instructionsToConnect.step3
					'refresh'            => esc_attr__( 'Refresh', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.apiIntegrations.instructionsToConnect.refresh
				),
			),
			'recipeType'             => array(
				/* translators: Verb conjugated in present-tense second-person singular */
				'title'                       => esc_attr__( 'Select a recipe type', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.recipeType.title
				/* translators: Recipe type. Logged-in recipes are triggered only by logged-in users */
				'userRecipeName'              => esc_attr_x( 'Logged-in users', 'Recipe', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.recipeType.userRecipeName
				'userRecipeDescription'       => esc_attr__( 'Recipe will be triggered by logged-in WordPress users.', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.recipeType.userRecipeDescription
				'cantChangeLaterNotice'       => esc_attr__( 'Recipe type cannot be changed later.', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.recipeType.cantChangeLaterNotice
				'errorDidNotSelectType'       => esc_attr__( 'Please select an option.', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.recipeType.errorDidNotSelectType
				'errorTryingToSaveOtherValue' => esc_attr__( 'Error when saving value.', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.recipeType.errorTryingToSaveOtherValue
				'errorSomethingWentWrong'     => esc_attr__( 'Sorry, something went wrong. Please try again.', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.recipeType.errorSomethingWentWrong
				'everyoneRecipeName'          => esc_attr_x( 'Everyone', 'Recipe type', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.recipeType.everyoneRecipeName
				'everyoneRecipeDescription'   => esc_attr__( 'Recipe will be triggered by logged-in WordPress users or logged out visitors.', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.recipeType.everyoneRecipeDescription
				'everyoneOnlyOneTrigger'      => esc_attr__( 'This recipe type supports one trigger per recipe.', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.recipeType.everyoneOnlyOneTrigger
			),
			'userSelector'           => array(
				/* translators: Verb conjugated in present-tense second-person singular */
				'firstStepTitle' => esc_attr__( 'Choose who will do the actions', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.userSelector.firstStepTitle
				/* translators: Verb conjugated in present-tense second-person singular */
				'setOptions'     => esc_attr__( 'Set user data', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.userSelector.setOptions
				'sentenceTitle'  => esc_attr__( 'Actions will be run on...', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.userSelector.sentenceTitle
				'logUserIn'      => esc_attr__( 'Log the new user in?', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.userSelector.logUserIn
				'userType'       => array(
					'existing' => esc_attr__( 'Existing user', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.userType.existing
					'new'      => esc_attr__( 'New user', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.userType.new
				),
				'summary'        => array(
					/* translators: 1. Field name, 2. Field value */
					'matches'            => esc_attr_x( '%1$s matches %2$s', 'User selector', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.summary.matches
					'email'              => esc_attr__( 'Email', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.summary.email
					'id'                 => esc_attr__( 'ID', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.summary.id
					'username'           => esc_attr__( 'Username', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.summary.username
					/* translators: 1. An email address */
					'withEmail'          => esc_attr_x( 'With the email %1$s', 'User selector', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.summary.withEmail
					/* translators: 1. An action */
					'otherwise'          => esc_attr_x( 'Otherwise, %1$s', 'User selector', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.summary.otherwise
					/* translators: Non-personal infinitive verb. It's used after "Otherwise, %1$s" */
					'doNothing'          => esc_attr_x( 'do nothing', 'User selector', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.summary.doNothing
					/* translators: Non-personal infinitive verb. It's used after "Otherwise, %1$s" */
					'createNewUser'      => esc_attr_x( 'create a new user', 'User selector', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.summary.createNewUser
					/* translators: Non-personal infinitive verb. It's used after "Otherwise, %1$s" */
					'selectExistingUser' => esc_attr_x( 'select an existing user', 'User selector', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.summary.selectExistingUser
				),
				'existingUser'   => array(
					'uniqueFieldLabel'                   => esc_attr__( 'Unique field', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.existingUser.uniqueFieldLabel
					'uniqueFieldOptionEmail'             => esc_attr__( 'Email', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.existingUser.uniqueFieldOptionEmail
					'uniqueFieldOptionId'                => esc_attr__( 'ID', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.existingUser.uniqueFieldOptionId
					'uniqueFieldOptionUsername'          => esc_attr__( 'Username', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.existingUser.uniqueFieldOptionUsername
					'valueFieldLabel'                    => esc_attr__( 'Value', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.existingUser.valueFieldLabel
					'valueFieldPlaceholder'              => esc_attr__( 'Value of the unique field', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.existingUser.valueFieldPlaceholder
					'createNewUserFieldLabel'            => esc_html__( "What to do if the user doesn't exist", 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.existingUser.createNewUserFieldLabel
					/* translators: Non-personal infinitive verb */
					'createNewUserFieldOptionCreateUser' => esc_attr__( 'Create new user', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.existingUser.createNewUserFieldOptionCreateUser
					/* translators: Non-personal infinitive verb */
					'createNewUserFieldOptionDoNothing'  => esc_attr__( 'Do nothing', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.existingUser.createNewUserFieldOptionDoNothing
					'doNothingMessage'                   => esc_attr__( 'If no user matches the unique field and value then the actions are not going to be executed.', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.existingUser.doNothingMessage
				),
				'newUser'        => array(
					'firstName'                        => esc_attr__( 'First name', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.newUser.firstName
					'lastName'                         => esc_attr__( 'Last name', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.newUser.lastName
					'email'                            => esc_attr__( 'Email', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.newUser.email
					'username'                         => esc_attr__( 'Username', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.newUser.username
					'displayName'                      => esc_attr__( 'Display name', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.newUser.displayName
					'password'                         => esc_attr__( 'Password', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.newUser.password
					/* translators: 1. Token name */
					'passwordDescription'              => sprintf( esc_attr__( 'If left empty, the user will need to reset their password to log in. Send an email containing the %1$s token to simplify the process.', 'uncanny-automator' ), '<em>' . esc_attr__( 'User reset password link', 'uncanny-automator' ) . '</em>' ),
					// UncannyAutomator._core.i18n.userSelector.newUser.passwordDescription
					/* translators: WordPress roles */
					'role'                             => esc_attr__( 'Role', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.newUser.role
					'existingUserFieldLabel'           => esc_attr__( 'What to do if the user already exists', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.newUser.existingUserFieldLabel
					/* translators: Non-personal infinitive verb */
					'existingUserFieldOptionExisting'  => esc_attr__( 'Select existing user', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.newUser.existingUserFieldOptionExisting
					/* translators: Non-personal infinitive verb */
					'existingUserFieldOptionDoNothing' => esc_attr__( 'Do nothing', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.newUser.existingUserFieldOptionDoNothing
					'doNothingMessage'                 => esc_attr__( 'If there is already a user with the defined email address or username, the actions are not going to be executed.', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.newUser.doNothingMessage
					'prioritizedFieldLabel'            => esc_attr__( 'Prioritized field', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.newUser.prioritizedFieldLabel
					'prioritizedFieldDescription'      => esc_attr__( 'Select the field that should be prioritized if, during creation of the user, two different users are found (one that matches the email field and another one that matches the username field).', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.userSelector.newUser.prioritizedFieldDescription
				),
				'userDataModal'  => array(
					'action' => array(
						// User can add the action, but needs to confirm
						'allowed'   => array(
							'title'         => esc_attr__( 'We need some user data', 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.userSelector.userDataModal.action.allowed.title
							'content'       => array(
								'mustRunOnUser' => esc_attr__( 'This action must be run on a WordPress user.', 'uncanny-automator' ),
								// UncannyAutomator._core.i18n.userSelector.userDataModal.action.allowed.content.mustRunOnUser
								'description'   => esc_attr__( "Since this is a recipe that runs for everyone, including logged-out users, you'll need to select a new or existing user that this action will run on.", 'uncanny-automator' ),
								// UncannyAutomator._core.i18n.userSelector.userDataModal.action.allowed.content.description
							),
							'confirmButton' => esc_attr__( 'Set user data', 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.userSelector.userDataModal.action.allowed.confirmButton
						),
						// User can't add the action
						'forbidden' => array(
							'title'         => esc_attr__( 'We need some user data', 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.userSelector.userDataModal.action.forbidden.title
							'content'       => array(
								'mustRunOnUser' => esc_attr__( 'This action must be run on a WordPress user.', 'uncanny-automator' ),
								// UncannyAutomator._core.i18n.userSelector.userDataModal.action.forbidden.content.mustRunOnUser
								/* translators: 1. Trademarked term. */
								'description'   => esc_attr__( 'Because the action is associated with user data, it must be mapped to a new or existing user. This requires %1$s.', 'uncanny-automator' ),
								// UncannyAutomator._core.i18n.userSelector.userDataModal.action.forbidden.content.description
								'upgradeLater'  => esc_attr__( "If you don't want to upgrade now, you can add this action to a recipe that runs only for logged-in users.", 'uncanny-automator' ),
								// UncannyAutomator._core.i18n.userSelector.userDataModal.action.forbidden.content.upgradeLater
							),
							'confirmButton' => esc_attr__( 'Upgrade to Pro', 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.userSelector.userDataModal.action.disabled.confirmButton
						),
					),
					'token'  => array(
						// User can add the token, but needs to confirm
						'allowed'   => array(
							'title'         => esc_attr__( 'We need some user data', 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.userSelector.userDataModal.token.allowed.title
							'content'       => array(
								/* translators: 1. Token ID */
								'mustRunOnUser' => esc_attr__( 'The token %1$s outputs the data of a WordPress user.', 'uncanny-automator' ),
								// UncannyAutomator._core.i18n.userSelector.userDataModal.token.allowed.content.mustRunOnUser
								'description'   => esc_attr__( "Since this is a recipe that runs for everyone, including logged-out users, you'll need to select a new or existing user that this data will come from.", 'uncanny-automator' ),
								// UncannyAutomator._core.i18n.userSelector.userDataModal.token.allowed.content.description
							),
							'cancelButton'  => esc_attr__( 'Remove token', 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.userSelector.userDataModal.token.allowed.cancelButton
							'confirmButton' => esc_attr__( 'Set user data', 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.userSelector.userDataModal.token.allowed.confirmButton
						),
						// User can't add the token
						'forbidden' => array(
							'title'         => esc_attr__( 'We need some user data', 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.userSelector.userDataModal.token.forbidden.title
							'content'       => array(
								/* translators: 1. Token ID */
								'mustRunOnUser' => esc_attr__( 'The token %1$s outputs the data of a WordPress user.', 'uncanny-automator' ),
								// UncannyAutomator._core.i18n.userSelector.userDataModal.token.forbidden.content.mustRunOnUser
								/* translators: 1. Trademarked term. */
								'description'   => esc_attr__( 'Because the token is associated with user data, it must be mapped to a new or existing user. This requires %1$s.', 'uncanny-automator' ),
								// UncannyAutomator._core.i18n.userSelector.userDataModal.token.forbidden.content.description
								'upgradeLater'  => esc_attr__( "If you don't want to upgrade now, you can add this token to a recipe that runs only for logged-in users.", 'uncanny-automator' ),
								// UncannyAutomator._core.i18n.userSelector.userDataModal.token.forbidden.content.upgradeLater
							),
							'cancelButton'  => esc_attr__( 'Remove token', 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.userSelector.userDataModal.token.forbidden.cancelButton
							'confirmButton' => esc_attr__( 'Upgrade to Pro', 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.userSelector.userDataModal.token.forbidden.confirmButton
						),
					),
				),
			),
			'debugging'              => array(
				'fatalErrorHandler'   => array(
					'title'        => esc_html__( 'Sorry, something went wrong', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.debugging.fatalErrorHandler.title
					'content'      => esc_html__( 'Click "Learn more" for steps you can take to resolve this issue.', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.debugging.fatalErrorHandler.content

					'accessDenied' => array(
						'title'   => esc_html__( 'Access denied', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.debugging.fatalErrorHandler.accessDenied.title
						/* translators: 1. The endpoint */
						'content' => esc_html__( 'Automator experienced a permissions (403) error. This might be caused by an expired WordPress session or a REST endpoint access issue. Automator specifically failed to do a REST call to %1$s. Reloading the page may fix the issue, otherwise have your host investigate why requests to %1$s are returning a 403 error.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.debugging.fatalErrorHandler.accessDenied.content
					),

					'notFound'     => array(
						/* translators: 1. The name of the endpoint */
						'title'          => esc_html__( 'Endpoint %1$s not found', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.debugging.fatalErrorHandler.notFound.title
						/* translators: 1. Link to "Site permalinks". 2. Link to "Uncanny Automator support" */
						'content'        => esc_html__( 'A endpoint that Automator requires is missing. If your %1$s are set to Plain, please change them to something else. Otherwise, removing and reinstalling Automator plugins to rule out an upload issue is recommended, otherwise please contact %2$s.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.debugging.fatalErrorHandler.notFound.content
						'sitePermalinks' => esc_html__( 'site permalinks', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.debugging.fatalErrorHandler.notFound.sitePermalinks=
					),

					'timeout'      => array(
						'title'   => esc_html__( 'Request timeout', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.debugging.fatalErrorHandler.timeout.title
						'content' => esc_html__( 'The Automator request timed out, likely due to insufficient server resources. Please contact your host.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.debugging.fatalErrorHandler.timeout.content
					),

					'serverError'  => array(
						'title'   => esc_html__( 'Internal error', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.debugging.fatalErrorHandler.serverError.title
						/* translators: 1. Link to "Uncanny Automator support" */
						'content' => esc_html__( 'Automator experienced a fatal error on your site. Please check your PHP and debug error log for more details, then sent the associated error details to %1$s.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.debugging.fatalErrorHandler.serverError.content
					),

					'parserError'  => array(
						'title'   => esc_html__( 'Parser error', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.debugging.fatalErrorHandler.parserError.title
						'content' => esc_html__( "We have detected a conflict with another plugin. Try testing in a Staging environment with only Automator plugins active, then gradually reactivate plugins until things break again to trace what's causing it.", 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.debugging.fatalErrorHandler.parserError.content
					),

					'dataMissing'  => array(
						'title'          => esc_html__( 'Data missing', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.debugging.fatalErrorHandler.dataMissing.title
						/* translators: 1. Link to contact support. 2. Link to the "Automator Tools" page */
						'content'        => esc_html__( 'Automator has detected that expected data is missing. Please %1$s with details of the issue and a copy of the System Report on the %2$s page.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.debugging.fatalErrorHandler.dataMissing.content
						'contactSupport' => esc_html__( 'contact support', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.debugging.fatalErrorHandler.dataMissing.contactSupport
						'automatorTools' => esc_html__( 'Automator Tools', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.debugging.fatalErrorHandler.dataMissing.automatorTools
					),

					'generic'      => array(
						'title'   => esc_html__( 'Unknown error', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.debugging.fatalErrorHandler.generic.title
						/* translators: 1. Link to contact support */
						'content' => esc_html__( 'Automator has encountered an unknown error. Please contact %1$s with a screenshot of your recipe, details about what you were doing and any other information that may help us with the error.', 'uncanny-automator' ),
						// UncannyAutomator._core.i18n.debugging.fatalErrorHandler.generic.content
					),

					'exceptions'   => array(
						'tags' => array(
							'couldNotSave' => esc_html__( "We couldn't save the tags", 'uncanny-automator' ),
							// UncannyAutomator._core.i18n.debugging.fatalErrorHandler.exceptions.tags.couldNotSave
						),
					),
				),
				'uiCantLoad'          => array(
					'title'   => esc_html__( 'The recipe creator could not be loaded', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.debugging.uiCantLoad.title
					'warning' => esc_html__( 'Sorry, something went wrong when loading the recipe interface. Technical details are listed below.', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.debugging.uiCantLoad.warning
					'content' => esc_html__( 'Click Learn More for steps you can take to resolve this issue.', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.debugging.uiCantLoad.content
				),
				'buttonGoBack'        => esc_html__( 'Go to all recipes', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.debugging.buttonGoBack
				'learnMore'           => esc_html__( 'Learn more', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.debugging.learnMore
				/* translators: 1. Trademarked term */
				'automatorSupport'    => sprintf( esc_html__( '%1$s support', 'uncanny-automator' ), 'Uncanny Automator' ),
				// UncannyAutomator._core.i18n.debugging.automatorSupport
				'recipeTitle'         => 'Recipe',
				'recipeItemsTitle'    => 'Recipe items',
				'recipeType'          => 'Type',
				'recipeStatus'        => 'Status',
				'recipeStatusLive'    => 'Live',
				'recipeStatusDraft'   => 'Draft',
				'recipeTypeUser'      => 'User',
				'recipeTypeAnonymous' => 'Anonymous',
				'noRecipeItemsFound'  => 'Recipe without items',
				'triggersTitle'       => 'Triggers',
				'noTriggers'          => 'No triggers',
				'actionsTitle'        => 'Actions',
				'noActions'           => 'No actions',
				'closuresTitle'       => 'Closures',
				'noClosures'          => 'No closures',
				'itemStatusLive'      => 'Live',
				'itemStatusDraft'     => 'Draft',
				'jsConsole'           => 'JS console',
				'token'               => array(
					'columnName'  => esc_html__( 'Name', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.debugging.token.columnName
					'columnID'    => esc_html__( 'ID', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.debugging.token.columnID
					'columnValue' => esc_html__( 'Value', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.debugging.token.columnValue
				),
			),
			'format'                 => array(
				'date' => array(
					'selectDate'       => esc_html__( 'Select date', 'uncanny-automator' ),
					'selectTime'       => esc_html__( 'Select time', 'uncanny-automator' ),
					'weekdays'         => array(
						'shorthand' => array(
							/* translators: Abbreviation - Monday (3 letters) */
							'monday'    => esc_attr_x( 'Mon', 'Date format', 'uncanny-automator' ),
							/* translators: Abbreviation - Tuesday (3 letters) */
							'tuesday'   => esc_attr_x( 'Tue', 'Date format', 'uncanny-automator' ),
							/* translators: Abbreviation - Wednesday (3 letters) */
							'wednesday' => esc_attr_x( 'Wed', 'Date format', 'uncanny-automator' ),
							/* translators: Abbreviation - Thursday (3 letters) */
							'thursday'  => esc_attr_x( 'Thu', 'Date format', 'uncanny-automator' ),
							/* translators: Abbreviation - Friday (3 letters) */
							'friday'    => esc_attr_x( 'Fri', 'Date format', 'uncanny-automator' ),
							/* translators: Abbreviation - Saturday (3 letters) */
							'saturday'  => esc_attr_x( 'Sat', 'Date format', 'uncanny-automator' ),
							/* translators: Abbreviation - Sunday (3 letters) */
							'sunday'    => esc_attr_x( 'Sun', 'Date format', 'uncanny-automator' ),
						),
						'longhand'  => array(
							'monday'    => esc_attr_x( 'Monday', 'Date format', 'uncanny-automator' ),
							'tuesday'   => esc_attr_x( 'Tuesday', 'Date format', 'uncanny-automator' ),
							'wednesday' => esc_attr_x( 'Wednesday', 'Date format', 'uncanny-automator' ),
							'thursday'  => esc_attr_x( 'Thursday', 'Date format', 'uncanny-automator' ),
							'friday'    => esc_attr_x( 'Friday', 'Date format', 'uncanny-automator' ),
							'saturday'  => esc_attr_x( 'Saturday', 'Date format', 'uncanny-automator' ),
							'sunday'    => esc_attr_x( 'Sunday', 'Date format', 'uncanny-automator' ),
						),
					),
					'months'           => array(
						'shorthand' => array(
							/* translators: Abbreviation - January (3 letters) */
							'january'   => esc_attr_x( 'Jan', 'Date format', 'uncanny-automator' ),
							/* translators: Abbreviation - February (3 letters) */
							'february'  => esc_attr_x( 'Feb', 'Date format', 'uncanny-automator' ),
							/* translators: Abbreviation - March (3 letters) */
							'march'     => esc_attr_x( 'Mar', 'Date format', 'uncanny-automator' ),
							/* translators: Abbreviation - April (3 letters) */
							'april'     => esc_attr_x( 'Apr', 'Date format', 'uncanny-automator' ),
							/* translators: Abbreviation - May (3 letters) */
							'may'       => esc_attr_x( 'May', 'Date format', 'uncanny-automator' ),
							/* translators: Abbreviation - June (3 letters) */
							'june'      => esc_attr_x( 'Jun', 'Date format', 'uncanny-automator' ),
							/* translators: Abbreviation - July (3 letters) */
							'july'      => esc_attr_x( 'Jul', 'Date format', 'uncanny-automator' ),
							/* translators: Abbreviation - August (3 letters) */
							'august'    => esc_attr_x( 'Aug', 'Date format', 'uncanny-automator' ),
							/* translators: Abbreviation - September (3 letters) */
							'september' => esc_attr_x( 'Sep', 'Date format', 'uncanny-automator' ),
							/* translators: Abbreviation - October (3 letters) */
							'october'   => esc_attr_x( 'Oct', 'Date format', 'uncanny-automator' ),
							/* translators: Abbreviation - November (3 letters) */
							'november'  => esc_attr_x( 'Nov', 'Date format', 'uncanny-automator' ),
							/* translators: Abbreviation - December (3 letters) */
							'december'  => esc_attr_x( 'Dec', 'Date format', 'uncanny-automator' ),
						),
						'longhand'  => array(
							'january'   => esc_attr_x( 'January', 'Date format', 'uncanny-automator' ),
							'february'  => esc_attr_x( 'February', 'Date format', 'uncanny-automator' ),
							'march'     => esc_attr_x( 'March', 'Date format', 'uncanny-automator' ),
							'april'     => esc_attr_x( 'April', 'Date format', 'uncanny-automator' ),
							'may'       => esc_attr_x( 'May', 'Date format', 'uncanny-automator' ),
							'june'      => esc_attr_x( 'June', 'Date format', 'uncanny-automator' ),
							'july'      => esc_attr_x( 'July', 'Date format', 'uncanny-automator' ),
							'august'    => esc_attr_x( 'August', 'Date format', 'uncanny-automator' ),
							'september' => esc_attr_x( 'September', 'Date format', 'uncanny-automator' ),
							'october'   => esc_attr_x( 'October', 'Date format', 'uncanny-automator' ),
							'november'  => esc_attr_x( 'November', 'Date format', 'uncanny-automator' ),
							'december'  => esc_attr_x( 'December', 'Date format', 'uncanny-automator' ),
						),
					),
					/* translators: Range separator */
					'rangeSeparator'   => esc_attr_x( 'to', 'Date format', 'uncanny-automator' ),
					/* translators: Numeric representation of the day of the week. 1 (for Sunday) through 7 (for Saturday) */
					'firstDayOfWeek'   => esc_attr_x( '1', 'Date format', 'uncanny-automator' ),
					/* translators: Abbreviation - Week */
					'weekAbbreviation' => esc_attr_x( 'Wk', 'Date format', 'uncanny-automator' ),
					'am'               => esc_attr_x( 'AM', 'Date format', 'uncanny-automator' ),
					'pm'               => esc_attr_x( 'PM', 'Date format', 'uncanny-automator' ),
					'year'             => esc_attr_x( 'Year', 'Date format', 'uncanny-automator' ),
					'month'            => esc_attr_x( 'Month', 'Date format', 'uncanny-automator' ),
					'hour'             => esc_attr_x( 'Hour', 'Date format', 'uncanny-automator' ),
					'minute'           => esc_attr_x( 'Minute', 'Date format', 'uncanny-automator' ),
				),
			),
			'components'             => array(
				// UncannyAutomator._core.i18n.components.item
				'item'     => array(
					'statusOn'      => esc_html__( 'Live', 'uncanny-automator' ),
					'statusOff'     => esc_html__( 'Draft', 'uncanny-automator' ),
					'delete'        => esc_html__( 'Delete', 'uncanny-automator' ),
					'support'       => _x( 'Support', 'Noun', 'uncanny-automator' ),
					'empty'         => _x( 'Empty', 'Adjective', 'uncanny-automator' ),
					'duplicate'     => _x( 'Duplicate', 'Verb', 'uncanny-automator' ),
					'delaySchedule' => _x( 'Delay or schedule', 'Verb', 'uncanny-automator' ),
				),
				// UncannyAutomator._core.i18n.components.itemLoop
				'itemLoop' => array(
					'loop'                   => _x( 'Loop', 'Block name, noun', 'uncanny-automator' ),
					'users'                  => esc_html__( 'Users', 'uncanny-automator' ),
					'posts'                  => esc_html__( 'Posts', 'uncanny-automator' ),
					'addAction'              => esc_html__( 'Add action', 'uncanny-automator' ),
					'addFilter'              => esc_html__( 'Add filter', 'uncanny-automator' ),
					'delete'                 => esc_html__( 'Delete', 'uncanny-automator' ),
					'support'                => _x( 'Support', 'Noun', 'uncanny-automator' ),
					'matchCriteria'          => esc_html__( 'that match the following criteria', 'uncanny-automator' ),
					'filterUsersButtonLabel' => esc_html__( 'Filter users', 'uncanny-automator' ),
					'filterPostsButtonLabel' => esc_html__( 'Filter posts', 'uncanny-automator' ),
					'filterTokenButtonLabel' => esc_html__( 'Filter data', 'uncanny-automator' ),
					'editLoopFilter'         => esc_html__( 'Edit filter', 'uncanny-automator' ),
					'deleteLoopFilter'       => esc_html__( 'Delete filter', 'uncanny-automator' ),
					'configureTheFilter'     => esc_html__( 'Configure the loop filter', 'uncanny-automator' ),
					'filterUsers'            => esc_html__( 'Filter users', 'uncanny-automator' ),
					'filterPosts'            => esc_html__( 'Filter posts', 'uncanny-automator' ),
					'filterData'             => esc_html__( 'Filter data', 'uncanny-automator' ),
					'cancel'                 => esc_html__( 'Cancel', 'uncanny-automator' ),
					'confirm'                => esc_html__( 'Confirm', 'uncanny-automator' ),
					'searchLoopFilters'      => esc_html__( 'Search loop filters', 'uncanny-automator' ),
					'noResultsLoopFilters'   => esc_html__( 'No results found', 'uncanny-automator' ),
					'instructionsHeading'    => esc_html__( "What's a loop filter?", 'uncanny-automator' ),
					'instructionsContent'    => esc_html__( 'Loop filters decide which users will be included in the loop. Action filters within the block, on the other hand, choose what actions are performed for each individual user.', 'uncanny-automator' ),
					'userLoop'               => esc_html__( 'User loop', 'uncanny-automator' ),
					'postLoop'               => esc_html__( 'Post loop', 'uncanny-automator' ),
					'tokenLoop'              => esc_html__( 'Token loop', 'uncanny-automator' ),
					'setDataDialogHeading'   => esc_html__( 'Set data', 'uncanny-automator' ),
				),
				// UncannyAutomator._core.i18n.components.token
				'token'    => array(
					'tokenData' => esc_html__( 'Token data', 'uncanny-automator' ),
					'confirm'   => esc_html__( 'Confirm', 'uncanny-automator' ),
					'cancel'    => esc_html__( 'Cancel', 'uncanny-automator' ),
				),
			),
			'core'                   => array(
				// UncannyAutomator._core.i18n.core.runNow
				'runNow'        => array(
					'starting' => esc_html__( 'Starting recipe', 'uncanny-automator' ),
					'started'  => esc_html__( 'Recipe started successfully', 'uncanny-automator' ),
				),

				// UncannyAutomator._core.i18n.core.title
				'title'         => array(
					'updating' => esc_html__( 'Updating recipe title', 'uncanny-automator' ),
					'updated'  => esc_html__( 'Recipe title updated successfully', 'uncanny-automator' ),
				),
				// UncannyAutomator._core.i18n.core.notes
				'notes'         => array(
					'updating' => esc_html__( 'Updating recipe notes', 'uncanny-automator' ),
					'updated'  => esc_html__( 'Recipe notes updated successfully', 'uncanny-automator' ),
				),
				'recipeType'    => array(
					'settingRecipeType' => esc_html__( 'Setting recipe type', 'uncanny-automator' ),
					'recipeTypeSet'     => esc_html__( 'Recipe type set successfully', 'uncanny-automator' ),
				),
				// UncannyAutomator._core.i18n.core.item
				'item'          => array(
					// UncannyAutomator._core.i18n.core.item.deleteItem
					'deleteItem'           => array(
						'warningMessage'  => esc_attr__( 'Deleting items in a live recipe can lead to unexpected behaviors.', 'uncanny-automator' ),
						'deletingTrigger' => esc_html__( 'Deleting trigger', 'uncanny-automator' ),
						'deletingAction'  => esc_html__( 'Deleting action', 'uncanny-automator' ),
						'triggerDeleted'  => esc_html__( 'Trigger deleted successfully', 'uncanny-automator' ),
						'actionDeleted'   => esc_html__( 'Action deleted successfully', 'uncanny-automator' ),
					),
					// UncannyAutomator._core.i18n.core.item.statusItem
					'statusItem'           => array(
						'loadingItemOff'   => esc_html__( 'Switching item off', 'uncanny-automator' ),
						'loadingItemOn'    => esc_html__( 'Switching item on', 'uncanny-automator' ),
						'completedItemOn'  => esc_html__( 'Item switched on successfully', 'uncanny-automator' ),
						'completedItemOff' => esc_html__( 'Item switched off successfully', 'uncanny-automator' ),
						'warningMessage'   => esc_attr__( 'Setting items to draft in a live recipe can lead to unexpected behaviors.', 'uncanny-automator' ),
					),
					'createItem'           => array(
						'addingTrigger' => esc_html__( 'Adding trigger', 'uncanny-automator' ),
						'triggerAdded'  => esc_html__( 'Trigger added successfully', 'uncanny-automator' ),
						'addingAction'  => esc_html__( 'Adding action', 'uncanny-automator' ),
						'actionAdded'   => esc_html__( 'Action added successfully', 'uncanny-automator' ),
					),
					'duplicateAction'      => array(
						'duplicatingAction' => esc_html__( 'Duplicating item', 'uncanny-automator' ),
						'actionDuplicated'  => esc_html__( 'Item duplicated successfully', 'uncanny-automator' ),
					),
					'setActionSchedule'    => array(
						'delayLoading'    => esc_html__( 'Saving delay', 'uncanny-automator' ),
						'delayUpdated'    => esc_html__( 'Delay saved successfully', 'uncanny-automator' ),
						'scheduleLoading' => esc_html__( 'Saving schedule date', 'uncanny-automator' ),
						'scheduleUpdated' => esc_html__( 'Schedule date saved successfully', 'uncanny-automator' ),
					),
					'removeActionSchedule' => array(
						'delayLoading'    => esc_html__( 'Removing delay', 'uncanny-automator' ),
						'delayUpdated'    => esc_html__( 'Delay removed successfully', 'uncanny-automator' ),
						'scheduleLoading' => esc_html__( 'Removing schedule date', 'uncanny-automator' ),
						'scheduleUpdated' => esc_html__( 'Schedule date removed successfully', 'uncanny-automator' ),
					),
					'triggerLogic'         => array(
						'updatingTriggerLogic' => esc_html__( 'Updating trigger logic', 'uncanny-automator' ),
						'triggerLogicUpdated'  => esc_html__( 'Trigger logic updated successfully', 'uncanny-automator' ),
					),
				),
				'itemLoop'      => array(
					'createNew'           => array(
						'creatingLoop' => esc_html__( 'Adding new loop', 'uncanny-automator' ),
						'loopCreated'  => esc_html__( 'Loop added successfully', 'uncanny-automator' ),
					),
					'updateLoop'          => array(
						'updatingLoop' => esc_html__( 'Updating loop', 'uncanny-automator' ),
						'loopUpdated'  => esc_html__( 'Loop updated successfully', 'uncanny-automator' ),
					),
					'deleteLoop'          => array(
						'deleteLoop'         => esc_html__( 'Delete loop', 'uncanny-automator' ),
						'irreversibleAction' => esc_html__( 'This action cannot be undone. Actions within the loop will be moved outside of the loop.', 'uncanny-automator' ),
						'deletingLoop'       => esc_html__( 'Deleting loop', 'uncanny-automator' ),
						'loopDeleted'        => esc_html__( 'Loop deleted successfully', 'uncanny-automator' ),
						'warningMessage'     => esc_attr__( 'Deleting a loop from an active recipe might lead to unexpected behaviors, so all actions inside will be switched to draft upon deletion.', 'uncanny-automator' ),
					),
					'createNewLoopFilter' => array(
						'creatingLoopFilter' => esc_html__( 'Adding new filter loop', 'uncanny-automator' ),
						'loopFilterCreated'  => esc_html__( 'Filter loop added successfully', 'uncanny-automator' ),
					),
					'deleteLoopFilter'    => array(
						'deleteLoopFilter'   => esc_html__( 'Delete loop filter', 'uncanny-automator' ),
						'irreversibleAction' => esc_html__( 'This action cannot be undone.', 'uncanny-automator' ),
						'deletingLoopFilter' => esc_html__( 'Deleting loop filter', 'uncanny-automator' ),
						'loopFilterDeleted'  => esc_html__( 'Loop filter deleted successfully', 'uncanny-automator' ),
					),
					'updateLoopFilter'    => array(
						'updatingLoopFilter' => esc_html__( 'Updating loop filter', 'uncanny-automator' ),
						'loopFilterUpdated'  => esc_html__( 'Loop filter updated successfully', 'uncanny-automator' ),
					),
				),
				'itemFilter'    => array(
					'updateFilters' => array(
						'updatingFilters' => esc_html__( 'Updating filters', 'uncanny-automator' ),
						'filtersUpdated'  => esc_html__( 'Filters updated successfully', 'uncanny-automator' ),
					),
				),
				// UncannyAutomator._core.i18n.core.userSelector
				'userSelector'  => array(
					'setRequiresUserSelector' => array(
						'updatingFlag'   => esc_html__( 'Removing user selector', 'uncanny-automator' ),
						'flagUpdated'    => esc_html__( 'User selector removed successfully', 'uncanny-automator' ),
						'warningMessage' => esc_html__( 'Removing the user selector may result in unexpected behavior if any actions or tokens require user data.', 'uncanny-automator' ),
					),
				),
				'directoryLoop' => array(
					'token' => array(
						'name'         => esc_html__( 'Token loop', 'uncanny-automator' ),
						'fields'       => array(
							'token' => array(
								'label' => esc_html__( 'Token', 'uncanny-automator' ),
							),
						),
						/* translators: 1. Token name */
						'sentence'     => sprintf( esc_html__( '{{Token:%1$s}}', 'uncanny-automator' ), 'TOKEN' ),
						'dialogHelper' => array(
							'loopTokens'          => esc_html__( 'Loop through tokens that contain multiple items, such as Woo products, LearnDash courses, BuddyBoss memberships, and more.', 'uncanny-automator' ),
							'loopTokensLearnMore' => esc_html__( 'Learn more', 'uncanny-automator' ),
						),
					),
				),
			),
			'runNow'                 => array(
				'runNowButtonLabel' => esc_html__( 'Run now', 'uncanny-automator' ),
				// UncannyAutomator._core.i18n.runNow.runNowButtonLabel
				'dialog'            => array(
					'heading'            => esc_html__( 'The recipe run has been initiated', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.runNow.dialog.heading
					'contentLine1'       => esc_html__( 'Actions are now running in the background and results will be available in the logs.', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.runNow.dialog.contentLine1
					'contentLine2'       => esc_html__( "Changes cannot be made to the recipe while it's running. Progress can be monitored from the logs and actions that haven't run yet can be cancelled.", 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.runNow.dialog.contentLine2
					'contentLine3'       => esc_html__( 'You can also return to the list of recipes or create a copy of this recipe.', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.runNow.dialog.contentLine3

					'duplicateRecipe'    => esc_html__( 'Duplicate this recipe', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.runNow.dialog.duplicateRecipe

					'viewItsLogs'        => esc_html__( 'View log entry', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.runNow.dialog.viewItsLogs

					'returnToRecipeList' => esc_html__( 'Return to recipe list', 'uncanny-automator' ),
					// UncannyAutomator._core.i18n.runNow.dialog.returnToRecipeList
				),
			),
			'viewLogs'               => esc_html__( 'View logs', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.viewLogs
			'refresh'                => esc_attr__( 'Refresh', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.refresh
			'refreshOptions'         => esc_attr__( 'Refresh options', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.refreshOptions
			'noResults'              => esc_attr__( 'No results found', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.noResults
			/* translators: Character to separate items */
			'itemSeparator'          => esc_html__( ',', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.itemSeparator
			/* translators: Non-personal infinitive verb */
			'save'                   => esc_attr__( 'Save', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.save
			/* translators: Non-personal infinitive verb */
			'search'                 => esc_attr__( 'Search', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.search
			'searchEllipsis'         => esc_attr__( 'Search...', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.searchEllipsis
			'searching'              => esc_attr__( 'Searching', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.searching
			/* translators: Non-personal infinitive verb */
			'confirm'                => esc_attr__( 'Confirm', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.confirm
			'somethingWentWrong'     => esc_attr__( 'Something went wrong', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.somethingWentWrong
			/* translators: Non-personal infinitive verb */
			'cancel'                 => esc_attr__( 'Cancel', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.cancel
			/* translators: Non-personal infinitive verb */
			'duplicate'              => esc_attr__( 'Duplicate', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.duplicate
			/* translators: Non-personal infinitive verb */
			'delete'                 => esc_attr__( 'Delete', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.delete
			/* translators: Non-personal infinitive verb */
			'clear'                  => esc_attr__( 'Clear', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.clear
			/* translators: Non-personal infinitive verb */
			'edit'                   => esc_attr__( 'Edit', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.edit
			/* translators: Noun */
			'support'                => esc_attr_x( 'Support', 'Item options', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.support
			/* translators: Non-personal infinitive verb */
			'learnMore'              => esc_attr__( 'Learn more', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.learnMore
			'trueLabel'              => esc_attr__( 'True', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.trueLabel
			'falseLabel'             => esc_attr__( 'False', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.falseLabel
			'yes'                    => esc_attr__( 'Yes', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.yes
			'no'                     => esc_attr__( 'No', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.no
			'loadingMoreResults'     => esc_attr__( 'Loading more results...', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.loadingMoreResults
			/* translators: 1. Post ID */
			'postIDPlaceholder'      => esc_attr__( 'ID: %1$s', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.postIDPlaceholder
			'emptyValue'             => esc_html__( '(empty)', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.emptyValue
			'debuggingTools'         => esc_attr__( 'Debugging tools', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.debuggingTools
			'itemMissing'            => esc_attr__( 'This item was disabled because it could not be found on the system. To re-enable, ensure the associated plugin is installed and activated.', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.itemMissing
			'itemPermalink'          => esc_attr__( 'This item was disabled because your Permalinks are not set correctly.', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.itemPermalink
			'unsupportedUnavailable' => array(
				'freeConnection' => array(
					// translators: Item type (action, trigger)
					'message' => esc_attr__( 'Please connect your account to use this %s.', 'uncanny-automator' ),
					'button'  => esc_attr__( 'Connect account', 'uncanny-automator' ),
					'url'     => AUTOMATOR_STORE_URL . AUTOMATOR_FREE_STORE_CONNECT_URL . '?redirect_url=' . rawurlencode( admin_url( 'admin.php?page=uncanny-automator-dashboard' ) ),
				),
				'proLicense'     => array(
					// translators: Item type (action, trigger)
					'message' => esc_attr__( 'Please enter your Automator Pro license key to use this %s.', 'uncanny-automator' ),
					'button'  => esc_attr__( 'Enter license key', 'uncanny-automator' ),
					'url'     => admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-config&tab=general&general=license' ),
				),
				'proRequired'    => array(
					// translators: Item type (action, trigger)
					'message' => esc_attr__( 'Automator Pro is required to use this %s.', 'uncanny-automator' ),
					'button'  => esc_attr__( 'Upgrade now', 'uncanny-automator' ),
					'url'     => automator_utm_parameters( 'https://automatorplugin.com/pricing/', 'recipe_ui', 'update_to_pro' ),
				),
				'appConnection'  => array(
					// translators: Item type (action, trigger)
					'message' => esc_attr__( 'App integration not connected. Please connect to use this %s.', 'uncanny-automator' ),
					'button'  => esc_attr__( 'Connect app', 'uncanny-automator' ),
					//'url' => Uses the App settings URL
				),
				'pluginRequired' => array(
					// translators: 1. Item type (action, trigger), 2. Plugin name
					'message' => esc_attr__( 'This %1$s requires the %2$s plugin which is not currently activated.', 'uncanny-automator' ),
					'button'  => esc_attr__( 'Learn more', 'uncanny-automator' ),
					// translators: 1. Plugin integration code
					'url'     => automator_utm_parameters( 'https://automatorplugin.com/knowledge-base/the-action-trigger-requires-a-plugin-that-is-not-installed-or-active-message/', 'recipe_ui', 'plugin_required' ),
				),
			),
			'noLabel'                => esc_attr__( '(no label)', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.noLabel
			/* translators: Non-personal infinitive verb */
			'upgradeNow'             => esc_attr__( 'Upgrade now', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.upgradeNow
			/* translators: Non-personal infinitive verb */
			'add'                    => esc_attr__( 'Add', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.add
			/* translators: Non-personal infinitive verb */
			'addRow'                 => esc_attr__( 'Add row', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.addRow
			/* translators: Non-personal infinitive verb */
			'removeRow'              => esc_attr__( 'Remove row', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.removeRow
			/* translators: 1. Row number */
			'rowNumber'              => esc_attr__( 'Row %1$s', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.no
			'close'                  => esc_attr_x( 'Close', 'Verb', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.close
			'areYouSure'             => esc_attr__( 'Are you sure?', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.areYouSure
			'needsUserData'          => esc_attr__( 'Needs user data', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.needsUserData
			'scrollToIncrement'      => esc_attr__( 'Scroll to increment', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.scrollToIncrement
			'clickToToggle'          => esc_attr__( 'Click to toggle', 'uncanny-automator' ),
			// UncannyAutomator._core.i18n.clickToToggle
			'uncannyAutomator'       => 'Uncanny Automator',
			// Don't translate this string
			// UncannyAutomator._core.i18n.uncannyAutomator
			'uncannyAutomatorPro'    => 'Uncanny Automator Pro',
			// Don't translate this string
			// UncannyAutomator._core.i18n.uncannyAutomatorPro
		);

		do_action_deprecated( 'uap_localized_string_after', array(), '3.0', 'automator_localized_string_after' );
		do_action( 'automator_localized_string_after' );
	}

	/**
	 * @return Automator_Translations
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
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
		$localized_string = apply_filters_deprecated(
			'uap_localized_string',
			array(
				$localized_string,
				$string_key,
			),
			'3.0',
			'automator_localized_string'
		);

		return apply_filters( 'automator_localized_string', $localized_string, $string_key );
	}

	/**
	 * Get get all translated strings
	 *
	 * @return array
	 */
	public function get_all() {
		$this->set_strings();
		$this->ls          = apply_filters_deprecated( 'uap_localized_strings', array( $this->ls ), '3.0', 'automator_localized_strings' );
		$localized_strings = apply_filters( 'automator_localized_strings', $this->ls );

		return $localized_strings;
	}

}
