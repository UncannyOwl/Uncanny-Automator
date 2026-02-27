<?php

/**
 * Pro items list.
 *
 * The list of items that are available in the pro version of the plugin.
 * Used to generate the pro items list.
 *
 * @return array
 */
function automator_pro_items_list() {
	return array(
		'ADVADS' => array(
			'name'       => 'Advanced Ads',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( "{{An ad's}} status changes from {{a specific status}} to {{a specific status}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(),
		),
		'ACFWC' => array(
			'name'       => 'Advanced Coupons',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user receives {{a number}} of loyalty points', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's current store credit exceeds {{a specific amount}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's lifetime store credit exceeds {{a specific amount}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( "Add {{a specific amount of}} store credit to the user's account", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Remove {{a specific amount of}} store credit from the user's account", 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'ACF' => array(
			'name'       => 'Advanced Custom Fields',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( '{{A sub field}} in {{a group field}} is updated', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{A sub field}} in {{a group field}} is updated to {{a specific value}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user updates {{a field}} on {{a post}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's {{field}} is updated", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{A field}} is updated on {{a post}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
		'AFFWP' => array(
			'name'       => 'AffiliateWP',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user purchases {{a WooCommerce product}} using an affiliate referral', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'An affiliate refers a sale of {{an Easy Digital Downloads product}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "An affiliate's referral of {{a specific type}} is paid", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "An affiliate's referral of {{a specific type}} is rejected", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{A WooCommerce product}} is purchased using an affiliate referral', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Create {{a referral}} for {{a specific affiliate ID}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Create {{a referral}} for the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Create {{an affiliate}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Link {{a customer}} to {{an affiliate}} for lifetime commissions', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'AMELIABOOKING' => array(
			'name'       => 'Amelia',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user books an appointment {{for a specific service}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's booking for {{an event}} changes to {{a specific status}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's booking of an appointment for {{a service}} has been changed to {{a specific status}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's booking of an appointment for {{a specific service}} is canceled", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's booking of an appointment for {{a specific service}} is rescheduled", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A booking of an appointment for {{a service}} has been changed to {{a specific status}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'A booking of an appointment for {{a specific service}} is canceled', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'A booking of an appointment for {{a specific service}} is rescheduled', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'An appointment is booked for {{a specific service}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
		'ARMEMBER' => array(
			'name'       => 'ARMember',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Add the user to {{a membership plan}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove the user from {{a membership plan}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'ASANA' => array(
			'name'       => 'Asana',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A comment is added to {{a task}} in {{a specific project}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A task is created in {{a specific project}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{A task}} is updated in {{a specific project}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A tasks's {{custom field}} is set to {{a specific value}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{An approval task}} is set to {{a status}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(),
		),
		'UOA' => array(
			'name'       => 'Automator Core',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Cancel the scheduled actions of a recipe for a user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Cancel the user's scheduled actions for {{a recipe}}", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Activate/deactivate a recipe', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'BO' => array(
			'name'       => 'BadgeOS',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user earns {{a achievement}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Revoke {{a rank}} from the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Revoke all {{of a certain type of}} points from the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Revoke {{an achievement}} from the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Revoke {{a number}} {{of a certain type of}} points from the user', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'BB' => array(
			'name'       => 'bbPress',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user replies to {{a topic}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A guest replies to {{a topic}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Post a reply to {{a topic}} in {{a forum}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Post a topic in {{a forum}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Subscribe the user to {{a forum}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'BDB' => array(
			'name'       => 'BuddyBoss',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user activates a new account via an email invitation', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user creates a forum', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user creates a group', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user is banned from a {{specific type of}} group', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user is removed from {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user is removed from a {{specific type of}} group', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user is suspended', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user is unsuspended', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user joins {{a hidden group}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user joins {{a private group}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user joins {{a public group}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user joins a {{specific type of}} group', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user leaves {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user leaves a {{specific type of}} group', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user makes a post to the activity stream of {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user receives a private message from {{a user}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user receives a {{type of}} on-screen notification', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user registers a new account via an email invitation', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user registers with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user rejects a friendship request', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A {{user}} replies to an activity stream message', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user requests access to {{a private group}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user requests to join a {{specific type of}} private group', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user sends a private message to {{a user}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user updates their profile with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's profile type is set to {{a specific type}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's connection request is accepted", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's email invitation results in a new member activation", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's email invitation results in a new member registration", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's friendship request is accepted", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's topic in {{a forum}} receives a reply", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A guest replies to {{a topic}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Add a post to the activity stream of {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Add a post to the sitewide {{activity}} stream', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Add a post to the user's {{activity}} stream", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Create a friendship between {{a user}} and {{another user}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Create {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'End friendship with {{a user}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Follow {{a user}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Post a reply to {{a topic}} in {{a forum}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Post a topic in {{a forum}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove user from {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Send a friendship request to {{a user}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Send a notification to all members of {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Send {{a private message}} to {{a specific user}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Send a private message to all members of {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Send {{a private message}} to the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Send an email to all members of {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Send the user a {{notification}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Set the user's member type to {{a specific type}}", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Set the user's {{Xprofile data}}", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Show {{an on-screen notification}} to the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Stop following {{a user}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Subscribe the user to {{a forum}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Unsubscribe the user from {{a forum}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'BP' => array(
			'name'       => 'BuddyPress',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user creates a group', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user is removed from {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user is removed from {{a specific type of}} group', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user joins {{a private group}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user joins {{a public group}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user leaves {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user leaves {{a specific type of}} group', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user makes a post to the activity stream of {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user registers with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user rejects a friendship request', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{A user}} replies to an activity stream message', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user requests access to {{a private group}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user requests to join a {{specific type of}} group', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user updates their profile with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's member type is set to {{a specific type}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's connection request is accepted", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's topic in {{a forum}} receives a reply", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "User's account is activated", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Add a post to the activity stream of {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Add a post to the sitewide {{activity}} stream', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Add a post to the user's {{activity}} stream", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Create {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'End friendship with {{a user}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove user from {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Send a friendship request to {{a user}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Send {{a private message}} to {{a specific user}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Send {{a private message}} to the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Send all members of {{a group}} a notification', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Send the user a {{notification}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Set the user's member type to {{a specific type}}", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Set the user's {{Xprofile data}}", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Send a private message to a specific user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Unsubscribe the user from {{a forum}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'CF' => array(
			'name'       => 'Caldera Forms',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{A form}} is submitted with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Register a new user', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'CHARITABLE' => array(
			'name'       => 'Charitable',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A donation is made via {{a campaign}} for an amount {{greater than, less than, or equal to}} {{an amount}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'A recurring donation to {{a campaign}} is cancelled', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'A recurring donation to {{a campaign}} is made', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Add an entry in {{a donation}} log', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'CODE_SNIPPETS' => array(
			'name'       => 'Code Snippets',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Create {{a snippet}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'CF7' => array(
			'name'       => 'Contact Form 7',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{A form}} is submitted with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Register a new user', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'LOOPABLE_CSV' => array(
			'name'       => 'CSV',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'Import {{a CSV file}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Import {{a CSV file}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'DO_ACTION' => array(
			'name'       => 'Custom Action',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Call {{a do_action hook}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'CUSTOMUSERFIELDS' => array(
			'name'       => 'Custom User Fields',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( "A user's {{custom user field}} is updated", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's {{custom user field}} is updated to a value", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( "Update the user's {{custom user field}} to {{a value}}", 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'DB_QUERY' => array(
			'name'       => 'Database Query',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Run {{a SELECT query}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Run {{an SQL query}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'DATETIME' => array(
			'name'       => 'Date and Time',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Generate a {{date}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Generate a {{date and time}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Generate a date and time based on a {{date}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'DIVI' => array(
			'name'       => 'Divi',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{A form}} is submitted with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
		'DYNAMIC_CONTENT' => array(
			'name'       => 'Dynamic Content',
			'pro_only'   => 'no',
			'elite_only' => 'yes',
			'triggers'   => array(),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Clear {{a dynamic content block}} for {{a specific user}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Clear {{a dynamic content block}} for the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Update {{a dynamic content block}} for {{a specific user}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Update {{a global dynamic content block}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Update {{a dynamic content block}} for the user', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'ESAF' => array(
			'name'       => 'Easy Affiliate',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A payout is made to {{an affiliate}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Add a {{new affiliate}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Record a sale for {{an affiliate}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'EDD' => array(
			'name'       => 'Easy Digital Downloads',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'User completes {{an order}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A payment fails', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{A product}} is purchased with {{a discount code}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{A file}} is downloaded', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Delete a customer by {{email}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Delete a customer by {{ID}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Generate a discount code', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'EDD_RECURRING' => array(
			'name'       => 'EDD – Recurring Payments',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user cancels a subscription to {{a download}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user cancels their subscription to {{a price option}} of {{a download}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user receives a Stripe refund for their subscription to {{a price option}} of {{a download}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user subscribes to {{a price option}} of {{a download}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's subscription to {{a download}} expires", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's subscription to {{a price option}} of {{a download}} expires", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's subscription to {{a price option}} of {{a download}} is renewed", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( "Cancel the user's subscription matching a {{subscription ID}}", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Set {{a subscription download}} to expire on {{a specific date}} for the user', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'EDD_SL' => array(
			'name'       => 'EDD – Software Licensing',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( "A user's license for {{a download}} is disabled", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(),
		),
		'ELEM' => array(
			'name'       => 'Elementor Pro',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{A form}} is submitted with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Show {{a popup}} to the user', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'EVENTSMANAGER' => array(
			'name'       => 'Events Manager',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user registers for {{an event}} with a {{specific}} ticket', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user unregisters from {{an event}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A booking for {{an event}} is approved', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Unregister the user from {{an event}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'EVEREST_FORMS' => array(
			'name'       => 'Everest Forms',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A form is submitted with a specific value in a specific field', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'A user submits a form with a specific value in a specific field', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(),
		),
		'WPFF' => array(
			'name'       => 'Fluent Forms',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{A form}} is submitted with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Register a new user', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'FLSUPPORT' => array(
			'name'       => 'Fluent Support',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A ticket for {{a product}} is closed by {{a customer or an agent}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A ticket for {{a product}} is opened by {{a customer or an agent}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A ticket for {{a product}} is replied to by a customer', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A ticket for {{a product}} is replied to by an agent', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A ticket with {{a priority}} is closed by {{a customer or an agent}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A ticket with {{a priority}} is opened by {{a customer or an agent}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A ticket with {{a priority}} is replied to by a customer', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A ticket with {{a priority}} is replied to by an agent', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A ticket is closed', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Create {{a ticket}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'FLUENT_BOOKING' => array(
			'name'       => 'FluentBooking',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A group meeting is scheduled with {{a specific host}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'A one-to-one meeting is scheduled with {{a specific host}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
		'FLUENT_COMMUNITY' => array(
			'name'       => 'FluentCommunity',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user comments on a post in {{a space}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user is unenrolled from {{a course}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user leaves {{a space}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user reacts to a post in {{a space}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user submits a request to join {{a space}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Add {{a post}} to {{a space}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Mark {{a course}} complete for the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Mark {{a lesson}} complete for the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove the user from {{a space}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Unenroll the user from {{a course}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'FCRM' => array(
			'name'       => 'FluentCRM',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( '{{A tag}} is removed from a user', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user is removed from {{a list}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A contact is removed from {{a list}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( '{{A tag}} is removed from a contact', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Add {{a contact}} to {{a list}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove {{a contact}} from {{lists}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove {{tags}} from a contact', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove {{tags}} from the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove the user from {{lists}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'FORMATTER' => array(
			'name'       => 'Formatter',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Convert {{date}} into {{format}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Convert {{number}} into {{format}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Convert {{text}} into {{format}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Extract the first word from {{a string}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Replace values in {{a string}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'FI' => array(
			'name'       => 'Formidable Forms',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user updates an entry in {{a form}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{A form}} is submitted with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Create an entry for {{a form}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Register a new user', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'FR' => array(
			'name'       => 'Forminator',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Register a new user', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'AUTONAMI' => array(
			'name'       => 'FunnelKit Automations',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( '{{A tag}} is removed from a user', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user is removed from {{a list}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A contact is removed from {{a list}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( '{{A tag}} is removed from a contact', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Add a contact to {{a list}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Add the user to {{a list}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove a contact from {{a list}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove {{a tag}} from a contact', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove {{a tag}} from the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove the user from {{a list}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'GP' => array(
			'name'       => 'GamiPress',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user attains {{a rank}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user earns {{a number of}} {{a specific type of}} points', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user earns {{an achievement}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's total points reaches {{a specific threshold}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{An achievement}} is revoked from the user', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Revoke {{a rank}} from the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Revoke all {{of a certain type of}} points from the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Revoke {{an achievement}} from the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Revoke {{points}} from the user', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'AUTOMATOR_GENERATOR' => array(
			'name'       => 'Generator',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Generate a {{hash}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Generate a {{nonce}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Generate a random {{email}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Generate a random {{string}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'GITHUB' => array(
			'name'       => 'GitHub',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A pull request is merged in {{a repository}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A pull request is opened in {{a repository}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A push is made to {{a repository}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A release is published in {{a repository}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{An event}} occurs in {{a repository}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'An issue is created in {{a repository}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(),
		),
		'GIVEWP' => array(
			'name'       => 'GiveWP',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user cancels {{a recurring donation}} from {{a specific form}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user continues {{a recurring donation}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user makes a donation via {{a form}} for an amount {{great than, less than, or equal to}} {{an amount}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user makes a donation via {{a form}} with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{A donation form}} is submitted', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'A donation is made via {{a form}} for an amount {{greater than, less than, or equal to}} {{an amount}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Add a note to {{a donor}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Create {{a donor}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'GOOGLE_SHEETS' => array(
			'name'       => 'Google Sheets Web App',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'Receive data from Google Sheets {{Web App}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
		'GF' => array(
			'name'       => 'Gravity Forms',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user is registered', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user registers with {{a form}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user submits {{a form}} with payment', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{A form}} entry is updated to {{a status}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( '{{A form}} is submitted with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( '{{A form}} is submitted with payment', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( '{{A list}} row is submitted in {{a form}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( '{{A specific field}} in an entry for {{a form}} is updated to {{a specific value}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'An entry is deleted from {{a form}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Create an entry for {{a form}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Delete all {{form}} entries for {{a specific user}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Delete the entry that matches {{an entry ID}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Submit an entry for {{a form}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Update {{an entry}} of {{a form}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Register a new user', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'GK' => array(
			'name'       => 'GravityKit',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'An entry for {{a specific form}} is approved', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'An entry for {{a specific form}} is rejected', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
		'GH' => array(
			'name'       => 'Groundhogg',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( '{{A tag}} is added to a user', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{A tag}} is removed from a user', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A note is added to {{a contact}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( '{{A tag}} is added to a contact', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( '{{A tag}} is removed from a contact', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
		'H5P' => array(
			'name'       => 'H5P',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user achieves a score {{greater than, less than or equal to}} {{a value}} on {{H5P content}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user completes any {{of a specific type of}} H5P content', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user completes {{H5P content}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(),
		),
		'HF' => array(
			'name'       => 'HappyForms',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{A form}} is submitted with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Register a new user', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'IFTTT' => array(
			'name'       => 'IFTTT',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'Receive data from IFTTT {{webhook}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Register a new user', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'INTEGRATELY' => array(
			'name'       => 'Integrately',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'Receive data from Integrately {{webhook}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Register a new user', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'INTEGROMAT' => array(
			'name'       => 'Integromat',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'Receive data from Integromat {{webhook}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
		'JETENGINE' => array(
			'name'       => 'JetEngine',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user updates {{a specific JetEngine field}} on {{a specific post type}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user updates {{a specific JetEngine field}} on {{a specific post type}} to {{a specific value}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(),
		),
		'JET_FORM_BUILDER' => array(
			'name'       => 'JetFormBuilder',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{A form}} is submitted with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
		'JETCRM' => array(
			'name'       => 'Jetpack CRM',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A company is deleted', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'A contact is deleted', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'A quote is created', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'A quote status is accepted', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( '{{A tag}} is added to a company', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( '{{A tag}} is added to a contact', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'A transaction is deleted', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'An invoice is created', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'An invoice is deleted', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( "Change a contact's status to {{a new status}}", 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'LOOPABLE_JSON' => array(
			'name'       => 'JSON',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'Import {{a JSON file}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Import {{a JSON file}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'KADENCE' => array(
			'name'       => 'Kadence',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(),
		),
		'KONNECTZ_IT' => array(
			'name'       => 'KonnectzIT',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'Receive data from KonnectzIT {{webhook}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
		'LD' => array(
			'name'       => 'LearnDash',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A Group Leader is added to {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A Group Leader is removed from {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user answers {{a quiz}} question correctly', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user answers {{a quiz}} question incorrectly', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user completes {{a group's}} courses", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user is added to {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user is enrolled in {{a course}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user is removed from {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user is unenrolled from {{a course}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user submits an assignment for {{a lesson or topic}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user submits an essay for {{a quiz}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's access to {{a course}} expires", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{An assignment}} is graded', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{An essay question}} is graded', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{A course}} is added to {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'A user is added to a group that has access to {{a course}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Add {{a course}} to {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Add the user to {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Extend the user's access to {{a course}} by {{a number of}} days", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Mark {{a lesson}} not complete for the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Mark {{a quiz}} complete for the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Mark {{a quiz}} not complete for the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Mark {{a topic}} not complete for the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove {{a course}} from {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove the Group Leader from {{a group}} and all its children', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove the user as a leader of {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove the user from {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove the user from {{a group}} and all its children', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove the user from all groups', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Repair the progress of {{a completed course}} for the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Reset the user's attempts for {{a quiz}}", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Reset the user's progress in {{a course}}", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Reset the user's progress for all courses associated with {{a group}}", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Send a {{certificate}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Send an {{email}} to Group Leaders of {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Send an {{email}} to the user's group leader(s)", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Unenroll the user from {{a course}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Unenroll the user from all courses', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Unenroll the user from all courses associated with {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'LP' => array(
			'name'       => 'LearnPress',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Enroll the user in {{a course}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Mark {{a course}} complete for the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove the user from {{a course}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'LF' => array(
			'name'       => 'LifterLMS',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user cancels {{a membership}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user enrolls in {{a membership}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user is unenrolled from {{a course}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user joins {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user leaves {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user triggers {{an engagement}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's enrollment of {{a type}} is changed to {{a status}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's order status of {{a product type}} changes to {{a specific status}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Enroll the user in {{a course}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Enroll the user in {{a membership}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Mark {{a course}} complete for the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove the user from {{a course}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove the user from {{a membership}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Reset the user's attempts for {{a quiz}}", 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'LOOP' => array(
			'name'       => 'Loop',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'End Loop', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'MAGIC_BUTTON' => array(
			'name'       => 'Magic Button',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user clicks {{a magic button}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user clicks {{a magic link}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{A magic button}} is clicked', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( '{{A magic link}} is clicked', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
		'MAILERLITE' => array(
			'name'       => 'MailerLite',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Add {{a subscriber}} to {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Create {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Create or update {{a subscriber}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove {{a specific group}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove {{a subscriber}} from {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'MAILPOET' => array(
			'name'       => 'MailPoet',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Remove {{a subscriber}} from {{a list}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove the user from {{a list}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'MAILSTER' => array(
			'name'       => 'Mailster',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A new subscriber is removed from {{a Mailster list}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'A subscriber clicks a link in {{a Mailster email}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'A subscriber opens a Mailster email', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Remove a subscriber from {{a Mailster list}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'MAKE' => array(
			'name'       => 'Make',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'Receive data from Make {{webhook}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
		'MSLMS' => array(
			'name'       => 'MasterStudy LMS',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user achieves a percentage {{greater than, less than or equal to}} {{a value}} on {{a quiz}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Mark {{a lesson}} complete for the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Mark {{a lesson}} not complete for the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Mark {{a quiz}} complete for the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Reset the user's progress in {{a course}}", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Unenroll the user from {{a course}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'M4IS' => array(
			'name'       => 'Memberium for Keap',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Add {{a tag(s)}} to {{a contact}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Add or remove {{a contact}} {{tag(s)}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Add the user to {{a membership level}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove {{a tag(s)}} from {{a contact}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove the user from {{a membership level}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'MEMBER_MOUSE' => array(
			'name'       => 'MemberMouse',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( "A member's account data of {{a specific field}} is updated to {{a specific value}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A member's account status is changed to {{a different status}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A member's membership level is changed to {{a different level}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( "Add {{a bundle}} to the member's account", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Create or update {{a member}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'MP' => array(
			'name'       => 'MemberPress',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( '{{A coupon code}} is redeemed', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A sub account is added to {{a parent account}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A sub account is removed from {{a parent account}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's membership to {{a specific product}} expires", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's membership to {{a specific product}} is cancelled", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's membership to {{a specific product}} is paused", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's membership to {{a specific product}} is resumed", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's payment for {{a product}} fails", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's transaction for {{a membership}} is set to {{a status}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Add the user to {{a membership}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove the user from {{a membership}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Cancel the user's {{recurring membership}}", 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'MPC' => array(
			'name'       => 'MemberPress Courses',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user achieves a score {{greater than, less than or equal to}} a {{value}} on a {{quiz}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user achieves points {{greater than, less than or equal to}} a {{value}} on a {{quiz}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( "Reset the user's progress in {{a course}}", 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'METABOX' => array(
			'name'       => 'Meta Box',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user updates {{a field}} on {{a post}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's {{Meta Box field}} is updated", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{A field}} is updated on {{a post}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
		'MEC' => array(
			'name'       => 'Modern Events Calendar',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( "A user's booking of {{an event}} is cancelled", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's booking of {{an event}} is confirmed", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's booking of {{an event}} is pending", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Register a user for {{an event}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'MYCRED' => array(
			'name'       => 'myCred',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user earns {{a rank}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's current balance reaches {{a number of}} {{a specific type of}} points", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's total balance reaches {{a number of}} {{a specific type of}} points", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( "Increase the user's rank for {{a specific type of}} points", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Reduce the user's rank for {{a specific type of}} points", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Revoke {{a badge}} from the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Revoke all {{of a specific type of}} points from the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Revoke {{points}} from the user', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'NEWSLETTER' => array(
			'name'       => 'Newsletter',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A subscription form is submitted with {{a specific list}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Add the user to {{a list}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove the user from {{a list}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'NF' => array(
			'name'       => 'Ninja Forms',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{A form}} is submitted with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Register a new user', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'OPEN_AI' => array(
			'name'       => 'OpenAI',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Use {{a prompt}} to generate text with the Davinci model', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'OPTINMONSTER' => array(
			'name'       => 'OptinMonster',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'Receive data from OptinMonster {{webhook}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Register a new user', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'PMP' => array(
			'name'       => 'Paid Memberships Pro',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'An admin assigns {{a membership level}} to a user', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user renews {{an expired membership}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Add the user to {{a membership level}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove the user from {{a membership level}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'PP' => array(
			'name'       => 'PeepSo',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user gains a new follower', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user loses a follower', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user publishes an activity post', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user unfollows {{another PeepSo member}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user updates {{a specific field}} to {{a specific field value}} in their profile', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Add {{a post}} to the site wide activity stream', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Change the user's PeepSo role to {{a new role}}", 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'ADD_ACTION' => array(
			'name'       => 'Plugin Actions',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'Create a custom trigger for {{a plugin action hook}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
		'PRESTO' => array(
			'name'       => 'Presto Player',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user watches at least {{a specific percentage}} of {{a video}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(),
		),
		'AUTOMATOR_QR_CODE' => array(
			'name'       => 'QR Code',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Generate a {{QR code}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'QUICKBOOKS' => array(
			'name'       => 'QuickBooks Online',
			'pro_only'   => 'no',
			'elite_only' => 'yes',
			'triggers'   => array(),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Create an expense', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Create an invoice', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Create a payment', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Create a product', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Create or update a customer', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'RAFFLE_PRESS' => array(
			'name'       => 'RafflePress',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'Someone enters {{a giveaway}} with {{a specific action}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
		'RC' => array(
			'name'       => 'Restrict Content Pro',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( "A user's membership to {{a specific level}} expires", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's membership to {{a specific level}} is cancelled", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Remove the user from {{a membership level}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'LOOPABLE_RSS' => array(
			'name'       => 'RSS feed',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'Process {{an RSS feed}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Process {{an RSS feed}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'RUN_CODE' => array(
			'name'       => 'Run Code',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Call {{a custom function/method}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Run {{a WordPress hook}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Run {{JavaScript code}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'RUN_NOW' => array(
			'name'       => 'Run now',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'Trigger recipe manually', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
		'SALESFORCE' => array(
			'name'       => 'Salesforce',
			'pro_only'   => 'no',
			'elite_only' => 'yes',
			'triggers'   => array(),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Add {{a contact}} to a {{campaign}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Add {{a lead}} to a {{campaign}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Create {{a contact}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Create {{a lead}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Delete {{a contact}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Update {{a contact}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Update {{a lead}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'SCHEDULE' => array(
			'name'       => 'Schedule',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( '{{Repeat}} {{every hour, day, week, month or year}} at a {{specific time}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( '{{Repeat}} every {{weekday}} at a {{specific time}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'Run on a {{specific date}} and a {{specific time}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
		'STUDIOCART' => array(
			'name'       => 'Studiocart',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( "A user's subscription is cancelled for {{a product}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A guest completes an order for {{a product}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
		'SUGAR_CALENDAR' => array(
			'name'       => 'Sugar Calendar Lite',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'An event is updated in {{a calendar}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'An RSVP is submitted for {{an event}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Update {{an event}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'SURECART' => array(
			'name'       => 'SureCart',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user renews a subscription to {{a product}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's subscription to {{a product}} is cancelled", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A guest purchases {{a product}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'A refund for {{a product}} is issued to a customer', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
		'SURE_FORMS' => array(
			'name'       => 'SureForms',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{A form}} is submitted with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
		'EC' => array(
			'name'       => 'The Events Calendar',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user checks in for {{an event}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'An attendee checks in for {{an event}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'An attendee is registered for {{an event}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'An attendee is registered for {{an event}} with WooCommerce', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'RSVP on behalf of {{an attendee}} for {{an event}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'THRIVE_APPRENTICE' => array(
			'name'       => 'Thrive Apprentice',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user attempts to access {{a restricted course}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user makes a purchase', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user progresses in {{a course}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user starts {{a course}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user starts {{a lesson}} in {{a course}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user starts {{a module}} in {{a course}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Grant the user access to {{a product}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Remove the user's access to {{a product}}", 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'THRIVECART' => array(
			'name'       => 'ThriveCart',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'Receive data from ThriveCart {{webhook}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
		'TUTORLMS' => array(
			'name'       => 'Tutor LMS',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user achieves a percentage {{greater than, less than or equal to}} {{a value}} on a quiz', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user is enrolled in {{a course}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user posts a question in {{a course}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Enroll the user in {{a course}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Mark {{a course}} complete for the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Mark {{a lesson}} complete for the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Reset the user's progress in {{a course}}", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Unenroll a user from {{a course}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'TYPEFORM' => array(
			'name'       => 'Typeform',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'Receive data from Typeform {{webhook}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
		'UM' => array(
			'name'       => 'Ultimate Member',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user registers with {{a form}} with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( "Add {{a role}} to the user's roles", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Set the user's role to {{a specific role}}", 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'UPSELLPLUGIN' => array(
			'name'       => 'Upsell Plugin',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user subscribes to {{a product}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(),
		),
		'URL' => array(
			'name'       => 'URL',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user visits a URL with {{a URL parameter}} set to {{a specific value}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A URL with {{a URL parameter}} set to {{a specific value}} is visited', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
		'UAUSERLISTS' => array(
			'name'       => 'User Lists',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user is added to {{a list}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user is removed from {{a list}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Add the user to {{a list}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Create {{a list}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove the user from {{a list}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'WEBHOOKS' => array(
			'name'       => 'Webhooks',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'Receive data from a webhook', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
		'WHOLESALESUITE' => array(
			'name'       => 'Wholesale Suite',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A wholesale lead is approved', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A wholesale lead is rejected', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A wholesale order for {{a specific product}} is received', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Deactivate a wholesale customer matching {{a user ID or email}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Reject a wholesale lead matching {{a user ID or email}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Set the wholesale price of {{a product}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'WISHLISTMEMBER' => array(
			'name'       => 'Wishlist Member',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user is approved for {{a membership level}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user is confirmed for {{a membership level}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user is unconfirmed for {{a membership level}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user submits {{a registration form}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user submits {{a registration form}} with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's access to {{a membership level}} is cancelled", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's access to {{a membership level}} is unapproved", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's access to {{a membership level}} is uncancelled", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's {{specific}} membership level account is approved", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Remove the user from {{a membership level}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'WC' => array(
			'name'       => 'Woo ShipStation',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user adds {{a product}} to their cart', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user {{purchases a product variation}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user {{completes}} an order with {{ specific payment method}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user {{purchases}} a product in {{a category}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user {{purchases}} a product with {{a tag}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user {{purchases}} {{a quantity}} of {{a product}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user {{completes}} an order with a total {{greater than, less than or equal to}} {{a specific amount}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user reviews {{a product}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user reviews {{a product}} with a rating {{greater than, less than or equal to}} {{an amount}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's order status changes to {{a specific status}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's review on {{a product}} is approved", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A customer makes a payment and their lifetime value is {{greater than, less than, or equal to}} {{a specific amount}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'A guest {{completes, pays for, lands on a thank you page for}} an order with {{a product}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'A guest {{completes, pays for, lands on a thank you page for}} an order with a product in {{a category}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'A guest {{completes, pays for, lands on a thank you page for}} an order with {{a product variation}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'A guest {{completes, pays for, lands on a thank you page for}} an order with {{a specific coupon}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( "A guest order's status is changed to {{a specific status}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'A payment fails on an order', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( '{{A product}} has its associated order {{completed, paid for, or a thank you page visited}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( '{{A product}} has its associated order refunded', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( '{{A product}} has its associated order set to {{a specific status}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( '{{A product}} in {{a specific term}} in {{a specific taxonomy}} has its associated order set to {{a specific status}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( '{{A product}} is restocked', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( '{{A product}} variation is out of stock', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( '{{A product}} variation is restocked', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( "{{A product variation's}} inventory status is set to {{a specific status}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( "{{A product's}} inventory status is set to {{a specific status}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'An order is partially refunded', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'An order is refunded', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'An order with {{a specific product}} is shipped', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'An order with a total {{greater than, less than or equal to}} {{a specific amount}} is shipped', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Add {{a note}} to an order', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Add {{a product}} to {{an order}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Change the price of {{a specific product}} to {{a new price}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Create {{a simple product}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Create an order with {{a product}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Create an order with {{a product}} with a payment gateway', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Generate and email a coupon {{code}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Get order details', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Set {{a specific order}} to {{a specific status}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Generate a coupon code', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'WC_BOOKINGS' => array(
			'name'       => 'Woo Bookings',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A booking status is changed to {{a specific status}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A booking is updated', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Change booking to a specific status', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Create a booking', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'WCMEMBERSHIPS' => array(
			'name'       => 'Woo Memberships',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( "A user's access to {{a membership plan}} is cancelled", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's access to {{a membership plan}} expires", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's access to {{a membership plan}} is changed to {{a status}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Remove the user from {{a membership plan}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'WOOCOMMERCE_SUBSCRIPTION' => array(
			'name'       => 'Woo Subscriptions',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user cancels a subscription to {{a product}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user purchases {{a variable subscription}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user purchases {{a variable subscription}} with {{a variation}} selected', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user renews a subscription to {{a product}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user renews a subscription to {{a product}} for the {{nth}} time', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user renews a subscription to {{a specific}} variation of {{a variable subscription}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user subscribes to {{a product}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's subscription to {{a product}} expires", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's renewal payment for {{a subscription product}} fails", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's subscription switches from {{a specific variation}} to {{a specific variation}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's subscription to {{a product}} is set to {{a status}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's subscription to {{a specific}} variation of {{a variable subscription}} expires", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's subscription to {{a specific}} variation of {{a variable subscription}} is set to {{a status}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's trial period to {{a subscription}} expires", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's trial period to {{a specific}} variation of {{a variable subscription}} expires", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( "Cancel the user's subscription to {{a specific variation}} of {{a variable subscription variation}}", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Cancel the user's subscription to {{a variable subscription product}}", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Create a subscription order with {{a product}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Create a subscription order with {{a product}} with a payment method', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Extend a user's subscription to {{a specific product}} by {{a number of days}}", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Extend a user's subscription to {{a specific product variation}} of {{a specific product}} by {{a number of days}}", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Extend the user's next subscription renewal date to {{a specific product}} by {{a number of days}}", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Extend the user's next subscription renewal date to {{a specific product variation}} of {{a specific product}} by {{a number of days}}", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Remove {{a subscription product}} from the user's {{subscription}}", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Remove {{a variation}} of {{a subscription product}} from the user's {{subscription}}", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Set the user's subscription of {{a subscription product}} to {{a status}}", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Set the user's subscription to {{a specific}} variation of {{a variable subscription product}} to {{a status}}", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Shorten a user's subscription to {{a specific product}} by {{a number of days}}", 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'WP' => array(
			'name'       => 'WordPress Core',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( '{{A post}} is moved to the trash', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{A specific}} role is removed from the user', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user is created', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user is created with {{a specific}} role', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user is deleted', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user resets their password', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user updates {{a post}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user updates a post in {{a specific}} status', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user updates a post in {{a specific taxonomy}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user updates a post not in {{a specific}} status', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user updates {{a specific meta key}} of a {{specific type of post}} to {{a specific value}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user views {{a term}} archive', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "{{A user's post}} is set to {{a specific}} status", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's {{profile field}} is updated", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's role changed from {{a specific role}} to {{a specific role}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's {{specific}} meta key is updated", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's {{specific}} meta key is updated to {{a specific value}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's {{specific type of post}} is moved to the trash", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's {{specific type of post}} is set to {{a status}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's {{specific type of post}} is updated", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's comment on {{a post}} is approved", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's comment on {{a specific type of post}} receives a reply", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A guest comment is submitted on a user's {{post}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( "A guest comment on a user's {{post}} is approved", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( '{{A specific meta key}} of a {{specific type of post}} updates to {{a specific value}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( '{{A specific type of post}} is set to {{a status}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( '{{A taxonomy term}} is added to a {{specific type of post}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( '{{A term}} archive is viewed', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Add a comment to {{a post}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Add {{a taxonomy term}} to {{a post}} in {{a post type}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Add {{an image}} to the media library', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Create a {{user}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Delete {{a user}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Delete {{user meta}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( '{{Enable/disable}} comments on {{a post}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Fetch {{an existing user}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Move {{a post}} to the trash', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Move all {{of a specific type of posts}} with {{a taxonomy term}} in {{a taxonomy}} to the trash', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Remove {{a role}} from the user's roles", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove {{a taxonomy term}} from {{a post}} in {{a post type}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Set {{a post}} to {{a status}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Set {{post meta}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Set the featured image of {{a post}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Set {{user meta}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Update the author of {{a post}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Update the content of {{a post}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Update the published date of {{a post}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Update the slug of {{a post}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Update the title of {{a post}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( "Update the user's {{details}}", 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Verify a {{nonce}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'WPDM' => array(
			'name'       => 'WordPress Download Manager',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user downloads {{a file}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(),
		),
		'WPMU' => array(
			'name'       => 'WordPress Multisite',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user is added to {{a subsite}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Add {{a specific user}} to {{a specific subsite}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Add the user to {{a subsite}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'WP_ADMIN' => array(
			'name'       => 'WP Admin',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( '{{A plugin}} is {{activated/deactivated}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( '{{A plugin}} is updated', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( '{{A theme}} is {{activated/deactivated}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( '{{A theme}} is updated', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'WordPress version is updated', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
		'WPAI' => array(
			'name'       => 'WP All Import',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'An import fails', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
		'WP_BITLY' => array(
			'name'       => 'WP Bitly',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Shorten {{a URL}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'WPCW' => array(
			'name'       => 'WP Courseware',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Enroll the user in {{a course}}', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove the user from {{a course}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'WF' => array(
			'name'       => 'WP Fusion Lite',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( '{{A tag}} is added to a user', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{A tag}} is removed from a user', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{A tag}} is added to a user', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{A tag}} is removed from a user', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Remove {{a tag}} from the user', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Remove {{a tag}} from the user', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'WPJM' => array(
			'name'       => 'WP Job Manager',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( '{{A job}} is filled', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user marks a {{specific type of}} job as filled', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user marks a {{specific type of}} job as not filled', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user receives an application to a {{specific type of}} job', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user updates {{a job}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's application is set to {{a specific status}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( "A user's application to a {{specific type of}} job is set to {{a specific status}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'An application is received for {{a job}} of {{a specific type}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(),
		),
		'WPLMS' => array(
			'name'       => 'WP LMS',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Enroll the user in {{a course}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'WPMAILSMTPPRO' => array(
			'name'       => 'WP Mail SMTP Pro',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'In an email with {{specific text}} in the subject, a URL containing {{a string}} is clicked', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'In any email, a URL containing {{a string}} is clicked', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
		'WPSIMPLEPAY' => array(
			'name'       => 'WP Simple Pay',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A payment for {{a form}} is partially refunded', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'A payment intent for {{a form}} is set to processing', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'A subscription for {{a form}} is cancelled', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( 'A subscription for {{a form}} is renewed', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
		'WPUSERMANAGER' => array(
			'name'       => 'WP User Manager',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user is approved', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user is approved to join {{a private group}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user is rejected', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user is rejected from joining {{a private group}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user joins {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user leaves {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user registers using {{a form}} with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user updates their account information', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user verifies their email address', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(),
		),
		'WPP' => array(
			'name'       => 'WP-Polls',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user submits {{a poll}} with {{a specific answer}} selected', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(),
		),
		'WPCODE_IHAF' => array(
			'name'       => 'WPCode',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( '{{A snippet}} is deactivated', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Create a code snippet', 'Automator Pro item', 'uncanny-automator' ),
				),
				array(
					'name' => esc_html_x( 'Run {{an on-demand code snippet}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'WPDISCUZ' => array(
			'name'       => 'wpDiscuz',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( "A guest comment is submitted on a user's {{post}}", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( "A guest comment on a user's {{post}} is approved", 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Add {{a comment}} to {{a post}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'WPF' => array(
			'name'       => 'WPForms',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}} with PayPal payment', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( 'A user submits {{a form}} with PayPal payment', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{A form}} is submitted with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( '{{A form}} is submitted with {{a specific value}} in {{a specific field}} with PayPal payment', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
				array(
					'name' => esc_html_x( '{{A form}} is submitted with PayPal payment', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Register a new user', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'WPFORO' => array(
			'name'       => 'wpForo',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user replies to {{a topic}} in {{a forum}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Remove the user from {{a group}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'WSFORMLITE' => array(
			'name'       => 'WS Form',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'A user submits {{a form}} with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'logged-in',
				),
				array(
					'name' => esc_html_x( '{{A form}} is submitted with {{a specific value}} in {{a specific field}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
		'LOOPABLE_XML' => array(
			'name'       => 'XML',
			'pro_only'   => 'yes',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'Import {{an XML file}}', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(
				array(
					'name' => esc_html_x( 'Import {{an XML file}}', 'Automator Pro item', 'uncanny-automator' ),
				),
			),
		),
		'ZAPIER' => array(
			'name'       => 'Zapier',
			'pro_only'   => 'no',
			'elite_only' => 'no',
			'triggers'   => array(
				array(
					'name' => esc_html_x( 'Receive data from Zapier webhook', 'Automator Pro item', 'uncanny-automator' ),
					'type' => 'anonymous',
				),
			),
			'actions'    => array(),
		),
	);
}
