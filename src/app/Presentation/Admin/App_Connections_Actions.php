<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin;

use Uncanny_Automator\App\Infrastructure\Connections\Adoption_Report;
use Uncanny_Automator\App\Infrastructure\Connections\Connection_Adoption;
use Uncanny_Automator\App\Infrastructure\Connections\Connection_Sharing_Feature;
use Uncanny_Automator\App\Infrastructure\Connections\Connection_Registry;
use Uncanny_Automator\App\Infrastructure\Connections\Connection_Sharing_State;
use Uncanny_Automator\App\Infrastructure\Connections\Connection_Sync;
use Uncanny_Automator\App\Infrastructure\Connections\Connection_Sync_Result;
use Uncanny_Automator\App\Infrastructure\Connections\Disconnect_Service;
use Uncanny_Automator\App\Infrastructure\Connections\Platform_Connections_Api;
use Uncanny_Automator\App\Infrastructure\Connections\Site_Copy_Record;

/**
 * Every admin answer about this site's app connections across sites — one
 * admin-post handler each, all nonce-gated and held to the admin capability
 * the license screen uses, because each one decides which accounts this
 * site's recipes act on:
 *
 *  - answer   what this copy is: "use the live site's app connections here"
 *             (adopt, attached at once when the copy is ready) or "keep the
 *             copied connections switched off" (R2). A copy that already uses
 *             the live site's connections is not switched back: its attached
 *             connections are its own, disconnected per app.
 *  - retry    run the adoption again (R3, R4).
 *  - sharing  turn the site's sharing switch on or off (R1). The platform's
 *             answer is the only value shown afterwards; a failed write says
 *             it was not saved.
 *  - repair   run a connection sync now (R5).
 *  - remove   remove a switched-off inherited row, locally (R7). Only a
 *             switched-off row: any other is disconnected on its app's page.
 *
 * The outcome is told once, on the next page, through a short-lived per-user
 * flash.
 *
 * @since 8.0.0
 */
final class App_Connections_Actions {

	public const ACTION_ANSWER  = 'automator_platform_copy_answer';
	public const ACTION_RETRY   = 'automator_platform_copy_retry';
	public const ACTION_SHARING = 'automator_platform_connection_sharing';
	public const ACTION_REPAIR  = 'automator_platform_connections_repair';
	public const ACTION_REMOVE  = 'automator_platform_connection_remove';

	/**
	 * Nonce action guarding every answer.
	 */
	public const NONCE_ACTION = 'automator_platform_app_connections';

	/**
	 * @var Connection_Adoption
	 */
	private $adoption;

	/**
	 * @var Connection_Sync
	 */
	private $sync;

	/**
	 * @var Platform_Connections_Api
	 */
	private $api;

	/**
	 * @var Connection_Registry
	 */
	private $registry;

	/**
	 * @var Disconnect_Service
	 */
	private $disconnect;

	/**
	 * @var Site_Copy_Record
	 */
	private $record;

	/**
	 * @var callable(string): void
	 */
	private $redirect;

	/**
	 * @var Connection_Sharing_Feature
	 */
	private $sharing;

	/**
	 * @param Connection_Adoption      $adoption   Attaches a copy to the live site's accounts.
	 * @param Connection_Sync          $sync       Rebuilds the rows from the platform's list.
	 * @param Platform_Connections_Api $api        The sharing switch.
	 * @param Connection_Registry      $registry   Local uap_connections store.
	 * @param Disconnect_Service       $disconnect Removes a row.
	 * @param Site_Copy_Record|null    $record     The copy's answer.
	 * @param callable|null                   $redirect   Receives the redirect URL. Defaults to
	 *                                                    wp_safe_redirect() + exit.
	 * @param Connection_Sharing_Feature|null $sharing    Says whether sharing, and a copy's
	 *                                                    use of the live site's connections, are on.
	 */
	public function __construct(
		Connection_Adoption $adoption,
		Connection_Sync $sync,
		Platform_Connections_Api $api,
		Connection_Registry $registry,
		Disconnect_Service $disconnect,
		?Site_Copy_Record $record = null,
		?callable $redirect = null,
		?Connection_Sharing_Feature $sharing = null
	) {
		$this->adoption   = $adoption;
		$this->sync       = $sync;
		$this->api        = $api;
		$this->registry   = $registry;
		$this->disconnect = $disconnect;
		$this->record     = $record ?? new Site_Copy_Record();
		$this->redirect   = $redirect ?? static function ( string $url ): void {
			wp_safe_redirect( $url );
			exit;
		};
		$this->sharing    = $sharing ?? new Connection_Sharing_Feature();
	}

