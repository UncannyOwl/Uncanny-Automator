<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Local_Site_Status;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Registration_Backoff;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Registration_Outcomes;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Registration_Wire;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration\Site_Address_Drift;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Site_Credentials_Store;
use Uncanny_Automator\App\Infrastructure\Connections\Site_Copy_Record;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Action;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Message;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Screen;

/**
 * Production URL-drift notice (site identity spec 2026-09-01, §8).
 *
 * The plugin registered this site under one Site-Name and now runs under
 * another. Two things produce that signal — a real move, and a staging or
 * dev copy that carries production's platform secret — and only the admin
 * can tell them apart. So nothing happens automatically in production:
 * this notice asks, and each answer is an explicit admin-post action.
 *
 *  - "This site moved here" → force_register() with migrate_from, which the
 *    platform honours only with proof of possession + license membership.
 *    It moves the site at once (200), or first confirms the new address
 *    through the host challenge (202, L199). Offered whenever the drift is a
 *    URL change, and the platform's answer decides (handoff R4): a typed
 *    refusal is told with the platform's description. Also offered when this
 *    install can no longer read its secret (its security keys changed, as a managed
 *    host's migration does): the engine then sends the move without the
 *    secret and the platform checks that the old address no longer serves
 *    this site (MOVE-NO-SECRET, move contract §10). That takes minutes, and
 *    can end with the old address still live (copy only) or undetermined
 *    (try again, or copy). A landed one is announced by Site_Moved_Notice.
 *  - "This is a staging copy — use the live site's app connections here"
 *    and "This is a separate site — keep the copied connections switched
 *    off" → register_as_copy() with that answer (staging-copies R2): a new
 *    platform row and secret; the old uuid rides along so the platform can
 *    log a clone. The answer is recorded when the copy begins, so whatever
 *    follows the copy — attaching to the live site's accounts, or nothing —
 *    happens on whichever request completes the registration.
 *
 * Every state the notice shows is read from the registration engine, never
 * from what an answer returned, so every admin sees the same thing on every
 * page load:
 *
 *  - a copy is pending          → the copy's progress, no question;
 *  - any handshake is in flight → "confirming", no question; a move also
 *                                 offers "Cancel the move" while nothing
 *                                 can have moved yet;
 *  - otherwise                  → the question, after why the last move
 *                                 ended without moving, when one did.
 *
 * An answer is accepted only while the question is open. A pending attempt
 * keeps the first intention it was sent with, so a second answer under it —
 * a stale tab's "This is a copy" during a move — would be completed as the
 * first one (L199).
 *
 * No dismiss: connections are paused until the admin chooses. Shown by the
 * notice center; this class keeps the answers' handlers.
 *
 * @since 8.0.0
 */
final class Site_Address_Drift_Notice extends Notice {

	/**
	 * admin-post action behind the "This site moved here" answer.
	 *
	 * @var string
	 */
	public const ACTION_MOVED = 'automator_platform_site_moved';

	/**
	 * admin-post action behind "This is a separate site — keep the copied
	 * connections switched off".
	 *
	 * @var string
	 */
	public const ACTION_COPY = 'automator_platform_site_copy';

	/**
	 * admin-post action behind "This is a staging copy — use the live site's
	 * app connections here".
	 *
	 * @var string
	 */
	public const ACTION_STAGING_COPY = 'automator_platform_site_staging_copy';

	/**
	 * admin-post action behind "Cancel the move", offered while a move is
	 * being confirmed.
	 *
	 * @var string
	 */
	public const ACTION_CANCEL_MOVE = 'automator_platform_site_move_cancel';

	/**
	 * Nonce action guarding every answer.
	 *
	 * @var string
	 */
	public const NONCE_ACTION = 'automator_platform_site_address_drift';

	/**
	 * Move endings the platform refused with a description worth showing the
	 * admin beside the plugin's sentence (handoff R4).
	 *
	 * @var string[]
	 */
	private const DESCRIBED_ENDINGS = array( 'address_in_use', 'not_production', 'license_transfer' );

	/**
	 * Move endings told in the platform's own words when it gave them, in
	 * place of the plugin's sentence: its description names what can still
	 * be done (move contract §10.2). The plugin's sentence is the fallback.
	 *
	 * @var string[]
	 */
	private const PLATFORM_WORDED_ENDINGS = array( 'old_address_unknown' );

