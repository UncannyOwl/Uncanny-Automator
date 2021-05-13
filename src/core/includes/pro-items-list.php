<?php
/**
 * @return array
 */
function automator_pro_items_list() {
	return array(
		'UOA'            => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Automator Core */
					'name' => __( 'A user clicks {{a magic button}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Automator Core */
					'name' => __( 'A user clicks {{a magic link}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - Automator Core */
					'name' => __( 'Receive data from a webhook', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(),
		),
		'BB'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - bbPress */
					'name' => __( 'A user replies to {{a topic}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(),
		),
		'BDB'            => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					'name' => __( "A user's connection request is accepted", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					'name' => __( 'A user registers with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					'name' => __( 'A user registers a new account via an email invitation', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					'name' => __( 'A user activates a new account via an email invitation', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					'name' => __( "A user's email invitation results in a new member activation", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					'name' => __( "A user's email invitation results in a new member registration", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					'name' => __( "A user's member type is set to {{a specific type}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					'name' => __( 'A user joins {{a public group}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					'name' => __( 'A user leaves {{a group}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					'name' => __( 'A user joins {{a private group}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					'name' => __( 'A user makes a post to the activity stream of {{a group}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					'name' => __( 'A user updates their profile with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - BuddyBoss */
					'name' => __( "Set the user's member type to {{a specific type}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyBoss */
					'name' => __( "Add a post to the user's activity stream", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyBoss */
					'name' => __( 'Add a post to the activity stream of {{a group}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyBoss */
					'name' => __( 'Add a post to the sitewide activity stream', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyBoss */
					'name' => __( 'Send {{a private message}} to the user', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyBoss */
					'name' => __( 'Create {{a group}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyBoss */
					'name' => __( 'Set {{Xprofile data}}', 'uncanny-automator' ),
				),
			),
		),
		'BP'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - BuddyPress */
					'name' => __( 'A user joins {{a public group}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyPress */
					'name' => __( 'A user leaves {{a group}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyPress */
					'name' => __( "A user's connection request is accepted", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyPress */
					'name' => __( ' A user registers with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyPress */
					'name' => __( "A user's member type is set to {{a specific type}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyPress */
					'name' => __( 'A user joins {{a private group}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyPress */
					'name' => __( 'A user makes a post to the activity stream of {{a group}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyPress */
					'name' => __( 'A user updates their profile with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - BuddyPress */
					'name' => __( 'Remove the user from {{a group}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyPress */
					'name' => __( "Set the user's member type to {{a specific type}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyPress */
					'name' => __( "Add a post to the user's {{activity}} stream", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyPress */
					'name' => __( 'Add a post to the activity stream of {{a group}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyPress */
					'name' => __( 'Add a post to the sitewide {{activity}} stream', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyPress */
					'name' => __( 'Send {{a private message}} to the user', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyPress */
					'name' => __( 'Create {{a group}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyPress */
					'name' => __( 'Set {{Xprofile data}}', 'uncanny-automator' ),
				),
			),
		),
		'CF'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Caldera Forms */
					'name' => __( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Caldera Forms */
					'name' => __( 'Register a new user', 'uncanny-automator' ),
				),
			),
		),
		'CF7'            => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Contact Form 7 */
					'name' => __( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Contact Form 7 */
					'name' => __( 'Register a new user', 'uncanny-automator' ),
				),
			),
		),
		'ELEM'           => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Elementor */
					'name' => __( ' A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - Elementor */
					'name' => __( '{{A form}} is submitted with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - Elementor */
					'name' => __( 'A user submits {{a form}}', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Elementor */
					'name' => __( 'Show {{a popup}} to the user', 'uncanny-automator' ),
				),
			),
		),
		'EVENTSMANAGER'  => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Events Manager */
					'name' => __( 'A user unregisters from {{an event}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Events Manager */
					'name' => __( 'A user registers for {{an event}} with {{a specific ticket}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Events Manager */
					'name' => __( 'Unregister the user from {{an event}}', 'uncanny-automator' ),
				),
			),
		),
		'WPFF'           => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Fluent Forms */
					'name' => __( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Fluent Forms */
					'name' => __( '{{A form}} is submitted', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Fluent Forms */
					'name' => __( '{{A form}} is submitted with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Fluent Forms */
					'name' => __( 'Register a new user', 'uncanny-automator' ),
				),
			),
		),
		'FCRM'           => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - FluentCRM */
					'name' => __( 'A user is removed from {{a list}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - FluentCRM */
					'name' => __( '{{A tag}} is removed from a user', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - FluentCRM */
					'name' => __( 'Remove the user from {{a list}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - FluentCRM */
					'name' => __( 'Remove {{a tag}} from the user', 'uncanny-automator' ),
				),
			),
		),
		'FI'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Formidable Forms */
					'name' => __( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Formidable Forms */
					'name' => __( 'A user submits {{a form}} with payment', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Formidable Forms */
					'name' => __( 'A user updates an entry in {{a form}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Formidable Forms */
					'name' => __( 'Register a new user', 'uncanny-automator' ),
				),
			),
		),
		'FR'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Forminator */
					'name' => __( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Forminator */
					'name' => __( 'Register a new user', 'uncanny-automator' ),
				),
			),
		),
		'GP'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - GamiPress */
					'name' => __( 'A user earns {{an achievement}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - GamiPress */
					'name' => __( 'A user earns {{a number}} {{of a specfic type of}} points', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - GamiPress */
					'name' => __( 'A user attains {{a rank}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - GamiPress */
					'name' => __( 'Revoke {{an achievement}} from the user', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - GamiPress */
					'name' => __( 'Revoke {{a rank}} from the user', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - GamiPress */
					'name' => __( 'Revoke {{a number}} {{of a certain type of}} points from the user', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - GamiPress */
					'name' => __( 'Revoke all {{of a certain type of}} points from the user', 'uncanny-automator' ),
				),
			),
		),
		'GIVEWP'         => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - GiveWP */
					'name' => __( 'A user cancels {{a recurring donation}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - GiveWP */
					'name' => __( '{{A donation form}} is submitted', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Logged-in trigger - GiveWP */
					'name' => __( 'A user makes a donation via {{a form}} with {{a specific value}} in {{a specifc field}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - GiveWP */
					'name' => __( 'A user makes a donation via {{a form}} for an amount {{great than, less than, or equal to}} {{an amount}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - GiveWP */
					'name' => __( 'A user continues {{a recurring donation}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - GiveWP */
					'name' => __( 'Add a note to {{a donor}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - GiveWP */
					'name' => __( 'Create a donor', 'uncanny-automator' ),
				),
			),
		),
		'GOOGLESHEET'    => array(
			'triggers' => array(),
			'actions'  => array(
				array(
					/* translators: Action - Google Sheets */
					'name' => __( 'Create a row in Google Sheets', 'uncanny-automator' ),
				),
			),
		),
		'GTT'            => array(
			'triggers' => array(),
			'actions'  => array(
				array(
					/* translators: Action - GoToTraining */
					'name' => __( 'Add the user to a {{training session}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - GoToTraining */
					'name' => __( 'Remove the user from a {{training session}}', 'uncanny-automator' ),
				),
			),
		),
		'GTM'            => array(
			'triggers' => array(),
			'actions'  => array(
				array(
					/* translators: Action - GoToWebinar */
					'name' => __( 'Add the user to {{a webinar}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - GoToWebinar */
					'name' => __( 'Remove the user from {{a webinar}}', 'uncanny-automator' ),
				),
			),
		),
		'GF'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Gravity Forms */
					'name' => __( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Gravity Forms */
					'name' => __( 'A user submits {{a form}} with payment', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Gravity Forms */
					'name' => __( 'Register a new user', 'uncanny-automator' ),
				),
			),
		),
		'GH'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Groundhogg */
					'name' => __( '{{A tag}} is added to a user', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Groundhogg */
					'name' => __( '{{A tag}} is removed from a user', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(),
		),
		'H5P'            => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - H5P */
					'name' => __( 'A user completes {{H5P content}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - H5P */
					'name' => __( 'A user completes any {{of a specific type of}} H5P content', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - H5P */
					'name' => __( 'A user achieves a score {{greater than, less than or equal to}} {{a value}} on {{H5P content}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(),
		),
		'HF'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - HappyForms */
					'name' => __( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - HappyForms */
					'name' => __( '{{A form}} is submitted', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - HappyForms */
					'name' => __( '{{A form}} is submitted with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(),
		),
		'INTEGROMAT'     => array(
			'triggers' => array(
				array(
					/* translators: Anonymous trigger - Integromat */
					'name' => __( 'Receive data from {{Integromat webhook}}', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(),
		),
		'LD'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - LearnDash */
					'name' => __( 'A user submits an assignment for {{a lesson or topic}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - LearnDash */
					'name' => __( 'A user is enrolled in {{a course}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - LearnDash */
					'name' => __( 'A user is added to {{a group}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - LearnDash */
					'name' => __( 'Unenroll the user from {{a course}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnDash */
					'name' => __( "Reset the user's progress in {{a course}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnDash */
					'name' => __( "Reset the user's attempts for {{a quiz}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnDash */
					'name' => __( 'Add the user to {{a group}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnDash */
					'name' => __( 'Remove the user from {{a group}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnDash */
					'name' => __( "Send an {{email}} to the user's group leader(s)", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnDash */
					'name' => __( 'Mark {{a lesson}} not complete for the user', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnDash */
					'name' => __( 'Mark {{a topic}} not complete for the user', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnDash */
					'name' => __( 'Generate and email a certificate', 'uncanny-automator' ),
				),
			),
		),
		'LP'             => array(
			'triggers' => array(),
			'actions'  => array(
				array(
					/* translators: Action - LearnPress */
					'name' => __( 'Enroll the user in {{a course}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnPress */
					'name' => __( 'Mark {{a course}} complete for the user', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnPress */
					'name' => __( 'Remove the user from {{a course}}', 'uncanny-automator' ),
				),
			),
		),
		'LF'             => array(
			'triggers' => array(),
			'actions'  => array(
				array(
					/* translators: Action - LifterLMS */
					'name' => __( 'Remove the user from {{a course}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LifterLMS */
					'name' => __( 'Enroll the user in {{a course}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LifterLMS */
					'name' => __( 'Mark {{a course}} complete for the user', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LifterLMS */
					'name' => __( 'Remove the user from {{a membership}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LifterLMS */
					'name' => __( 'Enroll the user in {{a membership}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LifterLMS */
					'name' => __( "Reset the user's attempts for {{a quiz}}", 'uncanny-automator' ),
				),
			),
		),
		'MAILCHIMP'      => array(
			'triggers' => array(),
			'actions'  => array(
				array(
					/* translators: Action - Mailchimp */
					'name' => __( 'Add the user to {{an audience}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Mailchimp */
					'name' => __( 'Add {{a tag}} to the user', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Mailchimp */
					'name' => __( 'Remove {{a tag}} from the user', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Mailchimp */
					'name' => __( 'Add {{a note}} to the user', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Mailchimp */
					'name' => __( 'Create and send {{a campaign}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Mailchimp */
					'name' => __( 'Unsubscribe the user from {{an audience}}', 'uncanny-automator' ),
				),
			),
		),
		'MAILPOET'       => array(
			'triggers' => array(),
			'actions'  => array(
				array(
					/* translators: Action - MailPoet */
					'name' => __( 'Remove {{a subscriber}} from {{a list}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - MailPoet */
					'name' => __( 'Remove the user from {{a list}}', 'uncanny-automator' ),
				),
			),
		),
		'MSLMS'          => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - MasterStudy LMS */
					'name' => __( 'A user achieves a percentage {{greater than, less than or equal to}} {{a value}} on {{a quiz}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - MasterStudy LMS */
					'name' => __( 'Mark {{a quiz}} complete for the user', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - MasterStudy LMS */
					'name' => __( 'Mark {{a lesson}} complete for the user', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - MasterStudy LMS */
					'name' => __( "Reset the user's progress in {{a course}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - MasterStudy LMS */
					'name' => __( 'Mark {{a lesson}} not complete for the user', 'uncanny-automator' ),
				),
			),
		),
		'MP'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - MemberPress */
					'name' => __( "A user's membership to {{a specific product}} is cancelled", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - MemberPress */
					'name' => __( 'Add the user to {{a membership}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - MemberPress */
					'name' => __( 'Remove the user from {{a membership}}', 'uncanny-automator' ),
				),
			),
		),
		'MYCRED'         => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - myCred */
					'name' => __( 'A user earns {{a rank}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - myCred */
					'name' => __( "A user's total balance reaches {{a number of}} {{a specific type of}} points", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - myCred */
					'name' => __( "A user's current balance reaches {{a number of}} {{a specific type of}} points", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - myCred */
					'name' => __( 'Revoke {{a number of}} {{a specific type of}} points from the user', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - myCred */
					'name' => __( 'Revoke all {{of a specific type of}} points from the user', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - myCred */
					'name' => __( 'Revoke {{a badge}} from the user', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - myCred */
					'name' => __( "Increase the user's rank for {{a specific type of}} points", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - myCred */
					'name' => __( "Decrease the user's rank for {{a specific type of}} points", 'uncanny-automator' ),
				),
			),
		),
		'NEWSLETTER'     => array(
			'triggers' => array(
				array(
					/* translators: Anonymous trigger - Newsletter */
					'name' => __( '{{A subscription form}} is submitted with {{a specific list}} selected', 'uncanny-automator' ),
					'type' => 'logged-in_and_anonymous',
				),
			),
			'actions'  => array(),
		),
		'NF'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Ninja Forms */
					'name' => __( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Ninja Forms */
					'name' => __( 'Register a new user', 'uncanny-automator' ),
				),
			),
		),
		'PMP'            => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Paid Memberships Pro */
					'name' => __( 'A user renews {{a membership}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Paid Memberships Pro */
					'name' => __( 'Add the user to {{a membership level}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Paid Memberships Pro */
					'name' => __( 'Remove the user from {{a membership level}}', 'uncanny-automator' ),
				),
			),
		),
		'RC'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Restrict Content */
					'name' => __( "A user's membership to {{a specific level}} is cancelled", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Restrict Content */
					'name' => __( "A user's membership to {{a specific level}} expires", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Restrict Content */
					'name' => __( 'Remove the user from {{a membership level}}', 'uncanny-automator' ),
				),
			),
		),
		'SLACK'          => array(
			'triggers' => array(),
			'actions'  => array(
				array(
					/* translators: Action - Slack */
					'name' => __( 'Send a message to {{a channel}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Slack */
					'name' => __( 'Send a private message to {{a Slack user}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Slack */
					'name' => __( 'Create {{a channel}}', 'uncanny-automator' ),
				),
			),
		),
		'EC'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - The Events Calendar */
					'name' => __( 'A user attends {{an event}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - The Events Calendar */
					'name' => __( 'RSVP for {{an event}}', 'uncanny-automator' ),
				),
			),
		),
		'TUTORLMS'       => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Tutor LMS */
					'name' => __( 'A user achieves a percentage {{greater than, less than or equal to}} {{a value}} on a quiz', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Tutor LMS */
					'name' => __( 'A user is enrolled in {{a course}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Tutor LMS */
					'name' => __( 'Mark {{a lesson}} complete for the user', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Tutor LMS */
					'name' => __( 'Mark {{a course}} complete for the user', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Tutor LMS */
					'name' => __( 'Enroll the user in {{a course}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Tutor LMS */
					'name' => __( "Reset the user's progress in {{a course}}", 'uncanny-automator' ),
				),
			),
		),
		'TWILIO'         => array(
			'triggers' => array(),
			'actions'  => array(
				array(
					/* translators: Action - Twilio */
					'name' => __( 'Send an SMS message to {{a number}}', 'uncanny-automator' ),
				),
			),
		),
		'UM'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Ultimate Member */
					'name' => __( 'A user registers with {{a form}} with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Ultimate Member */
					'name' => __( "Set the user's role to {{a specific role}}", 'uncanny-automator' ),
				),
			),
		),
		'UPSELL'         => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Upsell Plugin */
					'name' => __( 'A user subscribes to {{a product}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(),
		),
		'WISHLISTMEMBER' => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Wishlist Member */
					'name' => __( 'A user submits {{a registration form}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Wishlist Member */
					'name' => __( 'A user submits a registration form with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Wishlist Member */
					'name' => __( 'Remove the user from {{a membership level}}', 'uncanny-automator' ),
				),
			),
		),
		'WC'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - WooCommerce */
					'name' => __( 'A user completes {{an order}} with a value {{greater than, less than or equal to}} {{an amount}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce */
					'name' => __( "A user's order status changes to {{a specific status}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - WooCommerce */
					'name' => __( 'A guest completes an order with {{a product}}', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce */
					'name' => __( 'A user completes an order with a product with {{a tag}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce */
					'name' => __( 'A user completes an order with a product in {{a category}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce */
					'name' => __( 'A user purchases {{a variable product}} with {{a variation}} selected', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - WooCommerce */
					'name' => __( "A guest order's status is changed to {{a specific status}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce */
					'name' => __( 'A user reviews {{a product}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce */
					'name' => __( "A user's review of {{a product}} is approved", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce */
					'name' => __( 'A user completes an order with a specific quantity of {{a product}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce Memberships */
					'name' => __( "A user's access to {{a membership plan}} is cancelled", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce Memberships */
					'name' => __( "A user's access to {{a membership plan}} expires", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce ShipStation */
					'name' => __( 'An order with a total {{greater than, less than or equal to}} {{a specific amount}} is shipped', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce ShipStation */
					'name' => __( 'An order with {{a specific product}} is shipped', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce Subscriptions */
					'name' => __( "A user's subscription to {{a product}} expires", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce Subscriptions */
					'name' => __( 'A user cancels a subscription to {{a product}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce Subscriptions */
					'name' => __( 'A user renews a subscription to {{a product}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - WooCommerce */
					'name' => __( 'Generate and email {{a coupon code}} to the user', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WooCommerce Memberships */
					'name' => __( 'Remove the user from {{a membership plan}}', 'uncanny-automator' ),
				),
			),
		),
		'WP'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - WordPress Core */
					'name' => __( 'A user is created', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					'name' => __( '{{A post}} is updated', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					'name' => __( 'A user resets their password', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - WordPress Core */
					'name' => __( 'A guest comment is submitted on {{a post}}', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - WordPress Core */
					'name' => __( 'A guest comment on {{a post}} is approved', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					'name' => __( "A user's post is set to {{a specific status}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					'name' => __( 'A post in {{a specific taxonomy}} is updated', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					'name' => __( "A user's comment on {{a post}} is approved", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					'name' => __( '{{A post}} of {{a specific type}} is moved to the trash', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					'name' => __( "A user's {{profile field}} is updated", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					'name' => __( "A user's {{specific}} meta key is updated", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					'name' => __( "A user's role changes from {{a specific role}} to {{a specific role}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - WordPress Core */
					'name' => __( "Remove {{a role}} from the user's roles", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WordPress Core */
					'name' => __( 'Set {{post meta}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WordPress Core */
					'name' => __( 'Set {{user meta}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WordPress Core */
					'name' => __( 'Update {{the user}}', 'uncanny-automator' ),
				),
			),
		),
		'WPCW'           => array(
			'triggers' => array(),
			'actions'  => array(
				array(
					/* translators: Action - WP Courseware */
					'name' => __( 'Remove the user from {{a course}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WP Courseware */
					'name' => __( 'Enroll the user in {{a course}}', 'uncanny-automator' ),
				),
			),
		),
		'WF'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - WP Fusion */
					'name' => __( '{{A tag}} is added to a user', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WP Fusion */
					'name' => __( '{{A tag}} is removed from a user', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - WP Fusion */
					'name' => __( 'Remove {{a tag}} from the user', 'uncanny-automator' ),
				),
			),
		),
		'WPLMS'          => array(
			'triggers' => array(),
			'actions'  => array(
				array(
					/* translators: Action - WP LMS */
					'name' => __( 'Enroll the user in {{a course}}', 'uncanny-automator' ),
				),
			),
		),
		'WPUSERMANAGER'  => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - WP User Manager */
					'name' => __( 'A user registers using {{a form}} with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WP User Manager */
					'name' => __( 'A user is approved', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WP User Manager */
					'name' => __( 'A user is rejected', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WP User Manager */
					'name' => __( 'A user verifies their email address', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WP User Manager */
					'name' => __( 'A user updates their account information', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WP User Manager */
					'name' => __( 'A user joins {{a group}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WP User Manager */
					'name' => __( 'A user leaves {{a group}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WP User Manager */
					'name' => __( 'A user is approved to join {{a private group}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WP User Manager */
					'name' => __( 'A user is rejected from joining {{a private group}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(),
		),
		'WPPOLLS'        => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - WP-Polls */
					'name' => __( 'A user submits a poll with {{a specific choice}} selected', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(),
		),
		'WPF'            => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - WPForms */
					'name' => __( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - WPForms */
					'name' => __( 'Register a new user', 'uncanny-automator' ),
				),
			),
		),
		'WPFORO'         => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - wpForo */
					'name' => __( 'A user replies to {{a topic}} in {{a forum}}', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - wpForo */
					'name' => __( 'Remove the user from {{a group}}', 'uncanny-automator' ),
				),
			),
		),
		'ZAPIER'         => array(
			'triggers' => array(
				array(
					/* translators: Anonymous trigger - Zapier */
					'name' => __( 'Receive data from Zapier webhook', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(),
		),
		'ZOOM'           => array(
			'triggers' => array(),
			'actions'  => array(
				array(
					/* translators: Action - Zoom Meetings */
					'name' => __( 'Add the user to {{a meeting}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Zoom Meetings */
					'name' => __( 'Remove the user from {{a meeting}}', 'uncanny-automator' ),
				),
			),
		),
		'ZOOMWEBINAR'    => array(
			'triggers' => array(),
			'actions'  => array(
				array(
					/* translators: Action - Zoom Webinars */
					'name' => __( 'Add the user to {{a webinar}}', 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Zoom Webinars */
					'name' => __( 'Remove the user from {{a webinar}}', 'uncanny-automator' ),
				),
			),
		),
	);
}
