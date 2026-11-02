<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin;

use Uncanny_Automator\App\Infrastructure\Automator_Platform\Local_Site_Status;
use Uncanny_Automator\App\Recipe_Runner\Background\Background_Actions_Setting;

/**
 * Settings > Advanced > Background app actions: the switch, on its own side
 * menu entry right after the legacy Background actions, in a form that saves
 * only this setting.
 *
 * @since 8.0.0
 */
final class Background_App_Actions_Tab {

	/**
	 * The Advanced sub-tab key.
	 */
	public const TAB = 'background_app_actions';

	/**
	 * The legacy Background actions sub-tab this one goes before.
	 */
	private const BEFORE = 'background_actions';

	/**
	 * @return void
	 */
	public function register(): void {

		// After the legacy Advanced tabs (priority 99), so Background actions
		// is there to go before.
		add_filter( 'automator_settings_advanced_tabs', array( $this, 'add_tab' ), 100 );
	}

	/**
	 * The tab, right before the retired Background actions; last when that
	 * tab is absent.
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
				'name'     => esc_html__( 'Background app actions', 'uncanny-automator' ),
				'function' => array( $this, 'render' ),
				'preload'  => false,
			),
		);

		$before = array_search( self::BEFORE, array_keys( $tabs ), true );
		if ( false === $before ) {
			return $tabs + $tab;
		}

		return array_slice( $tabs, 0, $before, true ) + $tab + array_slice( $tabs, $before, null, true );
	}

	/**
	 * @return void
	 */
	public function render(): void {
		?>
		<form method="POST" action="options.php">

			<?php settings_fields( Background_Actions_Setting::GROUP ); ?>

			<div class="uap-settings-panel">

				<div class="uap-settings-panel-top">

					<div class="uap-settings-panel-title">
						<?php esc_html_e( 'Background app actions', 'uncanny-automator' ); ?>
					</div>

					<div class="uap-settings-panel-content">

						<div class="uap-field uap-spacing-top--small">
							<uo-switch
								id="<?php echo esc_attr( Background_Actions_Setting::OPTION ); ?>"
								<?php echo Background_Actions_Setting::enabled() ? 'checked' : ''; ?>
								status-label="<?php esc_attr_e( 'Enabled', 'uncanny-automator' ); ?>,<?php esc_attr_e( 'Disabled', 'uncanny-automator' ); ?>"
								class="uap-spacing-top"
							></uo-switch>
							<div class="uap-field-description">
								<?php esc_html_e( 'When enabled, an app action that supports it hands its request to the Automator App Platform and the recipe continues at once; the action completes when the Automator App Platform reports back, and later steps that use its result wait for it.', 'uncanny-automator' ); ?>
							</div>
							<?php if ( ( new Local_Site_Status() )->is_local() ) : ?>
								<div class="uap-field-description">
									<?php esc_html_e( "This site is local, so the Automator App Platform can't report back to it. App actions on this site run right away, even with this setting on.", 'uncanny-automator' ); ?>
								</div>
							<?php endif; ?>
						</div>

					</div>

				</div>

				<div class="uap-settings-panel-bottom">
					<div class="uap-settings-panel-bottom-left">
						<uas-button type="submit">
							<?php esc_html_e( 'Save settings', 'uncanny-automator' ); ?>
						</uas-button>
					</div>
				</div>

			</div>

		</form>
		<?php
	}
}
