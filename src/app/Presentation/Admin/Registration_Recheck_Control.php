<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Failure_Message;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration_Status;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration;
use Uncanny_Automator\App\Infrastructure\Connections\Connection_Sync;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Action;

/**
 * "Re-check registration" — the one repair control for this site's
 * registration with the Automator platform (registration and move handoff
 * R1, and Saad's L210 note: when the registration went wrong, never send the
 * admin to disconnect and reconnect their apps).
 *
 * It clears the registration backoff and runs the registration step now
 * ({@see Platform_Site_Registration::recheck()}); once the registration is
 * settled it also repairs app connections a sync still owes. The outcome is
 * reported on the page the admin came from, once, from where the
 * registration then stands.
 *
 * Offered by the registration notice ({@see Site_Registration_Status_Notice})
 * and on Settings > App connections; gated on the admin capability and a
 * nonce, like every answer that changes the site's registration.
 *
 * @since 8.0.0
 */
final class Registration_Recheck_Control {

	/**
	 * admin-post action.
	 *
	 * @var string
	 */
	public const ACTION = 'automator_platform_registration_recheck';

	/**
	 * Nonce action.
	 *
	 * @var string
	 */
	public const NONCE_ACTION = 'automator_platform_registration_recheck';

	/**
	 * @var Platform_Site_Registration
	 */
	private $registration;

	/**
	 * @var Connection_Sync
	 */
	private $sync;

	/**
	 * @var callable(string): void
	 */
	private $redirect;

	/**
	 * @param Platform_Site_Registration $registration Owner of the registration.
	 * @param Connection_Sync            $sync         Repairs the app connections.
	 * @param callable|null              $redirect     Receives the redirect URL. Defaults to
	 *                                                 wp_safe_redirect() + exit.
	 */
	public function __construct( Platform_Site_Registration $registration, Connection_Sync $sync, ?callable $redirect = null ) {

		$this->registration = $registration;
		$this->sync         = $sync;
		$this->redirect     = $redirect ?? static function ( string $url ): void {
			wp_safe_redirect( $url );
			exit;
		};
	}

	/**
	 * @return void
	 */
	public function register(): void {

		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Re-check now, then report where the registration stands.
	 *
	 * @return void
	 */
	public function handle(): void {

		check_admin_referer( self::NONCE_ACTION );
		if ( ! current_user_can( automator_get_admin_capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'uncanny-automator' ), 403 );
		}

		$status = $this->registration->recheck();

		if ( $status->is_registered() && $this->sync->is_owed() ) {
			$this->sync->sync();
		}

		set_transient( $this->flash_key(), self::outcome( $status ), 5 * MINUTE_IN_SECONDS );

		$referer = wp_get_referer();
		( $this->redirect )( $referer ? $referer : admin_url() );
	}

	/**
	 * What a re-check that ended in this status reports: a notice severity
	 * and the escaped sentence.
	 *
	 * @since 8.0.0
	 *
	 * @param Registration_Status $status Where the registration stands.
	 *
	 * @return array{type: string, message: string}
	 */
	public static function outcome( Registration_Status $status ): array {

		return array(
			'type'    => $status->is_registered() ? 'success' : ( $status->needs_attention() ? 'error' : 'info' ),
			'message' => Platform_Failure_Message::for_registration( $status )->text(),
		);
	}

	/**
	 * The outcome of this user's last re-check, without taking it: what a
	 * notice reads to say it. The message is escaped text.
	 *
	 * @since 8.0.0
	 *
	 * @return array{type: string, message: string}|null
	 */
	public function peek_outcome(): ?array {

		$outcome = get_transient( $this->flash_key() );

		if ( ! is_array( $outcome ) || ! isset( $outcome['type'], $outcome['message'] ) ) {
			return null;
		}

		return array(
			'type'    => (string) $outcome['type'],
			'message' => (string) $outcome['message'],
		);
	}

	/**
	 * The outcome of this user's last re-check, once. The message is escaped
	 * text.
	 *
	 * @return array{type: string, message: string}|null
	 */
	public function take_outcome(): ?array {

		$outcome = $this->peek_outcome();
		if ( null !== $outcome ) {
			delete_transient( $this->flash_key() );
		}

		return $outcome;
	}

	/**
	 * The control as a notice's button: the form that posts the re-check.
	 *
	 * @since 8.0.0
	 *
	 * @return Notice_Action
	 */
	public function action(): Notice_Action {

		return Notice_Action::post( esc_html_x( 'Re-check registration', 'Admin notice', 'uncanny-automator' ), self::ACTION, self::NONCE_ACTION )->primary();
	}

	/**
	 * The control: a button posting the re-check.
	 *
	 * @return void
	 */
	public function render_button(): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="automator-registration-recheck uap-spacing-top--small">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
			<?php wp_nonce_field( self::NONCE_ACTION ); ?>
			<uas-button type="submit" size="sm" color="secondary"><?php esc_html_e( 'Re-check registration', 'uncanny-automator' ); ?></uas-button>
		</form>
		<?php
	}

	/**
	 * @return string
	 */
	private function flash_key(): string {

		return 'automator_platform_registration_recheck_' . get_current_user_id();
	}
}
