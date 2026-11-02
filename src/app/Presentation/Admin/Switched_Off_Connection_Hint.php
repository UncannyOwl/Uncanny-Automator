<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin;

use Uncanny_Automator\App\Infrastructure\Connections\Connection_Registry;
use Uncanny_Automator\App\Infrastructure\Connections\Default_Connection_Lookup;
use Uncanny_Automator\App\Infrastructure\Connections\Site_Copy_Record;

/**
 * On an app's settings page, says why the app is not connected when the
 * reason is that this site is a copy — staging-copies handoff R8.
 *
 * The app's own panel shows "connect" whenever no default connection
 * resolves. For a row switched off because this site is a copy, that reads as
 * broken or missing; the account is fine and still works on the site it was
 * copied from. This hint, above the panel, says the connection is switched
 * off here and points at the copy's choice (the App Platform status tab). While
 * the copy is still registering, it says connections are paused.
 *
 * Reads only {@see Connection_Registry::lookup_default()}, as a `/v3` request
 * reads it ({@see Default_Connection_Lookup::for_v3_dispatch()}): a legacy
 * (/v2) row is never described, as a /v2 app dispatches through its own
 * credentials.
 *
 * Shown through the settings page's own alert (`alert_html()`, a
 * `<uas-alert>`) — the settings page hides every WordPress `.notice` — and
 * where the panel prints its own alerts, under its title and above its
 * content (`automator_settings_premium_integration_alerts`), in the connected
 * and disconnected layouts alike.
 *
 * @since 8.0.0
 */
final class Switched_Off_Connection_Hint {

	/**
	 * @var Connection_Registry
	 */
	private $registry;

	/**
	 * @var Site_Copy_Record
	 */
	private $record;

	/**
	 * @var Copy_Connections_View
	 */
	private $view;

	/**
	 * @param Connection_Registry        $registry Local uap_connections store.
	 * @param Site_Copy_Record|null      $record   Names the source site.
	 * @param Copy_Connections_View|null $view     Shared wording.
	 */
	public function __construct( Connection_Registry $registry, ?Site_Copy_Record $record = null, ?Copy_Connections_View $view = null ) {

		$this->registry = $registry;
		$this->record   = $record ?? new Site_Copy_Record();
		$this->view     = $view ?? new Copy_Connections_View();
	}

	/**
	 * @return void
	 */
	public function register(): void {

		add_action( 'automator_settings_premium_integration_alerts', array( $this, 'render' ) );
	}

	/**
	 * @param object $settings The app's settings page.
	 *
	 * @return void
	 */
	public function render( $settings ): void {

		if ( ! is_object( $settings ) || ! method_exists( $settings, 'get_integration' ) || ! method_exists( $settings, 'alert_html' ) ) {
			return;
		}

		$integration = (string) $settings->get_integration();
		if ( '' === $integration ) {
			return;
		}

		$lookup = $this->registry->lookup_default( $integration )->for_v3_dispatch();

		if ( Default_Connection_Lookup::STATE_RECHECKING === $lookup->state() ) {
			$this->render_alert( $settings, esc_html__( 'This site is being registered as a copy of another site, so its app connections are paused until that finishes.', 'uncanny-automator' ) );
			return;
		}

		$row = $lookup->connection();
		if ( ! $lookup->is_switched_off() || null === $row ) {
			return;
		}

		$this->render_alert(
			$settings,
			$this->view->switched_off_sentence( $this->record->source_site_name() )
			. ' <a href="' . esc_url( App_Platform_Status_Tab::url() ) . '">' . esc_html__( 'Choose what this copy does with its app connections', 'uncanny-automator' ) . '</a>'
		);
	}

	/**
	 * @param object $settings The app's settings page; its `alert_html()` prints the page's alert.
	 * @param string $html     Escaped message; the alert keeps its link.
	 *
	 * @return void
	 */
	private function render_alert( $settings, string $html ): void {

		$settings->alert_html(
			array(
				'type'    => 'info',
				'content' => $html,
			)
		);
	}
}