	/**
	 * Move endings after which only the copy answers are offered on the render
	 * that explains them: the old address is still a live Automator site, one
	 * of the two addresses is local, or the platform has no confirmed address
	 * for the old site to check. Later renders ask the whole question again —
	 * the old site may be taken down, or registered again with its secret.
	 *
	 * @var string[]
	 */
	private const COPY_ONLY_ENDINGS = array( 'still_live', 'local', 'old_address_unknown' );

	/**
	 * @var Platform_Site_Registration
	 */
	private $registration;

	/**
	 * @var Site_Credentials_Store
	 */
	private $store;

	/**
	 * @var Site_Address_Drift
	 */
	private $address;

	/**
	 * @var Registration_Outcomes
	 */
	private $outcomes;

	/**
	 * @var Registration_Wire
	 */
	private $wire;

	/**
	 * @var Registration_Backoff
	 */
	private $backoff;

	/**
	 * @var callable(string): void
	 */
	private $redirect;

	/**
	 * Shared wording of the copy answers.
	 *
	 * @var Copy_Connections_View
	 */
	private $view;


	/**
	 * @param Platform_Site_Registration $registration Owner of the answers: force_register(), register_as_copy() and the pending reads.
	 * @param Site_Credentials_Store     $store        Holds the identity we registered with.
	 * @param callable|null              $redirect     Receives the redirect URL. Defaults to
	 *                                                 wp_safe_redirect() + exit; tests inject
	 *                                                 a recorder.
	 * @param Copy_Connections_View|null $view         Shared wording of the copy answers.
	 * @param Site_Address_Drift|null    $address      Says whether the address changed.
	 * @param Registration_Outcomes|null $outcomes     Holds why the last move ended.
	 * @param Registration_Wire|null     $wire         Knows the address this site runs on.
	 * @param Registration_Backoff|null  $backoff      Cleared when the admin answers.
	 */
	public function __construct(
		Platform_Site_Registration $registration,
		Site_Credentials_Store $store,
		?callable $redirect = null,
		?Copy_Connections_View $view = null,
		?Site_Address_Drift $address = null,
		?Registration_Outcomes $outcomes = null,
		?Registration_Wire $wire = null,
		?Registration_Backoff $backoff = null
	) {
		$this->registration = $registration;
		$this->store        = $store;
		$this->redirect     = $redirect ?? static function ( string $url ): void {
			wp_safe_redirect( $url );
			exit;
		};
		$this->view         = $view ?? new Copy_Connections_View();
		$this->wire         = $wire ?? new Registration_Wire();
		$this->address      = $address ?? new Site_Address_Drift( $store, $this->wire );
		$this->outcomes     = $outcomes ?? new Registration_Outcomes( $this->wire );
		$this->backoff      = $backoff ?? new Registration_Backoff();
	}

	/**
	 * Attach the answers' handlers to wp-admin; the notice center shows the
	 * notice.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::ACTION_MOVED, array( $this, 'handle_moved' ) );
		add_action( 'admin_post_' . self::ACTION_COPY, array( $this, 'handle_copy' ) );
		add_action( 'admin_post_' . self::ACTION_STAGING_COPY, array( $this, 'handle_staging_copy' ) );
		add_action( 'admin_post_' . self::ACTION_CANCEL_MOVE, array( $this, 'handle_cancel_move' ) );
	}

	/**
	 * Only a production site whose Site-Name drifted asks, and only of a user
	 * who could act on the answer.
	 *
	 * Gated on the ADMIN capability, not the core one: either answer
	 * re-registers the site with the platform, which can move or orphan every
	 * app connection on it — the license/credential class of operation.
	 *
	 * @return bool
	 */
	public function should_render(): bool {
		// L247 — a local site cannot move; Platform_Site_Registration registers
		// it as its own site without asking.
		return 'url' === $this->address->drift()
			&& ! ( new Local_Site_Status() )->is_local()
			&& $this->address->is_production()
			&& current_user_can( automator_get_admin_capability() );
	}

	/**
	 * Whether the notice is asking its question right now — the only time an
	 * answer is accepted. Nothing may be pending: neither a copy, nor any
	 * handshake (a move, or a recovery begun before the address changed).
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	private function is_question_open(): bool {
		return $this->should_render()
			&& ! $this->registration->is_copy_pending()
			&& ! $this->registration->is_challenge_pending();
	}

	/**
	 * @return string
	 */
	public function id(): string {

		return 'platform-site-address';
	}

