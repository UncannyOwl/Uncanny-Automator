<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin\Notices;

use InvalidArgumentException;
use Throwable;
use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\Automator_Review;

/**
 * The one class that shows Automator's admin notices.
 *
 * Notices are added here (Free) or through the `automator_admin_notices`
 * filter (Pro, add-ons); none hooks `admin_notices` itself. On each admin
 * page the center keeps the notices this user may see on this screen that
 * have something to say and are not put away, orders them by priority (how
 * urgent Automator judges each), then severity, then the order they were
 * added, and hands them to one skin: Automator's look on an Automator screen
 * with its components loaded, the WordPress look everywhere else. A drawn
 * notice is then told so, once.
 *
 * With `SHOW_ALL_FILTER` on (staging sites, for review), it draws every
 * notice's previews instead, whatever its state or dismissal, still only to
 * its users and on its screens; a preview changes nothing.
 *
 * Automator removes every `admin_notices` callback on its own screens and
 * fires `automator_show_internal_admin_notice` instead (on `admin_head`, too
 * early to print): the center attaches itself again there.
 *
 * @since 8.0.0
 */
final class Notice_Center {

	/**
	 * Filter that receives the notices other plugins add: Notice[] in, Notice[] out.
	 */
	public const FILTER = 'automator_admin_notices';

	/**
	 * Filter that, true, draws every notice's previews instead of what each
	 * says now: bool in, bool out. Default false.
	 */
	public const SHOW_ALL_FILTER = 'automator_admin_notices_show_all';

	/**
	 * The admin-post action that stores a dismissal.
	 */
	public const DISMISS_ACTION = 'automator_notice_dismiss';

	/**
	 * The nonce action prefix; the notice id completes it.
	 */
	public const NONCE_ACTION = 'automator_notice_dismiss_';

	/**
	 * What a notice id may be.
	 */
	private const ID_PATTERN = '/^[a-z0-9][a-z0-9-]{0,63}$/';

	/**
	 * @var array<string, Notice>
	 */
	private $notices = array();

	/**
	 * @var Notice_Dismissals
	 */
	private $dismissals;

	/**
	 * @var callable(): Notice_Screen
	 */
	private $screen;

	/**
	 * @var Notice_Skin
	 */
	private $automator_skin;

	/**
	 * @var Notice_Skin
	 */
	private $wordpress_skin;

	/**
	 * @var callable(): int
	 */
	private $clock;

	/**
	 * @var callable(string): void
	 */
	private $redirect;

	/**
	 * @var Notice_Limits
	 */
	private $limits;

	/**
	 * @var bool
	 */
	private $rendered = false;

	/**
	 * @param Notice_Dismissals|null $dismissals     Stored dismissals.
	 * @param callable|null          $screen         Returns the Notice_Screen being drawn.
	 * @param Notice_Skin|null       $automator_skin Automator's look.
	 * @param Notice_Skin|null       $wordpress_skin The WordPress look.
	 * @param callable|null          $clock          Returns the Unix time.
	 * @param callable|null          $redirect       Receives the address to return to after a dismissal.
	 * @param Notice_Limits|null     $limits         How many of each kind show at once.
	 */
	public function __construct(
		?Notice_Dismissals $dismissals = null,
		?callable $screen = null,
		?Notice_Skin $automator_skin = null,
		?Notice_Skin $wordpress_skin = null,
		?callable $clock = null,
		?callable $redirect = null,
		?Notice_Limits $limits = null
	) {
		$this->dismissals     = $dismissals ?? new Notice_Dismissals();
		$this->screen         = $screen ?? array( $this, 'current_screen' );
		$this->automator_skin = $automator_skin ?? new Automator_Notice_Skin();
		$this->wordpress_skin = $wordpress_skin ?? new WordPress_Notice_Skin();
		$this->clock          = $clock ?? 'time';
		$this->redirect       = $redirect ?? static function ( string $url ): void {
			wp_safe_redirect( $url );
			exit;
		};
		$this->limits         = $limits ?? new Notice_Limits();
	}

