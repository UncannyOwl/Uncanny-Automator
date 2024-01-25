<?php

namespace Uncanny_Automator;

/**
 * Delete all data on uninstall
 * Settings > General > Logs > Delete all data on uninstall
 *
 * @since   v5.4
 * @version v5.4
 * @author  Saad S.
 *
 * Variables:
 * $is_enabled  True if the setting is enabled
 * @package Uncanny_Automator
 */

?>

<form method="POST">

	<?php
	// Add setting fields
	wp_nonce_field( 'uncanny_automator' );
	?>

	<div class="uap-settings-panel-content-separator"></div>

	<div class="uap-settings-panel-content-subtitle">
		<?php esc_html_e( 'Delete all data', 'uncanny-automator' ); ?>
	</div>

	<uo-switch
		id="automator_delete_data_on_uninstall"
		<?php echo $is_enabled ? 'checked' : ''; ?>
		status-label="<?php esc_attr_e( 'Enabled', 'uncanny-automator' ); ?>,<?php esc_attr_e( 'Disabled', 'uncanny-automator' ); ?>"
		class="uap-spacing-top"
	></uo-switch>
	<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
		<p><?php esc_html_e( 'Remove ALL recipe data, log data and settings when Uncanny Automator is deleted.', 'uncanny-automator' ); ?></p>
		<p class="uap-danger" style="color:#e94b35">
			<strong><?php esc_html_e( 'All recipes and logs will be unrecoverable.', 'uncanny-automator' ); ?></strong>
		</p>
	</div>

	<uo-button
		type="submit"
		class="uap-spacing-top"
	>
		<?php esc_html_e( 'Save', 'uncanny-automator' ); ?>
	</uo-button>

</form>