	/**
	 * What the notice says now: the copy's progress, the confirmation in
	 * flight, or the question (after why the last move ended without moving,
	 * when one did). Nothing while the platform's refusal of this address is
	 * the registration notice's to tell.
	 *
	 * @param Notice_Screen $screen The screen.
	 *
	 * @return Notice_Message|null
	 */
	public function message( Notice_Screen $screen ): ?Notice_Message {

		if ( ! $this->should_render() || null !== $this->outcomes->host_refusal() ) {
			return null;
		}

		// The admin already answered "This is a copy" and the copy has not
		// finished registering. Report that instead of asking again — a second
		// answer would contradict a decision that is already durable.
		if ( $this->registration->is_copy_pending() ) {
			return $this->copy_pending_message( $this->registration->is_challenge_pending() );
		}

		// The platform is confirming this site — after "This site moved here",
		// or a recovery. Same reason not to ask: the attempt in flight keeps the
		// answer it was sent with.
		if ( $this->registration->is_challenge_pending() ) {
			return $this->confirming_message( $this->registration->is_move_pending(), $this->registration->is_move_without_secret_pending(), $this->registration->can_cancel_move(), (string) $this->store->registered_site_name() );
		}

		return $this->question_message( $this->outcomes->peek_move_failure(), (string) $this->store->registered_site_name(), $this->wire->current_site_name(), $this->store->has() );
	}

	/**
	 * The question, with and without the key; with each way a move can end
	 * without moving; the copy being confirmed or retried; the site, or its
	 * move, being confirmed.
	 *
	 * @return Notice_Message[]
	 */
	public function previews(): array {

		$from     = 'https://shop.example.com';
		$to       = 'https://staging.shop.example.com';
		$none     = array(
			'reason'      => '',
			'description' => '',
		);
		$previews = array(
			$this->question_message( $none, $from, $to, true ),
			$this->question_message( $none, $from, $to, false ),
		);

		foreach ( array( 'unconfirmed', 'superseded', 'not_verified', 'address_in_use', 'not_production', 'license_transfer', 'local', 'old_address_unknown', 'still_live', 'undetermined', 'refused' ) as $reason ) {
			$described  = in_array( $reason, self::DESCRIBED_ENDINGS, true ) || in_array( $reason, self::PLATFORM_WORDED_ENDINGS, true );
			$previews[] = $this->question_message(
				array(
					'reason'      => $reason,
					'description' => $described ? 'This site address belongs to another license.' : '',
				),
				$from,
				$to,
				true
			);
		}

		$previews[] = $this->copy_pending_message( true );
		$previews[] = $this->copy_pending_message( false );
		$previews[] = $this->confirming_message( true, true, true, $from );
		$previews[] = $this->confirming_message( true, false, true, $from );
		$previews[] = $this->confirming_message( false, false, false, $from );

		return $previews;
	}

	/**
	 * Drawn with the question: forget why the last move ended, so it is told
	 * once.
	 *
	 * @return void
	 */
	public function shown(): void {

		if ( $this->is_question_open() ) {
			$this->outcomes->take_move_failure();
		}
	}

	/**
	 * The question: moved here, staging copy, or separate site.
	 *
	 * @param array{reason: string, description: string} $move_failure Why the last move ended, if it did.
	 * @param string                                     $from         The address the site was registered at.
	 * @param string                                     $to           Its address now.
	 * @param bool                                       $has_secret   Whether this install can read its key.
	 *
	 * @return Notice_Message
	 */
	private function question_message( array $move_failure, string $from, string $to, bool $has_secret ): Notice_Message {

		$reason = $move_failure['reason'];

		$message = Notice_Message::warning( esc_html_x( "This site's address changed", 'Admin notice', 'uncanny-automator' ) )
			->with_state( '' !== $reason ? 'move-failed-' . $reason : 'question' );

		if ( '' !== $reason ) {
			$message = $message->with_paragraph( $this->move_failure_text( $move_failure, $from ) );
		}

		$message = $message->with_paragraph(
			sprintf(
				/* translators: 1: previous site address, 2: current site address */
				esc_html__( "This site's address changed from %1\$s to %2\$s. Your app connections are paused until you tell us which this is.", 'uncanny-automator' ),
				'<code>' . esc_html( $from ) . '</code>',
				'<code>' . esc_html( $to ) . '</code>'
			)
		);

		if ( ! in_array( $reason, self::COPY_ONLY_ENDINGS, true ) ) {
			$message = $message->with_action( Notice_Action::post( esc_html_x( 'This site moved here', 'Admin notice', 'uncanny-automator' ), self::ACTION_MOVED, self::NONCE_ACTION )->with_description( $this->moved_description( $from, $has_secret ) ) );
		}

		if ( $this->view->offers_adoption() ) {
			$message = $message->with_action( Notice_Action::post( $this->view->answer_label( Site_Copy_Record::ANSWER_ADOPT ), self::ACTION_STAGING_COPY, self::NONCE_ACTION )->with_description( $this->view->answer_description( Site_Copy_Record::ANSWER_ADOPT, $from ) ) );
		}

		return $message->with_action( Notice_Action::post( $this->view->answer_label( Site_Copy_Record::ANSWER_KEEP_OFF ), self::ACTION_COPY, self::NONCE_ACTION )->primary()->with_description( $this->view->answer_description( Site_Copy_Record::ANSWER_KEEP_OFF, $from ) ) );
	}