	/**
	 * Add a notice.
	 *
	 * @param Notice $notice The notice.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException For a malformed id, or one already added.
	 */
	public function add( Notice $notice ): void {

		$id = $notice->id();

		if ( 1 !== preg_match( self::ID_PATTERN, $id ) ) {
			throw new InvalidArgumentException( sprintf( 'Notice id "%s" must be lower case letters, digits and hyphens.', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer message, never printed.
		}

		if ( isset( $this->notices[ $id ] ) ) {
			throw new InvalidArgumentException( sprintf( 'Notice id "%s" is already taken.', $id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Developer message, never printed.
		}

		$this->notices[ $id ] = $notice;
	}

	/**
	 * The ids of every notice it holds: added here, then through the filter.
	 *
	 * @return string[]
	 */
	public function ids(): array {

		return array_keys( $this->all_notices() );
	}

	/**
	 * Attach to wp-admin.
	 *
	 * @return void
	 */
	public function register(): void {

		add_action( 'admin_notices', array( $this, 'render' ) );
		add_action( 'automator_show_internal_admin_notice', array( $this, 'restore' ) );
		add_action( 'admin_post_' . self::DISMISS_ACTION, array( $this, 'handle_dismiss' ) );
	}

	/**
	 * Attach again after Automator cleared `admin_notices` on its own screen.
	 *
	 * @return void
	 */
	public function restore(): void {

		if ( false === has_action( 'admin_notices', array( $this, 'render' ) ) ) {
			add_action( 'admin_notices', array( $this, 'render' ) );
		}
	}

	/**
	 * Draw this screen's notices, once per request.
	 *
	 * @return void
	 */
	public function render(): void {

		if ( $this->rendered ) {
			return;
		}
		$this->rendered = true;

		$screen  = ( $this->screen )();
		$entries = $this->entries( $screen );
		if ( array() === $entries ) {
			return;
		}

		$skin  = $screen->takes_automator_look() ? $this->automator_skin : $this->wordpress_skin;
		$drawn = $skin->render( array_column( $entries, 'entry' ), $screen );

		foreach ( $entries as $item ) {
			if ( ! $item['entry']->is_preview() && in_array( $item['entry']->id(), $drawn, true ) ) {
				$item['notice']->shown();
			}
		}
	}

	/**
	 * Store the current user's dismissal of a notice.
	 *
	 * @param string $id The notice's id.
	 *
	 * @return bool Whether it was stored: false for an unknown notice, one
	 *              that keeps no dismissal, or a user who may not see it.
	 */
	public function dismiss( string $id ): bool {

		$notice = $this->all_notices()[ $id ] ?? null;
		if ( null === $notice || ! current_user_can( $notice->capability() ) ) { // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Each notice names its own capability.
			return false;
		}

		$dismissal = $notice->dismissal();
		if ( ! $dismissal->is_stored() ) {
			return false;
		}

		$this->dismissals->dismiss( $id, $dismissal, get_current_user_id(), ( $this->clock )() );
		$notice->dismissed();

		return true;
	}

	/**
	 * admin-post handler for the dismiss address: nonce-checked, then back to
	 * the page the admin was on. The skins' script calls the same address in
	 * the background.
	 *
	 * @return void
	 */
	public function handle_dismiss(): void {

		$id = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified next, with the id in the nonce action.
		check_admin_referer( self::NONCE_ACTION . $id );

		$this->dismiss( $id );

		$referer = wp_get_referer();
		( $this->redirect )( $referer ? $referer : admin_url() );
	}

	/**
	 * The notices to draw on this screen, in order, each with its entry: what
	 * each says now, or with show-all on, each of its previews.
	 *
	 * @param Notice_Screen $screen The screen.
	 *
	 * @return array<int, array{notice: Notice, entry: Notice_Entry, priority: int, rank: int, order: int}>
	 */
	private function entries( Notice_Screen $screen ): array {

		$show_all = true === (bool) Dispatcher::filter( self::SHOW_ALL_FILTER, false );
		$entries  = array();
		$order    = 0;

		foreach ( $this->all_notices() as $id => $notice ) {
			if ( ! $this->is_for( $notice, $screen ) ) {
				continue;
			}

			$built = $show_all ? $this->preview_entries( $id, $notice ) : $this->live_entries( $id, $notice, $screen );
			foreach ( $built as $entry ) {
				$entries[] = array(
					'notice'   => $notice,
					'entry'    => $entry,
					'priority' => $entry->message()->priority() ?? $notice->priority(),
					'rank'     => Notice_Severity::rank( $entry->message()->severity() ),
					'order'    => $order++,
				);
			}
		}

		usort(
			$entries,
			static function ( array $a, array $b ): int {
				return array( $a['priority'], $a['rank'], $a['order'] ) <=> array( $b['priority'], $b['rank'], $b['order'] );
			}
		);

		return $this->fold( $entries );
	}

	/**
	 * Fold each notice past its kind's limit, in order.
	 *
	 * @param array<int, array{notice: Notice, entry: Notice_Entry, priority: int, rank: int, order: int}> $entries In order.
	 *
	 * @return array<int, array{notice: Notice, entry: Notice_Entry, priority: int, rank: int, order: int}>
	 */
	private function fold( array $entries ): array {

		$shown = array();
		foreach ( $entries as $index => $item ) {
			$kind           = $this->limits->kind( $item['entry']->message()->severity(), $item['priority'] );
			$shown[ $kind ] = ( $shown[ $kind ] ?? 0 ) + 1;
			if ( $shown[ $kind ] > $this->limits->limit( $kind ) ) {
				$entries[ $index ]['entry'] = $item['entry']->folded();
			}
		}

		return $entries;
	}

	/**
	 * Whether this user may see the notice on this screen.
	 *
	 * @param Notice        $notice The notice.
	 * @param Notice_Screen $screen The screen.
	 *
	 * @return bool
	 */
	private function is_for( Notice $notice, Notice_Screen $screen ): bool {

		if ( ! current_user_can( $notice->capability() ) ) { // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- Each notice names its own capability.
			return false;
		}

		return Notice::AUTOMATOR_SCREENS !== $notice->placement() || $screen->is_automator();
	}

	/**
	 * What the notice says now, unless it is put away, silent, or failing.
	 *
	 * @param string        $id     The notice's id.
	 * @param Notice        $notice The notice.
	 * @param Notice_Screen $screen The screen.
	 *
	 * @return Notice_Entry[]
	 */
	private function live_entries( string $id, Notice $notice, Notice_Screen $screen ): array {

		try {
			$dismissal = $notice->dismissal();
			if ( $this->dismissals->is_dismissed( $id, $dismissal, get_current_user_id(), ( $this->clock )() ) ) {
				return array();
			}

			$message = $notice->message( $screen );
		} catch ( Throwable $e ) {
			$this->log_failure( $id, $e );
			return array();
		}

		if ( null === $message ) {
			return array();
		}

		return array( new Notice_Entry( $id, $message, $dismissal, $dismissal->is_stored() ? $this->dismiss_url( $id ) : '' ) );
	}

	/**
	 * Each of the notice's previews: its X, when it has one, only closes it
	 * in this view, and nothing is stored or posted.
	 *
	 * @param string $id     The notice's id.
	 * @param Notice $notice The notice.
	 *
	 * @return Notice_Entry[]
	 */
	private function preview_entries( string $id, Notice $notice ): array {

		try {
			$previews  = $notice->previews();
			$dismissal = $notice->dismissal()->is_dismissible() ? Notice_Dismissal::for_this_view() : Notice_Dismissal::none();
		} catch ( Throwable $e ) {
			$this->log_failure( $id, $e );
			return array();
		}

		$entries = array();
		foreach ( $previews as $message ) {
			if ( $message instanceof Notice_Message ) {
				$entries[] = new Notice_Entry( $id, $message, $dismissal, '', true );
			}
		}

		return $entries;
	}

	/**
	 * One broken notice must not take the others, or the page, down: it is
	 * left out and logged.
	 *
	 * @param string    $id The notice's id.
	 * @param Throwable $e  What it threw.
	 *
	 * @return void
	 */
	private function log_failure( string $id, Throwable $e ): void {

		automator_log( $e->getMessage(), 'Admin notice "' . $id . '" failed', false, 'notices' );
	}

	/**
	 * The notices added here, then those added through the filter, first id
	 * wins.
	 *
	 * @return array<string, Notice>
	 */
	private function all_notices(): array {

		$notices = $this->notices;
		$added   = Dispatcher::filter( self::FILTER, array() );

		foreach ( is_array( $added ) ? $added : array() as $notice ) {
			if ( ! $notice instanceof Notice ) {
				continue;
			}
			$id = $notice->id();
			if ( 1 === preg_match( self::ID_PATTERN, $id ) && ! isset( $notices[ $id ] ) ) {
				$notices[ $id ] = $notice;
			}
		}

		return $notices;
	}

	/**
	 * The nonced address that stores a dismissal of the notice.
	 *
	 * @param string $id The notice's id.
	 *
	 * @return string
	 */
	private function dismiss_url( string $id ): string {

		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::DISMISS_ACTION,
					'notice' => $id,
				),
				admin_url( 'admin-post.php' )
			),
			self::NONCE_ACTION . $id
		);
	}

	/**
	 * The screen WordPress is drawing: an Automator screen is one where
	 * Automator replaces `admin_notices` with its own hook, or another of its
	 * pages (Pro's Scheduled recipes).
	 *
	 * @return Notice_Screen
	 */
	public function current_screen(): Notice_Screen {

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id     = $screen instanceof \WP_Screen ? (string) $screen->id : '';

		$automator = '' !== $id
			&& class_exists( Automator_Review::class )
			&& ( in_array( $id, Automator_Review::get_allowed_page_for_credits_notif( true ), true ) || Automator_Review::is_automator_page() );

		return new Notice_Screen( $id, $automator, wp_script_is( 'uap-admin', 'enqueued' ) );
	}
}
