<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin;

use Uncanny_Automator\App\Bridge\Automator_Integration_Registry_Bridge;
use Uncanny_Automator\App\Bridge\Integration_Registry_Bridge;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Local_Site_Status;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Failure_Message;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Site_Registration;
use Uncanny_Automator\App\Infrastructure\Automator_Platform\Registration_Status;
use Uncanny_Automator\App\Infrastructure\Connections\Connection;
use Uncanny_Automator\App\Infrastructure\Connections\Connection_Adoption;
use Uncanny_Automator\App\Infrastructure\Connections\Connection_Sharing_Feature;
use Uncanny_Automator\App\Infrastructure\Connections\Connection_Registry;
use Uncanny_Automator\App\Infrastructure\Connections\Connection_Sharing_State;
use Uncanny_Automator\App\Infrastructure\Connections\Platform_Connection;
use Uncanny_Automator\App\Infrastructure\Connections\Platform_Connections_Api;
use Uncanny_Automator\App\Infrastructure\Connections\Registry_Read_Failed_Exception;
use Uncanny_Automator\App\Infrastructure\Connections\Site_Copy_Record;

/**
 * Settings > Advanced > App Platform status: where this site stands with the
 * Automator App Platform, and its app connections across the sites of one
 * Uncanny Automator account. It sits right above Background app actions.
 *
 *  - This site is a copy (R2, R4, R8): whose copy it is, what its copied
 *    connections do here, and — depending on the answer — the choice, the
 *    switched-off rows with Remove, or how the adoption went with Retry.
 *  - Sharing (R1): the site's switch, as the platform holds it right now —
 *    read on every render, never remembered locally. A value the platform did
 *    not give is never shown as a switch. A copy that only uses the live
 *    site's connections has none of its own to share and is told so.
 *  - Repair (R5): rebuild the rows from the platform's list now.
 *  - Registration (registration and move handoff R1): where this site's
 *    registration stands, from the one owner of that answer, with the
 *    Re-check registration control.
 *
 * Rendered only when the tab is opened (it reads the platform), and only for
 * the admin capability every answer requires.
 *
 * @since 8.0.0
 */
final class App_Platform_Status_Tab {

	/**
	 * The Advanced sub-tab key.
	 */
	public const TAB = 'app_platform_status';

	/**
	 * The legacy Background actions sub-tab, which it goes before when
	 * Background app actions is absent.
	 */
	private const BEFORE_LEGACY = 'background_actions';

	/**
	 * @var App_Connections_Actions
	 */
	private $actions;

	/**
	 * @var Connection_Adoption
	 */
	private $adoption;

	/**
	 * @var Platform_Connections_Api
	 */
	private $api;

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
	 * @var Platform_Site_Registration
	 */
	private $registration;

	/**
	 * @var Registration_Recheck_Control
	 */
	private $recheck;

	/**
	 * @var Integration_Registry_Bridge
	 */
	private $integrations;

	/**
	 * @var Connection_Sharing_Feature
	 */
	private $sharing;

	/**
	 * @param App_Connections_Actions    $actions  Handles every form here; holds the flash.
	 * @param Connection_Adoption        $adoption Reports the copy's adoption.
	 * @param Platform_Connections_Api   $api      Reads the sharing switch.
	 * @param Connection_Registry          $registry     Local uap_connections store.
	 * @param Platform_Site_Registration   $registration Where the registration stands.
	 * @param Registration_Recheck_Control $recheck      The registration's repair control.
	 * @param Site_Copy_Record|null        $record       The copy's answer.
	 * @param Copy_Connections_View|null   $view         Shared wording and forms.
	 * @param Integration_Registry_Bridge|null $integrations Names the App Platform apps.
	 * @param Connection_Sharing_Feature|null  $sharing      Says whether the sharing section shows.
	 */
	public function __construct(
		App_Connections_Actions $actions,
		Connection_Adoption $adoption,
		Platform_Connections_Api $api,
		Connection_Registry $registry,
		Platform_Site_Registration $registration,
		Registration_Recheck_Control $recheck,
		?Site_Copy_Record $record = null,
		?Copy_Connections_View $view = null,
		?Integration_Registry_Bridge $integrations = null,
		?Connection_Sharing_Feature $sharing = null
	) {
		$this->actions      = $actions;
		$this->adoption     = $adoption;
		$this->api          = $api;
		$this->registry     = $registry;
		$this->registration = $registration;
		$this->recheck      = $recheck;
		$this->record       = $record ?? new Site_Copy_Record();
		$this->view         = $view ?? new Copy_Connections_View();
		$this->integrations = $integrations ?? new Automator_Integration_Registry_Bridge();
		$this->sharing      = $sharing ?? new Connection_Sharing_Feature();
	}

