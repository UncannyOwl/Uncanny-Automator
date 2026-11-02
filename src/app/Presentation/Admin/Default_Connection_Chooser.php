<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin;

use Uncanny_Automator\App\Infrastructure\Connections\Connection;
use Uncanny_Automator\App\Infrastructure\Connections\Connection_Registry;
use Uncanny_Automator\App\Infrastructure\Connections\Default_Connection_Lookup;
use Uncanny_Automator\App\Infrastructure\Connections\Multiple_Connections_Feature;

use function Uncanny_Automator\App\Infrastructure\automator_connection_registry;

/**
 * Asks the admin to choose the account an app's recipes use, when the app
 * has active accounts and none is chosen (Saad, 29 Oct): on the app's
 * settings page, in place of Connect.
 *
 * An app with one account uses it ({@see Connection_Registry::default_sole_connections()});
 * with two or more, nothing chooses for the admin. The settings page of an
 * App Platform app renders {@see render()} in its disconnected panel and
 * leaves out Connect while {@see is_needed()} says so.
 *
 * The choice goes to `admin-post.php`: the admin capability and a nonce, then
 * an active `/v3` account of that same app becomes its default.
 *
 * Not in 8.0: it ships with multiple connections (Saad, 29 Oct). While
 * {@see Multiple_Connections_Feature} is off, it is never needed, renders
 * nothing and makes no choice, so the settings page keeps Connect.
 *
 * @since 8.0.0
 */
final class Default_Connection_Chooser {

	/**
	 * admin-post action.
	 *
	 * @var string
	 */
	public const ACTION = 'automator_choose_default_connection';

	/**
	 * Nonce action.
	 *
	 * @var string
	 */
	public const NONCE_ACTION = 'automator_choose_default_connection';

	/**
	 * @var Connection_Registry
	 */
	private $registry;

	/**
	 * @var callable(string): void
	 */
	private $redirect;

	/**
	 * @var Multiple_Connections_Feature
	 */
	private $feature;

	/**
	 * @param Connection_Registry|null          $registry The app connections.
	 * @param callable|null                     $redirect Receives the redirect URL. Defaults to
	 *                                                    wp_safe_redirect() + exit.
	 * @param Multiple_Connections_Feature|null $feature  Says whether the choice is offered at all.
	 */
	public function __construct( ?Connection_Registry $registry = null, ?callable $redirect = null, ?Multiple_Connections_Feature $feature = null ) {

		$this->registry = $registry ?? automator_connection_registry();
		$this->redirect = $redirect ?? static function ( string $url ): void {
			wp_safe_redirect( $url );
			exit;
		};
		$this->feature  = $feature ?? new Multiple_Connections_Feature();
	}

