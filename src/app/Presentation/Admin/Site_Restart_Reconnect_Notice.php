<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin;

use Uncanny_Automator\App\Bridge\Automator_Integration_Registry_Bridge;
use Uncanny_Automator\App\Bridge\Integration_Registry_Bridge;
use Uncanny_Automator\App\Infrastructure\Connections\Restart_Disconnections;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Action;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Message;
use Uncanny_Automator\App\Presentation\Admin\Notices\Notice_Screen;

/**
 * "Connect these apps again" notice after a local site's restart (L247).
 *
 * Lists, by name, the apps whose connections the restart disconnected and
 * that are not connected yet ({@see Restart_Disconnections::awaiting()}). Each
 * app drops off once it is connected again; the notice is gone when none is
 * left. Shown by the notice center; no dismiss.
 *
 * @since 8.0.0
 */
final class Site_Restart_Reconnect_Notice extends Notice {

	/**
	 * @var Restart_Disconnections
	 */
	private $disconnections;

	/**
	 * @var Integration_Registry_Bridge
	 */
	private $integrations;

	/**
	 * @param Restart_Disconnections           $disconnections What the restart took down.
	 * @param Integration_Registry_Bridge|null $integrations   Names integrations.
	 */
	public function __construct( Restart_Disconnections $disconnections, ?Integration_Registry_Bridge $integrations = null ) {
		$this->disconnections = $disconnections;
		$this->integrations   = $integrations ?? new Automator_Integration_Registry_Bridge();
	}

	/**
	 * @return string
	 */
	public function id(): string {

		return 'platform-restart-reconnect';
	}

	/**
	 * @param Notice_Screen $screen The screen.
	 *
	 * @return Notice_Message|null
	 */
	public function message( Notice_Screen $screen ): ?Notice_Message {

		$apps = $this->disconnections->awaiting();
		if ( array() === $apps ) {
			return null;
		}

		return $this->reconnect( implode( ', ', array_map( array( $this, 'app_name' ), $apps ) ) );
	}

	/**
	 * @return Notice_Message[]
	 */
	public function previews(): array {

		return array( $this->reconnect( 'Dropbox, Google Drive' ) );
	}

	/**
	 * @param string $names The apps to connect again, comma-separated.
	 *
	 * @return Notice_Message
	 */
	private function reconnect( string $names ): Notice_Message {

		return Notice_Message::warning( esc_html_x( 'Connect these apps again', 'Admin notice', 'uncanny-automator' ) )
			->with_paragraph(
				sprintf(
					/* translators: %s: comma-separated app names, e.g. "Dropbox, Google Drive" */
					esc_html__( 'This site registered with the Automator App Platform again with a new key, and its app connections were disconnected. Connect these apps again: %s.', 'uncanny-automator' ),
					esc_html( $names )
				)
			)
			->with_action(
				Notice_Action::link(
					esc_html_x( 'Go to App integrations', 'Admin notice', 'uncanny-automator' ),
					add_query_arg(
						array(
							'post_type' => AUTOMATOR_POST_TYPE_RECIPE,
							'page'      => 'uncanny-automator-config',
							'tab'       => 'premium-integrations',
						),
						admin_url( 'edit.php' )
					)
				)->primary()
			);
	}

	/**
	 * @param string $integration An integration slug, e.g. "google_drive".
	 *
	 * @return string The app's name.
	 */
	private function app_name( string $integration ): string {

		$code = strtoupper( $integration );
		$name = $this->integrations->get_integration_name_by_code( $code );

		return $code !== $name ? $name : ucwords( str_replace( '_', ' ', $integration ) );
	}
}
