<?php
/**
 * Setup wizard controller.
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

use Throwable;
use Uncanny_Automator\App\Application\Mcp\Hosted_App;
use Uncanny_Automator\App\Feature_State\Domain\Feature_State;
use Uncanny_Automator\App\Infrastructure\Page_Builder\Page_Builder_Settings;
use Uncanny_Automator\App\Plan\Services\Account\Account_Connect_Url;
use Uncanny_Automator\App\Plan\Services\License\License_Service;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Hosted_Bridge\Hosted_Ai_Dashboard;
use WP_Error;

use function Uncanny_Automator\App\Infrastructure\automator_feature_state_query;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Setup_Wizard
 *
 * The post-pivot setup wizard: link a free account, connect the customer's own
 * AI through the hosted Automator MCP app, offer Uncanny Agent and Uncanny Page
 * Builder, then hand over prompts to try.
 *
 * Four steps, ten screens. The screen is derived from the `step` query parameter
 * plus the flags `recipes`, `app`, `agent` and `skipped`, then checked against the
 * site state (account linked, AI connected, Agent enabled) before it renders.
 *
 * @since ${VERSION}
 */
class Setup_Wizard {

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'uncanny-automator-setup-wizard';

	/**
	 * Screen identifiers.
	 */
	const SCREEN_ACCOUNT       = 'account';
	const SCREEN_CREDITS       = 'credits';
	const SCREEN_CHOOSE        = 'choose';
	const SCREEN_WAITING       = 'waiting';
	const SCREEN_AGENT         = 'agent';
	const SCREEN_BUILDER       = 'builder';
	const SCREEN_BUILDER_AGENT = 'builder-agent';
	const SCREEN_CONNECTED     = 'connected';
	const SCREEN_AGENT_TRY     = 'agent-try';
	const SCREEN_SKIPPED       = 'skipped';

	/**
	 * Admin-post actions for the two yes/no offers.
	 */
	const AGENT_CHOICE_ACTION        = 'automator_setup_wizard_agent';
	const PAGE_BUILDER_CHOICE_ACTION = 'automator_setup_wizard_page_builder';
	const CHOICE_NONCE               = 'automator_setup_wizard_choice';

	/**
	 * AJAX action polled by the waiting screen.
	 */
	const STATUS_AJAX_ACTION = 'automator_setup_wizard_connection_status';
	const STATUS_NONCE       = 'automator_setup_wizard_status';

	/**
	 * Help link on the waiting screen.
	 */
	const KNOWLEDGE_BASE_URL = 'https://automatorplugin.com/knowledge-base/';

	/**
	 * The vendor the hosted service reports for the AI apps that have one. It
	 * comes from the app's sign-in address, so Claude Code is not Claude.
	 */
	const HOSTED_VENDORS = array(
		'chatgpt' => 'chatgpt',
		'claude'  => 'claude',
		'gemini'  => 'gemini',
	);

	/**
	 * The brand the hosted service picks from the name of the AI apps that
	 * have no vendor of their own.
	 */
	const HOSTED_BRANDS = array(
		'copilot'    => 'copilot',
		'perplexity' => 'perplexity',
	);

	/**
	 * Request cache for the account connection state.
	 *
	 * @var bool|null
	 */
	private $is_user_connected_cache = null;

	/**
	 * Request cache for the AI connection state, by AI app.
	 *
	 * @var array<string,bool|WP_Error>
	 */
	private $is_ai_connected_cache = array();