	/**
	 * What "This site moved here" does, and, when this install can no longer
	 * read its key, the check it starts first. Escaped for HTML.
	 *
	 * @param string $from       The address the site was registered at.
	 * @param bool   $has_secret Whether this install can read its key.
	 *
	 * @return string
	 */
	private function moved_description( string $from, bool $has_secret ): string {

		$description = esc_html__( 'Keep every app connection. Only choose this if the old address is no longer in use, because the site there will lose its connections.', 'uncanny-automator' );

		if ( $has_secret ) {
			return $description;
		}

		return $description . ' ' . sprintf(
			/* translators: %s: previous site address */
			esc_html__( 'This site can no longer read the key it registered with: its WordPress security keys changed, as hosts do when they move a site. Choosing this first checks that %s no longer serves this site. That takes a few minutes.', 'uncanny-automator' ),
			'<code>' . esc_html( $from ) . '</code>'
		);
	}

	/**
	 * The copy decision is made but the copy is not registered yet — either the
	 * platform is still confirming this site (a C1 handshake in flight) or the
	 * last attempt failed and will be retried. Connections stay blocked
	 * throughout, so the one thing the admin can act on is reachability.
	 *
	 * @param bool $confirming Whether the platform is confirming the copy now.
	 *
	 * @return Notice_Message
	 */
	private function copy_pending_message( bool $confirming ): Notice_Message {

		$sentence = $confirming
			? esc_html__( "We're confirming this site as a copy. Your app connections stay paused until that finishes. That usually takes under a minute. Reload this page to check.", 'uncanny-automator' )
			: esc_html__( "This site is being registered as a copy, but we haven't been able to confirm it yet. Your app connections stay paused and we'll keep retrying. Make sure this site's REST API is reachable from the internet (security plugins, firewalls and password-protected staging sites can block it).", 'uncanny-automator' );

		return Notice_Message::info( esc_html_x( 'Confirming this site as a copy', 'Admin notice', 'uncanny-automator' ) )
			->with_state( 'copy-pending' )
			->with_paragraph( $sentence );
	}

	/**
	 * The platform is confirming this site and the question is not open. A
	 * move may be taken back while nothing can have moved yet
	 * (Platform_Site_Registration::can_cancel_move()).
	 *
	 * @since 8.0.0
	 *
	 * @param bool   $is_move        Whether a move is being confirmed.
	 * @param bool   $without_secret Whether that move is checked without the key.
	 * @param bool   $can_cancel     Whether the move can still be taken back.
	 * @param string $from           The address the site was registered at.
	 *
	 * @return Notice_Message
	 */
	private function confirming_message( bool $is_move, bool $without_secret, bool $can_cancel, string $from ): Notice_Message {

		if ( $without_secret ) {
			$sentence = sprintf(
				/* translators: %s: previous site address */
				esc_html__( 'Checking that %s no longer serves this site. This takes a few minutes; your app connections stay paused until it finishes. Reload this page to check.', 'uncanny-automator' ),
				'<code>' . esc_html( $from ) . '</code>'
			);
		} elseif ( $is_move ) {
			$sentence = esc_html__( "We're confirming this site at its new address. Your app connections stay paused until that finishes. That usually takes under a minute. Reload this page to check.", 'uncanny-automator' );
		} else {
			$sentence = esc_html__( "We're confirming this site with the Automator App Platform. Your app connections stay paused until that finishes. That usually takes under a minute. Reload this page to check.", 'uncanny-automator' );
		}

		$message = Notice_Message::info( $is_move ? esc_html_x( "Confirming this site's new address", 'Admin notice', 'uncanny-automator' ) : esc_html_x( 'Confirming this site', 'Admin notice', 'uncanny-automator' ) )
			->with_state( $is_move ? 'move-pending' : 'confirming' )
			->with_paragraph( $sentence );

		if ( $can_cancel ) {
			$message = $message->with_action( Notice_Action::post( esc_html_x( 'Cancel the move', 'Admin notice', 'uncanny-automator' ), self::ACTION_CANCEL_MOVE, self::NONCE_ACTION )->with_description( esc_html__( "Nothing has moved yet. Choose this if the site didn't move. You'll be asked again.", 'uncanny-automator' ) ) );
		}

		return $message;
	}