	/**
	 * @return void
	 */
	public function register(): void {

		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Whether the choice is offered and the app has accounts to choose from
	 * and none is chosen.
	 *
	 * @param string $integration The app's integration slug.
	 *
	 * @return bool
	 */
	public function is_needed( string $integration ): bool {

		return $this->feature->is_enabled()
			&& Default_Connection_Lookup::STATE_UNCHOSEN === $this->registry->lookup_default( $integration )->for_v3_dispatch()->state();
	}

	/**
	 * Render the choice when it is needed.
	 *
	 * @param string $integration The app's integration slug.
	 * @param string $app_name    The app's name, e.g. "Dropbox".
	 *
	 * @return bool Whether the choice was rendered.
	 */
	public function render( string $integration, string $app_name ): bool {

		if ( ! $this->is_needed( $integration ) ) {
			return false;
		}

		?>
		<div class="automator-default-connection-chooser">
			<div class="uap-settings-panel-content-subtitle">
				<?php
				/* translators: %s: app name, e.g. "Dropbox" */
				echo sprintf( esc_html_x( 'Choose the %s account recipes use', 'App connections', 'uncanny-automator' ), esc_html( $app_name ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Both parts escaped.
				?>
			</div>
			<div class="uap-settings-panel-content-paragraph">
				<?php
				/* translators: %s: app name, e.g. "Dropbox" */
				echo sprintf( esc_html_x( 'This site holds %s accounts, and none is chosen for its recipes yet. Recipes act on the one you choose.', 'App connections', 'uncanny-automator' ), esc_html( $app_name ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Both parts escaped.
				?>
			</div>
			<?php foreach ( $this->choosable( $integration ) as $connection ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="uap-spacing-top--small">
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
					<input type="hidden" name="integration" value="<?php echo esc_attr( $integration ); ?>" />
					<input type="hidden" name="connection" value="<?php echo esc_attr( (string) $connection->id() ); ?>" />
					<?php wp_nonce_field( self::NONCE_ACTION ); ?>
					<span><?php echo esc_html( $this->name_of( $connection, $app_name ) ); ?></span>
					<uas-button type="submit" size="sm" color="secondary"><?php echo esc_html_x( 'Use for recipes', 'App connections', 'uncanny-automator' ); ?></uas-button>
				</form>
			<?php endforeach; ?>
		</div>
		<?php

		return true;
	}

	/**
	 * The admin chose an account: check who asked, make it the default, and go
	 * back to the page they came from.
	 *
	 * @return void
	 */
	public function handle(): void {

		check_admin_referer( self::NONCE_ACTION );
		if ( ! current_user_can( automator_get_admin_capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'uncanny-automator' ), 403 );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified above by check_admin_referer().
		$integration = isset( $_POST['integration'] ) ? sanitize_key( wp_unslash( $_POST['integration'] ) ) : '';
		$id          = isset( $_POST['connection'] ) ? absint( wp_unslash( $_POST['connection'] ) ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$this->choose( $integration, $id );

		$referer = wp_get_referer();
		( $this->redirect )( $referer ? $referer : admin_url() );
	}

	/**
	 * Make the account the app's default, when the choice is offered and it
	 * is an active `/v3` account of that app.
	 *
	 * @param string $integration The app's integration slug.
	 * @param int    $id          The account's row id.
	 *
	 * @return bool Whether it is now the default.
	 */
	public function choose( string $integration, int $id ): bool {

		if ( ! $this->feature->is_enabled() ) {
			return false;
		}

		$connection = 0 < $id ? $this->registry->get_by_id( $id ) : null;

		if ( null === $connection || ! $this->is_choosable( $connection, $integration ) ) {
			return false;
		}

		$this->registry->set_default( $integration, $id );

		return true;
	}

	/**
	 * The app's accounts the admin can choose from.
	 *
	 * @param string $integration The app's integration slug.
	 *
	 * @return array<int, Connection>
	 */
	private function choosable( string $integration ): array {

		return array_values(
			array_filter(
				$this->registry->list_for_integration( $integration ),
				function ( Connection $connection ) use ( $integration ): bool {
					return $this->is_choosable( $connection, $integration );
				}
			)
		);
	}

	/**
	 * @param Connection $connection  The account.
	 * @param string     $integration The app's integration slug.
	 *
	 * @return bool Whether it is an active `/v3` account of that app.
	 */
	private function is_choosable( Connection $connection, string $integration ): bool {

		return strtolower( $integration ) === $connection->integration()
			&& $connection->is_active()
			&& ! $connection->is_legacy();
	}

	/**
	 * How the admin knows the account: its label, else its account id.
	 *
	 * @param Connection $connection The account.
	 * @param string     $app_name   The app's name.
	 *
	 * @return string
	 */
	private function name_of( Connection $connection, string $app_name ): string {

		$label = (string) $connection->label();
		if ( '' !== $label ) {
			return $label;
		}

		$identity = (string) $connection->identity_id();

		/* translators: %s: app name, e.g. "Dropbox" */
		return '' !== $identity ? $identity : sprintf( esc_html_x( '%s account', 'App connections', 'uncanny-automator' ), $app_name );
	}
}