	/**
	 * Set-ups action hooks.
	 *
	 * @return void
	 */
	public function __construct() {

		if ( $this->is_setup_wizard_page() ) {
			// Keep Uncanny Agent off the setup wizard. The Try it screen opens it
			// on the Dashboard instead.
			add_filter( 'automator_mcp_should_render_surface', '__return_false' );
		}

		add_action( 'admin_menu', array( $this, 'setup_menu_page' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Send the request to the screen its state allows.
		add_action( 'admin_init', array( $this, 'redirect_to_allowed_screen' ), 20 );

		add_action( 'admin_post_' . self::AGENT_CHOICE_ACTION, array( $this, 'handle_agent_choice' ) );
		add_action( 'admin_post_' . self::PAGE_BUILDER_CHOICE_ACTION, array( $this, 'handle_page_builder_choice' ) );

		if ( filter_has_var( INPUT_GET, 'recipe_ui_connect_automator_license' ) && filter_has_var( INPUT_GET, 'origin' ) ) {

			add_action( 'admin_init', array( $this, 'redirect_if_from_recipe_builder' ) );

		}
	}

	/**
	 * Redirect account connections that start in the recipe builder.
	 *
	 * @return void
	 */
	public function redirect_if_from_recipe_builder() {

		wp_redirect( $this->get_recipe_builder_connect_uri( (string) filter_input( INPUT_GET, 'return_to' ) ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect

		die;
	}

	/**
	 * The store link for an account connection that starts in the recipe
	 * builder. The store returns to the recipe with the redirect nonce.
	 *
	 * Built through Account_Connect_Url for the same reason as the connect
	 * button: the store needs the return address encoded to read it whole.
	 *
	 * @param string $return_to The recipe URL, URL-encoded.
	 *
	 * @return string
	 */
	public function get_recipe_builder_connect_uri( $return_to ) {

		return ( new Account_Connect_Url() )->returning_to(
			add_query_arg( 'state', wp_create_nonce( 'automator_setup_wizard_redirect_nonce' ), urldecode( $return_to ) )
		);
	}

	/**
	 * Set-ups the menu page.
	 *
	 * @return void
	 */
	public function setup_menu_page() {

		$is_setup_wizard_page = $this->is_setup_wizard_page();
		$is_visible           = $this->feature_is_visible( Feature_State::SETUP_WIZARD );

		if ( $is_visible || $is_setup_wizard_page ) {
			// Preserve an in-progress direct route without advertising a hidden
			// wizard in the Recipes menu. WordPress requires a string parent slug.
			add_submenu_page(
				$is_visible ? 'edit.php?post_type=uo-recipe' : '',
				esc_attr__( 'Uncanny Automator Setup Wizard', 'uncanny-automator' ),
				esc_attr__( 'Setup wizard', 'uncanny-automator' ),
				automator_get_capability(),
				self::PAGE_SLUG,
				array(
					$this,
					'setup_wizard_view',
				),
				0
			);
		}
	}

	/**
	 * Retrieves the views directory path.
	 *
	 * @return string
	 */
	public function get_view_path() {
		return UA_ABSPATH . 'src/core/admin/setup-wizard/src/views/';
	}

	/**
	 * Includes the set-up wizard view.
	 *
	 * @return void
	 */
	public function setup_wizard_view() {
		include_once $this->get_view_path() . 'welcome.php';
	}

	/**
	 * Enqueues the wizard stylesheet and script.
	 *
	 * @return void
	 */
	public function enqueue_assets() {

		if ( ! $this->is_setup_wizard_page() ) {
			return;
		}

		wp_enqueue_style(
			'uap-setup-wizard',
			plugins_url( 'assets/css/setup-wizard.css', __FILE__ ),
			array( 'uap-admin' ),
			Utilities::automator_get_version()
		);

		wp_enqueue_script(
			'uap-setup-wizard',
			Utilities::automator_get_asset( 'build/setup-wizard.js' ),
			array( 'uap-admin' ),
			Utilities::automator_get_version(),
			true
		);

		wp_localize_script( 'uap-setup-wizard', 'AutomatorSetupWizard', $this->get_script_data() );
	}

	/**
	 * Data handed to the wizard script.
	 *
	 * @return array<string,mixed>
	 */
	public function get_script_data() {

		$screen   = $this->get_screen();
		$app      = $this->get_selected_app();
		$app_name = $this->get_app_name( $app );

		return array(
			'screen'       => $screen,
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'statusAction' => self::STATUS_AJAX_ACTION,
			'statusNonce'  => wp_create_nonce( self::STATUS_NONCE ),
			'app'          => $app,
			'pollInterval' => (int) apply_filters( 'automator_setup_wizard_poll_interval', 3000 ),
			// The waiting screen keeps naming the chosen app on the next screens.
			'nextUrl'      => $this->get_screen_uri( self::SCREEN_BUILDER, '' !== $app ? array( 'app' => $app ) : array() ),
			'agentUrl'     => $this->get_automator_dashboard_agent_uri(),
			'strings'      => array(
				'copy'              => esc_html_x( 'Copy', 'Setup wizard prompt action', 'uncanny-automator' ),
				'copied'            => esc_html_x( 'Copied', 'Setup wizard prompt action done', 'uncanny-automator' ),
				/* translators: %s: Name of the AI app. */
				'connected'         => sprintf( esc_html_x( '%s is connected to this site', 'Setup wizard connection pill', 'uncanny-automator' ), $app_name ),
				'checking'          => esc_html_x( 'Checking the connection...', 'Setup wizard connection status', 'uncanny-automator' ),
				'statusUnavailable' => esc_html_x( 'Connection status unavailable', 'Setup wizard connection status', 'uncanny-automator' ),
				'unavailable'       => esc_html_x( 'We could not check the connection. We will try again shortly.', 'Setup wizard connection status', 'uncanny-automator' ),
				/* translators: %s: Address of the hosted app. */
				'unavailableFinal'  => sprintf( esc_html_x( 'We could not check the connection. Reload this page and sign in to WordPress if asked, or check your sign-in at %s. If this continues, ask your host to check outgoing connections.', 'Setup wizard connection status', 'uncanny-automator' ), esc_html( $this->get_hosted_app_address() ) ),
				'forbidden'         => esc_html_x( 'We could not check the connection. Reload this page and sign in to WordPress again if asked. If this continues, ask a site administrator to check your access.', 'Setup wizard connection status', 'uncanny-automator' ),
			),
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Screens
	|--------------------------------------------------------------------------
	*/

	/**
	 * Every screen with its step number and the query flags that select it.
	 *
	 * @return array<string,array{step:int,args:array<string,string>}>
	 */
	public function get_screen_map() {
		return array(
			self::SCREEN_ACCOUNT       => array(
				'step' => 1,
				'args' => array(),
			),
			self::SCREEN_CREDITS       => array(
				'step' => 1,
				'args' => array( 'recipes' => '1' ),
			),
			self::SCREEN_CHOOSE        => array(
				'step' => 2,
				'args' => array(),
			),
			self::SCREEN_WAITING       => array(
				'step' => 2,
				'args' => array( 'app' => '' ),
			),
			self::SCREEN_AGENT         => array(
				'step' => 2,
				'args' => array( 'agent' => '1' ),
			),
			self::SCREEN_BUILDER       => array(
				'step' => 3,
				'args' => array(),
			),
			self::SCREEN_BUILDER_AGENT => array(
				'step' => 3,
				'args' => array( 'agent' => '1' ),
			),
			self::SCREEN_CONNECTED     => array(
				'step' => 4,
				'args' => array(),
			),
			self::SCREEN_AGENT_TRY     => array(
				'step' => 4,
				'args' => array( 'agent' => '1' ),
			),
			self::SCREEN_SKIPPED       => array(
				'step' => 4,
				'args' => array( 'skipped' => '1' ),
			),
		);
	}

	/**
	 * Read one request value. The only place the wizard touches the request,
	 * so tests can hand it a request of their own.
	 *
	 * @param string $key  Parameter name.
	 * @param int    $type INPUT_GET or INPUT_POST.
	 *
	 * @return string Sanitised value, empty when absent.
	 */
	protected function request_value( $key, $type = INPUT_GET ) {
		return (string) automator_filter_input( $key, $type );
	}

	/**
	 * Read one request flag (`1`, `true`, `yes`, `on`).
	 *
	 * @param string $key  Parameter name.
	 * @param int    $type INPUT_GET or INPUT_POST.
	 *
	 * @return bool
	 */
	protected function request_flag( $key, $type = INPUT_GET ) {
		return (bool) filter_var( $this->request_value( $key, $type ), FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * The screen the query string asks for, before the state is checked.
	 *
	 * @return string
	 */
	public function get_requested_screen() {

		$step    = absint( $this->request_value( 'step' ) );
		$agent   = $this->request_flag( 'agent' );
		$skipped = $this->request_flag( 'skipped' );
		$recipes = $this->request_flag( 'recipes' );
		$app     = $this->get_selected_app();

		switch ( $step ) {
			case 2:
				if ( $agent ) {
					return self::SCREEN_AGENT;
				}

				return '' !== $app ? self::SCREEN_WAITING : self::SCREEN_CHOOSE;
			case 3:
				return $agent ? self::SCREEN_BUILDER_AGENT : self::SCREEN_BUILDER;
			case 4:
				if ( $skipped ) {
					return self::SCREEN_SKIPPED;
				}

				return $agent ? self::SCREEN_AGENT_TRY : self::SCREEN_CONNECTED;
			default:
				return $recipes ? self::SCREEN_CREDITS : self::SCREEN_ACCOUNT;
		}
	}

	/**
	 * The screen the current site state allows for the requested one.
	 *
	 * @param string $requested Requested screen.
	 *
	 * @return string
	 */
	public function resolve_screen( $requested ) {

		$linked = $this->is_step_1_complete();

		switch ( $requested ) {
			case self::SCREEN_ACCOUNT:
				// A linked site skips the account screen unless the customer asked
				// to review it (Back, Not you?).
				$review = $this->request_flag( 'review' );

				return $linked && ! $review ? self::SCREEN_CHOOSE : self::SCREEN_ACCOUNT;

			case self::SCREEN_CREDITS:
				// App credits belong to the free account. Pro's step 1 asks for a license.
				return $this->is_pro_active() ? self::SCREEN_ACCOUNT : self::SCREEN_CREDITS;

			case self::SCREEN_SKIPPED:
				return $requested;

			case self::SCREEN_CHOOSE:
				return $linked ? $requested : self::SCREEN_ACCOUNT;

			case self::SCREEN_AGENT:
				if ( ! $linked ) {
					return self::SCREEN_ACCOUNT;
				}

				return $this->has_agent_access() ? $requested : self::SCREEN_SKIPPED;

			case self::SCREEN_WAITING:
				if ( ! $linked ) {
					return self::SCREEN_ACCOUNT;
				}

				return $this->is_ai_connected() ? self::SCREEN_BUILDER : self::SCREEN_WAITING;

			case self::SCREEN_BUILDER:
			case self::SCREEN_CONNECTED:
				if ( ! $linked ) {
					return self::SCREEN_ACCOUNT;
				}

				if ( ! $this->is_ai_connected() ) {
					$app = $this->get_selected_app();
					if ( '' !== $app && isset( $this->is_ai_connected_cache[ $app ] ) && is_wp_error( $this->is_ai_connected_cache[ $app ] ) ) {
						return self::SCREEN_WAITING;
					}

					return self::SCREEN_CHOOSE;
				}

				return self::SCREEN_BUILDER === $requested && ! $this->can_offer_page_builder() ? self::SCREEN_CONNECTED : $requested;

			case self::SCREEN_BUILDER_AGENT:
			case self::SCREEN_AGENT_TRY:
				if ( ! $linked ) {
					return self::SCREEN_ACCOUNT;
				}

				if ( ! $this->has_agent_access() ) {
					return self::SCREEN_SKIPPED;
				}

				if ( ! $this->is_agent_enabled() ) {
					return self::SCREEN_AGENT;
				}

				return self::SCREEN_BUILDER_AGENT === $requested && ! $this->can_offer_page_builder() ? self::SCREEN_AGENT_TRY : $requested;
		}

		return self::SCREEN_ACCOUNT;
	}

	/**
	 * The screen to render for this request.
	 *
	 * @return string
	 */
	public function get_screen() {
		return $this->resolve_screen( $this->get_requested_screen() );
	}

	/**
	 * Redirects to the allowed screen when the requested one is not.
	 *
	 * @return void
	 */
	public function redirect_to_allowed_screen() {

		if ( ! $this->is_setup_wizard_page() ) {
			return;
		}

		$requested = $this->get_requested_screen();
		$allowed   = $this->resolve_screen( $requested );

		if ( $allowed === $requested ) {
			return;
		}

		// These screens name the chosen app, so a redirect to one keeps it.
		$args = in_array( $allowed, array( self::SCREEN_BUILDER, self::SCREEN_WAITING, self::SCREEN_CONNECTED ), true ) && '' !== $this->get_selected_app()
			? array( 'app' => $this->get_selected_app() )
			: array();

		$this->redirect( $this->get_screen_uri( $allowed, $args ) );
	}

	/**
	 * Retrieves the template path of the current screen.
	 *
	 * Pro replaces `step-1.php` with its license form through the
	 * `automator_setup_wizard_view_path` filter, so the account screen keeps that
	 * file name when Pro is active.
	 *
	 * @return string
	 */
	public function get_screen_view() {

		$screen = $this->get_screen();

		if ( self::SCREEN_ACCOUNT === $screen && $this->is_pro_active() ) {
			return $this->get_view_path() . 'step-1.php';
		}

		$views = array(
			self::SCREEN_ACCOUNT       => 'account.php',
			self::SCREEN_CREDITS       => 'credits.php',
			self::SCREEN_CHOOSE        => 'choose.php',
			self::SCREEN_WAITING       => 'waiting.php',
			self::SCREEN_AGENT         => 'agent.php',
			self::SCREEN_BUILDER       => 'builder.php',
			self::SCREEN_BUILDER_AGENT => 'builder.php',
			self::SCREEN_CONNECTED     => 'try-it.php',
			self::SCREEN_AGENT_TRY     => 'try-it.php',
			self::SCREEN_SKIPPED       => 'skipped.php',
		);

		return $this->get_view_path() . $views[ $screen ];
	}

	/**
	 * Whether the current screen belongs to the Uncanny Agent path.
	 *
	 * @return bool
	 */
	public function is_agent_path() {
		return in_array( $this->get_screen(), array( self::SCREEN_AGENT, self::SCREEN_BUILDER_AGENT, self::SCREEN_AGENT_TRY ), true );
	}

	/**
	 * Whether the current screen is one of the two Try it screens.
	 *
	 * @return bool
	 */
	public function is_try_it_screen() {
		return in_array( $this->get_screen(), array( self::SCREEN_CONNECTED, self::SCREEN_AGENT_TRY ), true );
	}

	/*
	|--------------------------------------------------------------------------
	| Stepper
	|--------------------------------------------------------------------------
	*/

	/**
	 * Retrieves the step number of the current screen.
	 *
	 * @return int
	 */
	public function get_step_number() {

		$map = $this->get_screen_map();

		return (int) $map[ $this->get_screen() ]['step'];
	}

	/**
	 * Retrieves the current step (`step-N`) for the card class.
	 *
	 * @return string
	 */
	public function get_step() {
		return sprintf( 'step-%d', $this->get_step_number() );
	}

	/**
	 * The number of steps in the stepper.
	 *
	 * @return int
	 */
	public function get_total_steps() {
		return 4;
	}

	/**
	 * The four stepper labels with their state for the current screen.
	 *
	 * @return array<int,array{label:string,state:string,tag:string}>
	 */
	public function get_stepper() {

		/**
		 * The four stepper labels. Pro renames the first one, since its step 1
		 * activates a license instead of creating an account.
		 *
		 * @param string[] $labels Labels in step order.
		 */
		$labels = (array) apply_filters(
			'automator_setup_wizard_step_labels',
			array(
				esc_html_x( 'Create account', 'Setup wizard step', 'uncanny-automator' ),
				esc_html_x( 'Connect your AI', 'Setup wizard step', 'uncanny-automator' ),
				esc_html_x( 'Page Builder', 'Setup wizard step', 'uncanny-automator' ),
				esc_html_x( 'Try it', 'Setup wizard step', 'uncanny-automator' ),
			)
		);

		$screen  = $this->get_screen();
		$current = $this->get_step_number();
		$steps   = array();

		foreach ( $labels as $index => $label ) {
			$number = $index + 1;
			$state  = '';

			// The skipped screen reaches step 4 without doing the others.
			if ( $number === $current ) {
				$state = 'now';
			} elseif ( $number < $current && self::SCREEN_SKIPPED !== $screen ) {
				$state = 'done';
			}

			if ( 'done' === $state ) {
				$tag = esc_html_x( 'Done', 'Setup wizard step state', 'uncanny-automator' );
			} elseif ( 'now' === $state ) {
				$tag = esc_html_x( 'Now', 'Setup wizard step state', 'uncanny-automator' );
			} else {
				/* translators: %d: Step number. */
				$tag = sprintf( esc_html_x( 'Step %d', 'Setup wizard step state', 'uncanny-automator' ), $number );
			}

			$steps[] = array(
				'label' => $label,
				'state' => $state,
				'tag'   => $tag,
			);
		}

		return $steps;
	}

	/**
	 * Retrieves the steps in the shape Pro's license view loops over.
	 *
	 * @return array
	 */
	public function get_steps() {

		$steps        = array();
		$current_step = $this->get_step_number();

		for ( $i = 1; $i <= $this->get_total_steps(); $i++ ) {
			$steps[ $i ] = array(
				'label'   => esc_html( $i ),
				'classes' => array( sprintf( $i ) ),
			);
			if ( $current_step === $i ) {
				$steps[ $i ]['classes'][] = 'active';
			}
		}

		return (array) $steps;
	}

	/*
	|--------------------------------------------------------------------------
	| URLs
	|--------------------------------------------------------------------------
	*/

	/**
	 * Retrieves the URL of a screen.
	 *
	 * @param string               $screen Screen identifier.
	 * @param array<string,string> $args   Extra query arguments.
	 *
	 * @return string
	 */
	public function get_screen_uri( $screen, $args = array() ) {

		$map = $this->get_screen_map();

		if ( ! isset( $map[ $screen ] ) ) {
			$screen = self::SCREEN_ACCOUNT;
		}

		$query = array_merge(
			array(
				'post_type' => AUTOMATOR_POST_TYPE_RECIPE,
				'page'      => self::PAGE_SLUG,
				'step'      => $map[ $screen ]['step'],
			),
			array_filter( $map[ $screen ]['args'], 'strlen' ),
			$args
		);

		return add_query_arg( $query, admin_url( 'edit.php' ) );
	}

	/**
	 * Retrieves the wizard URL by step number.
	 *
	 * Kept for Pro's license view and the recipe builder hand-off.
	 *
	 * @param int  $step      Wizard step number.
	 * @param bool $is_method Whether the URL starts an account connection.
	 *
	 * @return string
	 */
	public function get_dashboard_uri( $step = 1, $is_method = false ) {

		$args = array(
			'post_type' => AUTOMATOR_POST_TYPE_RECIPE,
			'page'      => self::PAGE_SLUG,
			'state'     => wp_create_nonce( 'automator_setup_wizard_redirect_nonce' ),
			'step'      => absint( $step ),
		);

		if ( $is_method ) {
			$args['method'] = 'connect';
		}

		return add_query_arg(
			$args,
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Retrieves the connect button URL.
	 *
	 * The store sends the customer back to `redirect_url` once the account is
	 * linked: the Choose your AI screen, or the Skipped screen for a customer who
	 * is here to build recipes by hand.
	 *
	 * Built through Account_Connect_Url, which encodes the return address. The
	 * store reads `state` and `redirect_url` as query parameters, so an unencoded
	 * return address replaces the secret with its own `state` and is cut short at
	 * its first `&`, and the store falls back to a Dashboard URL without the nonce.
	 *
	 * @param string $return_screen Screen to come back to.
	 *
	 * @return string
	 */
	public function get_connect_button_uri( $return_screen = self::SCREEN_CHOOSE ) {

		return ( new Account_Connect_Url() )->returning_to(
			$this->get_screen_uri(
				$return_screen,
				array(
					'state'  => wp_create_nonce( 'automator_setup_wizard_redirect_nonce' ),
					'method' => 'connect',
				)
			)
		);
	}

	/**
	 * Retrieves the hosted app URL where an AI app is connected: the same link
	 * as every other "Connect your AI" entry, saying which step opened it and
	 * which AI it was opened for.
	 *
	 * @param string $app AI app key; '' when none is selected.
	 *
	 * @return string
	 */
	public function get_ai_connect_uri( $app = '' ) {
		return Hosted_App::connect_url(
			'wizard_step_' . $this->get_step_number(),
			'' !== $app ? array( 'selected_ai' => $app ) : array()
		);
	}

	/**
	 * The hosted app's address as the wizard's text names it: where its links
	 * go, without the scheme.
	 *
	 * @return string
	 */
	public function get_hosted_app_address() {
		return (string) preg_replace( '#^https?://#', '', Hosted_App::base_url() );
	}

	/**
	 * Retrieves the dashboard URL.
	 *
	 * @return string
	 */
	public function get_automator_dashboard_uri() {
		return add_query_arg(
			array(
				'post_type' => AUTOMATOR_POST_TYPE_RECIPE,
				'page'      => 'uncanny-automator-dashboard',
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Retrieves the dashboard URL that opens Uncanny Agent.
	 *
	 * @return string
	 */
	public function get_automator_dashboard_agent_uri() {

		return add_query_arg( 'automator_open_agent', '1', $this->get_automator_dashboard_uri() );
	}

	/**
	 * Retrieves the new-recipe URL.
	 *
	 * @return string
	 */
	public function get_new_recipe_uri() {
		return add_query_arg( 'post_type', AUTOMATOR_POST_TYPE_RECIPE, admin_url( 'post-new.php' ) );
	}

	/**
	 * Retrieves the app integrations list URL.
	 *
	 * @return string
	 */
	public function get_app_integrations_uri() {
		return 'https://automatorplugin.com/integrations/?utm_source=uncanny-automator&utm_medium=in-plugin&utm_content=setup-wizard';
	}

	/**
	 * Get the signed Page Builder page creation URL.
	 *
	 * @return string
	 */
	public function get_page_builder_create_uri() {

		return add_query_arg(
			array(
				'action'   => 'uncanny_page_builder_create_page',
				'_wpnonce' => wp_create_nonce( 'uncanny_page_builder_create_page' ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/*
	|--------------------------------------------------------------------------
	| AI apps
	|--------------------------------------------------------------------------
	*/

	/**
	 * The AI apps offered on Choose your AI, in display order.
	 *
	 * `brand` is an icon brand for `<uap-d-ai-app-logo>`, which draws a plug for
	 * an app without one; `icon` is an image URL for apps it has no brand for.
	 *
	 * @return array<string,array{label:string,open_url:string,brand?:string,icon?:string,sub?:string}>
	 */
	public function get_ai_apps() {

		$apps = array(
			'chatgpt'    => array(
				'label'    => 'ChatGPT',
				'open_url' => 'https://chatgpt.com/',
				'brand'    => 'openai',
			),
			'claude'     => array(
				'label'    => 'Claude',
				'open_url' => 'https://claude.ai/new',
				'brand'    => 'claude',
			),
			'gemini'     => array(
				'label'    => 'Gemini',
				'open_url' => 'https://gemini.google.com/',
				'brand'    => 'gemini',
			),
			'copilot'    => array(
				'label'    => 'Copilot',
				'open_url' => 'https://copilot.microsoft.com/',
				'brand'    => 'microsoft',
			),
			'perplexity' => array(
				'label'    => 'Perplexity',
				'open_url' => 'https://www.perplexity.ai/',
				'icon'     => plugins_url( 'src/integrations/perplexity/img/perplexity-icon.svg', AUTOMATOR_BASE_FILE ),
			),
			'other'      => array(
				'label'    => esc_html_x( 'Another app', 'Setup wizard AI app', 'uncanny-automator' ),
				'sub'      => esc_html_x( 'Cursor and other MCP apps', 'Setup wizard AI app', 'uncanny-automator' ),
				'open_url' => '',
			),
		);

		return (array) apply_filters( 'automator_setup_wizard_ai_apps', $apps );
	}

	/**
	 * The AI app picked on Choose your AI, from the `app` query parameter.
	 *
	 * @return string Empty when none or unknown.
	 */
	public function get_selected_app() {

		$app = sanitize_key( $this->request_value( 'app' ) );

		return isset( $this->get_ai_apps()[ $app ] ) ? $app : '';
	}

	/**
	 * How the wizard names the chosen app in a sentence.
	 *
	 * @param string $app AI app key.
	 *
	 * @return string
	 */
	public function get_app_name( $app ) {

		$apps = $this->get_ai_apps();

		if ( '' === $app || 'other' === $app || ! isset( $apps[ $app ] ) ) {
			return esc_html_x( 'your AI', 'Setup wizard generic AI app name', 'uncanny-automator' );
		}

		return (string) $apps[ $app ]['label'];
	}

	/**
	 * The URL that opens the chosen app, or an empty string for "Another app".
	 *
	 * @param string $app AI app key.
	 *
	 * @return string
	 */
	public function get_app_open_uri( $app ) {

		$apps = $this->get_ai_apps();

		return isset( $apps[ $app ]['open_url'] ) ? (string) $apps[ $app ]['open_url'] : '';
	}

	/*
	|--------------------------------------------------------------------------
	| State
	|--------------------------------------------------------------------------
	*/

	/**
	 * Determines whether the user is connected or not.
	 *
	 * @return bool
	 */
	public function is_user_connected() {

		if ( null !== $this->is_user_connected_cache ) {
			return $this->is_user_connected_cache;
		}

		// Pull data from the licensing server on the wizard itself: the customer
		// may have just come back from linking the account.
		if ( $this->is_setup_wizard_page() ) {
			$this->is_user_connected_cache = (bool) Api_Server::is_automator_connected( true );

			return $this->is_user_connected_cache;
		}

		// Otherwise pull data from local db to avoid multiple calls.
		$this->is_user_connected_cache = ! empty( Api_Server::get_license_key() );

		return $this->is_user_connected_cache;
	}

	/**
	 * Determines whether the user has done what step 1 asks of them.
	 *
	 * Use the same locally validated license key as hosted Agent consent. A
	 * connected free account is sufficient even when Pro is installed.
	 *
	 * @return bool
	 */
	public function is_step_1_complete() {

		return '' !== sanitize_text_field( ( new License_Service() )->get_license_key() );
	}

	/**
	 * The e-mail of the linked account, for the "Signed in as" pill.
	 *
	 * @return string Empty when unknown.
	 */
	public function get_account_email() {

		try {
			$license = automator_license_manager()->get_license_data();
		} catch ( Throwable $error ) {
			unset( $error );

			return '';
		}

		if ( is_array( $license ) && ! empty( $license['customer_email'] ) ) {
			return (string) $license['customer_email'];
		}

		return '';
	}

	/**
	 * Determine whether Automator Pro is active.
	 *
	 * @return bool
	 */
	public function is_pro_active() {
		return defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' );
	}

	/**
	 * Whether the AI app the customer picked is confirmed connected to this site.
	 *
	 * AI apps connect to the hosted Automator MCP app, which lists each one with
	 * its brand. This site's approval of the hosted app only shows that someone
	 * signed in there, not which app connected, so the wizard asks the hosted
	 * app. The list comes through live hosted sign-ins only.
	 * Use get_ai_connection_status() to distinguish an unavailable read from a
	 * confirmed absence.
	 *
	 * @param string|null $app AI app key; null reads the request. '' accepts any app.
	 *
	 * @return bool
	 */
	public function is_ai_connected( $app = null ) {
		return true === $this->get_ai_connection_status( $app );
	}

	/**
	 * Check whether the selected app is connected without guessing on a failed read.
	 *
	 * @since 8.0.0
	 *
	 * @param string|null $app AI app key; null reads the request. '' accepts any app.
	 * @return bool|WP_Error Whether the app is connected, or an unavailable status.
	 */
	public function get_ai_connection_status( $app = null ) {

		$app = null === $app ? $this->get_selected_app() : (string) $app;

		if ( isset( $this->is_ai_connected_cache[ $app ] ) ) {
			return $this->is_ai_connected_cache[ $app ];
		}

		try {
			$connected = $this->read_ai_connection( $app );
		} catch ( Throwable $error ) {
			$connected = new WP_Error( 'connection_status_unavailable', 'The AI connection could not be checked.' );
		}

		if ( is_wp_error( $connected ) ) {
			$this->is_ai_connected_cache[ $app ] = $connected;

			return $connected;
		}

		/**
		 * Whether the wizard treats an AI as connected to this site.
		 *
		 * @param bool         $connected The hosted app lists a connection of the app.
		 * @param Setup_Wizard $wizard    The wizard.
		 * @param string       $app       The AI app the customer picked, or '' for any.
		 */
		$this->is_ai_connected_cache[ $app ] = (bool) apply_filters( 'automator_setup_wizard_ai_connected', $connected, $this, $app );

		return $this->is_ai_connected_cache[ $app ];
	}

	/**
	 * Whether the hosted app lists a connection of the AI app.
	 *
	 * An unreadable list leaves the connection state unknown.
	 *
	 * @param string $app AI app key, or '' for any.
	 *
	 * @return bool|WP_Error
	 */
	protected function read_ai_connection( $app ) {

		$connections = $this->hosted_ai_apps();

		if ( is_wp_error( $connections ) ) {
			return $connections;
		}

		if ( ! is_array( $connections ) ) {
			return new WP_Error( 'connection_status_unavailable', 'The AI connection could not be checked.' );
		}

		foreach ( $connections as $connection ) {
			if ( $this->is_connection_of( $app, (string) ( $connection['vendor'] ?? '' ), $connection['brand'] ?? null ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a hosted connection is the AI app.
	 *
	 * ChatGPT, Claude and Gemini match their vendor; Copilot and Perplexity,
	 * which have none, match their brand. Another app, or an app a filter
	 * added, is any app the screen does not name.
	 *
	 * @param string      $app    AI app key, or '' for any.
	 * @param string      $vendor The vendor the hosted service reported.
	 * @param string|null $brand  The brand the hosted service reported.
	 *
	 * @return bool
	 */
	private function is_connection_of( $app, $vendor, $brand ) {

		if ( '' === $app ) {
			return true;
		}

		if ( isset( self::HOSTED_VENDORS[ $app ] ) ) {
			return self::HOSTED_VENDORS[ $app ] === $vendor;
		}

		if ( isset( self::HOSTED_BRANDS[ $app ] ) ) {
			return self::HOSTED_BRANDS[ $app ] === $brand;
		}

		return ! in_array( $vendor, self::HOSTED_VENDORS, true ) && ! in_array( $brand, self::HOSTED_BRANDS, true );
	}

	/**
	 * The vendor and brand of each AI app the hosted service lists for this site.
	 *
	 * @return array<int,array{vendor:string,brand:?string}>|\WP_Error
	 */
	protected function hosted_ai_apps() {
		return Hosted_Ai_Dashboard::instance()->app_kinds();
	}

	/**
	 * Whether Uncanny Agent is switched on in its settings.
	 *
	 * @return bool
	 */
	public function is_agent_enabled() {
		return (bool) Admin_Settings_Uncanny_Agent_General::get_setting( Admin_Settings_Uncanny_Agent_General::ENABLED_KEY, true );
	}

	/**
	 * Whether the Uncanny Agent setting was ever saved.
	 *
	 * The offer preselects Yes until a choice exists, since the default is off.
	 *
	 * @return bool
	 */
	public function is_agent_setting_saved() {
		return null !== automator_get_option( Admin_Settings_Uncanny_Agent_General::OPTION_NAME, null, true );
	}

	/**
	 * Whether Uncanny Page Builder is switched on in its settings.
	 *
	 * @return bool
	 */
	public function is_page_builder_enabled() {
		return ( new Page_Builder_Settings() )->is_enabled( true );
	}

	/**
	 * Whether the Uncanny Page Builder setting was ever saved.
	 *
	 * @return bool
	 */
	public function is_page_builder_setting_saved() {
		return null !== automator_get_option( Page_Builder_Settings::OPTION_NAME, null, true );
	}

	/**
	 * Whether WooCommerce is active, for the store prompts.
	 *
	 * @return bool
	 */
	public function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Determine whether the wizard can show the Uncanny Agent option.
	 *
	 * @return bool
	 */
	public function has_agent_access() {
		// The wizard button follows the launcher column, not settings-tab access.
		return $this->feature_is_visible( Feature_State::AGENT_LAUNCHER_TAB );
	}

	/**
	 * Determine whether the wizard can offer to switch Page Builder on.
	 *
	 * The policy alone decides, by the menu column Can_Load_Page_Builder reads.
	 * The runtime check would always refuse: 8.0 starts Page Builder off, and
	 * its module runs only once the setting is on.
	 *
	 * @return bool
	 */
	public function can_offer_page_builder() {
		return $this->feature_is_visible( Feature_State::PAGE_BUILDER_MENU );
	}

	/**
	 * Determine whether the current site can start a Page Builder page.
	 *
	 * The policy and the runtime must both permit new Page Builder pages.
	 *
	 * @return bool
	 */
	public function has_page_builder_access() {

		// The wizard button is a new-page affordance and therefore follows the
		// Page Builder menu column before applying runtime readiness checks.
		if ( ! $this->can_offer_page_builder() ) {
			return false;
		}

		try {
			return $this->page_builder_is_available();
		} catch ( Throwable $error ) {
			unset( $error );

			return false;
		}
	}

	/**
	 * Read Page Builder readiness for new-page creation.
	 *
	 * @return bool
	 */
	protected function page_builder_is_available() {

		return $this->is_page_builder_enabled()
			&& false !== has_action( 'admin_post_uncanny_page_builder_create_page' );
	}

	/**
	 * Check if the current request is for the setup wizard.
	 *
	 * @return bool
	 */
	protected function is_setup_wizard_page() {
		return self::PAGE_SLUG === $this->request_value( 'page' );
	}

	/**
	 * Get the visibility of one feature.
	 *
	 * @param string $feature Feature-state key.
	 *
	 * @return bool
	 */
	protected function feature_is_visible( $feature ) {
		try {
			return automator_feature_state_query()->execute()->is_visible( $feature );
		} catch ( Throwable $error ) {
			unset( $error );

			return false;
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Writes
	|--------------------------------------------------------------------------
	*/

	/**
	 * Saves the Uncanny Agent choice and moves on.
	 *
	 * Yes goes on to Page Builder; No ends the wizard on the Skipped screen.
	 *
	 * @return void
	 */
	public function handle_agent_choice() {

		$this->verify_choice_request();

		// A policy that hides Uncanny Agent leaves its settings alone.
		if ( ! $this->has_agent_access() ) {
			$this->redirect( $this->get_screen_uri( self::SCREEN_SKIPPED ) );

			return;
		}

		$enabled = 'yes' === $this->request_value( 'choice', INPUT_POST );

		$this->save_agent_enabled( $enabled );

		$this->redirect( $this->get_screen_uri( $enabled ? self::SCREEN_BUILDER_AGENT : self::SCREEN_SKIPPED ) );
	}

	/**
	 * Saves the Uncanny Page Builder choice and moves on to Try it.
	 *
	 * @return void
	 */
	public function handle_page_builder_choice() {

		$this->verify_choice_request();

		$enabled    = 'yes' === $this->request_value( 'choice', INPUT_POST );
		$agent_path = $this->request_flag( 'agent', INPUT_POST );
		$app        = sanitize_key( $this->request_value( 'app', INPUT_POST ) );

		// A policy that hides Page Builder leaves its setting alone.
		if ( $this->can_offer_page_builder() ) {
			$this->save_page_builder_enabled( $enabled );
		}

		$args = '' !== $app && isset( $this->get_ai_apps()[ $app ] ) ? array( 'app' => $app ) : array();

		$this->redirect( $this->get_screen_uri( $agent_path ? self::SCREEN_AGENT_TRY : self::SCREEN_CONNECTED, $args ) );
	}

	/**
	 * Writes the Uncanny Agent settings: both launchers on, or both off.
	 *
	 * Choosing Uncanny Agent in the wizard means the whole feature, so the
	 * launcher tab and the admin-bar button follow the one answer.
	 *
	 * @param bool $enabled Whether Uncanny Agent is on.
	 *
	 * @return void
	 */
	public function save_agent_enabled( $enabled ) {

		automator_update_option(
			Admin_Settings_Uncanny_Agent_General::OPTION_NAME,
			array(
				Admin_Settings_Uncanny_Agent_General::ENABLED_KEY                => (bool) $enabled,
				Admin_Settings_Uncanny_Agent_General::TOP_BAR_BUTTON_ENABLED_KEY => (bool) $enabled,
			)
		);
	}

	/**
	 * Writes the Uncanny Page Builder setting.
	 *
	 * @param bool $enabled Whether new Page Builder pages are available.
	 *
	 * @return void
	 */
	public function save_page_builder_enabled( $enabled ) {
		( new Page_Builder_Settings() )->update_enabled( (bool) $enabled );
	}

	/**
	 * Stops a choice request that is not an administrator's own.
	 *
	 * @return void
	 */
	protected function verify_choice_request() {

		if ( ! current_user_can( automator_get_admin_capability() ) ) { // phpcs:ignore WordPress.WP.Capabilities.Undetermined
			wp_die( esc_html__( 'You are not allowed to change this setting.', 'uncanny-automator' ), 403 );
		}

		if ( ! wp_verify_nonce( $this->request_value( '_wpnonce', INPUT_POST ), self::CHOICE_NONCE ) ) {
			wp_die( esc_html__( 'This request has expired. Go back and try again.', 'uncanny-automator' ), 403 );
		}
	}

	/**
	 * Redirects and stops. Separate so tests can observe the destination.
	 *
	 * @param string $url Destination.
	 *
	 * @return void
	 */
	protected function redirect( $url ) {
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * AJAX: whether an AI is connected to this site. Polled by the waiting screen.
	 *
	 * Registered from Automator_Load during AJAX requests, where the wizard is
	 * not otherwise instantiated.
	 *
	 * @return void
	 */
	public static function ajax_connection_status() {

		if ( ! current_user_can( automator_get_admin_capability() ) ) { // phpcs:ignore WordPress.WP.Capabilities.Undetermined
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}

		if ( ! check_ajax_referer( self::STATUS_NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => 'invalid_nonce' ), 403 );
		}

		$wizard = new self();
		$app    = sanitize_key( $wizard->request_value( 'app', INPUT_POST ) );

		$status = $wizard->get_ai_connection_status( isset( $wizard->get_ai_apps()[ $app ] ) ? $app : '' );

		if ( is_wp_error( $status ) ) {
			$forbidden = 'rest_forbidden' === $status->get_error_code();
			wp_send_json_error( array( 'message' => $forbidden ? 'forbidden' : 'connection_status_unavailable' ), $forbidden ? 403 : 503 );
		}

		wp_send_json_success( array( 'connected' => $status ) );
	}
}