	/**
	 * The whole line about a move that ended without moving the site: the
	 * plugin's sentence, followed by the platform's description for a
	 * described ending, or the platform's description in its place for a
	 * platform-worded one. Escaped for HTML.
	 *
	 * @since 8.0.0
	 *
	 * @param array{reason: string, description: string} $move_failure See Registration_Outcomes::take_move_failure().
	 * @param string                                     $from         The address the site was registered at.
	 *
	 * @return string
	 */
	private function move_failure_text( array $move_failure, string $from ): string {
		$reason = $move_failure['reason'];
		$said   = '' === $move_failure['description'] ? '' : sprintf(
			/* translators: %s: the Automator platform's own description of why it refused */
			esc_html__( 'The Automator App Platform said: “%s”', 'uncanny-automator' ),
			esc_html( $move_failure['description'] )
		);

		if ( '' !== $said && in_array( $reason, self::PLATFORM_WORDED_ENDINGS, true ) ) {
			return esc_html__( 'Nothing was moved.', 'uncanny-automator' ) . ' ' . $said;
		}

		$message = $this->move_failure_message( $reason, $from );

		return '' !== $said && in_array( $reason, self::DESCRIBED_ENDINGS, true ) ? $message . ' ' . $said : $message;
	}

	/**
	 * What the admin needs to hear about a move that ended without moving
	 * the site, by the reason the registration engine stored. Escaped for
	 * HTML.
	 *
	 * @since 8.0.0
	 *
	 * @param string $reason See Registration_Outcomes::take_move_failure().
	 * @param string $from   The address the site was registered at.
	 *
	 * @return string
	 */
	private function move_failure_message( string $reason, string $from ): string {
		$old      = '<code>' . esc_html( $from ) . '</code>';
		$messages = array(
			'unconfirmed'         => esc_html__( "We couldn't confirm this site at its new address, so nothing was moved. Make sure this site's REST API is reachable from the internet (security plugins, firewalls and password-protected sites can block it), then choose again.", 'uncanny-automator' ),
			'superseded'          => esc_html__( "This site's registration changed while we were confirming the move, or another site took this address, so nothing was moved. Choose again.", 'uncanny-automator' ),
			'not_verified'        => esc_html__( "We couldn't verify this as the site that moved, so nothing was changed. If this is a copy of another site, choose \"This is a copy of another site\".", 'uncanny-automator' ),
			'address_in_use'      => esc_html__( 'Another site is already registered at this address, so nothing was moved. Contact support to release it.', 'uncanny-automator' ),
			'not_production'      => esc_html__( 'Only a site running in production can claim a move, so nothing was moved. If this is the live site, set WP_ENVIRONMENT_TYPE to production; otherwise treat it as a copy.', 'uncanny-automator' ),
			'license_transfer'    => esc_html__( "This address is registered under another license, and the Automator App Platform couldn't verify this site, so nothing was moved.", 'uncanny-automator' ),
			'local'               => esc_html__( "One of these addresses is local, and a local site can't move or be moved, so nothing was moved. Treat this site as a copy.", 'uncanny-automator' ),
			/* translators: %s: previous site address */
			'old_address_unknown' => sprintf( esc_html__( "The Automator App Platform has no confirmed address for %s, so it can't check that the site left it, and nothing was moved. Treat this site as a copy, or register the old site again with its secret first.", 'uncanny-automator' ), $old ),
			/* translators: %s: previous site address */
			'still_live'          => sprintf( esc_html__( '%s is still a live Automator site, so this site is a copy, not a move.', 'uncanny-automator' ), $old ),
			/* translators: %s: previous site address */
			'undetermined'        => sprintf( esc_html__( '%s did not answer clearly. It may only be down. Try again later, or treat this site as a copy. If the old site is gone for good, contact Uncanny Automator support.', 'uncanny-automator' ), $old ),
		);

		return $messages[ $reason ] ?? esc_html__( "We couldn't move this site to its new address: the request was refused. Nothing was changed. Try again in a few minutes; if it keeps failing, contact support.", 'uncanny-automator' );
	}

