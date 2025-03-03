<?php

namespace Uncanny_Automator;

/**
 * Delete user records
 * Settings > General > Logs > Remove recipe log on completion
 *
 * @since   5.4
 * @version 5.4
 * @author  Saad S.
 *
 * Variables:
 * $is_enabled  True if the setting is enabled
 * @package Uncanny_Automator
 */

$removable_statuses = Automator_Status::get_removable_statuses();
$statuses           = array();
if ( $removable_statuses ) {
	foreach ( $removable_statuses as $__status ) {
		$statuses[] = str_replace( ',', ' -', Automator_Status::name( $__status ) );
	}
}
?>

<form method="POST">

	<?php

	// Add setting fields
	wp_nonce_field( 'uncanny_automator' );

	?>

	<div class="uap-settings-panel-content-separator"></div>

	<div class="uap-settings-panel-content-subtitle">
		<?php esc_html_e( 'Immediately delete log entries when recipes are completed', 'uncanny-automator' ); ?>
	</div>

	<uo-switch
		id="automator_delete_recipe_records_on_completion"
		<?php echo $is_enabled ? 'checked' : ''; ?>

		status-label="<?php esc_attr_e( 'Enabled', 'uncanny-automator' ); ?>,<?php esc_attr_e( 'Disabled', 'uncanny-automator' ); ?>"
		helper="<?php echo esc_attr( sprintf( '%s %s', esc_html__( 'Turning on this setting will automatically purge new recipe log entries with any of the following statuses:', 'uncanny-automator' ), '"' . join( '", "', $statuses ) . '"' ) ); ?>"
		class="uap-spacing-top"
	></uo-switch>
	<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">

		<ul>
			<li><?php esc_attr_e( 'This setting only affects new recipe completions. Historical records will not be deleted.', 'uncanny-automator' ); ?></li>
			<li><?php esc_attr_e( 'With this setting enabled, the "Times per user" setting for logged-in recipes will be ignored.', 'uncanny-automator' ); ?></li>
		</ul>
	</div>
	<uo-button
		type="submit"
		class="uap-spacing-top"
	>
		<?php esc_html_e( 'Save', 'uncanny-automator' ); ?>
	</uo-button>

</form>
