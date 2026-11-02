<?php
/**
 * Status > Debug
 *
 * @since   4.5
 */

namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<form method="POST" action="options.php">

	<?php settings_fields( Admin_Tools_Debug_Debug::SETTINGS_GROUP_NAME ); ?>

	<div class="uap-settings-panel">

		<div class="uap-settings-panel-top">

			<?php if ( ! empty( automator_filter_input( 'file_removed' ) ) ) { ?>

				<?php if ( 'yes' === automator_filter_input( 'failed' ) ) { ?>

					<uas-alert class="uap-spacing-bottom" type="error" heading="<?php esc_html_e( 'There was an error removing the file.', 'uncanny-automator' ); ?>"></uas-alert>

				<?php } else { ?>

					<uas-alert class="uap-spacing-bottom" type="success" heading="<?php esc_html_e( 'Log file has been successfully removed.', 'uncanny-automator' ); ?>"></uas-alert>

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
						status-label="<?php echo esc_attr_x( 'Enabled', 'toggle status label', 'uncanny-automator' ); ?>,<?php echo esc_attr_x( 'Disabled', 'toggle status label', 'uncanny-automator' ); ?>" class="uap-spacing-top">
					</uo-switch>

					<div class="uap-field-description">

						<?php esc_html_e( 'Enable additional error logging for Uncanny Automator when instructed by Uncanny Automator support.', 'uncanny-automator' ); ?>

					</div>

				</div>

				<!-- DISABLED div class="uap-field uap-spacing-top--small">

					<uo-switch label="<?php #esc_attr_e( 'Warnings and notices', 'uncanny-automator' ); ?>"
						<?php #checked( automator_get_option( 'automator_settings_debug_notices_enabled', false ), true, true ); ?>
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

				<uas-button type="submit">

					<?php esc_html_e( 'Save settings', 'uncanny-automator' ); ?>

				</uas-button>

			</div>

		</div>

	</div>

</form>