	/**
	 * "This site moved here" — hand the platform the identity we registered
	 * with so it moves that row onto this address instead of minting a new one.
	 * The engine signs the claim with the secret on file, or — when this
	 * install can no longer read it — names the old address for the platform
	 * to check instead (move contract §10).
	 *
	 * @return void
	 */
	public function handle_moved(): void {
		$this->authorize();
		// Only an open question takes an answer: a stale tab must not replay a
		// re-register, nor answer again under an attempt already in flight.
		if ( ! $this->is_question_open() ) {
			$this->redirect_back();
			return;
		}
		$this->backoff->clear_backoff();
		// An empty slot is an absent claim, not a claim of emptiness — hand
		// the platform only what we actually hold.
		$this->registration->force_register(
			array_filter(
				array(
					'site_uuid' => (string) $this->store->site_uuid(),
					'site_name' => (string) $this->store->registered_site_name(),
				)
			)
		);

		// The outcome is state the next render reads, not this return value:
		// moved at once (200), the drift and the notice are gone; started (202),
		// the move is pending and the notice reports progress; ended without
		// moving, the engine stored why, and the notice says so above the
		// question — for every admin, on every page load.
		$this->redirect_back();
	}

	/**
	 * "This is a separate site — keep the copied connections switched off" —
	 * register fresh. The original site keeps its row, its secret and its app
	 * connections; the copied ones stay switched off here.
	 *
	 * @return void
	 */
	public function handle_copy(): void {
		$this->answer_copy( Site_Copy_Record::ANSWER_KEEP_OFF );
	}

	/**
	 * "This is a staging copy — use the live site's app connections here" —
	 * register fresh, then attach to the live site's accounts once the copy
	 * holds its own secret (Connection_Adoption follows the end of the copy
	 * lifecycle). The live site is untouched.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	public function handle_staging_copy(): void {
		$this->answer_copy( Site_Copy_Record::ANSWER_ADOPT );
	}

	/**
	 * Register as a copy with the admin's answer.
	 *
	 * @param string $answer Site_Copy_Record::ANSWER_ADOPT or ANSWER_KEEP_OFF.
	 *
	 * @return void
	 */
	private function answer_copy( string $answer ): void {
		$this->authorize();
		// Only an open question takes an answer: a stale tab must not replay a
		// re-register, nor answer again under an attempt already in flight —
		// a copy sent during a move would be completed as the move. The
		// staging answer needs the live site's sharing, so it is taken only
		// while sharing is offered.
		if ( ! $this->is_question_open() || ( Site_Copy_Record::ANSWER_ADOPT === $answer && ! $this->view->offers_adoption() ) ) {
			$this->redirect_back();
			return;
		}
		$this->backoff->clear_backoff();

		// Register this install as its own site and quarantine the connections
		// it inherited from the source. register_as_copy() runs the fail-closed
		// lifecycle: resolution is blocked before registration, the inherited
		// rows are quarantined only after it succeeds, and a transient platform
		// failure leaves everything untouched and fail-closed rather than
		// destructively clearing connections the copy can't yet replace. The
		// outcome is not dropped: an unfinished copy leaves the fail-closed block
		// engaged, and render() reports that state on every admin page until the
		// copy completes (register_if_needed() keeps driving it).
		$this->registration->register_as_copy( $answer );

		$this->redirect_back();
	}

	/**
	 * "Cancel the move" — the admin answered "moved" by mistake. The engine
	 * drops the attempt and the site returns to the question. A stale tab is
	 * harmless: unless the move can still be dropped (none pending, or a
	 * completion may already have landed), the engine does nothing.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	public function handle_cancel_move(): void {
		$this->authorize();
		$this->registration->cancel_move();
		$this->redirect_back();
	}

	/**
	 * Every answer changes this site's registration with the platform — an
	 * operation that can move or orphan every app connection — so each is
	 * nonce-gated and held to the same admin capability the license screen
	 * uses.
	 *
	 * @return void
	 */
	private function authorize(): void {
		check_admin_referer( self::NONCE_ACTION );
		if ( ! current_user_can( automator_get_admin_capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'uncanny-automator' ), 403 );
		}
	}

	/**
	 * @return void
	 */
	private function redirect_back(): void {
		$referer = wp_get_referer();
		( $this->redirect )( $referer ? $referer : admin_url() );
	}
}
