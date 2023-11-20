<?php

namespace Uncanny_Automator;

/**
 *
 */
class Pro_Upsell {
	/**
	 * @var array
	 */
	public static $feature = array();

	/**
	 *
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'pro_upsell_menu' ) );
		add_filter(
			'admin_body_class',
			function ( $classes ) {
				global $current_screen;
				if ( 'uo-recipe_page_uncanny-automator-pro-upgrade' !== $current_screen->id ) {
					return $classes;
				}

				return "$classes uo-recipe_page_uncanny-automator-config";
			}
		);
	}

	/**
	 * @return void
	 */
	public function pro_upsell_menu() {
		if ( defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) ) {
			return;
		}
		add_submenu_page(
			'edit.php?post_type=uo-recipe',
			esc_attr__( 'Upgrade to Automator Pro', 'uncanny-automator' ),
			esc_attr__( 'Upgrade to Pro', 'uncanny-automator' ),
			'manage_options',
			'uncanny-automator-pro-upgrade',
			array(
				$this,
				'pro_upgrade_view',
			),
			PHP_INT_MAX
		);
		self::$feature = apply_filters(
			'automator_pro_upsell_features',
			array(
				__( 'Integrations', 'uncanny-automator' ) => array(
					array(
						'label' => esc_html__( 'Unlimited use of >70 WP integrations', 'uncanny-automator' ),
						'free'  => true,
						'pro'   => true,
						'link'  => '',
					),
					array(
						'label' => esc_html__( 'Unlimited recipe runs', 'uncanny-automator' ),
						'free'  => true,
						'pro'   => true,
						'link'  => '',
					),
					array(
						'label' => esc_html__( 'Unlimited recipes', 'uncanny-automator' ),
						'free'  => true,
						'pro'   => true,
						'link'  => '',
					),
					array(
						'label' => esc_html__( 'Unlimited use of >100 integrations', 'uncanny-automator' ),
						'free'  => false,
						'pro'   => true,
						'link'  => 'https://automatorplugin.com/integrations/',
					),
					array(
						'label' => esc_html__( 'Over 800 triggers & actions', 'uncanny-automator' ),
						'free'  => false,
						'pro'   => true,
						'link'  => 'https://automatorplugin.com/all-triggers-and-actions/',
					),
					array(
						'label' => esc_html__( 'Over 5,000 tokens', 'uncanny-automator' ),
						'free'  => false,
						'pro'   => true,
						'link'  => '',
					),
				),
				__( 'Features', 'uncanny-automator' )     => array(
					array(
						'label' => esc_html__( 'Intuitive recipe builder', 'uncanny-automator' ),
						'free'  => true,
						'pro'   => true,
						'link'  => '',
					),
					array(
						'label' => esc_html__( 'Support for anonymous and logged in users', 'uncanny-automator' ),
						'free'  => true,
						'pro'   => true,
						'link'  => '',
					),
					array(
						'label' => esc_html__( 'Run calculations on data', 'uncanny-automator' ),
						'free'  => true,
						'pro'   => true,
						'link'  => 'https://automatorplugin.com/knowledge-base/calculations-math-equations/',
					),
					array(
						'label' => esc_html__( 'Detailed recipe, action and trigger logs', 'uncanny-automator' ),
						'free'  => true,
						'pro'   => true,
						'link'  => 'https://automatorplugin.com/knowledge-base/using-automator-logs/',
					),
					array(
						'label' => esc_html__( 'Action tokens', 'uncanny-automator' ),
						'free'  => true,
						'pro'   => true,
						'link'  => 'https://automatorplugin.com/knowledge-base/managing-tokens/#action-tokens',
					),
					array(
						'label' => esc_html__( 'Delayed and scheduled actions', 'uncanny-automator' ),
						'free'  => false,
						'pro'   => true,
						'link'  => 'https://automatorplugin.com/knowledge-base/scheduled-actions/',
					),
					array(
						'label' => esc_html__( 'Conditions and filters', 'uncanny-automator' ),
						'free'  => false,
						'pro'   => true,
						'link'  => 'https://automatorplugin.com/knowledge-base/action-filters-conditions/',
					),
					array(
						'label' => esc_html__( 'Loops', 'uncanny-automator' ),
						'free'  => false,
						'pro'   => true,
						'link'  => 'https://automatorplugin.com/knowledge-base/user-loops/',
					),
					array(
						'label' => esc_html__( 'Run now', 'uncanny-automator' ),
						'free'  => false,
						'pro'   => true,
						'link'  => 'https://automatorplugin.com/knowledge-base/run-now/',
					),
					array(
						'label' => esc_html__( 'Magic buttons and magic links', 'uncanny-automator' ),
						'free'  => false,
						'pro'   => true,
						'link'  => 'https://automatorplugin.com/knowledge-base/magic-button/',
					),
					array(
						'label' => esc_html__( 'Create WordPress users', 'uncanny-automator' ),
						'free'  => false,
						'pro'   => true,
						'link'  => '',
					),
					array(
						'label' => esc_html__( 'Automatic data cleanup', 'uncanny-automator' ),
						'free'  => false,
						'pro'   => true,
						'link'  => '',
					),
					array(
						'label' => esc_html__( 'Rerun failed API-based actions', 'uncanny-automator' ),
						'free'  => true,
						'pro'   => true,
						'link'  => '',
					),
				),
				__( 'Webhooks', 'uncanny-automator' )     => array(
					array(
						'label' => esc_html__( 'Send unlimited outgoing webhooks', 'uncanny-automator' ),
						'free'  => true,
						'pro'   => true,
						'link'  => '',
					),
					array(
						'label' => esc_html__( 'Support all data types', 'uncanny-automator' ),
						'free'  => true,
						'pro'   => true,
						'link'  => '',
					),
					array(
						'label' => esc_html__( 'JSON and nested data support', 'uncanny-automator' ),
						'free'  => true,
						'pro'   => true,
						'link'  => '',
					),
					array(
						'label' => esc_html__( 'Authentication support', 'uncanny-automator' ),
						'free'  => true,
						'pro'   => true,
						'link'  => '',
					),
					array(
						'label' => esc_html__( 'Receive data via webhooks', 'uncanny-automator' ),
						'free'  => false,
						'pro'   => true,
						'link'  => 'https://automatorplugin.com/knowledge-base/webhook-triggers/',
					),
				),
				__( 'Apps', 'uncanny-automator' )         => array(
					array(
						/* translators: 1. Application name */
						'label' => sprintf( esc_html__( 'Unlimited use of %1$s', 'uncanny-automator' ), '<uo-icon integration="ACTIVE_CAMPAIGN"></uo-icon>' . esc_html__( 'ActiveCampaign', 'uncanny-automator' ) ),
						'free'  => false,
						'pro'   => true,
						'link'  => '',
					),
					array(
						/* translators: 1. Application name */
						'label' => sprintf( esc_html__( 'Unlimited use of %1$s', 'uncanny-automator' ), '<uo-icon integration="FACEBOOK"></uo-icon>' . esc_html__( 'Facebook Groups', 'uncanny-automator' ) ),
						'free'  => false,
						'pro'   => true,
						'link'  => '',
					),
					array(
						/* translators: 1. Application name */
						'label' => sprintf( esc_html__( 'Unlimited use of %1$s', 'uncanny-automator' ), '<uo-icon integration="FACEBOOK"></uo-icon>' . esc_html__( 'Facebook Pages', 'uncanny-automator' ) ),
						'free'  => false,
						'pro'   => true,
						'link'  => '',
					),
					array(
						/* translators: 1. Application name */
						'label' => sprintf( esc_html__( 'Unlimited use of %1$s', 'uncanny-automator' ), '<uo-icon integration="GOOGLE_CALENDAR"></uo-icon>' . esc_html__( 'Google Calendar', 'uncanny-automator' ) ),
						'free'  => false,
						'pro'   => true,
						'link'  => '',
					),
					array(
						/* translators: 1. Application name */
						'label' => sprintf( esc_html__( 'Unlimited use of %1$s', 'uncanny-automator' ), '<uo-icon integration="GOOGLESHEET"></uo-icon>' . esc_html__( 'Google Sheets', 'uncanny-automator' ) ),
						'free'  => false,
						'pro'   => true,
						'link'  => '',
					),
					array(
						/* translators: 1. Application name */
						'label' => sprintf( esc_html__( 'Unlimited use of %1$s', 'uncanny-automator' ), '<uo-icon integration="GTT"></uo-icon>' . esc_html__( 'GoTo Training', 'uncanny-automator' ) ),
						'free'  => false,
						'pro'   => true,
						'link'  => '',
					),
					array(
						/* translators: 1. Application name */
						'label' => sprintf( esc_html__( 'Unlimited use of %1$s', 'uncanny-automator' ), '<uo-icon integration="GTW"></uo-icon>' . esc_html__( 'GoTo Webinar', 'uncanny-automator' ) ),
						'free'  => false,
						'pro'   => true,
						'link'  => '',
					),
					array(
						/* translators: 1. Application name */
						'label' => sprintf( esc_html__( 'Unlimited use of %1$s', 'uncanny-automator' ), '<uo-icon integration="HUBSPOT"></uo-icon>' . esc_html__( 'HubSpot', 'uncanny-automator' ) ),
						'free'  => false,
						'pro'   => true,
						'link'  => '',
					),
					array(
						/* translators: 1. Application name */
						'label' => sprintf( esc_html__( 'Unlimited use of %1$s', 'uncanny-automator' ), '<uo-icon integration="INSTAGRAM"></uo-icon>' . esc_html__( 'Instagram', 'uncanny-automator' ) ),
						'free'  => false,
						'pro'   => true,
						'link'  => '',
					),
					array(
						/* translators: 1. Application name */
						'label' => sprintf( esc_html__( 'Unlimited use of %1$s', 'uncanny-automator' ), '<uo-icon integration="LINKEDIN"></uo-icon>' . esc_html__( 'LinkedIn Pages', 'uncanny-automator' ) ),
						'free'  => false,
						'pro'   => true,
						'link'  => '',
					),
					array(
						/* translators: 1. Application name */
						'label' => sprintf( esc_html__( 'Unlimited use of %1$s', 'uncanny-automator' ), '<uo-icon integration="MAILCHIMP"></uo-icon>' . esc_html__( 'Mailchimp', 'uncanny-automator' ) ),
						'free'  => false,
						'pro'   => true,
						'link'  => '',
					),
					array(
						/* translators: 1. Application name */
						'label' => sprintf( esc_html__( 'Unlimited use of %1$s', 'uncanny-automator' ), '<uo-icon integration="SLACK"></uo-icon>' . esc_html__( 'Slack', 'uncanny-automator' ) ),
						'free'  => false,
						'pro'   => true,
						'link'  => '',
					),
					array(
						/* translators: 1. Application name */
						'label' => sprintf( esc_html__( 'Unlimited use of %1$s', 'uncanny-automator' ), '<uo-icon integration="TWILIO"></uo-icon>' . esc_html__( 'Twilio', 'uncanny-automator' ) ),
						'free'  => false,
						'pro'   => true,
						'link'  => '',
					),
					array(
						/* translators: 1. Application name */
						'label' => sprintf( esc_html__( 'Unlimited use of %1$s', 'uncanny-automator' ), '<uo-icon integration="TWITTER"></uo-icon>' . esc_html__( 'X/Twitter', 'uncanny-automator' ) ),
						'free'  => false,
						'pro'   => true,
						'link'  => '',
					),
					array(
						/* translators: 1. Application name */
						'label' => sprintf( esc_html__( 'Unlimited use of %1$s', 'uncanny-automator' ), '<uo-icon integration="WHATSAPP"></uo-icon>' . esc_html__( 'WhatsApp', 'uncanny-automator' ) ),
						'free'  => false,
						'pro'   => true,
						'link'  => '',
					),
					array(
						/* translators: 1. Application name */
						'label' => sprintf( esc_html__( 'Unlimited use of %1$s', 'uncanny-automator' ), '<uo-icon integration="ZOOM"></uo-icon>' . esc_html__( 'Zoom Meetings', 'uncanny-automator' ) ),
						'free'  => false,
						'pro'   => true,
						'link'  => '',
					),
					array(
						/* translators: 1. Application name */
						'label' => sprintf( esc_html__( 'Unlimited use of %1$s', 'uncanny-automator' ), '<uo-icon integration="ZOOMWEBINAR"></uo-icon>' . esc_html__( 'Zoom Webinars', 'uncanny-automator' ) ),
						'free'  => false,
						'pro'   => true,
						'link'  => '',
					),
				),
			)
		);
	}

	/**
	 * @return void
	 */
	public function pro_upgrade_view() {
		$pricing_link = add_query_arg(
		// UTM
			array(
				'utm_source'  => 'uncanny_automator',
				'utm_medium'  => 'upgrade_to_pro',
				'utm_content' => 'upgrade_to_pro_button',
			),
			'https://automatorplugin.com/pricing/'
		);
		include_once 'view-pro-upsell.php';
	}
}
