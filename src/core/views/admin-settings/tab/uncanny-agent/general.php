<?php
/**
 * Uncanny Agent - General
 * Settings > Uncanny Agent > General
 *
 * @since   7.1
 * @version 1.1
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<form method="POST">

	<?php settings_fields( Admin_Settings_Uncanny_Agent_General::SETTINGS_GROUP ); ?>

	<div class="uap-settings-panel">

			<div class="uap-settings-panel-top">

				<?php if ( $save_failed ) { ?>
					<uo-alert
						class="uap-spacing-bottom"
						type="error"
						heading="<?php echo esc_attr_x( 'Settings could not be saved. Please try again.', 'settings save error', 'uncanny-automator' ); ?>"
					></uo-alert>
				<?php } ?>

				<div class="uap-settings-panel-title">
				<?php echo esc_html_x( 'Uncanny Agent', 'settings panel title', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content">

				<div class="uap-field uap-spacing-top--small">
					<?php echo esc_html_x( 'Uncanny Agent is the AI assistant built into Uncanny Automator. It works from your WordPress dashboard and checks with you before important actions. Uncanny Agent is available to Administrator users only.', 'agent feature description', 'uncanny-automator' ); ?>
				</div>

				<div class="uap-field uap-spacing-top--small">
					<?php echo esc_html_x( 'Uncanny Agent opens from a tab at the bottom of the WordPress admin screen or a button in the admin bar. These are static links that open the Uncanny Agent window; nothing loads until you send a message. Switch either launcher on or off below.', 'agent launcher description', 'uncanny-automator' ); ?>
				</div>

				<div class="uap-spacing-top">
					<strong>
						<?php echo esc_html_x( 'Uncanny Agent Launcher Tab', 'launcher tab subtitle', 'uncanny-automator' ); ?>
					</strong>
				</div>

				<div class="uap-field uap-spacing-top--small">
					<uo-switch
						id="<?php echo esc_attr( Admin_Settings_Uncanny_Agent_General::ENABLED_KEY ); ?>"
						<?php checked( $is_launcher_enabled ); ?>
						status-label="<?php echo esc_attr_x( 'Enabled', 'toggle status label', 'uncanny-automator' ); ?>,<?php echo esc_attr_x( 'Disabled', 'toggle status label', 'uncanny-automator' ); ?>"
					></uo-switch>
				</div>

				<div class="uap-spacing-top">
					<strong>
						<?php echo esc_html_x( 'Uncanny Agent Top Bar Button', 'top bar button subtitle', 'uncanny-automator' ); ?>
					</strong>
				</div>

				<div class="uap-field uap-spacing-top--small">
					<uo-switch
						id="<?php echo esc_attr( Admin_Settings_Uncanny_Agent_General::TOP_BAR_BUTTON_ENABLED_KEY ); ?>"
						<?php checked( $is_top_bar_button_enabled ); ?>
						status-label="<?php echo esc_attr_x( 'Enabled', 'toggle status label', 'uncanny-automator' ); ?>,<?php echo esc_attr_x( 'Disabled', 'toggle status label', 'uncanny-automator' ); ?>"
					></uo-switch>
				</div>

			</div>

		</div>

		<div class="uap-settings-panel-bottom">

			<div class="uap-settings-panel-bottom-left">
				<uo-button type="submit">
					<?php echo esc_html_x( 'Save settings', 'settings save button', 'uncanny-automator' ); ?>
				</uo-button>
			</div>

		</div>

	</div><!--.uap-settings-panel-->

</form>
