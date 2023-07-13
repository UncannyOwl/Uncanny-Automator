<?php
/**
 * Status > Debug
 *
 * @since   4.5
 */

namespace Uncanny_Automator;

?>

<form method="POST" action="options.php">

	<?php settings_fields( Admin_Tools_Debug_Debug::SETTINGS_GROUP_NAME ); ?>

	<div class="uap-settings-panel">

		<div class="uap-settings-panel-top">

			<?php if ( ! empty( automator_filter_input( 'file_removed' ) ) ) { ?>

				<?php if ( 'yes' === automator_filter_input( 'failed' ) ) { ?>

					<uo-alert class="uap-spacing-bottom" type="error" heading="<?php esc_html_e( 'There was an error removing the file.', 'uncanny-automator' ); ?>"></uo-alert>	

				<?php } else { ?>

					<uo-alert class="uap-spacing-bottom" type="success" heading="<?php esc_html_e( 'Log file has been successfully removed.', 'uncanny-automator' ); ?>"></uo-alert>	

				<?php } ?>

			<?php } ?>

			<div class="uap-settings-panel-title">

				<?php esc_html_e( 'Debug', 'uncanny-automator' ); ?>

			</div>

			<div class="uap-settings-panel-content">

				<div class="uap-field uap-spacing-top--small">

					<uo-switch label="<?php esc_attr_e( 'Logs', 'uncanny-automator' ); ?>"
						<?php checked( automator_get_option( 'automator_settings_debug_enabled', false ), true, true ); ?> 
						id="automator_settings_debug_enabled" 
						status-label="Enabled,Disabled" class="uap-spacing-top">
					</uo-switch>

					<div class="uap-field-description">

						<?php esc_html_e( 'Enable additional error logging for Uncanny Automator when instructed by Uncanny Automator support.', 'uncanny-automator' ); ?>

					</div>

				</div>

				<!-- DISABLED div class="uap-field uap-spacing-top--small">

					<uo-switch label="<?php #esc_attr_e( 'Warnings and notices', 'uncanny-automator' ); ?>"
						<?php #checked( get_option( 'automator_settings_debug_notices_enabled', false ), true, true ); ?> 
						id="automator_settings_debug_notices_enabled" 
						status-label="Enabled,Disabled" class="uap-spacing-top">
					</uo-switch>

					<div class="uap-field-description">

						<?php #esc_html_e( 'Enable or disable developer warnings and notices.', 'uncanny-automator' ); ?>

					</div>

				</div -->

				<?php do_action( 'automator_settings_debug' ); ?>

			</div>

		</div>

		<div class="uap-settings-panel-bottom">

			<div class="uap-settings-panel-bottom-left">

				<uo-button type="submit">

					<?php esc_html_e( 'Save settings', 'uncanny-automator' ); ?>

				</uo-button>

			</div>

		</div>

	</div>

</form>
