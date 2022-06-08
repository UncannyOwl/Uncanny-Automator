<?php
/**
 * @return array
 */
function automator_pro_items_list() {
	return array(
		'ACFWC'          => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Advanced Coupons */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's current store credit exceeds {{a specific amount}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Advanced Coupons */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's lifetime store credit exceeds {{a specific amount}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Advanced Coupons */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add {{a specific amount of}} store credit to the user's account", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Advanced Coupons */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove {{a specific amount of}} store credit from the user's account", 'uncanny-automator' ),
				),
			),
		),
		'ACF'            => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Advanced Custom Fields */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user updates {{a field}} on {{a post}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Advanced Custom Fields */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's {{field}} is updated", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - Advanced Custom Fields */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A field}} is updated on {{a post}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(),
		),
		'AFFWP'          => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - AffiliateWP */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "An affiliate refers a sale of {{a MemberPress product}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - AffiliateWP */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "An affiliate refers a sale of {{a WooCommerce product}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - AffiliateWP */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "An affiliate refers a sale of {{an Easy Digital Downloads product}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - AffiliateWP */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "An affiliate's referral of {{a specific type}} is paid", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - AffiliateWP */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "An affiliate's referral of {{a specific type}} is rejected", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - AffiliateWP */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Create a referral for a specific affiliate", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - AffiliateWP */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Create a referral for the user", 'uncanny-automator' ),
				),
			),
		),
		'AMELIABOOKING'  => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Amelia */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user books an appointment for {{a specific service}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - Amelia */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "An appointment is booked for {{a specific service}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(),
		),
		'UOA'            => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Automator Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user clicks {{a magic button}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Automator Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user clicks {{a magic link}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - Automator Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A magic button}} is clicked", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - Automator Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A magic link}} is clicked", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Automator Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Call {{a custom function/method}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Automator Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Run {{a WordPress hook}}", 'uncanny-automator' ),
				),
			),
		),
		'BO'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - BadgeOS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user earns {{a achievement}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - BadgeOS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Revoke {{a rank}} from the user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BadgeOS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Revoke all {{of a certain type of}} points from the user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BadgeOS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Revoke {{an achievement}} from the user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BadgeOS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Revoke {{a number}} {{of a certain type of}} points from the user", 'uncanny-automator' ),
				),
			),
		),
		'BB'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - bbPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user replies to {{a topic}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - bbPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Post a reply to {{a topic}} in {{a forum}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - bbPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Post a topic in {{a forum}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - bbPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Subscribe the user to {{a forum}}", 'uncanny-automator' ),
				),
			),
		),
		'BDB'            => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user activates a new account via an email invitation", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user joins {{a private group}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user joins {{a public group}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user leaves {{a group}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user makes a post to the activity stream of {{a group}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user registers a new account via an email invitation", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user registers with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user requests access to {{a private group}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user updates their profile with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's profile type is set to {{a specific type}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's connection request is accepted", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's email invitation results in a new member activation", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's email invitation results in a new member registration", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add a post to the activity stream of {{a group}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add a post to the sitewide {{activity}} stream", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add a post to the user's {{activity}} stream", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Create {{a group}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "End friendship with {{a user}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Follow {{a user}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Post a reply to {{a topic}} in {{a forum}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Post a topic in {{a forum}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove user from {{a group}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Send a friendship request to {{a user}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Send a notification to all members of {{a group}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Send a private message to all members of {{a group}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Send {{a private message}} to the user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Send the user a {{notification}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Set the user's member type to {{a specific type}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Set the user's {{Xprofile data}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Stop following {{a user}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Subscribe the user to {{a forum}}", 'uncanny-automator' ),
				),
			),
		),
		'BP'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - BuddyPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user joins {{a private group}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user joins {{a public group}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user leaves {{a group}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user makes a post to the activity stream of {{a group}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( " A user registers with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user requests access to {{a private group}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user updates their profile with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's member type is set to {{a specific type}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's connection request is accepted", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "User's account is activated", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - BuddyPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add a post to the activity stream of {{a group}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add a post to the sitewide {{activity}} stream", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add a post to the user's {{activity}} stream", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Create {{a group}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "End friendship with {{a user}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove user from {{a group}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Send a friendship request to {{a user}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Send {{a private message}} to the user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Send the user a {{notification}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Set the user's member type to {{a specific type}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Set the user's {{Xprofile data}}", 'uncanny-automator' ),
				),
			),
		),
		'CF'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Caldera Forms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user submits {{a form}} with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - Caldera Forms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A form}} is submitted with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Caldera Forms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Register a new user", 'uncanny-automator' ),
				),
			),
		),
		'CF7'            => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Contact Form 7 */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user submits {{a form}} with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - Contact Form 7 */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A form}} is submitted with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Contact Form 7 */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Register a new user", 'uncanny-automator' ),
				),
			),
		),
		'DIVI'           => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Divi */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user submits {{a form}} with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - Divi */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A form}} is submitted with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(),
		),
		'EDD'            => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Easy Digital Downloads */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A product}} is purchased with {{a discount code}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(),
		),
		'ELEM'           => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Elementor Pro */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( " A user submits {{a form}} with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - Elementor Pro */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A form}} is submitted with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Elementor Pro */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Show {{a popup}} to the user", 'uncanny-automator' ),
				),
			),
		),
		'EVENTSMANAGER'  => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Events Manager */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user registers for {{an event}} with a {{specific}} ticket", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Events Manager */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user unregisters from {{an event}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - Events Manager */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A booking for {{an event}} is approved", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Events Manager */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Unregister the user from {{an event}}", 'uncanny-automator' ),
				),
			),
		),
		'WPFF'           => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Fluent Forms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user submits {{a form}} with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - Fluent Forms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A form}} is submitted with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Fluent Forms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Register a new user", 'uncanny-automator' ),
				),
			),
		),
		'FCRM'           => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - FluentCRM */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A tag}} is removed from a user", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - FluentCRM */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user is removed from {{a list}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - FluentCRM */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A contact is removed from {{a list}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - FluentCRM */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A tag}} is removed from a contact", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - FluentCRM */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove {{tags}} from the user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - FluentCRM */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove the user from {{lists}}", 'uncanny-automator' ),
				),
			),
		),
		'FI'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Formidable Forms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user submits {{a form}} with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Formidable Forms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user updates an entry in {{a form}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - Formidable Forms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A form}} is submitted with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Formidable Forms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Register a new user", 'uncanny-automator' ),
				),
			),
		),
		'FR'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Forminator */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user submits {{a form}} with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Forminator */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Register a new user", 'uncanny-automator' ),
				),
			),
		),
		'GP'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - GamiPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user attains {{a rank}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - GamiPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user earns {{a number of}} {{a specific type of}} points", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - GamiPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user earns {{an achievement}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - GamiPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's total points reaches {{a specific threshold}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - GamiPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{An achievement}} is revoked from the user", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - GamiPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Revoke {{a rank}} from the user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - GamiPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Revoke all {{of a certain type of}} points from the user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - GamiPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Revoke {{an achievement}} from the user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - GamiPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Revoke {{points}} from the user", 'uncanny-automator' ),
				),
			),
		),
		'GIVEWP'         => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - GiveWP */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user cancels {{a recurring donation}} from {{a specific form}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - GiveWP */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user continues {{a recurring donation}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - GiveWP */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user makes a donation via {{a form}} for an amount {{great than, less than, or equal to}} {{an amount}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - GiveWP */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user makes a donation via {{a form}} with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - GiveWP */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A donation form}} is submitted", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - GiveWP */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A donation is made via {{a form}} for an amount {{greater than, less than, or equal to}} {{an amount}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - GiveWP */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add a note to {{a donor}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - GiveWP */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Create {{a donor}}", 'uncanny-automator' ),
				),
			),
		),
		'GF'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Gravity Forms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user is registered", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Gravity Forms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user submits {{a form}} with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Gravity Forms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user submits {{a form}} with payment", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - Gravity Forms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A form}} is submitted with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - Gravity Forms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A form}} is submitted with payment", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Gravity Forms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Create an entry for {{a form}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Gravity Forms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Register a new user", 'uncanny-automator' ),
				),
			),
		),
		'GH'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Groundhogg */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A tag}} is added to a user", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Groundhogg */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A tag}} is removed from a user", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - Groundhogg */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A tag}} is added to a contact", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - Groundhogg */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A tag}} is removed from a contact", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(),
		),
		'H5P'            => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - H5P */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user achieves a score {{greater than, less than or equal to}} {{a value}} on {{H5P content}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - H5P */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user completes any {{of a specific type of}} H5P content", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - H5P */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user completes {{H5P content}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(),
		),
		'HF'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - HappyForms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user submits {{a form}} with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - HappyForms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A form}} is submitted with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - HappyForms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Register a new user", 'uncanny-automator' ),
				),
			),
		),
		'IFTTT'          => array(
			'triggers' => array(
				array(
					/* translators: Anonymous trigger - IFTTT */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Receive data from IFTTT {{webhook}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - IFTTT */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Register a new user", 'uncanny-automator' ),
				),
			),
		),
		'INTEGRATELY'    => array(
			'triggers' => array(
				array(
					/* translators: Anonymous trigger - Integrately */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Receive data from Integrately {{webhook}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Integrately */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Register a new user", 'uncanny-automator' ),
				),
			),
		),
		'INTEGROMAT'     => array(
			'triggers' => array(
				array(
					/* translators: Anonymous trigger - Integromat */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Receive data from Integromat {{webhook}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(),
		),
		'LD'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user completes {{a group's}} courses", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user is added to {{a group}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user is enrolled in {{a course}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user is removed from {{a group}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user is unenrolled from {{a course}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user submits an assignment for {{a lesson or topic}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's access to {{a course}} expires", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add the user to {{a group}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Mark {{a lesson}} not complete for the user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Mark {{a topic}} not complete for the user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove the Group Leader from {{a group}} and all its children", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove the user from {{a group}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove the user from {{a group}} and all its children", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Reset the user's attempts for {{a quiz}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Reset the user's progress in {{a course}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Send a {{certificate}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Send an {{email}} to the user's group leader(s)", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Unenroll the user from {{a course}}", 'uncanny-automator' ),
				),
			),
		),
		'LP'             => array(
			'triggers' => array(),
			'actions'  => array(
				array(
					/* translators: Action - LearnPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Enroll the user in {{a course}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Mark {{a course}} complete for the user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove the user from {{a course}}", 'uncanny-automator' ),
				),
			),
		),
		'LF'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - LifterLMS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user cancels {{a membership}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - LifterLMS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user is unenrolled from {{a course}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - LifterLMS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user joins {{a group}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - LifterLMS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user leaves {{a group}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - LifterLMS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Enroll the user in {{a course}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LifterLMS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Enroll the user in {{a membership}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LifterLMS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Mark {{a course}} complete for the user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LifterLMS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove the user from {{a course}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LifterLMS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove the user from {{a membership}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LifterLMS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Reset the user's attempts for {{a quiz}}", 'uncanny-automator' ),
				),
			),
		),
		'MAILPOET'       => array(
			'triggers' => array(),
			'actions'  => array(
				array(
					/* translators: Action - MailPoet */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove {{a subscriber}} from {{a list}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - MailPoet */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove the user from {{a list}}", 'uncanny-automator' ),
				),
			),
		),
		'MSLMS'          => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - MasterStudy LMS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user achieves a percentage {{greater than, less than or equal to}} {{a value}} on {{a quiz}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - MasterStudy LMS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Mark {{a lesson}} complete for the user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - MasterStudy LMS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Mark {{a lesson}} not complete for the user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - MasterStudy LMS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Mark {{a quiz}} complete for the user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - MasterStudy LMS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Reset the user's progress in {{a course}}", 'uncanny-automator' ),
				),
			),
		),
		'MP'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - MemberPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's membership to {{a specific product}} expires", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - MemberPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's membership to {{a specific product}} is cancelled", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - MemberPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's membership to {{a specific product}} is paused", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - MemberPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A sub account is added to {{a parent account}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - MemberPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's membership to {{a specific product}} expires", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - MemberPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's membership to {{a specific product}} is cancelled", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - MemberPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's membership to {{a specific product}} is paused", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - MemberPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add the user to {{a membership}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - MemberPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove the user from {{a membership}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - MemberPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add the user to {{a membership}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - MemberPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove the user from {{a membership}}", 'uncanny-automator' ),
				),
			),
		),
		'MPC'            => array(
			'triggers' => array(),
			'actions'  => array(
				array(
					/* translators: Action - MemberPress Courses */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Reset the user's progress in {{a course}}", 'uncanny-automator' ),
				),
			),
		),
		'MEC'            => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Modern Events Calendar */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's booking of {{an event}} is cancelled", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Modern Events Calendar */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's booking of {{an event}} is confirmed", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Modern Events Calendar */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's booking of {{an event}} is pending", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Modern Events Calendar */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Register a user for {{an event}}", 'uncanny-automator' ),
				),
			),
		),
		'MYCRED'         => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - myCred */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user earns {{a rank}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - myCred */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's current balance reaches {{a number of}} {{a specific type of}} points", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - myCred */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's total balance reaches {{a number of}} {{a specific type of}} points", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - myCred */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Increase the user's rank for {{a specific type of}} points", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - myCred */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Reduce the user's rank for {{a specific type of}} points", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - myCred */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Revoke {{a badge}} from the user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - myCred */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Revoke all {{of a specific type of}} points from the user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - myCred */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Revoke {{points}} from the user", 'uncanny-automator' ),
				),
			),
		),
		'NEWSLETTER'     => array(
			'triggers' => array(
				array(
					/* translators: Anonymous trigger - Newsletter */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A subscription form is submitted with {{a specific list}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Newsletter */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add the user to {{a list}}", 'uncanny-automator' ),
				),
			),
		),
		'NF'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Ninja Forms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user submits {{a form}} with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - Ninja Forms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A form}} is submitted with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Ninja Forms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Register a new user", 'uncanny-automator' ),
				),
			),
		),
		'OPTINMONSTER'   => array(
			'triggers' => array(
				array(
					/* translators: Anonymous trigger - OptinMonster */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Receive data from OptinMonster {{webhook}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - OptinMonster */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Register a new user", 'uncanny-automator' ),
				),
			),
		),
		'PMP'            => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Paid Memberships Pro */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user renews {{an expired membership}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Paid Memberships Pro */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add the user to {{a membership level}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Paid Memberships Pro */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove the user from {{a membership level}}", 'uncanny-automator' ),
				),
			),
		),
		'PP'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - PeepSo */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user gains a new follower", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - PeepSo */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user loses a follower", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - PeepSo */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user publishes an activity post", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - PeepSo */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user unfollows {{another PeepSo member}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - PeepSo */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user updates {{a specific field}} to {{a specific field value}} in their profile", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - PeepSo */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add {{a post}} to the site wide activity stream", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - PeepSo */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Change the user's PeepSo role to {{a new role}}", 'uncanny-automator' ),
				),
			),
		),
		'PRESTO'         => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Presto Player */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user watches at least {{a specific percentage}} of {{a video}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(),
		),
		'RC'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Restrict Content Pro */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's membership to {{a specific level}} expires", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Restrict Content Pro */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's membership to {{a specific level}} is cancelled", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Restrict Content Pro */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove the user from {{a membership level}}", 'uncanny-automator' ),
				),
			),
		),
		'EC'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - The Events Calendar */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user attends {{an event}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - The Events Calendar */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "An attendee is registered for {{an event}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - The Events Calendar */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "RSVP for {{an event}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - The Events Calendar */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "RSVP on behalf of the user for {{an event}}", 'uncanny-automator' ),
				),
			),
		),
		'TUTORLMS'       => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Tutor LMS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user achieves a percentage {{greater than, less than or equal to}} {{a value}} on a quiz", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Tutor LMS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user is enrolled in {{a course}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Tutor LMS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user posts a question in {{a course}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Tutor LMS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Enroll the user in {{a course}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Tutor LMS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Mark {{a course}} complete for the user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Tutor LMS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Mark {{a lesson}} complete for the user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Tutor LMS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Reset the user's progress in {{a course}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Tutor LMS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Unenroll a user from {{a course}}", 'uncanny-automator' ),
				),
			),
		),
		'UM'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Ultimate Member */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user registers with {{a form}} with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Ultimate Member */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add {{a role}} to the user's roles", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Ultimate Member */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Set the user's role to {{a specific role}}", 'uncanny-automator' ),
				),
			),
		),
		'UPSELL'         => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Upsell Plugin */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user subscribes to {{a product}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(),
		),
		'WEBHOOKS'       => array(
			'triggers' => array(
				array(
					/* translators: Anonymous trigger - Webhooks */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Receive data from a webhook", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(),
		),
		'WISHLISTMEMBER' => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Wishlist Member */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user submits {{a registration form}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Wishlist Member */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user submits {{a registration form}} with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Wishlist Member */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's {{specific}} membership level account is approved", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Wishlist Member */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove the user from {{a membership level}}", 'uncanny-automator' ),
				),
			),
		),
		'WC'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user adds {{a product}} to their cart", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user {{completes, pays for, lands on a thank you page for}} an order", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user {{completes, pays for, lands on a thank you page for}} an order paid for with {{a specific method}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user {{completes, pays for, lands on a thank you page for}} an order with a product in {{a category}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user {{completes, pays for, lands on a thank you page for}} an order with a product with {{a tag}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user {{completes, pays for, lands on a thank you page for}} an order with a quantity {{greater than, less than or equal to}} {{a quantity}} of {{a product}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user {{completes, pays for, lands on a thank you page for}} an order with {{a specific quantity}} of {{a product}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user purchases {{a variable product}} with {{a variation}} selected", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user reviews {{a product}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user reviews {{a product}} with a rating {{greater than, less than or equal to}} {{an amount}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's order status changes to {{a specific status}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's review on {{a product}} is approved", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A guest {{completes, pays for, lands on a thank you page for}} an order with {{a product}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A guest {{completes, pays for, lands on a thank you page for}} an order with a product in {{a category}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A guest order's status is changed to {{a specific status}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A product}} has its associated order {{completed, paid for, or a thank you page visited}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce Memberships */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's access to {{a membership plan}} is cancelled", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce Memberships */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's access to {{a membership plan}} expires", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce ShipStation */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "An order with {{a specific product}} is shipped", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce ShipStation */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "An order with a total {{greater than, less than or equal to}} {{a specific amount}} is shipped", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce Subscriptions */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user cancels a subscription to {{a product}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce Subscriptions */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user purchases {{a variable subscription}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce Subscriptions */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user purchases {{a variable subscription}} with {{a variation}} selected", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce Subscriptions */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user renews a subscription to {{a product}} ", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce Subscriptions */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user subscribes to {{a product}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce Subscriptions */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user renews a subscription to {{a specific}} variation of {{a variable subscription}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce Subscriptions */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's subscription to {{a product}} expires", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce Subscriptions */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's subscription to {{a product}} is set to {{a status}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce Subscriptions */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's subscription to {{a specific}} variation of {{a variable subscription}} expires", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce Subscriptions */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's subscription to {{a specific}} variation of {{a variable subscription}} is set to {{a status}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce Subscriptions */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's trial period to {{a subscription}} expires", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce Subscriptions */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's trial period to {{a specific}} variation of {{a variable subscription}} expires", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Cancel the user's subscription to {{a subscription product}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Create a subscription order with {{a product}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Create a subscription order with {{a product}} with a payment method", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Create an order with {{a product}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Create an order with {{a product}} with a payment gateway", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Generate and email a coupon {{code}} to the user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Set {{a specific order}} to {{a specific status}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WooCommerce Memberships */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove the user from {{a membership plan}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WooCommerce Subscriptions */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Cancel the user's subscription to {{a specific variation}} of {{a variable subscription variation}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WooCommerce Subscriptions */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Cancel the user's subscription to {{a variable subscription product}}", 'uncanny-automator' ),
				),
			),
		),
		'WP'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A post}} is moved to the trash", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A specific}} role is removed from the user", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user is created", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user is created with {{a specific role}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user resets their password", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user updates {{a post}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user updates a post in {{a specific}} status", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user updates a post in a specific {{taxonomy}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user updates a post not in {{a specific}} status", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user updates {{a specific meta key}} of a {{specific type of post}} to {{a specific value}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A user's post}} is set to {{a specific status}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's {{profile field}} is updated", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's role changed from {{a specific role}} to {{a specific role}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's {{specific}} meta key is updated", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's {{specific}} meta key is updated to {{a specific value}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's {{specific type of post}} is moved to the trash", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's {{specific type of post}} is set to {{a status}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's {{specific type of post}} is updated", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's comment on {{a post}} is approved", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's comment on {{a specific type of post}} receives a reply", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A guest comment is submitted on a user's {{post}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A guest comment on {{a user's post}} is approved", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add {{a taxonomy term}} to {{a post}} in {{a post type}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add {{an image}} to the media library", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Create a {{user}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Delete a user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Delete user meta", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Move {{a post}} to the trash", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove {{a role}} from the user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove {{a taxonomy term}} from {{a post}} in {{a post type}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Set {{a post}} to {{a status}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Set {{post meta}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Set the featured image of {{a post}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Set {{user meta}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Update the content of {{a post}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Update the slug of {{a post}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Update the title of {{a post}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Update the user's {{details}}", 'uncanny-automator' ),
				),
			),
		),
		'WPCW'           => array(
			'triggers' => array(),
			'actions'  => array(
				array(
					/* translators: Action - WP Courseware */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Enroll the user in {{a course}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WP Courseware */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove the user from {{a course}}", 'uncanny-automator' ),
				),
			),
		),
		'WF'             => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - WP Fusion */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A tag}} is added to a user", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WP Fusion */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A tag}} is removed from a user", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WP Fusion Lite */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A tag}} is added to a user", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WP Fusion Lite */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A tag}} is removed from a user", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - WP Fusion */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove {{a tag}} from the user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WP Fusion Lite */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove {{a tag}} from the user", 'uncanny-automator' ),
				),
			),
		),
		'WPJM'           => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - WP Job Manager */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A job}} is filled", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WP Job Manager */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user marks a {{specific type of}} job as filled", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WP Job Manager */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user marks a {{specific type of}} job as not filled", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WP Job Manager */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user receives an application to a {{specific type of}} job", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WP Job Manager */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user updates {{a job}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WP Job Manager */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's application is set to {{a specific status}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WP Job Manager */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's application to a {{specific type of}} job is set to {{a specific status}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WP Job Manager */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "An application is received for {{a job}} of {{a specific type}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(),
		),
		'WPLMS'          => array(
			'triggers' => array(),
			'actions'  => array(
				array(
					/* translators: Action - WP LMS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Enroll the user in {{a course}}", 'uncanny-automator' ),
				),
			),
		),
		'WPUSERMANAGER'  => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - WP User Manager */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user is approved", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WP User Manager */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user is approved to join {{a private group}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WP User Manager */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user is rejected", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WP User Manager */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user is rejected from joining {{a private group}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WP User Manager */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user joins {{a group}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WP User Manager */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user leaves {{a group}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WP User Manager */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user registers using {{a form}} with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WP User Manager */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user updates their account information", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WP User Manager */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user verifies their email address", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(),
		),
		'WPPOLLS'        => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - WP-Polls */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user submits {{a poll}} with {{a specific answer}} selected", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(),
		),
		'WPF'            => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - WPForms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user submits {{a form}} with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WPForms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user submits {{a form}} with PayPal payment", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - WPForms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A form}} is submitted with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - WPForms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A form}} is submitted with PayPal payment ", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - WPForms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Register a new user", 'uncanny-automator' ),
				),
			),
		),
		'WPFORO'         => array(
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - wpForo */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user replies to {{a topic}} in {{a forum}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - wpForo */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove the user from {{a group}}", 'uncanny-automator' ),
				),
			),
		),
		'ZAPIER'         => array(
			'triggers' => array(
				array(
					/* translators: Anonymous trigger - Zapier */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Receive data from Zapier webhook", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(),
		),
	);
}