	/**
	 * @return void
	 */
	public function register(): void {

		// After Background app actions (priority 100), so it is there to go
		// before.
		add_filter( 'automator_settings_advanced_tabs', array( $this, 'add_tab' ), 101 );
	}

	/**
	 * The tab, right before Background app actions; before the retired
	 * Background actions when that tab is absent; last when both are.
	 *
	 * @param array<string, object> $tabs The Advanced sub-tabs.
	 *
	 * @return array<string, object>
	 */
	public function add_tab( $tabs ): array {

		$tabs = is_array( $tabs ) ? $tabs : array();
		unset( $tabs[ self::TAB ] );

		$tab = array(
			self::TAB => (object) array(
				'name'     => esc_html__( 'App Platform status', 'uncanny-automator' ),
				'function' => array( $this, 'render' ),
				'preload'  => false,
			),
		);

		$before = $this->position_before( array_keys( $tabs ) );
		if ( null === $before ) {
			return $tabs + $tab;
		}

		return array_slice( $tabs, 0, $before, true ) + $tab + array_slice( $tabs, $before, null, true );
	}

	/**
	 * Where the tab goes: the position of Background app actions, else of the
	 * retired Background actions.
	 *
	 * @param array<int, string> $keys The Advanced sub-tab keys, in order.
	 *
	 * @return int|null Null when neither tab is there.
	 */
	private function position_before( array $keys ): ?int {

		foreach ( array( Background_App_Actions_Tab::TAB, self::BEFORE_LEGACY ) as $key ) {
			$position = array_search( $key, $keys, true );
			if ( false !== $position ) {
				return $position;
			}
		}

		return null;
	}