	/**
	 * @return void
	 */
	public function register(): void {

		add_action( 'admin_post_' . self::ACTION_ANSWER, array( $this, 'handle_answer' ) );
		add_action( 'admin_post_' . self::ACTION_RETRY, array( $this, 'handle_retry' ) );
		add_action( 'admin_post_' . self::ACTION_SHARING, array( $this, 'handle_sharing' ) );
		add_action( 'admin_post_' . self::ACTION_REPAIR, array( $this, 'handle_repair' ) );
		add_action( 'admin_post_' . self::ACTION_REMOVE, array( $this, 'handle_remove' ) );
	}

	/**
	 * What this copy is (R2).
	 *
	 * @return void
	 */
	public function handle_answer(): void {

		$this->authorize();

		$answer = isset( $_POST['answer'] ) ? sanitize_key( wp_unslash( $_POST['answer'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in authorize().

		if ( ! $this->is_copy() || ( Site_Copy_Record::ANSWER_ADOPT === $answer && ! $this->sharing->is_enabled() ) ) {
			$this->redirect_back();
			return;
		}

		if ( Site_Copy_Record::ANSWER_KEEP_OFF === $answer && $this->record->wants_adoption() ) {
			$this->flash( 'error', esc_html_x( "This copy already uses the live site's app connections. To stop using one, disconnect it on that app's settings page; the live site is not affected.", 'App connections', 'uncanny-automator' ) );
			$this->redirect_back();
			return;
		}

		if ( ! $this->record->choose( $answer ) ) {
			$this->redirect_back();
			return;
		}

		if ( Site_Copy_Record::ANSWER_ADOPT === $answer ) {
			$this->flash_adoption( $this->adoption->run() );
			$this->redirect_back();
			return;
		}

		$this->flash( 'success', esc_html_x( 'The copied app connections stay switched off on this site. Connect any apps you want to use here.', 'App connections', 'uncanny-automator' ) );
		$this->redirect_back();
	}

	/**
	 * Run the adoption again (R3).
	 *
	 * @return void
	 */
	public function handle_retry(): void {

		$this->authorize();

		if ( $this->sharing->is_enabled() && $this->record->wants_adoption() ) {
			$this->flash_adoption( $this->adoption->run() );
		}

		$this->redirect_back();
	}

	/**
	 * Turn the sharing switch on or off (R1).
	 *
	 * @return void
	 */
	public function handle_sharing(): void {

		$this->authorize();

		if ( ! $this->sharing->is_enabled() ) {
			$this->redirect_back();
			return;
		}

		$share = isset( $_POST['share'] ) && '1' === sanitize_key( wp_unslash( $_POST['share'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in authorize().
		$state = $this->api->write_sharing( $share );

		if ( $state->is_known() && $share === $state->is_enabled() ) {
			$this->flash(
				'success',
				$share
					? esc_html_x( "Sharing is on. Your other sites on this license can now use this site's app connections.", 'App connections', 'uncanny-automator' )
					: esc_html_x( "Sharing is off. Sites that already use this site's app connections keep them until they disconnect.", 'App connections', 'uncanny-automator' )
			);
			$this->redirect_back();
			return;
		}

		$this->flash( 'error', esc_html_x( 'Your change was not saved:', 'App connections', 'uncanny-automator' ) . ' ' . $this->sharing_failure( $state ) );
		$this->redirect_back();
	}

	/**
	 * Run a connection sync now (R5).
	 *
	 * @return void
	 */
	public function handle_repair(): void {

		$this->authorize();

		$result = $this->sync->sync();

		$this->flash( $result->is_synced() ? 'success' : 'error', $this->repair_message( $result ) );
		$this->redirect_back();
	}

	/**
	 * Remove a switched-off inherited row (R7).
	 *
	 * @return void
	 */
	public function handle_remove(): void {

		$this->authorize();

		$connection_id = isset( $_POST['connection_id'] ) ? sanitize_text_field( wp_unslash( $_POST['connection_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in authorize().
		$row           = '' !== $connection_id ? $this->registry->get_by_connection_id( $connection_id ) : null;

		if ( null === $row || ! $row->is_quarantined() ) {
			$this->flash( 'error', esc_html_x( "Only a switched-off connection can be removed here. Disconnect any other on its app's settings page.", 'App connections', 'uncanny-automator' ) );
			$this->redirect_back();
			return;
		}

		$this->disconnect->disconnect( $connection_id );
		$this->flash( 'success', esc_html_x( 'The switched-off connection was removed from this site. The site it was copied from is not affected.', 'App connections', 'uncanny-automator' ) );
		$this->redirect_back();
	}

	/**
	 * The outcome of the last answer, once. The message is escaped text.
	 *
	 * @return array{type: string, message: string}|null
	 */
	public function take_flash(): ?array {

		$key   = $this->flash_key();
		$flash = get_transient( $key );

		if ( ! is_array( $flash ) || ! isset( $flash['type'], $flash['message'] ) ) {
			return null;
		}

		delete_transient( $key );

		return array(
			'type'    => (string) $flash['type'],
			'message' => (string) $flash['message'],
		);
	}

	/**
	 * Whether this site is a copy: the admin or the plugin recorded it, or it
	 * holds rows switched off as inherited (a copy made before the record
	 * existed).
	 *
	 * @return bool
	 */
	public function is_copy(): bool {

		return $this->record->is_copy() || $this->adoption->has_inherited_rows();
	}

	/**
	 * @param Adoption_Report $report Where the adoption stands.
	 *
	 * @return void
	 */
	private function flash_adoption( Adoption_Report $report ): void {

		$attached = count( $report->attached() );
		$off      = count( $report->not_attached() );

		if ( 0 === $off ) {
			/* translators: %d: number of app connections */
			$this->flash( 'success', sprintf( esc_html( _nx( '%d app connection of the live site is in use on this copy.', '%d app connections of the live site are in use on this copy.', $attached, 'App connections', 'uncanny-automator' ) ), $attached ) );
			return;
		}

		/* translators: 1: number of app connections in use, 2: number still switched off */
		$this->flash( 'warning', sprintf( esc_html_x( '%1$d in use on this copy, %2$d still switched off. See the list below.', 'App connections', 'uncanny-automator' ), $attached, $off ) );
	}

	/**
	 * @param Connection_Sync_Result $result The sync's outcome.
	 *
	 * @return string
	 */
	private function repair_message( Connection_Sync_Result $result ): string {

		switch ( $result->status() ) {
			case Connection_Sync_Result::STATUS_SYNCED:
				$changed = $result->restored() + $result->updated() + $result->created();
				return 0 === $changed
					? esc_html_x( 'Nothing needed repairing: every app connection on this site agrees with the Automator App Platform.', 'App connections', 'uncanny-automator' )
					/* translators: 1: connections switched back on, 2: connections updated, 3: connections added */
					: sprintf( esc_html_x( 'Repaired: %1$d switched back on, %2$d updated, %3$d added.', 'App connections', 'uncanny-automator' ), $result->restored(), $result->updated(), $result->created() );
			case Connection_Sync_Result::STATUS_INCOMPLETE:
				return esc_html_x( "Some app connections could not be repaired yet. We'll keep trying.", 'App connections', 'uncanny-automator' );
			case Connection_Sync_Result::STATUS_DEFERRED:
				return esc_html_x( "This site's registration with the Automator App Platform is not confirmed yet. Its app connections will be repaired once it is.", 'App connections', 'uncanny-automator' );
			default:
				return esc_html_x( "We couldn't read this site's app connections from the Automator App Platform, so nothing was changed. We'll try again shortly.", 'App connections', 'uncanny-automator' );
		}
	}

	/**
	 * @param Connection_Sharing_State $state A failed write.
	 *
	 * @return string
	 */
	private function sharing_failure( Connection_Sharing_State $state ): string {

		if ( Connection_Sharing_State::FAILURE_NOT_REGISTERED === $state->failure() ) {
			return esc_html_x( 'this site is not registered with the Automator App Platform yet.', 'App connections', 'uncanny-automator' );
		}

		if ( Connection_Sharing_State::FAILURE_REFUSED === $state->failure() ) {
			return esc_html_x( 'the Automator App Platform refused it.', 'App connections', 'uncanny-automator' );
		}

		return esc_html_x( 'the Automator App Platform could not be reached. Try again in a few minutes.', 'App connections', 'uncanny-automator' );
	}

	/**
	 * @param string $type    success, warning or error.
	 * @param string $message Escaped text; shown through wp_kses() with no tags.
	 *
	 * @return void
	 */
	private function flash( string $type, string $message ): void {

		set_transient(
			$this->flash_key(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			5 * MINUTE_IN_SECONDS
		);
	}

	/**
	 * @return string
	 */
	private function flash_key(): string {

		return 'automator_app_connections_flash_' . get_current_user_id();
	}

	/**
	 * Every answer changes which accounts this site's recipes act on.
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
		( $this->redirect )( $referer ? $referer : App_Platform_Status_Tab::url() );
	}
}
