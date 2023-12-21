<?php

namespace Uncanny_Automator;

/**
 * Delete user records
 * Settings > General > Logs > Delete recipe records when user is permanently deleted
 *
 * @since   5.4
 * @version 5.4
 * @package Uncanny_Automator
 * @author  Curtis K.
 *
 * Variables:
 * $is_enabled  True if the setting is enabled
 */

?>

<form method="POST">

	<?php

	// Add setting fields
	wp_nonce_field( 'uncanny_automator' );

	?>

	<div class="uap-settings-panel-content-separator"></div>

	<div class="uap-settings-panel-content-subtitle">
		<?php esc_html_e( 'Delete recipe records when user is deleted', 'uncanny-automator' ); ?>
	</div>

	<uo-switch
		id="automator_delete_user_records_on_user_delete"
		<?php echo $is_enabled ? 'checked' : ''; ?>

		status-label="<?php esc_attr_e( 'Enabled', 'uncanny-automator' ); ?>,<?php esc_attr_e( 'Disabled', 'uncanny-automator' ); ?>"

		class="uap-spacing-top"
	></uo-switch>

	<uo-button
		type="submit"
		class="uap-spacing-top"
	>
		<?php esc_html_e( 'Save', 'uncanny-automator' ); ?>
	</uo-button>

</form>