	/**
	 * The tab's URL.
	 *
	 * @return string
	 */
	public static function url(): string {

		return add_query_arg(
			array(
				'post_type' => AUTOMATOR_POST_TYPE_RECIPE,
				'page'      => 'uncanny-automator-config',
				'tab'       => 'advanced',
				'advanced'  => self::TAB,
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * The tab, in the settings panel every settings tab uses: the
	 * registration first, then the copy (or the local site's one sentence),
	 * sharing and repair, each its own section.
	 *
	 * @return void
	 */
	public function render(): void {

		if ( ! current_user_can( automator_get_admin_capability() ) ) {
			return;
		}
		?>
		<div class="uap-settings-panel automator-app-connections">
			<div class="uap-settings-panel-top">
				<div class="uap-settings-panel-title"><?php esc_html_e( 'App Platform status', 'uncanny-automator' ); ?></div>
				<div class="uap-settings-panel-content">
					<?php
					$this->render_flash();
					$this->render_registration();
					// L247 — a local site neither shares its accounts nor adopts another
					// site's, and gets no webhooks: one sentence in place of the copy
					// and sharing controls.
					if ( ( new Local_Site_Status() )->is_local() ) {
						$this->render_local();
					} else {
						$this->render_copy();
						if ( $this->sharing->is_enabled() ) {
							$this->render_sharing();
						}
					}
					$this->render_repair();
					?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Where this site's registration stands, as an alert of its kind, with
	 * the control that re-checks it (handoff R1).
	 *
	 * @return void
	 */
	private function render_registration(): void {

		$status = $this->registration->status();
		?>
		<section class="automator-app-connections-registration" data-state="<?php echo esc_attr( $status->state() ); ?>">
			<div class="uap-settings-panel-content-subtitle uap-spacing-top--none"><?php esc_html_e( 'Registration with the Automator App Platform', 'uncanny-automator' ); ?></div>
			<uas-alert type="<?php echo esc_attr( $this->registration_alert_type( $status ) ); ?>" class="uap-spacing-top">
				<div><?php echo wp_kses( Platform_Failure_Message::for_registration( $status )->text(), array() ); ?></div>
				<?php $this->recheck->render_button(); ?>
			</uas-alert>
		</section>
		<?php
	}

	/**
	 * The alert a registration status reads as: settled, waiting on the
	 * license, needing the admin, or on its way.
	 *
	 * @param Registration_Status $status The registration.
	 *
	 * @return string
	 */
	private function registration_alert_type( Registration_Status $status ): string {

		if ( $status->is_registered() ) {
			return 'success';
		}

		if ( $status->is_waiting_on_license() ) {
			return 'warning';
		}

		return $status->needs_attention() ? 'error' : 'info';
	}

	/**
	 * Whether the request is this tab: the screen its own controls (Re-check
	 * registration, Repair connections, the sharing switch, the copy answers)
	 * send the admin back to, and so where their outcomes are shown.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public static function is_current_screen(): bool {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only: which screen is showing.
		$page     = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$tab      = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$advanced = isset( $_GET['advanced'] ) ? sanitize_key( wp_unslash( $_GET['advanced'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return 'uncanny-automator-config' === $page && 'advanced' === $tab && self::TAB === $advanced;
	}

	/**
	 * The outcome of the last press on this tab, and of a registration
	 * re-check, once each — as the settings page's own alert: the settings
	 * page hides every WordPress `.notice`, so a notice here is never seen.
	 *
	 * @return void
	 */
	private function render_flash(): void {

		$flash = $this->actions->take_flash();
		if ( null !== $flash ) {
			$this->render_alert( $flash['type'], $flash['message'] );
		}

		$outcome = $this->recheck->take_outcome();
		if ( null !== $outcome ) {
			$this->render_alert( $outcome['type'], $outcome['message'], esc_html__( 'Registration re-checked', 'uncanny-automator' ) );
		}
	}

	/**
	 * @param string $type    success, error, warning or info.
	 * @param string $message Escaped sentence.
	 * @param string $heading Escaped heading, or ''.
	 *
	 * @return void
	 */
	private function render_alert( string $type, string $message, string $heading = '' ): void {
		?>
		<uas-alert type="<?php echo esc_attr( $type ); ?>" heading="<?php echo esc_attr( $heading ); ?>" class="uap-spacing-bottom"><?php echo wp_kses( $message, array() ); ?></uas-alert>
		<?php
	}

	/**
	 * This site is a copy: what that means for its connections here.
	 *
	 * @return void
	 */
	private function render_copy(): void {

		if ( ! $this->actions->is_copy() ) {
			return;
		}

		$source = $this->record->source_site_name();
		$answer = $this->record->answer();
		?>
		<div class="uap-settings-panel-content-separator"></div>
		<section class="automator-app-connections-copy">
			<div class="uap-settings-panel-content-subtitle"><?php esc_html_e( 'This site is a copy', 'uncanny-automator' ); ?></div>
			<?php
			if ( Site_Copy_Record::ANSWER_ADOPT === $answer ) {
				$this->render_adopted( $source );
			} elseif ( Site_Copy_Record::ANSWER_KEEP_OFF === $answer ) {
				$this->render_kept_off( $source );
			} else {
				$this->render_question( $source );
			}
			?>
		</section>
		<?php
	}

	/**
	 * @param string $source The recorded source address.
	 *
	 * @return void
	 */
	private function render_question( string $source ): void {
		?>
		<div class="uap-settings-panel-content-paragraph"><?php echo $this->view->copy_sentence( Site_Copy_Record::ANSWER_PENDING, $source ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in copy_sentence(). ?></div>
		<?php
		$this->view->render_answer_forms( $source );
	}

	/**
	 * @param string $source The recorded source address.
	 *
	 * @return void
	 */
	private function render_kept_off( string $source ): void {
		?>
		<div class="uap-settings-panel-content-paragraph"><?php echo $this->view->copy_sentence( Site_Copy_Record::ANSWER_KEEP_OFF, $source ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in copy_sentence(). ?></div>
		<?php
		$this->view->render_switched_off_rows( $this->switched_off_rows() );
		if ( ! $this->view->offers_adoption() ) {
			return;
		}
		?>
		<div class="uap-settings-panel-content-paragraph uap-spacing-top"><?php esc_html_e( "Or use the live site's app connections on this copy instead:", 'uncanny-automator' ); ?></div>
		<?php
		$this->view->render_answer_forms( $source, false );
	}

	/**
	 * @param string $source The recorded source address.
	 *
	 * @return void
	 */
	private function render_adopted( string $source ): void {

		?>
		<div class="uap-settings-panel-content-paragraph"><?php echo $this->view->copy_sentence( Site_Copy_Record::ANSWER_ADOPT, $source ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in copy_sentence(). ?></div>
		<?php
		$this->view->render_report( $this->adoption->report(), $source );
	}

	/**
	 * A local site (L247): what its connections cannot do, in the one sentence.
	 *
	 * @return void
	 */
	private function render_local(): void {
		?>
		<div class="uap-settings-panel-content-separator"></div>
		<section class="automator-app-connections-local">
			<div class="uap-settings-panel-content-paragraph"><?php echo esc_html( Platform_Failure_Message::local_site()->text() ); ?></div>
		</section>
		<?php
	}

	/**
	 * The sharing switch (R1).
	 *
	 * @return void
	 */
	private function render_sharing(): void {
		?>
		<div class="uap-settings-panel-content-separator"></div>
		<section class="automator-app-connections-sharing">
			<div class="uap-settings-panel-content-subtitle"><?php esc_html_e( "Share this site's app connections with its copies", 'uncanny-automator' ); ?></div>
			<?php
			if ( $this->has_nothing_to_share() ) {
				?>
				<div class="uap-settings-panel-content-paragraph">
					<?php
					echo sprintf(
						/* translators: %s: the address of the site this site was copied from */
						esc_html_x( 'This site uses the app connections of %s, so it has none of its own to share. Apps you connect on this site yourself can be shared from here.', 'App connections', 'uncanny-automator' ),
						$this->view->source( $this->record->source_site_name() ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in source().
					);
					?>
				</div>
				<?php
			} else {
				$this->render_switch( $this->api->read_sharing() );
			}
			?>
		</section>
		<?php
	}

	/**
	 * @param Connection_Sharing_State $state The platform's value.
	 *
	 * @return void
	 */
	private function render_switch( Connection_Sharing_State $state ): void {

		if ( ! $state->is_known() ) {
			$message = Connection_Sharing_State::FAILURE_NOT_REGISTERED === $state->failure()
				? esc_html_x( 'This site is not registered with the Automator App Platform yet, so sharing cannot be set. It registers once its license is active.', 'App connections', 'uncanny-automator' )
				: esc_html_x( "We couldn't read this setting from the Automator App Platform. Reload this page to try again.", 'App connections', 'uncanny-automator' );
			?>
			<uas-alert type="warning" class="uap-spacing-top"><?php echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?></uas-alert>
			<?php
			return;
		}

		$enabled = $state->is_enabled();
		?>
		<div class="uap-settings-panel-content-paragraph"><?php esc_html_e( "When this site is copied to another address on the same license, such as a staging or development site, the copy can use these app connections without signing in to each app again. The copy's administrator chooses this when the copy asks what it is.", 'uncanny-automator' ); ?></div>
		<div class="uap-settings-panel-content-paragraph uap-spacing-top--small"><?php esc_html_e( 'This applies to apps connected through the Automator App Platform. Recipes on a copy that uses these connections act on the same accounts as this site. Each copy can still disconnect on its own without affecting this site.', 'uncanny-automator' ); ?></div>
		<div class="uap-settings-panel-content-paragraph uap-spacing-top--small"><?php esc_html_e( 'Turning this off stops new copies from using these connections. Copies already using them keep working until they disconnect.', 'uncanny-automator' ); ?></div>
		<?php $this->render_covered_apps(); ?>
		<uas-alert type="warning" heading="<?php echo esc_attr_x( 'Sharing reaches every site on this license', 'App connections', 'uncanny-automator' ); ?>" class="uap-spacing-top">
			<?php echo esc_html_x( 'Turn sharing on only if every site on this license may use these accounts. If the license covers sites for different clients, keep sharing off and connect each app on the site that uses it.', 'App connections', 'uncanny-automator' ); ?>
		</uas-alert>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="automator-app-connections-sharing-form uap-spacing-top">
			<input type="hidden" name="action" value="<?php echo esc_attr( App_Connections_Actions::ACTION_SHARING ); ?>" />
			<input type="hidden" name="share" value="<?php echo $enabled ? '0' : '1'; ?>" />
			<?php wp_nonce_field( App_Connections_Actions::NONCE_ACTION ); ?>
			<uas-chip size="sm" color="<?php echo $enabled ? 'success' : 'neutral'; ?>" filled label="<?php echo $enabled ? esc_attr__( 'Sharing is on.', 'uncanny-automator' ) : esc_attr__( 'Sharing is off.', 'uncanny-automator' ); ?>" class="uap-spacing-right--small"></uas-chip>
			<uas-button type="submit" size="sm" color="<?php echo $enabled ? 'secondary' : 'primary'; ?>"><?php echo $enabled ? esc_html__( 'Turn sharing off', 'uncanny-automator' ) : esc_html__( 'Turn sharing on', 'uncanny-automator' ); ?></uas-button>
		</form>
		<?php
	}

	/**
	 * The apps the switch covers: each app whose connections the Automator
	 * App Platform holds, with this site's accounts for it. An account this
	 * site attached from another site is never shared on, and says so.
	 *
	 * @return void
	 */
	private function render_covered_apps(): void {

		$apps = $this->integrations->get_app_platform_integrations();
		if ( array() === $apps ) {
			return;
		}
		?>
		<div class="uap-settings-panel-content-paragraph uap-spacing-top--small"><strong><?php esc_html_e( 'Apps this covers:', 'uncanny-automator' ); ?></strong></div>
		<ul class="automator-app-connections-covered" style="list-style:disc;margin:4px 0 0 20px">
			<?php foreach ( $apps as $code => $name ) : ?>
				<li><?php echo esc_html( $name ) . ': ' . $this->accounts_for( $code ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in accounts_for(). ?></li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	/**
	 * This site's active accounts for an app, escaped; "not connected on this
	 * site" when it has none.
	 *
	 * @param string $code The integration code.
	 *
	 * @return string
	 */
	private function accounts_for( string $code ): string {

		$accounts = array();
		foreach ( $this->registry->list_for_integration( strtolower( $code ) ) as $row ) {
			$label      = $this->view->account_label( $row );
			$accounts[] = $this->is_own( $row )
				/* translators: %s: an app account this site attached from another site */
				? $label : sprintf( esc_html_x( '%s (shared by another site, so not shared again from here)', 'App connections', 'uncanny-automator' ), $label );
		}

		return array() !== $accounts ? implode( '; ', $accounts ) : esc_html_x( 'not connected on this site', 'App connections', 'uncanny-automator' );
	}

	/**
	 * Repair (R5).
	 *
	 * @return void
	 */
	private function render_repair(): void {
		?>
		<div class="uap-settings-panel-content-separator"></div>
		<section class="automator-app-connections-repair">
			<div class="uap-settings-panel-content-subtitle"><?php esc_html_e( 'Repair connections', 'uncanny-automator' ); ?></div>
			<div class="uap-settings-panel-content-paragraph"><?php esc_html_e( "Re-reads this site's app connections from the Automator App Platform: switches back on any it still holds for this site, updates their details, and adds any missing here. Nothing is removed.", 'uncanny-automator' ); ?></div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="uap-spacing-top">
				<input type="hidden" name="action" value="<?php echo esc_attr( App_Connections_Actions::ACTION_REPAIR ); ?>" />
				<?php wp_nonce_field( App_Connections_Actions::NONCE_ACTION ); ?>
				<uas-button type="submit" size="sm" color="secondary"><?php esc_html_e( 'Repair connections', 'uncanny-automator' ); ?></uas-button>
			</form>
		</section>
		<?php
	}

	/**
	 * Whether this site is a copy that only uses the live site's connections:
	 * the switch would offer nothing.
	 *
	 * @return bool
	 */
	private function has_nothing_to_share(): bool {

		if ( ! $this->record->wants_adoption() ) {
			return false;
		}

		try {
			foreach ( $this->registry->list_by_status( Connection::STATUS_ACTIVE ) as $row ) {
				if ( $this->is_own( $row ) ) {
					return false;
				}
			}
		} catch ( Registry_Read_Failed_Exception $e ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether a row is a connection this site made itself: the platform says
	 * `oauth` or `api_key`, or it has never said (a row connected here before a
	 * sync recorded its origin). `shared` is not, nor is an origin the platform
	 * does not know (`null`, contract §9).
	 *
	 * @param Connection $row An active row.
	 *
	 * @return bool
	 */
	private function is_own( Connection $row ): bool {

		if ( $row->is_legacy() ) {
			return false;
		}

		$metadata = $row->metadata();

		return ! array_key_exists( 'origin', $metadata )
			|| in_array( $metadata['origin'], array( Platform_Connection::ORIGIN_OAUTH, Platform_Connection::ORIGIN_API_KEY ), true );
	}

	/**
	 * @return array<int, Connection>
	 */
	private function switched_off_rows(): array {

		return array_map(
			static function ( array $item ): Connection {
				return $item['connection'];
			},
			$this->adoption->report()->not_attached()
		);
	}
}
