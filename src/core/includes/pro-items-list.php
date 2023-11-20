<?php
/**
 * @return array
 */
function automator_pro_items_list() {
	return array(
		'ADVADS'            => array(
			'name'     => 'Advanced Ads',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Advanced Ads */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{An ad's}} status changes from {{a specific status}} to {{a specific status}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(),
		),
		'ACFWC'             => array(
			'name'     => 'Advanced Coupons',
			'pro_only' => 'no',
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
		'ACF'               => array(
			'name'     => 'Advanced Custom Fields',
			'pro_only' => 'yes',
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
		'AFFWP'             => array(
			'name'     => 'AffiliateWP',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - AffiliateWP */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user purchases a WooCommerce product using an affiliate referral", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - AffiliateWP */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user purchases {{a WooCommerce product}} using an affiliate referral", 'uncanny-automator' ),
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
				array(
					/* translators: Anonymous trigger - AffiliateWP */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A WooCommerce product}} is purchased using an affiliate referral", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - AffiliateWP */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Create {{a referral}} for {{a specific affiliate ID}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - AffiliateWP */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Create {{a referral}} for the user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - AffiliateWP */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Create {{an affiliate}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - AffiliateWP */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Link {{a customer}} to {{an affiliate}} for lifetime commissions", 'uncanny-automator' ),
				),
			),
		),
		'AMELIABOOKING'     => array(
			'name'     => 'Amelia',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Amelia */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user books an appointment for {{a specific service}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Amelia */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's booking of an appointment for {{a service}} has been changed to {{a specific status}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Amelia */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's booking of an appointment for {{a specific service}} is canceled", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Amelia */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's booking of an appointment for {{a specific service}} is rescheduled", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - Amelia */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A booking of an appointment for {{a service}} has been changed to {{a specific status}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - Amelia */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A booking of an appointment for {{a specific service}} is canceled", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - Amelia */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A booking of an appointment for {{a specific service}} is rescheduled", 'uncanny-automator' ),
					'type' => 'anonymous',
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
		'ARMEMBER'          => array(
			'name'     => 'ARMember',
			'pro_only' => 'no',
			'triggers' => array(),
			'actions'  => array(
				array(
					/* translators: Action - ARMember */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add the user to {{a membership plan}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - ARMember */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove the user from {{a membership plan}}", 'uncanny-automator' ),
				),
			),
		),
		'UOA'               => array(
			'name'     => 'Automator Core',
			'pro_only' => 'no',
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
					'name' => __( "Generate a random string", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Automator Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Run {{a JavaScript code}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Automator Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Run {{a WordPress hook}}", 'uncanny-automator' ),
				),
			),
		),
		'BO'                => array(
			'name'     => 'BadgeOS',
			'pro_only' => 'no',
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
		'BB'                => array(
			'name'     => 'bbPress',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - bbPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user replies to {{a topic}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - bbPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A guest replies to {{a topic}}", 'uncanny-automator' ),
					'type' => 'anonymous',
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
		'WP_BITLY'          => array(
			'name'     => 'Bitly',
			'pro_only' => 'yes',
			'triggers' => array(),
			'actions'  => array(
				array(
					/* translators: Action - Bitly */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Shorten {{a URL}}", 'uncanny-automator' ),
				),
			),
		),
		'BDB'               => array(
			'name'     => 'BuddyBoss',
			'pro_only' => 'no',
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
					'name' => __( "A user creates a forum", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user creates a group", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user is banned from a {{specific type of}} group", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user is removed from {{a group}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user is removed from a {{specific type of}} group", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user is suspended", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user is unsuspended", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user joins {{a hidden group}}", 'uncanny-automator' ),
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
					'name' => __( "A user joins a {{specific type of}} group", 'uncanny-automator' ),
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
					'name' => __( "A user leaves a {{specific type of}} group", 'uncanny-automator' ),
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
					'name' => __( "A user receives a private message from {{a user}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user receives a {{type of}} on-screen notification", 'uncanny-automator' ),
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
					'name' => __( "A user rejects a friendship request", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A {{user}} replies to an activity stream message", 'uncanny-automator' ),
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
					'name' => __( "A user requests to join a {{specific type of}} private group", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user sends a private message to {{a user}}", 'uncanny-automator' ),
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
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's friendship request is accepted", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's topic in {{a forum}} receives a reply", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - BuddyBoss */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A guest replies to {{a topic}}", 'uncanny-automator' ),
					'type' => 'anonymous',
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
					'name' => __( "Create a friendship between {{a user}} and {{another user}}", 'uncanny-automator' ),
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
					'name' => __( "Send {{a private message}} to {{a specific user}}", 'uncanny-automator' ),
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
					'name' => __( "Send an email to all members of {{a group}}", 'uncanny-automator' ),
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
					'name' => __( "Show {{an on-screen notification}} to the user", 'uncanny-automator' ),
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
		'BP'                => array(
			'name'     => 'BuddyPress',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - BuddyPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user creates a group", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user is removed from {{a group}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user is removed from {{a specific type of}} group", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
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
					'name' => __( "A user leaves {{a specific type of}} group", 'uncanny-automator' ),
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
					'name' => __( "A user rejects a friendship request", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - BuddyPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A user}} replies to an activity stream message", 'uncanny-automator' ),
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
					'name' => __( "A user requests to join a {{specific type of}} group", 'uncanny-automator' ),
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
					'name' => __( "A user's topic in {{a forum}} receives a reply", 'uncanny-automator' ),
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
					'name' => __( "Send {{a private message}} to {{a specific user}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Send {{a private message}} to the user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - BuddyPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Send all members of {{a group}} a notification", 'uncanny-automator' ),
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
				array(
					/* translators: Action - BuddyPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Send a private message to a specific user", 'uncanny-automator' ),
				),
			),
		),
		'CF'                => array(
			'name'     => 'Caldera Forms',
			'pro_only' => 'no',
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
		'CHARITABLE'        => array(
			'name'     => 'Charitable',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Anonymous trigger - Charitable */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A donation is made via {{a campaign}} for an amount {{greater than, less than, or equal to}} {{an amount}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - Charitable */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A recurring donation to {{a campaign}} is cancelled", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - Charitable */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A recurring donation to {{a campaign}} is made", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Charitable */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add an entry in {{a donation}} log", 'uncanny-automator' ),
				),
			),
		),
		'CF7'               => array(
			'name'     => 'Contact Form 7',
			'pro_only' => 'no',
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
		'DIVI'              => array(
			'name'     => 'Divi',
			'pro_only' => 'no',
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
		'ESAF'              => array(
			'name'     => 'Easy Affiliate',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Anonymous trigger - Easy Affiliate */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A payout is made to {{an affiliate}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Easy Affiliate */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add a {{new affiliate}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Easy Affiliate */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Record a sale for {{an affiliate}}", 'uncanny-automator' ),
				),
			),
		),
		'EDD'               => array(
			'name'     => 'EDD – Recurring Payments',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Easy Digital Downloads */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A product}} is purchased with {{a discount code}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - EDD – Recurring Payments */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user cancels a subscription to {{a download}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - EDD – Recurring Payments */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's subscription to {{a download}} expires", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - EDD – Recurring Payments */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Delete a customer", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - EDD – Recurring Payments */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Set {{a subscription download}} to expire on {{a specific date}} for the user", 'uncanny-automator' ),
				),
			),
		),
		'ELEM'              => array(
			'name'     => 'Elementor Pro',
			'pro_only' => 'no',
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
		'EVENTSMANAGER'     => array(
			'name'     => 'Events Manager',
			'pro_only' => 'no',
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
		'WPFF'              => array(
			'name'     => 'Fluent Forms',
			'pro_only' => 'no',
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
		'FLSUPPORT'         => array(
			'name'     => 'Fluent Support',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Fluent Support */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A ticket for {{a product}} is closed by {{a customer or an agent}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Fluent Support */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A ticket for {{a product}} is opened by {{a customer or an agent}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Fluent Support */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A ticket for {{a product}} is replied to by a customer", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Fluent Support */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A ticket for {{a product}} is replied to by an agent", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Fluent Support */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A ticket with {{a priority}} is closed by {{a customer or an agent}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Fluent Support */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A ticket with {{a priority}} is opened by {{a customer or an agent}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Fluent Support */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A ticket with {{a priority}} is replied to by a customer", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Fluent Support */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A ticket with {{a priority}} is replied to by an agent", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Fluent Support */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Create {{a ticket}}", 'uncanny-automator' ),
				),
			),
		),
		'FCRM'              => array(
			'name'     => 'FluentCRM',
			'pro_only' => 'no',
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
					'name' => __( "Add {{a contact}} to {{a list}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - FluentCRM */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove {{tags}} from a contact", 'uncanny-automator' ),
				),
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
		'FORMATTER'         => array(
			'name'     => 'Formatter',
			'pro_only' => 'yes',
			'triggers' => array(),
			'actions'  => array(
				array(
					/* translators: Action - Formatter */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Convert {{date}} into {{format}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Formatter */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Convert {{number}} into {{format}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Formatter */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Convert {{text}} into {{format}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Formatter */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Extract the first word from {{a string}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Formatter */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Replace values in {{a string}}", 'uncanny-automator' ),
				),
			),
		),
		'FI'                => array(
			'name'     => 'Formidable Forms',
			'pro_only' => 'no',
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
		'FR'                => array(
			'name'     => 'Forminator',
			'pro_only' => 'no',
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
		'AUTONAMI'          => array(
			'name'     => 'FunnelKit Automations',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - FunnelKit Automations */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A tag}} is removed from a user", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - FunnelKit Automations */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user is removed from {{a list}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - FunnelKit Automations */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A contact is removed from {{a list}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - FunnelKit Automations */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A tag}} is removed from a contact", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - FunnelKit Automations */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add a contact to {{a list}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - FunnelKit Automations */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add the user to {{a list}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - FunnelKit Automations */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove a contact from {{a list}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - FunnelKit Automations */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove {{a tag}} from a contact", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - FunnelKit Automations */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove {{a tag}} from the user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - FunnelKit Automations */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove the user from {{a list}}", 'uncanny-automator' ),
				),
			),
		),
		'GP'                => array(
			'name'     => 'GamiPress',
			'pro_only' => 'no',
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
		'GIVEWP'            => array(
			'name'     => 'GiveWP',
			'pro_only' => 'no',
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
		'GF'                => array(
			'name'     => 'Gravity Forms',
			'pro_only' => 'no',
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
					'name' => __( "A user registers with {{a form}}", 'uncanny-automator' ),
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
				array(
					/* translators: Anonymous trigger - Gravity Forms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A specific field}} in an entry for {{a form}} is updated to {{a specific value}}", 'uncanny-automator' ),
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
					'name' => __( "Delete the entry that matches {{an entry ID}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Gravity Forms */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Register a new user", 'uncanny-automator' ),
				),
			),
		),
		'GK'                => array(
			'name'     => 'GravityKit',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Anonymous trigger - GravityKit */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "An entry for {{a specific form}} is approved", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - GravityKit */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "An entry for {{a specific form}} is rejected", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(),
		),
		'GH'                => array(
			'name'     => 'Groundhogg',
			'pro_only' => 'no',
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
					'name' => __( "A note is added to {{a contact}}", 'uncanny-automator' ),
					'type' => 'anonymous',
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
		'H5P'               => array(
			'name'     => 'H5P',
			'pro_only' => 'yes',
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
		'HF'                => array(
			'name'     => 'HappyForms',
			'pro_only' => 'no',
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
		'IFTTT'             => array(
			'name'     => 'IFTTT',
			'pro_only' => 'no',
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
		'INTEGRATELY'       => array(
			'name'     => 'Integrately',
			'pro_only' => 'no',
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
		'INTEGROMAT'        => array(
			'name'     => 'Integromat',
			'pro_only' => 'no',
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
		'JETENGINE'         => array(
			'name'     => 'JetEngine',
			'pro_only' => 'yes',
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - JetEngine */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user updates {{a specific JetEngine field}} on {{a specific post type}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - JetEngine */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user updates {{a specific JetEngine field}} on {{a specific post type}} to {{a specific value}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(),
		),
		'JET_FORM_BUILDER'  => array(
			'name'     => 'JetFormBuilder',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - JetFormBuilder */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user submits {{a form}} with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - JetFormBuilder */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A form}} is submitted with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(),
		),
		'JETCRM'            => array(
			'name'     => 'Jetpack CRM',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Anonymous trigger - Jetpack CRM */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A company is deleted", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - Jetpack CRM */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A contact is deleted", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - Jetpack CRM */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A quote is created", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - Jetpack CRM */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A quote status is accepted", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - Jetpack CRM */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A tag}} is added to a company", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - Jetpack CRM */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A tag}} is added to a contact", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - Jetpack CRM */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A transaction is deleted", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - Jetpack CRM */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "An invoice is created", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - Jetpack CRM */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "An invoice is deleted", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Jetpack CRM */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Change a contact's status to {{a new status}}", 'uncanny-automator' ),
				),
			),
		),
		'KONNECTZ_IT'       => array(
			'name'     => 'KonnectzIT',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Anonymous trigger - KonnectzIT */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Receive data from KonnectzIT {{webhook}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(),
		),
		'LD'                => array(
			'name'     => 'LearnDash',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A Group Leader is added to {{a group}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A Group Leader is removed from {{a group}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user answers {{a quiz}} question correctly", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user answers {{a quiz}} question incorrectly", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
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
					'name' => __( "A user submits an essay for {{a quiz}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's access to {{a course}} expires", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{An assignment}} is graded", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{An essay question}} is graded", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A course}} is added to {{a group}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add {{a course}} to {{a group}}", 'uncanny-automator' ),
				),
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
					'name' => __( "Mark {{a quiz}} complete for the user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Mark {{a quiz}} not complete for the user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Mark {{a topic}} not complete for the user", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove {{a course}} from {{a group}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove the Group Leader from {{a group}} and all its children", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove the user as a leader of {{a group}}", 'uncanny-automator' ),
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
					'name' => __( "Remove the user from all groups", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Repair the progress of {{a completed course}} for the user", 'uncanny-automator' ),
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
					'name' => __( "Reset the user's progress for all courses associated with {{a group}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Send a {{certificate}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Send an {{email}} to Group Leaders of {{a group}}", 'uncanny-automator' ),
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
				array(
					/* translators: Action - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Unenroll the user from all courses", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - LearnDash */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Unenroll the user from all courses associated with {{a group}}", 'uncanny-automator' ),
				),
			),
		),
		'LP'                => array(
			'name'     => 'LearnPress',
			'pro_only' => 'no',
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
		'LF'                => array(
			'name'     => 'LifterLMS',
			'pro_only' => 'no',
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
		'MAILERLITE'        => array(
			'name'     => 'MailerLite',
			'pro_only' => 'yes',
			'triggers' => array(),
			'actions'  => array(
				array(
					/* translators: Action - MailerLite */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add {{a subscriber}} to {{a group}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - MailerLite */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Create {{a group}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - MailerLite */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove {{a specific group}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - MailerLite */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove {{a subscriber}} from {{a group}}", 'uncanny-automator' ),
				),
			),
		),
		'MAILPOET'          => array(
			'name'     => 'MailPoet',
			'pro_only' => 'no',
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
		'MAKE'              => array(
			'name'     => 'Make',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Anonymous trigger - Make */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Receive data from Make {{webhook}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(),
		),
		'MSLMS'             => array(
			'name'     => 'MasterStudy LMS',
			'pro_only' => 'no',
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
		'M4IS'              => array(
			'name'     => 'Memberium for Keap',
			'pro_only' => 'no',
			'triggers' => array(),
			'actions'  => array(
				array(
					/* translators: Action - Memberium for Keap */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add {{a tag(s)}} to {{a contact}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Memberium for Keap */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add or remove {{a contact}} {{tag(s)}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Memberium for Keap */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add the user to {{a membership level}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Memberium for Keap */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove {{a tag(s)}} from {{a contact}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Memberium for Keap */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove the user from {{a membership level}}", 'uncanny-automator' ),
				),
			),
		),
		'MP'                => array(
			'name'     => 'MemberPress',
			'pro_only' => 'no',
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
					'name' => __( "{{A coupon code}} is redeemed", 'uncanny-automator' ),
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
					'name' => __( "A sub account is removed from {{a parent account}}", 'uncanny-automator' ),
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
				array(
					/* translators: Logged-in trigger - MemberPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's payment for {{a product}} fails", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - MemberPress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's transaction for {{a membership}} is set to {{a status}}", 'uncanny-automator' ),
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
		'MPC'               => array(
			'name'     => 'MemberPress Courses',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - MemberPress Courses */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user achieves a score {{greater than, less than or equal to}} a {{value}} on a {{quiz}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - MemberPress Courses */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user achieves points {{greater than, less than or equal to}} a {{value}} on a {{quiz}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - MemberPress Courses */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Reset the user's progress in {{a course}}", 'uncanny-automator' ),
				),
			),
		),
		'METABOX'           => array(
			'name'     => 'Meta Box',
			'pro_only' => 'yes',
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Meta Box */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user updates {{a field}} on {{a post}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Meta Box */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's {{Meta Box field}} is updated", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - Meta Box */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A field}} is updated on {{a post}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(),
		),
		'MEC'               => array(
			'name'     => 'Modern Events Calendar',
			'pro_only' => 'no',
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
		'MYCRED'            => array(
			'name'     => 'myCred',
			'pro_only' => 'no',
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
		'NEWSLETTER'        => array(
			'name'     => 'Newsletter',
			'pro_only' => 'yes',
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
				array(
					/* translators: Action - Newsletter */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove the user from {{a list}}", 'uncanny-automator' ),
				),
			),
		),
		'NF'                => array(
			'name'     => 'Ninja Forms',
			'pro_only' => 'no',
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
		'OPEN_AI'           => array(
			'name'     => 'OpenAI',
			'pro_only' => 'no',
			'triggers' => array(),
			'actions'  => array(
				array(
					/* translators: Action - OpenAI */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Use {{a prompt}} to generate text with the Davinci model", 'uncanny-automator' ),
				),
			),
		),
		'OPTINMONSTER'      => array(
			'name'     => 'OptinMonster',
			'pro_only' => 'yes',
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
		'PMP'               => array(
			'name'     => 'Paid Memberships Pro',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Paid Memberships Pro */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "An admin assigns {{a membership level}} to a user", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
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
		'PP'                => array(
			'name'     => 'PeepSo',
			'pro_only' => 'no',
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
		'PRESTO'            => array(
			'name'     => 'Presto Player',
			'pro_only' => 'no',
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
		'RAFFLE_PRESS'      => array(
			'name'     => 'RafflePress',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Anonymous trigger - RafflePress */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Someone enters {{a giveaway}} with {{a specific action}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(),
		),
		'RC'                => array(
			'name'     => 'Restrict Content Pro',
			'pro_only' => 'no',
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
		'Run_Now'           => array(
			'name'     => 'Run now',
			'pro_only' => 'yes',
			'triggers' => array(
				array(
					/* translators: Anonymous trigger - Run now */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Trigger recipe manually", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(),
		),
		'STUDIOCART'        => array(
			'name'     => 'Studiocart',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Studiocart */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's subscription is cancelled for {{a product}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - Studiocart */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A guest completes an order for {{a product}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(),
		),
		'SURECART'          => array(
			'name'     => 'SureCart',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Anonymous trigger - SureCart */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A guest purchases {{a product}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(),
		),
		'EC'                => array(
			'name'     => 'The Events Calendar',
			'pro_only' => 'no',
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
				array(
					/* translators: Anonymous trigger - The Events Calendar */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "An attendee is registered for {{an event}} with WooCommerce", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - The Events Calendar */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "RSVP on behalf of {{an attendee}} for {{an event}}", 'uncanny-automator' ),
				),
			),
		),
		'THRIVE_APPRENTICE' => array(
			'name'     => 'Thrive Apprentice',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Thrive Apprentice */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user attempts to access {{a restricted course}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Thrive Apprentice */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user makes a purchase", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Thrive Apprentice */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user progresses in {{a course}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Thrive Apprentice */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user starts {{a course}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Thrive Apprentice */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user starts {{a lesson}} in {{a course}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Thrive Apprentice */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user starts {{a module}} in {{a course}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Thrive Apprentice */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Grant the user access to {{a product}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Thrive Apprentice */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove the user's access to {{a product}}", 'uncanny-automator' ),
				),
			),
		),
		'THRIVECART'        => array(
			'name'     => 'ThriveCart',
			'pro_only' => 'yes',
			'triggers' => array(
				array(
					/* translators: Anonymous trigger - ThriveCart */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Receive data from ThriveCart {{webhook}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(),
		),
		'TUTORLMS'          => array(
			'name'     => 'Tutor LMS',
			'pro_only' => 'no',
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
		'TYPEFORM'          => array(
			'name'     => 'Typeform',
			'pro_only' => 'yes',
			'triggers' => array(
				array(
					/* translators: Anonymous trigger - Typeform */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Receive data from Typeform {{webhook}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(),
		),
		'UM'                => array(
			'name'     => 'Ultimate Member',
			'pro_only' => 'no',
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
		'UPSELL'            => array(
			'name'     => 'Upsell Plugin',
			'pro_only' => 'no',
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
		'WEBHOOKS'          => array(
			'name'     => 'Webhooks',
			'pro_only' => 'no',
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
		'WHOLESALESUITE'    => array(
			'name'     => 'Wholesale Suite',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Wholesale Suite */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A wholesale lead is approved", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Wholesale Suite */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A wholesale lead is rejected", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Wholesale Suite */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A wholesale order for {{a specific product}} is received", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - Wholesale Suite */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Deactivate a wholesale customer matching {{a user ID or email}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Wholesale Suite */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Reject a wholesale lead matching {{a user ID or email}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - Wholesale Suite */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Set the wholesale price of {{a product}}", 'uncanny-automator' ),
				),
			),
		),
		'WISHLISTMEMBER'    => array(
			'name'     => 'Wishlist Member',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - Wishlist Member */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user is approved for {{a membership level}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Wishlist Member */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user is confirmed for {{a membership level}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Wishlist Member */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user is unconfirmed for {{a membership level}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
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
					'name' => __( "A user's access to {{a membership level}} is cancelled", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Wishlist Member */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's access to {{a membership level}} is unapproved", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - Wishlist Member */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's access to {{a membership level}} is uncancelled", 'uncanny-automator' ),
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
		'WC'                => array(
			'name'     => 'WooCommerce Subscriptions',
			'pro_only' => 'yes',
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
					'name' => __( "A user {{completes, pays for, lands on a thank you page for}} {{a variable product}} with {{a variation}} selected", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user {{completes, pays for, lands on a thank you page for}} an order paid for with {{a specific payment method}}", 'uncanny-automator' ),
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
					'name' => __( "A user {{completes, pays for, lands on a thank you page for}} an order with a total {{greater than, less than or equal to}} {{a specific amount}}", 'uncanny-automator' ),
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
					'name' => __( "A guest {{completes, pays for, lands on a thank you page for}} an order with {{a product variation}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A guest {{completes, pays for, lands on a thank you page for}} an order with {{a specific coupon}}", 'uncanny-automator' ),
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
					'name' => __( "A payment fails on an order", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A product}} has its associated order {{completed, paid for, or a thank you page visited}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A product}} has its associated order refunded", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A product}} has its associated order set to {{a specific status}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A product}} in {{a specific term}} in {{a specific taxonomy}} has its associated order set to {{a specific status}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A product variation's}} inventory status is set to {{a specific status}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A product's}} inventory status is set to {{a specific status}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "An order is partially refunded", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "An order is refunded", 'uncanny-automator' ),
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
					'name' => __( "A user renews a subscription to {{a product}} for the {{nth}} time", 'uncanny-automator' ),
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
					'name' => __( "A user subscribes to {{a product}}", 'uncanny-automator' ),
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
					'name' => __( "A user's renewal payment for {{a subscription product}} fails", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WooCommerce Subscriptions */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user's subscription switches from {{a specific variation}} to {{a specific variation}}", 'uncanny-automator' ),
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
					'name' => __( "Add {{a product}} to {{an order}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WooCommerce */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Change the price of {{a specific product}} to {{a new price}}", 'uncanny-automator' ),
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
					'name' => __( "Generate and email a coupon {{code}}", 'uncanny-automator' ),
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
				array(
					/* translators: Action - WooCommerce Subscriptions */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Create a subscription order with {{a product}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WooCommerce Subscriptions */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Create a subscription order with {{a product}} with a payment method", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WooCommerce Subscriptions */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Extend a user’s subscription to {{a specific product}} by {{a number of days}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WooCommerce Subscriptions */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Extend a user's subscription to {{a specific product variation}} of {{a specific product}} by {{a number of days}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WooCommerce Subscriptions */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Set the user's subscription of {{a subscription product}} to {{a status}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WooCommerce Subscriptions */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Set the user's subscription to {{a specific}} variation of {{a variable subscription product}} to {{a status}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WooCommerce Subscriptions */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Shorten a user's subscription to {{a specific product}} by {{a number of days}}", 'uncanny-automator' ),
				),
			),
		),
		'WC_BOOKINGS'       => array(
			'name'     => 'WooCommerce Bookings',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - WooCommerce Bookings */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A booking status is changed to {{a specific status}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - WooCommerce Bookings */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A booking is updated", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(),
		),
		'WP'                => array(
			'name'     => 'WordPress Core',
			'pro_only' => 'no',
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
					'name' => __( "A user is created with {{a specific}} role", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user is deleted", 'uncanny-automator' ),
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
					'name' => __( "A user updates a post in {{a specific taxonomy}}", 'uncanny-automator' ),
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
					'name' => __( "A user views {{a term}} archive", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Logged-in trigger - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A user's post}} is set to {{a specific}} status", 'uncanny-automator' ),
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
					'name' => __( "A guest comment on a user's {{post}} is approved", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A specific meta key}} of a {{specific type of post}} updates to {{a specific value}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A specific type of post}} is set to {{a status}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A taxonomy term}} is added to a {{specific type of post}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A term}} archive is viewed", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add a comment to {{a post}}", 'uncanny-automator' ),
				),
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
					'name' => __( "Delete {{a user}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Delete {{user meta}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Fetch {{an existing user}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Move {{a post}} to the trash", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Move all {{of a specific type of posts}} with {{a taxonomy term}} in {{a taxonomy}} to the trash", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WordPress Core */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Remove {{a role}} from the user's roles", 'uncanny-automator' ),
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
					'name' => __( "Update the author of {{a post}}", 'uncanny-automator' ),
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
		'WPDM'              => array(
			'name'     => 'WordPress Download Manager',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - WordPress Download Manager */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user downloads {{a file}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(),
		),
		'WPMU'              => array(
			'name'     => 'WordPress Multisite',
			'pro_only' => 'yes',
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - WordPress Multisite */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user is added to {{a subsite}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'  => array(
				array(
					/* translators: Action - WordPress Multisite */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add {{a specific user}} to {{a specific subsite}}", 'uncanny-automator' ),
				),
				array(
					/* translators: Action - WordPress Multisite */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Add the user to {{a subsite}}", 'uncanny-automator' ),
				),
			),
		),
		'WPAI'              => array(
			'name'     => 'WP All Import',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Anonymous trigger - WP All Import */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "An import fails", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(),
		),
		'WPCW'              => array(
			'name'     => 'WP Courseware',
			'pro_only' => 'no',
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
		'WF'                => array(
			'name'     => 'WP Fusion Lite',
			'pro_only' => 'no',
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
		'WPJM'              => array(
			'name'     => 'WP Job Manager',
			'pro_only' => 'no',
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
		'WPLMS'             => array(
			'name'     => 'WP LMS',
			'pro_only' => 'no',
			'triggers' => array(),
			'actions'  => array(
				array(
					/* translators: Action - WP LMS */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Enroll the user in {{a course}}", 'uncanny-automator' ),
				),
			),
		),
		'WPMAILSMTPPRO'     => array(
			'name'     => 'WP Mail SMTP Pro',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Anonymous trigger - WP Mail SMTP Pro */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "In an email with {{specific text}} in the subject, a URL containing {{a string}} is clicked", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					/* translators: Anonymous trigger - WP Mail SMTP Pro */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "In any email, a URL containing {{a string}} is clicked", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(),
		),
		'WPSIMPLEPAY'       => array(
			'name'     => 'WP Simple Pay',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Anonymous trigger - WP Simple Pay */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A subscription for {{a form}} is renewed", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(),
		),
		'WPUSERMANAGER'     => array(
			'name'     => 'WP User Manager',
			'pro_only' => 'no',
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
		'WPPOLLS'           => array(
			'name'     => 'WP-Polls',
			'pro_only' => 'no',
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
		'WPCODE_IHAF'       => array(
			'name'     => 'WPCode',
			'pro_only' => 'no',
			'triggers' => array(),
			'actions'  => array(
				array(
					/* translators: Action - WPCode */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "Create a code snippet", 'uncanny-automator' ),
				),
			),
		),
		'WPF'               => array(
			'name'     => 'WPForms',
			'pro_only' => 'no',
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
					'name' => __( "A user submits {{a form}} with {{a specific value}} in {{a specific field}} with PayPal payment", 'uncanny-automator' ),
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
					'name' => __( "{{A form}} is submitted with {{a specific value}} in {{a specific field}} with PayPal payment", 'uncanny-automator' ),
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
		'WPFORO'            => array(
			'name'     => 'wpForo',
			'pro_only' => 'no',
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
		'WSFORMLITE'        => array(
			'name'     => 'WS Form',
			'pro_only' => 'no',
			'triggers' => array(
				array(
					/* translators: Logged-in trigger - WS Form */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "A user submits {{a form}} with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					/* translators: Anonymous trigger - WS Form */
					// phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired
					'name' => __( "{{A form}} is submitted with {{a specific value}} in {{a specific field}}", 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'  => array(),
		),
		'ZAPIER'            => array(
			'name'     => 'Zapier',
			'pro_only' => 'no',
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
