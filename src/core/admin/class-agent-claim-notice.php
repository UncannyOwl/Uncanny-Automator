<?php
/**
 * Agent claim notice.
 *
 * Site-wide, per-user dismissible admin notice about the free Uncanny Agent
 * usage included with Uncanny Automator Lite.
 *
 * Two audiences see different messages. A site with a connected free account
 * has an allocation waiting to be claimed. A site with no account has nothing
 * to claim yet, so it is pointed at the setup wizard instead — telling those
 * users their "account" has an update would describe an account they do not
 * have.
 *
 * @package Uncanny_Automator
 * @since   7.6
 */

namespace Uncanny_Automator;

use Uncanny_Automator\App\Infrastructure\License\License_Manager;

use function Uncanny_Automator\App\Infrastructure\automator_license_manager;

/**
 * Class Agent_Claim_Notice
 *
 * @package Uncanny_Automator
 */
class Agent_Claim_Notice {

	/**
	 * Prefix for the user meta storing a per-user, per-message dismissal.
	 *
	 * The two messages are different offers, so dismissing one must not
	 * silence the other. Someone who dismisses "connect an account" and later
	 * connects anyway still needs to hear that usage is waiting to be claimed.
	 *
	 * @var string
	 */
	const USER_META_DISMISSED_PREFIX = 'automator_agent_claim_notice_dismissed_';

	/**
	 * Message shown to a site with no connected account.
	 *
	 * @var string
	 */
	const VARIANT_CONNECT = 'connect';

	/**
	 * Message shown to a connected account with an unclaimed allocation.
	 *
	 * @var string
	 */
	const VARIANT_CLAIM = 'claim';

	/**
	 * Prefix for the user meta holding a temporary snooze expiry.
	 *
	 * Separate from the permanent dismissal so that "Dismiss forever" keeps
	 * meaning exactly that, and a snooze can never be mistaken for one.
	 *
	 * @var string
	 */
	const USER_META_SNOOZED_PREFIX = 'automator_agent_claim_notice_snoozed_';

	/**
	 * How long a snooze lasts.
	 *
	 * Comfortably longer than the 12-hour license cache, so a site that has
	 * just claimed never sees the notice again: the refreshed ledger takes
	 * over before the snooze lapses.
	 *
	 * @var int
	 */
	const SNOOZE_SECONDS = DAY_IN_SECONDS;

	/**
	 * Suppression that never lapses. Written by the X and "Dismiss forever".
	 *
	 * @var string
	 */
	const MODE_FOREVER = 'forever';

	/**
	 * Suppression that lapses. Written by the claim call to action only.
	 *
	 * @var string
	 */
	const MODE_SNOOZE = 'snooze';

	/**
	 * The admin-ajax action used to persist a dismissal.
	 *
	 * @var string
	 */
	const AJAX_ACTION = 'automator_dismiss_agent_notice';

	/**
	 * The nonce action guarding the dismissal request.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'automator_agent_claim_notice';

	/**
	 * The admin-post action backing the no-JavaScript dismissal link.
	 *
	 * @var string
	 */
	const POST_ACTION = 'automator_agent_claim_notice_dismiss';

	/**
	 * Where a connected account claims its waiting allocation.
	 *
	 * @var string
	 */
	const REGISTRATION_URL = 'https://automatorplugin.com/uncanny-agent-lite-registration/';

	/**
	 * Guards against rendering twice on a single request.
	 *
	 * @var bool
	 */
	private $rendered = false;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->register_hooks();
	}

	/**
	 * Registers the hooks required for the notice to appear across wp-admin.
	 *
	 * Automator strips every `admin_notices` callback on its own screens
	 * (Automator_Review::hide_all_admin_notices_on_automator_pages) and then
	 * fires `automator_show_internal_admin_notice` in its place, so the notice
	 * is registered on both paths to survive everywhere.
	 *
	 * @return void
	 */
	private function register_hooks() {

		add_action( 'admin_notices', array( $this, 'render' ) );

		add_action( 'automator_show_internal_admin_notice', array( $this, 'restore_notice' ) );

		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'ajax_dismiss' ) );

		// Plain-link fallback so "Dismiss forever" still works without JS.
		add_action( 'admin_post_' . self::POST_ACTION, array( $this, 'handle_dismiss_request' ) );
	}

	/**
	 * Re-registers the notice after Automator clears the `admin_notices` hook.
	 *
	 * Callback for `automator_show_internal_admin_notice`, which fires on
	 * `admin_head` — too early to echo markup, so this only re-adds the
	 * renderer at its proper render point.
	 *
	 * @return void
	 */
	public function restore_notice() {

		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Renders the notice when every gate passes.
	 *
	 * @return void
	 */
	public function render() {

		if ( true === $this->rendered ) {
			return;
		}

		if ( ! $this->should_render() ) {
			return;
		}

		$this->rendered = true;

		// Consumed by the view.
		$variant       = $this->get_variant();
		$dismiss_url   = $this->get_dismiss_url();
		$ajax_action   = self::AJAX_ACTION;
		$ajax_url      = admin_url( 'admin-ajax.php' );
		$dismiss_nonce = wp_create_nonce( self::NONCE_ACTION );
		$mode_forever  = self::MODE_FOREVER;
		$mode_snooze   = self::MODE_SNOOZE;

		include Utilities::automator_get_view( 'agent-claim-notice.php' );
	}

	/**
	 * Determines whether the notice should be shown on this request.
	 *
	 * @return bool
	 */
	public function should_render() {

		if ( ! current_user_can( automator_get_capability() ) ) {
			return false;
		}

		// The copy speaks to the Lite plan; Pro carries its own allocation.
		if ( is_automator_pro_active() ) {
			return false;
		}

		if ( $this->is_dismissed() ) {
			return false;
		}

		if ( $this->is_snoozed() ) {
			return false;
		}

		// Only a definitive "not claimed" shows the notice. An unknown state
		// (cold license cache) is skipped rather than guessed at.
		return false === $this->has_claimed_usage();
	}

	/**
	 * Selects which audience this site belongs to, and where its button goes.
	 *
	 * A connected free account has an allocation waiting to be claimed, so it
	 * gets the account-update message and the external claim form. A site with
	 * no account has nothing to claim yet and is sent to the setup wizard.
	 *
	 * @return array{connected: bool, key: string, url: string}
	 */
	public function get_variant() {

		$connected = $this->is_connected();

		return array(
			'connected' => $connected,
			'key'       => $this->get_variant_key(),
			'url'       => $connected ? $this->get_claim_url() : $this->get_setup_wizard_url(),
		);
	}

	/**
	 * Checks whether the current user has already dismissed the notice.
	 *
	 * @return bool
	 */
	private function is_dismissed() {

		$dismissed = get_user_meta( get_current_user_id(), $this->get_meta_key( $this->get_variant_key() ), true );

		return ! empty( $dismissed );
	}

	/**
	 * Checks whether this message is temporarily hidden.
	 *
	 * @return bool
	 */
	private function is_snoozed() {

		$until = (int) get_user_meta( get_current_user_id(), $this->get_snooze_key( $this->get_variant_key() ), true );

		return $until > time();
	}

	/**
	 * Identifies which of the two messages applies to this site.
	 *
	 * @return string
	 */
	private function get_variant_key() {

		return $this->is_connected() ? self::VARIANT_CLAIM : self::VARIANT_CONNECT;
	}

	/**
	 * Maps a message to its user meta key.
	 *
	 * @param string $variant One of the VARIANT_* identifiers.
	 *
	 * @return string
	 */
	private function get_meta_key( $variant ) {

		return self::USER_META_DISMISSED_PREFIX . $variant;
	}

	/**
	 * Maps a message to the user meta key holding its snooze expiry.
	 *
	 * @param string $variant One of the VARIANT_* identifiers.
	 *
	 * @return string
	 */
	private function get_snooze_key( $variant ) {

		return self::USER_META_SNOOZED_PREFIX . $variant;
	}

	/**
	 * Validates a submitted message identifier.
	 *
	 * The value reaches a meta key, so it is matched against the known
	 * identifiers rather than sanitised and trusted.
	 *
	 * @param string $variant The submitted value.
	 *
	 * @return string|null The identifier, or null when unrecognised.
	 */
	private function resolve_variant( $variant ) {

		$allowed = array( self::VARIANT_CONNECT, self::VARIANT_CLAIM );

		return in_array( $variant, $allowed, true ) ? $variant : null;
	}

	/**
	 * Checks whether the site holds a usable license key.
	 *
	 * Reads options only — no API call, and no cached payload required.
	 *
	 * @return bool
	 */
	private function is_connected() {

		$license_manager = automator_license_manager();

		if ( ! $license_manager instanceof License_Manager ) {
			return false;
		}

		return '' !== $license_manager->get_key();
	}

	/**
	 * Resolves whether the site has already claimed its free Agent usage.
	 *
	 * Mirrors the dashboard's `llm_credits.has_data` signal, but reads the
	 * cached license payload directly. License_Manager::get_license_data()
	 * falls through to a live API call on a cold transient, which must never
	 * happen from a notice that runs on every admin page load.
	 *
	 * @return bool|null True if claimed, false if definitively not, null if unknown.
	 */
	private function has_claimed_usage() {

		// No license key means the site was never connected, so there is no
		// account that could hold an allocation. No API call is made.
		if ( ! $this->is_connected() ) {
			return false;
		}

		$license = $this->get_cached_license();

		if ( null === $license ) {
			return null;
		}

		$ledger = isset( $license['llm_credits'] ) ? (array) $license['llm_credits'] : array();

		return ! empty( $ledger['success'] );
	}

	/**
	 * Reads the cached license payload without ever triggering a remote fetch.
	 *
	 * @return array|null The cached payload, or null when the cache is cold.
	 */
	private function get_cached_license() {

		$license = get_transient( License_Manager::TRANSIENT_LICENSE );

		return is_array( $license ) ? $license : null;
	}

	/**
	 * Builds the claim URL, prefilling the connected account's email.
	 *
	 * @return string
	 */
	private function get_claim_url() {

		$url   = self::REGISTRATION_URL;
		$email = $this->get_account_email();

		if ( '' !== $email ) {
			// Pre-encoded so a plus-addressed email survives the parse_str()
			// round trip inside automator_utm_parameters().
			$url = add_query_arg( 'email', rawurlencode( $email ), $url );
		}

		return automator_utm_parameters( $url, 'admin-notice', 'claim-agent-usage' );
	}

	/**
	 * Builds the in-plugin setup wizard link used when no account exists.
	 *
	 * The wizard registers its submenu on a direct hit even when hidden from
	 * the Recipes menu, so this deep link survives a dismissed wizard.
	 *
	 * @return string
	 */
	private function get_setup_wizard_url() {

		return add_query_arg(
			array(
				'post_type' => AUTOMATOR_POST_TYPE_RECIPE,
				'page'      => 'uncanny-automator-setup-wizard',
				'state'     => wp_create_nonce( 'automator_setup_wizard_redirect_nonce' ),
				'step'      => 1,
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Resolves the connected account email used to prefill the claim form.
	 *
	 * Only the account's own address is used. The current administrator's
	 * WordPress email is deliberately not substituted — it is a guess, and an
	 * unconnected site is routed to the setup wizard instead.
	 *
	 * @return string
	 */
	private function get_account_email() {

		$license = $this->get_cached_license();

		if ( null !== $license && ! empty( $license['customer_email'] ) ) {
			return (string) $license['customer_email'];
		}

		return '';
	}

	/**
	 * Builds the nonced URL behind the "Dismiss forever" button.
	 *
	 * A real link, not a JavaScript hook, so the permanent opt-out still works
	 * when scripts are blocked. JavaScript intercepts it for an in-place
	 * dismissal when it can.
	 *
	 * @return string
	 */
	private function get_dismiss_url() {

		return wp_nonce_url(
			add_query_arg(
				array(
					'action'  => self::POST_ACTION,
					'variant' => $this->get_variant_key(),
				),
				admin_url( 'admin-post.php' )
			),
			self::NONCE_ACTION
		);
	}

	/**
	 * Persists the per-user dismissal.
	 *
	 * @return void
	 */
	public function ajax_dismiss() {

		if ( ! wp_verify_nonce( automator_request_input( 'nonce' ), self::NONCE_ACTION ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce.' ), 403 );
		}

		// The nonce proves intent, not authority. Suppression writes user
		// meta, so the capability is checked independently.
		if ( ! current_user_can( automator_get_capability() ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
		}

		$variant = $this->resolve_variant( automator_request_input( 'variant' ) );

		if ( null === $variant ) {
			wp_send_json_error( array( 'message' => 'Unknown message.' ), 400 );
		}

		$mode = automator_request_input( 'mode' );

		// Both modes are named explicitly. A malformed request must not fall
		// through to the permanent one.
		if ( self::MODE_SNOOZE === $mode ) {
			$this->snooze_for_current_user( $variant );
			wp_send_json_success();
		}

		if ( self::MODE_FOREVER !== $mode ) {
			wp_send_json_error( array( 'message' => 'Unknown mode.' ), 400 );
		}

		$this->dismiss_for_current_user( $variant );

		wp_send_json_success();
	}

	/**
	 * Handles the no-JavaScript "Dismiss forever" link.
	 *
	 * @return void
	 */
	public function handle_dismiss_request() {

		check_admin_referer( self::NONCE_ACTION );

		if ( ! current_user_can( automator_get_capability() ) ) {
			wp_die(
				esc_html__( 'You do not have permission to dismiss this notice.', 'uncanny-automator' ),
				'',
				array( 'response' => 403 )
			);
		}

		$variant = $this->resolve_variant( automator_request_input( 'variant' ) );

		if ( null === $variant ) {
			wp_die(
				esc_html__( 'Unknown notice.', 'uncanny-automator' ),
				'',
				array( 'response' => 400 )
			);
		}

		$this->dismiss_for_current_user( $variant );

		wp_safe_redirect( $this->get_return_url() );

		exit;
	}

	/**
	 * Resolves where to send the user after a no-JavaScript dismissal.
	 *
	 * @return string
	 */
	private function get_return_url() {

		$referer = wp_get_referer();

		return false !== $referer ? $referer : admin_url();
	}

	/**
	 * Records the permanent dismissal of one message for the current user.
	 *
	 * @param string $variant One of the VARIANT_* identifiers, already resolved.
	 *
	 * @return void
	 */
	private function dismiss_for_current_user( $variant ) {

		update_user_meta( get_current_user_id(), $this->get_meta_key( $variant ), 1 );
	}

	/**
	 * Hides one message for the snooze window without dismissing it.
	 *
	 * @param string $variant One of the VARIANT_* identifiers, already resolved.
	 *
	 * @return void
	 */
	private function snooze_for_current_user( $variant ) {

		update_user_meta(
			get_current_user_id(),
			$this->get_snooze_key( $variant ),
			time() + self::SNOOZE_SECONDS
		);
	}
}
